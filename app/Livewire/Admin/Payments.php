<?php

namespace App\Livewire\Admin;

use App\Mail\AdHocChargeMail;
use App\Models\AdHocCharge;
use App\Models\Payment;
use App\Models\User;
use App\Services\MercadoPagoService;
use App\Services\PaymentMarkPaidService;
use App\Services\PaymentResendService;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class Payments extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $search = '';

    public bool $showChargeForm = false;

    public ?int $chargeUserId = null;

    public string $chargeDescription = '';

    public string $chargeAmount = '';

    public function openChargeForm(): void
    {
        $this->reset(['chargeUserId', 'chargeDescription', 'chargeAmount']);
        $this->showChargeForm = true;
    }

    public function createCharge(): void
    {
        $validated = $this->validate([
            'chargeUserId' => ['required', 'exists:users,id'],
            'chargeDescription' => ['required', 'string', 'max:255'],
            'chargeAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $charge = AdHocCharge::create([
            'user_id' => $validated['chargeUserId'],
            'description' => $validated['chargeDescription'],
            'created_by' => auth()->id(),
        ]);

        $payment = Payment::create([
            'user_id' => $charge->user_id,
            'payable_type' => AdHocCharge::class,
            'payable_id' => $charge->id,
            'amount' => $validated['chargeAmount'],
            'status' => 'pending',
        ]);

        if (MercadoPagoService::configured()) {
            $url = (new MercadoPagoService())->createPreference($payment, $validated['chargeDescription']);
            $payment->refresh();

            if ($url === null) {
                session()->flash('error', 'A cobrança foi criada, mas não foi possível gerar o link do Mercado Pago agora. Você pode confirmar o pagamento manualmente (PIX/dinheiro) quando ele chegar.');
            }
        }

        try {
            Mail::to($charge->user->email)->send(new AdHocChargeMail($charge, $charge->user, $payment));
        } catch (\Exception $e) {
            // Email driver issues should not block charge creation.
        }

        $this->showChargeForm = false;
    }

    public function markPaid(int $paymentId): void
    {
        PaymentMarkPaidService::markPaidById($paymentId);
    }

    public function cancel(int $paymentId): void
    {
        Payment::where('id', $paymentId)->where('status', 'pending')->update(['status' => 'failed']);
    }

    public function resend(int $paymentId): void
    {
        $payment = Payment::where('id', $paymentId)->where('status', 'pending')->with('user')->first();

        if (! $payment) {
            return;
        }

        if (PaymentResendService::resend($payment)) {
            session()->flash('success', 'Cobrança reenviada com sucesso.');
        } else {
            session()->flash('error', 'Não foi possível reenviar esta cobrança.');
        }
    }

    public function render()
    {
        $payments = Payment::with('user')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->latest()
            ->paginate(25);

        return view('livewire.admin.payments', [
            'payments' => $payments,
            'users' => User::whereIn('role', ['iniciado', 'consulente'])->orderBy('name')->get(),
        ]);
    }
}
