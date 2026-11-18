<?php

namespace Tests\Feature;

use App\Livewire\Admin\TelegramCourseManager;
use App\Livewire\PublicTelegramCourseRegistration;
use App\Models\Payment;
use App\Models\TelegramCourseSubscription;
use App\Models\User;
use App\Services\PaymentFulfillment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_for_telegram_course_and_accept_terms(): void
    {
        Livewire::test(PublicTelegramCourseRegistration::class)
            ->set('full_name', 'Carlos Eduardo')
            ->set('email', 'carlos@example.com')
            ->set('phone', '11999998888')
            ->set('telegram_username', '@carlostelegram')
            ->set('accept_no_tefa_odu', true)
            ->set('accept_initiation_requirements', true)
            ->set('accept_aborisa_variable_effect', true)
            ->set('accept_format_and_frequency', true)
            ->set('accept_materials_responsibility', true)
            ->set('accept_presential_sacrifices', true)
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('telegram_course_subscriptions', [
            'email' => 'carlos@example.com',
            'full_name' => 'Carlos Eduardo',
            'telegram_username' => '@carlostelegram',
            'monthly_price' => 180.00,
            'status' => 'pending_payment',
            'terms_accepted' => true,
        ]);

        $sub = TelegramCourseSubscription::where('email', 'carlos@example.com')->first();
        $this->assertNotNull($sub);

        $this->assertDatabaseHas('payments', [
            'payable_type' => TelegramCourseSubscription::class,
            'payable_id' => $sub->id,
            'amount' => 180.00,
            'status' => 'pending',
        ]);
    }

    public function test_registration_sends_payment_link_email_to_student(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        Livewire::test(PublicTelegramCourseRegistration::class)
            ->set('full_name', 'Carlos Eduardo')
            ->set('email', 'carlos@example.com')
            ->set('phone', '11999998888')
            ->set('telegram_username', '@carlostelegram')
            ->set('accept_no_tefa_odu', true)
            ->set('accept_initiation_requirements', true)
            ->set('accept_aborisa_variable_effect', true)
            ->set('accept_format_and_frequency', true)
            ->set('accept_materials_responsibility', true)
            ->set('accept_presential_sacrifices', true)
            ->call('register')
            ->assertHasNoErrors();

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TelegramCoursePaymentLinkMail::class, function ($mail) {
            return $mail->hasTo('carlos@example.com');
        });
    }

    public function test_person_can_recover_lost_payment_link_by_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $user = User::factory()->create(['status' => 'active']);
        TelegramCourseSubscription::create([
            'user_id' => $user->id,
            'full_name' => 'Beatriz Souza',
            'email' => 'beatriz@example.com',
            'telegram_username' => '@beatriz',
            'monthly_price' => 180.00,
            'status' => 'pending_payment',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        Livewire::test(PublicTelegramCourseRegistration::class)
            ->set('recoverEmail', 'beatriz@example.com')
            ->call('resendPaymentLink')
            ->assertHasNoErrors();

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TelegramCoursePaymentLinkMail::class, function ($mail) {
            return $mail->hasTo('beatriz@example.com');
        });
    }

    public function test_recover_payment_link_does_not_error_for_unknown_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        Livewire::test(PublicTelegramCourseRegistration::class)
            ->set('recoverEmail', 'ninguem@example.com')
            ->call('resendPaymentLink')
            ->assertHasNoErrors();

        \Illuminate\Support\Facades\Mail::assertNotSent(\App\Mail\TelegramCoursePaymentLinkMail::class);
    }

    public function test_registration_requires_all_terms_accepted(): void
    {
        Livewire::test(PublicTelegramCourseRegistration::class)
            ->set('full_name', 'Maria Oliveira')
            ->set('email', 'maria@example.com')
            ->set('telegram_username', '@maria')
            ->set('accept_no_tefa_odu', false)
            ->call('register')
            ->assertHasErrors(['accept_no_tefa_odu' => 'accepted']);
    }

    public function test_payment_fulfillment_activates_subscription_and_sets_expiration(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $user->id,
            'full_name' => 'Ana Paula',
            'email' => 'ana@example.com',
            'telegram_username' => '@anapaula',
            'monthly_price' => 180.00,
            'status' => 'pending_payment',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => TelegramCourseSubscription::class,
            'payable_id' => $sub->id,
            'amount' => 180.00,
            'status' => 'pending',
        ]);

        PaymentFulfillment::apply($payment);

        $sub->refresh();
        $this->assertEquals('active', $sub->status);
        $this->assertTrue($sub->isActive());
        $this->assertNotNull($sub->expires_at);
        $this->assertTrue($sub->expires_at->isFuture());
    }

    public function test_payment_fulfillment_emails_group_invite_link_to_student(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Http::fake([
            'api.telegram.org/*' => \Illuminate\Support\Facades\Http::response([
                'ok' => true,
                'result' => ['invite_link' => 'https://t.me/+paidinvitelink'],
            ]),
        ]);

        config(['services.telegram.bot_token' => 'test-token']);
        config(['services.telegram.course_group_chat_id' => '-100123456']);

        $user = User::factory()->create(['status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $user->id,
            'full_name' => 'Camila Nunes',
            'email' => 'camila@example.com',
            'telegram_username' => '@camila',
            'monthly_price' => 180.00,
            'status' => 'pending_payment',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => TelegramCourseSubscription::class,
            'payable_id' => $sub->id,
            'amount' => 180.00,
            'status' => 'pending',
        ]);

        PaymentFulfillment::apply($payment);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TelegramCourseGroupInviteMail::class, function ($mail) {
            return $mail->hasTo('camila@example.com') && $mail->inviteLink === 'https://t.me/+paidinvitelink';
        });
    }

    public function test_admin_can_access_manager_and_mark_manual_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $admin->id,
            'full_name' => 'Roberto Santos',
            'email' => 'roberto@example.com',
            'telegram_username' => '@roberto',
            'monthly_price' => 180.00,
            'status' => 'pending_payment',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('admin.courses.telegram'));
        $response->assertStatus(200);

        Livewire::test(TelegramCourseManager::class)
            ->call('markAsPaid', $sub->id)
            ->assertHasNoErrors();

        $sub->refresh();
        $this->assertEquals('active', $sub->status);
        $this->assertTrue($sub->isActive());

        $this->assertDatabaseHas('payments', [
            'payable_type' => TelegramCourseSubscription::class,
            'payable_id' => $sub->id,
            'status' => 'paid',
            'amount' => 180.00,
        ]);
    }

    public function test_admin_can_send_reminder_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $admin->id,
            'full_name' => 'Fernanda Lima',
            'email' => 'fernanda@example.com',
            'telegram_username' => '@fernanda',
            'monthly_price' => 180.00,
            'status' => 'pending_payment',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(TelegramCourseManager::class)
            ->call('sendReminderEmail', $sub->id)
            ->assertHasNoErrors();

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TelegramCoursePaymentReminderMail::class, function ($mail) use ($sub) {
            return $mail->hasTo($sub->email);
        });
    }

    public function test_telegram_webhook_course_commands(): void
    {
        \Illuminate\Support\Facades\Http::fake();

        $sub = TelegramCourseSubscription::create([
            'full_name' => 'Juliana Costa',
            'email' => 'juliana@example.com',
            'telegram_username' => '@julianacosta',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(20),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        // User command /curso
        $response = $this->postJson('/api/webhooks/telegram', [
            'update_id' => 1234,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 999,
                    'is_bot' => false,
                    'first_name' => 'Juliana',
                    'username' => 'julianacosta',
                ],
                'chat' => [
                    'id' => 999,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/curso',
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_subscription_receipt_pdf_download(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $user->id,
            'full_name' => 'Luciana Matos',
            'email' => 'luciana@example.com',
            'telegram_username' => '@luciana',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_type' => TelegramCourseSubscription::class,
            'payable_id' => $sub->id,
            'amount' => 180.00,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('telegram-subscriptions.receipt', $payment->id));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_send_subscription_reminders_command(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $user = User::factory()->create(['status' => 'active']);
        TelegramCourseSubscription::create([
            'user_id' => $user->id,
            'full_name' => 'Gabriel Ramos',
            'email' => 'gabriel@example.com',
            'telegram_username' => '@gabriel',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(2),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $this->artisan('subscriptions:send-reminders')
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TelegramCoursePaymentReminderMail::class);
    }

    public function test_admin_can_generate_invite_link_for_specific_subscriber(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.telegram.org/*' => \Illuminate\Support\Facades\Http::response([
                'ok' => true,
                'result' => ['invite_link' => 'https://t.me/+testinvitelink'],
            ]),
        ]);

        config(['services.telegram.bot_token' => 'test-token']);
        config(['services.telegram.course_group_chat_id' => '-100123456']);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $admin->id,
            'full_name' => 'Renata Alves',
            'email' => 'renata@example.com',
            'telegram_username' => '@renata',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(TelegramCourseManager::class)
            ->call('generateInviteLinkForSub', $sub->id)
            ->assertHasNoErrors()
            ->assertSet('generatedInviteLink', 'https://t.me/+testinvitelink')
            ->assertSet('generatedInviteLinkUser', 'Renata Alves (@renata)');
    }

    public function test_admin_can_manage_attendance_and_lessons(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sub = TelegramCourseSubscription::create([
            'user_id' => $admin->id,
            'full_name' => 'Marcos Dias',
            'email' => 'marcos@example.com',
            'telegram_username' => '@marcos',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(TelegramCourseManager::class)
            ->call('toggleAttendance', $sub->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('telegram_course_attendances', [
            'telegram_course_subscription_id' => $sub->id,
            'attended' => false,
        ]);

        Livewire::test(TelegramCourseManager::class)
            ->set('lessonTitle', 'Aula Prática de Obi')
            ->set('lessonType', 'presential_meeting')
            ->set('lessonChecklist', '1 Obi de 4 gomos')
            ->call('saveLesson')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('telegram_course_lessons', [
            'title' => 'Aula Prática de Obi',
            'type' => 'presential_meeting',
        ]);
    }

    public function test_next_due_date_always_lands_on_the_15th_of_the_following_month(): void
    {
        // First payment ever (no current expiry): always the 15th of the
        // month *after* the payment, regardless of what day it happened.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-08-05'));
        $due = TelegramCourseSubscription::nextDueDate(null);
        $this->assertSame('2026-09-15', $due->format('Y-m-d'));

        // Renewing early, before the current due date: next cycle jumps
        // from the *current* expiry, not from today.
        $due = TelegramCourseSubscription::nextDueDate(\Illuminate\Support\Carbon::parse('2026-09-15 23:59:59'));
        $this->assertSame('2026-10-15', $due->format('Y-m-d'));

        // Catching up late (current expiry already in the past): jumps from
        // today, not from the stale expiry date.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-10-20'));
        $due = TelegramCourseSubscription::nextDueDate(\Illuminate\Support\Carbon::parse('2026-09-15 23:59:59'));
        $this->assertSame('2026-11-15', $due->format('Y-m-d'));

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_only_active_subscriber_or_admin_can_download_lesson_material_pdf(): void
    {
        $lesson = \App\Models\TelegramCourseLesson::create([
            'title' => 'Fundamentos de Orogbo',
            'type' => 'weekly_telegram',
            'content' => '<p>Conteúdo protegido da aula.</p>',
            'published_at' => now(),
        ]);

        $activeStudent = User::factory()->create(['status' => 'active']);
        TelegramCourseSubscription::create([
            'user_id' => $activeStudent->id,
            'full_name' => 'Aluno Ativo',
            'email' => $activeStudent->email,
            'telegram_username' => '@alunoativo',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $overdueStudent = User::factory()->create(['status' => 'active']);
        TelegramCourseSubscription::create([
            'user_id' => $overdueStudent->id,
            'full_name' => 'Aluno Inadimplente',
            'email' => $overdueStudent->email,
            'telegram_username' => '@alunoatrasado',
            'monthly_price' => 180.00,
            'status' => 'overdue',
            'expires_at' => now()->subDays(3),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($activeStudent)
            ->get(route('telegram-course.lessons.material-pdf', $lesson))
            ->assertOk();

        $this->actingAs($overdueStudent)
            ->get(route('telegram-course.lessons.material-pdf', $lesson))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('telegram-course.lessons.material-pdf', $lesson))
            ->assertOk();
    }

    public function test_document_based_lesson_material_is_stamped_with_watermark_and_downloadable(): void
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, 'Conteúdo de teste do livro.');
        $sourcePath = 'telegram-course/materials/test-book.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($sourcePath, $pdf->Output('', 'S'));

        $lesson = \App\Models\TelegramCourseLesson::create([
            'title' => 'Aula 01 — Livro de Teste',
            'type' => 'weekly_telegram',
            'document_path' => $sourcePath,
            'published_at' => now(),
        ]);

        $student = User::factory()->create(['status' => 'active']);
        TelegramCourseSubscription::create([
            'user_id' => $student->id,
            'full_name' => 'Aluno Livro',
            'email' => $student->email,
            'telegram_username' => '@alunolivro',
            'monthly_price' => 180.00,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);

        $response = $this->actingAs($student)
            ->get(route('telegram-course.lessons.material-pdf', $lesson));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
