<?php

namespace App\Livewire\Consulente;

use App\Models\Budget;
use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\RecommendedWork;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.consulente')]
class Consultations extends Component
{
    use WithFileUploads;

    public bool $showRequestForm = false;

    public string $modality = 'online';

    public string $description = '';

    public $attachment = null;

    /** @var array<int, int> budget_id => chosen option index */
    public array $selectedBudgetOption = [];

    protected function rules(): array
    {
        return [
            'modality' => ['required', 'in:online,presential'],
            'description' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function openRequestForm(): void
    {
        $this->reset(['modality', 'description', 'attachment']);
        $this->modality = 'online';
        $this->showRequestForm = true;
    }

    public function submitRequest(): void
    {
        $validated = $this->validate();

        $path = $this->attachment?->store('consultation-attachments', 'local');

        ConsultationRequest::create([
            'user_id' => Auth::id(),
            'modality' => $validated['modality'],
            'description' => $validated['description'],
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        $this->showRequestForm = false;
    }

    /**
     * Consulente clicks "Solicitar orcamento" on a recommended work
     * (spec sec. 6.5 - the trigger that moves the work into the admin's
     * budget queue; without this action the work just stays "recommended"
     * indefinitely, as specified).
     */
    public function requestBudget(int $recommendedWorkId): void
    {
        RecommendedWork::where('id', $recommendedWorkId)
            ->where('user_id', Auth::id())
            ->where('status', 'recommended')
            ->update(['status' => 'budget_requested']);
    }

    public function selectBudgetOption(int $budgetId, int $index): void
    {
        $this->selectedBudgetOption[$budgetId] = $index;
    }

    /**
     * Consulente approves the budget and requests the charge in one action
     * (spec sec. 6.6) - creates a real Mercado Pago Checkout Pro charge
     * when credentials are configured; the webhook confirms payment and
     * moves the work to "approved" automatically.
     */
    public function approveBudget(int $budgetId): void
    {
        $budget = Budget::where('id', $budgetId)->where('user_id', Auth::id())->where('status', 'sent')->first();

        if (! $budget) {
            return;
        }

        $optionIndex = $this->selectedBudgetOption[$budgetId] ?? 0;

        if ($budget->hasMultipleOptions()) {
            if (! isset($this->selectedBudgetOption[$budgetId]) || empty($budget->options[$optionIndex] ?? null)) {
                $this->addError('approveBudget', 'Escolha uma das opções antes de aprovar o orçamento.');
                return;
            }
        }

        $option = $budget->options[$optionIndex] ?? null;

        try {
            DB::transaction(function () use ($budget, $option, $optionIndex) {
                $updates = ['status' => 'approved'];

                if ($option) {
                    $updates['selected_option_index'] = $optionIndex;
                    $updates['subtotal'] = $option['subtotal'];
                    $updates['total'] = $option['total'];
                }

                $budget->update($updates);

                $payment = Payment::findOrCreatePending(
                    RecommendedWork::class,
                    $budget->recommended_work_id,
                    Auth::id(),
                    (float) $budget->total
                );

                if (MercadoPagoService::configured() && ! $payment->checkout_url) {
                    $url = (new MercadoPagoService())->createPreference($payment, 'Trabalho espiritual — '.config('app.name'));

                    if ($url === null) {
                        throw new \RuntimeException('mercadopago_preference_failed');
                    }
                }
            });
        } catch (\RuntimeException $e) {
            session()->flash('error', 'Não foi possível gerar a cobrança agora. Tente novamente em instantes ou fale com o suporte.');
        }
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.consulente.consultations', [
            'requests' => $user->consultationRequests()
                ->with(
                    'consultation.oduMain',
                    'consultation.ireIbiType',
                    'consultation.recommendedWorks.ritualWork',
                    'consultation.recommendedWorks.budgets'
                )
                ->latest()
                ->paginate(25),
        ]);
    }
}
