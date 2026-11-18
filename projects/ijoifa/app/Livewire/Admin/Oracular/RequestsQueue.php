<?php

namespace App\Livewire\Admin\Oracular;

use App\Models\ConsultationPriceSetting;
use App\Models\ConsultationRequest;
use App\Services\ConsultationChargeService;
use App\Services\PaymentMarkPaidService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class RequestsQueue extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public ?int $confirmingId = null;

    public string $confirmAmount = '';

    public bool $showPriceForm = false;

    public string $newBasePrice = '';

    public string $newFeePercent = '';

    /**
     * New consultation requests are now charged automatically on submission
     * (see PublicConsultationRequest::submit()) at the price configured here.
     * This form only remains as a manual retry path for the rare case where
     * the automatic attempt failed (Mercado Pago misconfigured/unreachable at
     * that moment) — the request stays 'pending' until this succeeds.
     */
    public function showConfirmForm(int $id): void
    {
        $this->confirmingId = $id;
        $this->confirmAmount = (string) ConsultationPriceSetting::current()->chargeAmount();
    }

    public function confirm(): void
    {
        $this->validate(['confirmAmount' => ['required', 'numeric', 'min:0.01']]);

        $request = ConsultationRequest::where('id', $this->confirmingId)->where('status', 'pending')->first();

        if (! $request) {
            return;
        }

        $payment = ConsultationChargeService::charge($request, (float) $this->confirmAmount);

        if (! $payment) {
            session()->flash('error', 'Não foi possível gerar a cobrança agora. Tente novamente em instantes ou fale com o suporte.');
            return;
        }

        try {
            $payment->refresh();
            $user = $request->user;
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\ConsultationPaymentRequestMail($request, $user, $payment));
        } catch (\Exception $e) {
            // Silently capture or ignore mail driver issues in tests/dev environments
        }

        $this->confirmingId = null;
    }

    public function openPriceForm(): void
    {
        $current = ConsultationPriceSetting::current();
        $this->newBasePrice = (string) $current->base_price;
        $this->newFeePercent = (string) $current->fee_percent;
        $this->showPriceForm = true;
    }

    public function savePricing(): void
    {
        $validated = $this->validate([
            'newBasePrice' => ['required', 'numeric', 'min:0.01'],
            'newFeePercent' => ['required', 'numeric', 'min:0', 'max:99'],
        ]);

        ConsultationPriceSetting::create([
            'base_price' => $validated['newBasePrice'],
            'fee_percent' => $validated['newFeePercent'],
            'created_by' => Auth::id(),
        ]);

        $this->showPriceForm = false;
    }

    /**
     * Manual payment confirmation for when the consulente pays outside
     * Mercado Pago (PIX, dinheiro, etc.) - the webhook handles the
     * Mercado Pago Checkout Pro path automatically.
     */
    public function markPaid(int $id): void
    {
        DB::transaction(function () use ($id) {
            $request = ConsultationRequest::where('id', $id)->where('status', 'awaiting_payment')->first();

            if (! $request) {
                return;
            }

            $payment = PaymentMarkPaidService::markPaidForPayable(ConsultationRequest::class, $request->id);

            if (! $payment) {
                $request->update(['status' => 'paid']);
            }
        });
    }

    public function cancel(int $id): void
    {
        ConsultationRequest::where('id', $id)->update(['status' => 'cancelled']);
    }

    public function render()
    {
        return view('livewire.admin.oracular.requests-queue', [
            'requests' => ConsultationRequest::with('user', 'consultation.admin')
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->latest()
                ->paginate(25),
            'currentPrice' => ConsultationPriceSetting::current(),
            'priceHistory' => ConsultationPriceSetting::with('admin')->latest('id')->take(20)->get(),
        ]);
    }
}
