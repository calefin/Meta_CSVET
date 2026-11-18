<?php

namespace Tests\Feature;

use App\Models\InviteToken;
use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_invite_registration_and_activation_flow(): void
    {
        Mail::fake();

        // 1. Create an invite token
        $token = bin2hex(random_bytes(16));
        $inviteToken = InviteToken::create([
            'token' => $token,
            'email' => 'student@example.com',
            'category' => 'regular',
        ]);

        // 2. Open invite page
        $response = $this->get("/invite?token={$token}");
        $response->assertStatus(200);

        // 3. Post registration data
        $registerResponse = $this->post('/invite/register', [
            'token' => $token,
            'name' => 'John Doe Student',
            'email' => 'student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration' => '123456',
            'institution' => 'UNIVAP',
            'campus' => 'Urban',
            'period' => 'Noturno',
            'academic_level' => 'Graduação',
            'tcc_status' => 'defined',
            'tcc_title' => 'My Defined Title',
            'phone' => '11977998476',
            'terms_accepted' => '1',
        ]);

        $registerResponse->assertStatus(200);
        $registerResponse->assertViewIs('registration_status');

        // Check user & student were created in DB
        $user = User::where('email', 'student@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('student', $user->role);

        $student = $user->student;
        $this->assertNotNull($student);
        $this->assertEquals('awaiting_email_validation', $student->status);
        $this->assertNotNull($student->activation_token);

        // Verify activation email was sent
        Mail::assertSent(\App\Mail\AppNotificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && str_contains($mail->subject, 'Ativação de Conta');
        });

        // 4. Activate account by hitting GET /activate
        $activationToken = $student->activation_token;
        $activateResponse = $this->get("/activate?token={$activationToken}");

        $activateResponse->assertStatus(200);
        $activateResponse->assertViewIs('registration_status');

        // Check student status updated to awaiting_advisor_approval
        $student->refresh();
        $this->assertEquals('awaiting_advisor_approval', $student->status);
        $this->assertNotNull($student->activation_token);

        // Verify admin notification email was sent
        Mail::assertSent(\App\Mail\AppNotificationMail::class, function ($mail) {
            return $mail->hasTo('contato@profatilio.com.br') && str_contains($mail->subject, 'Aprovação');
        });

        // 5. Simulate approval (which changes status to active and sets token to null)
        $student->update([
            'status' => 'active',
            'activated_at' => now(),
            'activation_token' => null,
        ]);
        
        $student->refresh();
        $this->assertEquals('active', $student->status);
        $this->assertNull($student->activation_token);
    }

    public function test_student_registration_needing_tcc_evaluation_flow(): void
    {
        Mail::fake();

        // 1. Create an invite token
        $token = bin2hex(random_bytes(16));
        $inviteToken = InviteToken::create([
            'token' => $token,
            'email' => 'eval_student@example.com',
            'category' => 'regular',
        ]);

        // 2. Post registration data with needs_evaluation
        $registerResponse = $this->post('/invite/register', [
            'token' => $token,
            'name' => 'Evaluation Student',
            'email' => 'eval_student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration' => '654321',
            'institution' => 'UNIVAP',
            'campus' => 'Urban',
            'period' => 'Noturno',
            'academic_level' => 'Graduação',
            'tcc_status' => 'needs_evaluation',
            'tcc_title' => 'Proposed TCC Topic',
            'phone' => '11977998476',
            'terms_accepted' => '1',
        ]);

        $registerResponse->assertStatus(200);

        // Check student in DB
        $user = User::where('email', 'eval_student@example.com')->first();
        $this->assertNotNull($user);
        $student = $user->student;
        $this->assertNotNull($student);
        $this->assertEquals('needs_evaluation', $student->tcc_status);
        $this->assertEquals('Proposed TCC Topic', $student->research_topic);

        // Check that no AcademicTitle is created yet
        $this->assertEquals(0, $student->titles()->count());

        // Check that no DocumentSubmission is created yet
        $this->assertEquals(0, \App\Models\DocumentSubmission::where('student_id', $student->id)->count());

        // 3. Simulate email validation and admin approval
        $student->update([
            'status' => 'active',
            'activated_at' => now(),
            'activation_token' => null,
        ]);

        // Check that the DocumentSubmission was automatically created by the observer
        $submission = \App\Models\DocumentSubmission::where('student_id', $student->id)
            ->where('document_type', 'Concepção de TCC')
            ->first();

        $this->assertNotNull($submission);
        $this->assertEquals('Proposed TCC Topic', $submission->title);
        $this->assertEquals('Aguardando Tema do Aluno', $submission->status);
        $this->assertEquals(0, $submission->file_size_kb);
    }

    public function test_bulk_verification_job_flow(): void
    {
        Mail::fake();

        // Create an admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create a student requiring validation
        $user = User::create([
            'name' => 'Pending Student',
            'email' => 'pending@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'status' => 'awaiting_email_validation',
            'institution' => 'UNIVAP',
            'campus' => 'Urban',
            'academic_level' => 'Graduação',
            'research_topic' => 'Veterinária',
            'phone' => '11977998476',
            'terms_accepted' => true,
        ]);

        // 1. Dispatch the job (forceResend = false, customMessage = "Custom text")
        \App\Jobs\SendBulkVerificationEmailsJob::dispatch(
            false,
            'Custom text warning',
            $admin->id
        );

        // Verify mail was sent
        Mail::assertSent(\App\Mail\AppNotificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && 
                str_contains($mail->subject, 'Lembrete') &&
                str_contains($mail->messageContent, 'Custom text warning');
        });

        // Refresh user/student
        $user->refresh();
        $this->assertNotNull($user->metadata['last_confirmation_email_sent_at'] ?? null);

        // Reset Mail Fake to clear queue assertion history
        Mail::fake();

        // 2. Dispatch again without forcing - should skip because sent in last 24h
        \App\Jobs\SendBulkVerificationEmailsJob::dispatch(
            false,
            'Custom text warning 2',
            $admin->id
        );

        Mail::assertNotSent(\App\Mail\AppNotificationMail::class);

        // 3. Dispatch again forcing - should NOT skip
        \App\Jobs\SendBulkVerificationEmailsJob::dispatch(
            true,
            'Custom text warning 3',
            $admin->id
        );

        Mail::assertSent(\App\Mail\AppNotificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && 
                str_contains($mail->subject, 'Lembrete') &&
                str_contains($mail->messageContent, 'Custom text warning 3');
        });
    }

    public function test_student_registration_token_expiration(): void
    {
        // 1. Create an expired token
        $expiredToken = InviteToken::create([
            'token' => 'expired_token_123',
            'email' => 'expired@example.com',
            'category' => 'regular',
            'expires_at' => now()->subHour(), // Expired 1 hour ago
        ]);

        // Accessing invite page with expired token should return error
        $response = $this->get('/invite?token=expired_token_123');
        $response->assertStatus(200);
        $response->assertViewIs('registration_status');
        $response->assertSee('Este link de convite é inválido ou já foi utilizado');

        // Posting registration with expired token should redirect with errors
        $registerResponse = $this->post('/invite/register', [
            'token' => 'expired_token_123',
            'name' => 'Expired Student',
            'email' => 'expired@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'registration' => '123456',
            'institution' => 'UNIVAP',
            'campus' => 'Urban',
            'period' => 'Noturno',
            'academic_level' => 'Graduação',
            'phone' => '11977998476',
            'terms_accepted' => '1',
        ]);
        $registerResponse->assertRedirect();
        $registerResponse->assertSessionHasErrors(['token']);

        // 2. Create a valid token with future expiration
        $validToken = InviteToken::create([
            'token' => 'valid_token_123',
            'email' => 'valid@example.com',
            'category' => 'regular',
            'expires_at' => now()->addHour(), // Expires in 1 hour
        ]);

        $response = $this->get('/invite?token=valid_token_123');
        $response->assertStatus(200);
        $response->assertViewIs('invite');
    }

    public function test_invite_token_validation_and_pruning_flow(): void
    {
        // 1. Test Pruning
        // Token expired more than 30 days ago - should be pruned
        $prunable = InviteToken::create([
            'token' => 'prunable_token',
            'email' => 'prunable@example.com',
            'category' => 'regular',
            'expires_at' => now()->subDays(31),
        ]);

        // Token expired 10 days ago - should NOT be pruned
        $notPrunableRecent = InviteToken::create([
            'token' => 'recent_expired_token',
            'email' => 'recent@example.com',
            'category' => 'regular',
            'expires_at' => now()->subDays(10),
        ]);

        // Token used but expired - should NOT be pruned (only unused)
        $usedAndExpired = InviteToken::create([
            'token' => 'used_expired_token',
            'email' => 'used@example.com',
            'category' => 'regular',
            'used_at' => now(),
            'expires_at' => now()->subDays(31),
        ]);

        // Run pruning command
        $this->artisan('model:prune', ['--model' => [InviteToken::class]]);

        // Assert database records
        $this->assertDatabaseMissing('invite_tokens', ['id' => $prunable->id]);
        $this->assertDatabaseHas('invite_tokens', ['id' => $notPrunableRecent->id]);
        $this->assertDatabaseHas('invite_tokens', ['id' => $usedAndExpired->id]);
    }

    public function test_student_inactivation_and_exclusion_flows(): void
    {
        Mail::fake();

        // 1. Create student and verify automatic activated_at is set when status is active
        $user = User::create([
            'name' => 'Active Student',
            'email' => 'active_student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'status' => 'active',
            'institution' => 'UNICSUL',
            'campus' => 'Analise',
            'academic_level' => 'Graduação',
            'research_topic' => 'Veterinária',
            'phone' => '11977998476',
            'terms_accepted' => true,
        ]);

        $this->assertNotNull($student->activated_at);
        $originalActivatedAt = $student->activated_at;

        // 2. Create inactive student
        $inactiveUser = User::create([
            'name' => 'Inactive Student',
            'email' => 'inactive_student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $inactiveStudent = Student::create([
            'user_id' => $inactiveUser->id,
            'status' => 'inactive',
            'institution' => 'UNICSUL',
            'campus' => 'Analise',
            'academic_level' => 'Graduação',
            'research_topic' => 'Veterinária',
            'phone' => '11977998476',
            'terms_accepted' => true,
        ]);

        // 3. Test consolidated report access
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_test_report@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadHTML')
            ->andReturnUsing(function ($html) {
                if (str_contains($html, 'Relatório Consolidado')) {
                    $this->assertStringContainsString('Active Student', $html);
                    $this->assertStringNotContainsString('Inactive Student', $html);
                    
                    $mock = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
                    $mock->shouldReceive('setPaper')->andReturnSelf();
                    $mock->shouldReceive('download')->andReturn(response('PDF content mock'));
                    return $mock;
                }
                
                $dummy = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
                $dummy->shouldReceive('output')->andReturn('dummy PDF binary content');
                $dummy->shouldReceive('setPaper')->andReturnSelf();
                $dummy->shouldReceive('download')->andReturn(response('dummy'));
                return $dummy;
            });

        $response = $this->actingAs($admin)->get('/admin/relatorio-consolidado');
        $response->assertStatus(200);
        $this->assertEquals('PDF content mock', $response->getContent());

        // 4. Run deadline reminders command and check active student gets queued, while inactive does not
        // Add Termo de Aceite de IC submission for active student to trigger warning/alert if needed
        $activeSubmission = \App\Models\DocumentSubmission::create([
            'student_id' => $student->id,
            'document_type' => 'Termo de Aceite de IC',
            'title' => 'Termo Aceito',
            'status' => 'Aprovado',
            'file_path' => 'doc.pdf',
            'metadata' => [
                'ic_start_date' => now()->subMonths(2)->subDays(20)->format('Y-m-d'),
            ],
        ]);

        // Add Termo de Aceite de IC submission for inactive student
        $inactiveSubmission = \App\Models\DocumentSubmission::create([
            'student_id' => $inactiveStudent->id,
            'document_type' => 'Termo de Aceite de IC',
            'title' => 'Termo Aceito Inativo',
            'status' => 'Aprovado',
            'file_path' => 'doc2.pdf',
            'metadata' => [
                'ic_start_date' => now()->subMonths(2)->subDays(20)->format('Y-m-d'),
            ],
        ]);

        // Execute SendDeadlineReminders command
        $this->artisan('app:send-deadline-reminders');

        // Verify notification email was queued for active student but not for inactive student
        Mail::assertQueued(\App\Mail\BulkEmailAlunos::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        Mail::assertNotQueued(\App\Mail\BulkEmailAlunos::class, function ($mail) use ($inactiveUser) {
            return $mail->hasTo($inactiveUser->email);
        });
    }

    public function test_concepcao_de_tcc_advisor_suggested_title_flow(): void
    {
        // 1. Create a student
        $user = User::create([
            'name' => 'TCC Student',
            'email' => 'tcc_student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'status' => 'active',
            'institution' => 'UNICSUL',
            'campus' => 'Analise',
            'academic_level' => 'Graduação',
            'tcc_status' => 'needs_evaluation',
            'research_topic' => 'Original Student Theme',
            'phone' => '11977998476',
            'terms_accepted' => true,
        ]);

        // Verify that the observer created the Conception submission
        $submission = \App\Models\DocumentSubmission::where('student_id', $student->id)
            ->where('document_type', 'Concepção de TCC')
            ->first();

        $this->assertNotNull($submission);
        $this->assertEquals('Original Student Theme', $submission->title);

        // 2. Simulate advisor editing and suggesting a different title
        $metadata = $submission->metadata ?? [];
        $metadata['advisor_suggested_title'] = 'Suggested Advisor Theme';
        $submission->metadata = $metadata;
        $submission->save();

        // 3. Simulate advisor approving the theme
        $submission->status = 'Processo Concluído - Tema Definido';
        $submission->save();

        // Verify that the AcademicTitle was created with the suggested title
        $academicTitle = \App\Models\AcademicTitle::where('student_id', $student->id)
            ->where('type', 'TCC')
            ->first();

        $this->assertNotNull($academicTitle);
        $this->assertEquals('Suggested Advisor Theme', $academicTitle->title);

        // Verify that the Conception submission title was updated to the suggested title
        $submission->refresh();
        $this->assertEquals('Suggested Advisor Theme', $submission->title);

        // Verify that the spawned Objetivos de TCC submission has the suggested title
        $objetivosSubmission = \App\Models\DocumentSubmission::where('student_id', $student->id)
            ->where('document_type', 'Objetivos de TCC')
            ->first();

        $this->assertNotNull($objetivosSubmission);
        $this->assertEquals('Suggested Advisor Theme', $objetivosSubmission->title);
    }
}
