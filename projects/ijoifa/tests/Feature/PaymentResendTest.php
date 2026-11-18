<?php

namespace Tests\Feature;

use App\Livewire\Admin\Payments;
use App\Mail\AdHocChargeMail;
use App\Mail\BudgetSentMail;
use App\Mail\ConsultationPaymentRequestMail;
use App\Mail\QuickPaymentLinkMail;
use App\Models\AdHocCharge;
use App\Models\Budget;
use App\Models\ConsultationRequest;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Models\RitualWork;
use App\Models\User;
use App\Services\PaymentResendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentResendTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create(['role' => 'consulente', 'status' => 'active']);
    }

    public function test_resends_consultation_request_payment_link(): void
    {
        Mail::fake();
        $client = $this->client();
        $request = $client->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'awaiting_payment',
        ]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => ConsultationRequest::class,
            'payable_id' => $request->id, 'amount' => 100, 'status' => 'pending',
        ]);

        $this->assertTrue(PaymentResendService::resend($payment->fresh()));

        Mail::assertSent(ConsultationPaymentRequestMail::class, fn ($m) => $m->hasTo($client->email));
    }

    public function test_resends_ad_hoc_charge(): void
    {
        Mail::fake();
        $client = $this->client();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $charge = AdHocCharge::create(['user_id' => $client->id, 'description' => 'Taxa', 'created_by' => $admin->id]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => AdHocCharge::class,
            'payable_id' => $charge->id, 'amount' => 50, 'status' => 'pending',
        ]);

        $this->assertTrue(PaymentResendService::resend($payment->fresh()));

        Mail::assertSent(AdHocChargeMail::class, fn ($m) => $m->hasTo($client->email));
    }

    public function test_resends_quick_payment_link(): void
    {
        Mail::fake();
        $client = $this->client();
        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'user_id' => $client->id,
            'items' => [], 'markup_percent' => 0, 'fee_percent' => 0,
            'subtotal' => 80, 'total' => 80, 'status' => 'pending',
        ]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => QuickPayment::class,
            'payable_id' => $qp->id, 'amount' => 80, 'status' => 'pending',
        ]);

        $this->assertTrue(PaymentResendService::resend($payment->fresh()));

        Mail::assertSent(QuickPaymentLinkMail::class, fn ($m) => $m->hasTo($client->email));
    }

    public function test_resends_recommended_work_budget(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = $this->client();

        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $ritualWork = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        $request = $client->consultationRequests()->create(['modality' => 'online', 'description' => 'teste', 'status' => 'paid']);
        $consultation = $request->consultation()->create([
            'user_id' => $client->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);
        $work = $consultation->recommendedWorks()->create([
            'user_id' => $client->id, 'ritual_work_id' => $ritualWork->id, 'status' => 'budget_requested',
        ]);
        $budget = Budget::create([
            'recommended_work_id' => $work->id, 'user_id' => $client->id, 'admin_id' => $admin->id,
            'markup_percent' => 0, 'fee_percent' => 0, 'subtotal' => 120, 'total' => 120, 'status' => 'sent',
        ]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => RecommendedWork::class,
            'payable_id' => $work->id, 'amount' => 120, 'status' => 'pending',
        ]);

        $this->assertTrue(PaymentResendService::resend($payment->fresh()));

        Mail::assertSent(BudgetSentMail::class, fn ($m) => $m->hasTo($client->email) && $m->budget->is($budget));
    }

    public function test_does_not_resend_when_payment_already_paid(): void
    {
        Mail::fake();
        $client = $this->client();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $charge = AdHocCharge::create(['user_id' => $client->id, 'description' => 'Taxa', 'created_by' => $admin->id]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => AdHocCharge::class,
            'payable_id' => $charge->id, 'amount' => 50, 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->assertFalse(PaymentResendService::resend($payment->fresh()));

        Mail::assertNothingSent();
    }

    public function test_payments_livewire_resend_action_is_generalized(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = $this->client();
        $qp = QuickPayment::create([
            'type' => 'trabalhos_gerais', 'user_id' => $client->id,
            'items' => [], 'markup_percent' => 0, 'fee_percent' => 0,
            'subtotal' => 80, 'total' => 80, 'status' => 'pending',
        ]);
        $payment = Payment::create([
            'user_id' => $client->id, 'payable_type' => QuickPayment::class,
            'payable_id' => $qp->id, 'amount' => 80, 'status' => 'pending',
        ]);

        Livewire::actingAs($admin)->test(Payments::class)
            ->call('resend', $payment->id);

        Mail::assertSent(QuickPaymentLinkMail::class, fn ($m) => $m->hasTo($client->email));
    }
}
