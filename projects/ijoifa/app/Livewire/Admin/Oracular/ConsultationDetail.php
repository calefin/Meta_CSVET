<?php

namespace App\Livewire\Admin\Oracular;

use App\Models\ConsultationRequest;
use App\Models\Odu;
use App\Models\Payment;
use App\Models\RecommendedWork;
use App\Models\RitualWork;
use App\Services\PaymentMarkPaidService;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class ConsultationDetail extends Component
{
    public ConsultationRequest $consultationRequest;

    private const AREAS = ['espiritual', 'trabalho', 'relacionamento', 'saude', 'dinheiro'];

    private const AREA_LABELS = [
        'espiritual' => 'Espiritual',
        'trabalho' => 'Trabalho',
        'relacionamento' => 'Relacionamento',
        'saude' => 'Saude',
        'dinheiro' => 'Dinheiro',
    ];

    // Registration form fields
    public string $consulted_at = '';

    public string $modality = 'online';

    public ?int $odu_main_id = null;

    public ?int $odu_witness_1_id = null;

    public ?int $odu_witness_2_id = null;

    public string $ire_ibi = 'ire';

    public ?int $ire_ibi_type_id = null;

    public string $general_guidance = '';

    /** @var array<string, array{odu_1_id: ?int, odu_2_id: ?int, odu_3_id: ?int, notes: string}> */
    public array $lifeAreas = [];

    // Addendum form
    public string $addendumBody = '';

    // Recommended work form
    public ?int $newWorkId = null;

    public string $newWorkNotes = '';

    public function mount(ConsultationRequest $consultationRequest): void
    {
        $this->authorize('view', $consultationRequest);

        $this->consultationRequest = $consultationRequest;
        $this->modality = $consultationRequest->modality;
        $this->consulted_at = now()->format('Y-m-d\TH:i');

        foreach (self::AREAS as $area) {
            $this->lifeAreas[$area] = ['odu_1_id' => null, 'odu_2_id' => null, 'odu_3_id' => null, 'notes' => ''];
        }

        // Load draft data if exists
        if ($consultationRequest->draft_data) {
            $draft = $consultationRequest->draft_data;
            $this->consulted_at = $draft['consulted_at'] ?? $this->consulted_at;
            $this->modality = $draft['modality'] ?? $this->modality;
            $this->odu_main_id = $draft['odu_main_id'] ?? null;
            $this->odu_witness_1_id = $draft['odu_witness_1_id'] ?? null;
            $this->odu_witness_2_id = $draft['odu_witness_2_id'] ?? null;
            $this->ire_ibi = $draft['ire_ibi'] ?? 'ire';
            $this->ire_ibi_type_id = $draft['ire_ibi_type_id'] ?? null;
            $this->general_guidance = $draft['general_guidance'] ?? '';
            $this->lifeAreas = $draft['lifeAreas'] ?? $this->lifeAreas;
        }

        if ($consultationRequest->consultation) {
            Audit::record('consultation.view_sensitive', $consultationRequest->consultation);
        }
    }

    protected function rules(): array
    {
        return [
            'consulted_at' => ['required', 'date'],
            'modality' => ['required', 'in:online,presential'],
            'odu_main_id' => ['required', 'exists:odus,id'],
            'odu_witness_1_id' => ['nullable', 'exists:odus,id'],
            'odu_witness_2_id' => ['nullable', 'exists:odus,id'],
            'ire_ibi' => ['required', 'in:ire,ibi'],
            'ire_ibi_type_id' => ['required', 'exists:ire_ibi_types,id'],
            'general_guidance' => ['nullable', 'string'],
            'lifeAreas.*.odu_1_id' => ['nullable', 'exists:odus,id'],
            'lifeAreas.*.odu_2_id' => ['nullable', 'exists:odus,id'],
            'lifeAreas.*.odu_3_id' => ['nullable', 'exists:odus,id'],
            'lifeAreas.*.notes' => ['nullable', 'string'],
        ];
    }

    public function updatedIreIbi(): void
    {
        $this->ire_ibi_type_id = null;
    }

    public function updated($propertyName): void
    {
        $baseName = explode('.', $propertyName)[0];
        if (in_array($baseName, ['consulted_at', 'modality', 'odu_main_id', 'odu_witness_1_id', 'odu_witness_2_id', 'ire_ibi', 'ire_ibi_type_id', 'general_guidance', 'lifeAreas'])) {
            $this->saveDraft();
        }
    }

    public function saveDraft(): void
    {
        $this->consultationRequest->update([
            'draft_data' => [
                'consulted_at' => $this->consulted_at,
                'modality' => $this->modality,
                'odu_main_id' => $this->odu_main_id,
                'odu_witness_1_id' => $this->odu_witness_1_id,
                'odu_witness_2_id' => $this->odu_witness_2_id,
                'ire_ibi' => $this->ire_ibi,
                'ire_ibi_type_id' => $this->ire_ibi_type_id,
                'general_guidance' => $this->general_guidance,
                'lifeAreas' => $this->lifeAreas,
            ]
        ]);
    }

    public function registerConsultation(): void
    {
        if ($this->consultationRequest->status !== 'paid') {
            throw new \RuntimeException("Não é permitido registrar a consulta para uma solicitação não paga.");
        }

        $validated = $this->validate();

        $consultation = $this->consultationRequest->consultation()->create([
            'user_id' => $this->consultationRequest->user_id,
            'admin_id' => Auth::id(),
            'consulted_at' => $validated['consulted_at'],
            'modality' => $validated['modality'],
            'odu_main_id' => $validated['odu_main_id'],
            'odu_witness_1_id' => $validated['odu_witness_1_id'],
            'odu_witness_2_id' => $validated['odu_witness_2_id'],
            'ire_ibi' => $validated['ire_ibi'],
            'ire_ibi_type_id' => $validated['ire_ibi_type_id'],
            'general_guidance' => $validated['general_guidance'],
            'created_by' => Auth::id(),
            'created_ip' => request()->ip(),
        ]);

        foreach ($validated['lifeAreas'] as $area => $row) {
            if (! $row['odu_1_id']) {
                continue;
            }

            $consultation->lifeAreas()->create([
                'area' => $area,
                'odu_1_id' => $row['odu_1_id'],
                'odu_2_id' => $row['odu_2_id'],
                'odu_3_id' => $row['odu_3_id'],
                'notes' => $row['notes'],
            ]);
        }

        $this->consultationRequest->update([
            'status' => 'attended',
            'draft_data' => null
        ]);

        // Send summary email to client
        try {
            $consultation->load('oduMain');
            \Illuminate\Support\Facades\Mail::to($this->consultationRequest->user->email)
                ->send(new \App\Mail\ConsultationSummaryMail($consultation, $this->consultationRequest->user));
        } catch (\Exception $e) {
            // Ignore email driver issues in development
        }

        $this->consultationRequest->refresh();
    }

    public function addAddendum(): void
    {
        $this->validate(['addendumBody' => ['required', 'string']]);

        $this->consultationRequest->consultation->guidanceAddendums()->create([
            'admin_id' => Auth::id(),
            'body' => $this->addendumBody,
        ]);

        $this->addendumBody = '';
    }

    public function addRecommendedWork(): void
    {
        $this->validate([
            'newWorkId' => ['required', 'exists:ritual_works,id'],
            'newWorkNotes' => ['nullable', 'string'],
        ]);

        $this->consultationRequest->consultation->recommendedWorks()->create([
            'user_id' => $this->consultationRequest->user_id,
            'ritual_work_id' => $this->newWorkId,
            'notes' => $this->newWorkNotes,
            'status' => 'recommended',
        ]);

        $this->newWorkId = null;
        $this->newWorkNotes = '';
    }

    /**
     * Manual payment confirmation placeholder for the work charge (spec
     * sec. 6.6), same pattern as the consultation payment until Mercado
     * Pago credentials are available.
     */
    public function markWorkPaid(int $recommendedWorkId): void
    {
        PaymentMarkPaidService::markPaidForPayable(RecommendedWork::class, $recommendedWorkId);
    }

    public function render()
    {
        $recommendedWorks = $this->consultationRequest->consultation?->recommendedWorks ?? collect();

        $pendingPaymentWorkIds = Payment::where('payable_type', RecommendedWork::class)
            ->whereIn('payable_id', $recommendedWorks->pluck('id'))
            ->where('status', 'pending')
            ->pluck('payable_id');

        return view('livewire.admin.oracular.consultation-detail', [
            'odus' => Odu::orderBy('number')->get(),
            'ireIbiTypes' => \App\Models\IreIbiType::where('classification', $this->ire_ibi)->where('active', true)->orderBy('name')->get(),
            'ritualWorks' => RitualWork::where('active', true)->orderBy('name')->get(),
            'areaLabels' => self::AREA_LABELS,
            'pendingPaymentWorkIds' => $pendingPaymentWorkIds,
            'consultation' => $this->consultationRequest->consultation?->load(
                'oduMain', 'oduWitness1', 'oduWitness2', 'ireIbiType',
                'guidanceAddendums.admin', 'lifeAreas.odu1', 'lifeAreas.odu2', 'lifeAreas.odu3',
                'recommendedWorks.ritualWork'
            ),
        ]);
    }
}
