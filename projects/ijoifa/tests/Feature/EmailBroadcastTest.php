<?php

namespace Tests\Feature;

use App\Livewire\Admin\EmailBroadcast;
use App\Mail\AdminBroadcastMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EmailBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_a_broadcast_to_a_target_group(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
        $iniciado1 = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
        $iniciado2 = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'active']);
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);
        $pendingIniciado = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'iniciado', 'status' => 'pending']);

        Livewire::actingAs($admin)
            ->test(EmailBroadcast::class)
            ->set('targetGroup', 'iniciados')
            ->set('emailSubject', 'Mesa de Ifa nesta semana')
            ->set('emailBody', 'Venha participar.')
            ->call('send')
            ->assertSet('sent', true)
            ->assertSet('sentCount', 2);

        Mail::assertQueued(AdminBroadcastMail::class, fn ($mail) => $mail->hasTo($iniciado1->email));
        Mail::assertQueued(AdminBroadcastMail::class, fn ($mail) => $mail->hasTo($iniciado2->email));
        Mail::assertNotQueued(AdminBroadcastMail::class, fn ($mail) => $mail->hasTo($consulente->email));
        Mail::assertNotQueued(AdminBroadcastMail::class, fn ($mail) => $mail->hasTo($pendingIniciado->email));
    }

    public function test_consulente_cannot_access_email_broadcast(): void
    {
        $consulente = User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'consulente', 'status' => 'active']);

        $this->actingAs($consulente)->get(route('admin.email-broadcast'))->assertForbidden();
    }
}
