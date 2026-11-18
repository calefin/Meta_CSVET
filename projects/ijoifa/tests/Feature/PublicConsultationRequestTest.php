<?php

namespace Tests\Feature;

use App\Livewire\PublicConsultationRequest;
use App\Models\User;
use App\Models\ConsultationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicConsultationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_consultation_request_page(): void
    {
        $response = $this->get(route('public-consultation-request'));
        $response->assertStatus(200);
        $response->assertSeeLivewire(PublicConsultationRequest::class);
    }

    public function test_submitting_public_consultation_request_creates_active_consulente_and_auto_charges(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('document.pdf', 100);

        Livewire::test(PublicConsultationRequest::class)
            ->set('name', 'Olúségun Awólọ́wọ̀')
            ->set('mother_maiden_name', 'Fọláṣadé Awólọ́wọ̀')
            ->set('birthdate', '1990-05-15')
            ->set('country', 'BR')
            ->set('cpf', '123.456.789-00')
            ->set('email', 'olusegun@example.com')
            ->set('phone', '+5511999999999')
            ->set('modality', 'online')
            ->set('description', 'Gostaria de uma consulta para o meu trabalho.')
            ->set('attachment', $file)
            ->set('consent', true)
            ->call('submit')
            ->assertSet('submitted', true);

        // Verify user was created with status = active, role = consulente
        $user = User::where('email', 'olusegun@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('active', $user->status);
        $this->assertSame('consulente', $user->role);
        $this->assertSame('Olúségun Awólọ́wọ̀', $user->name);
        $this->assertSame('Fọláṣadé Awólọ́wọ̀', $user->mother_maiden_name);

        // Verify consultation request was created
        $request = ConsultationRequest::where('user_id', $user->id)->first();
        $this->assertNotNull($request);
        $this->assertSame('online', $request->modality);
        $this->assertSame('awaiting_payment', $request->status);
        $this->assertSame('Gostaria de uma consulta para o meu trabalho.', $request->description);
        $this->assertNotNull($request->attachment_path);

        Storage::disk('local')->assertExists($request->attachment_path);

        // Charged automatically at the default configured price (R$100 + 6%),
        // no admin approval step required.
        $this->assertDatabaseHas('payments', [
            'payable_type' => ConsultationRequest::class,
            'payable_id' => $request->id,
            'user_id' => $user->id,
            'amount' => 106.38,
            'status' => 'pending',
        ]);
    }

    public function test_submitting_request_uses_existing_user_if_email_already_exists(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'joao@example.com',
            'role' => 'consulente',
            'status' => 'active',
            'phone' => ''
        ]);

        Livewire::test(PublicConsultationRequest::class)
            ->set('name', 'João Silva')
            ->set('mother_maiden_name', 'Maria Silva')
            ->set('birthdate', '1985-10-10')
            ->set('country', 'BR')
            ->set('cpf', '111.222.333-44')
            ->set('email', 'joao@example.com')
            ->set('phone', '+5511988888888')
            ->set('modality', 'online')
            ->set('description', 'Consulta presencial por favor.')
            ->set('consent', true)
            ->call('submit')
            ->assertSet('submitted', true);

        // Verify existing user's details were linked
        $request = ConsultationRequest::where('description', 'Consulta presencial por favor.')->first();
        $this->assertNotNull($request);
        $this->assertEquals($existingUser->id, $request->user_id);
        
        $existingUser->refresh();
        $this->assertSame('+5511988888888', $existingUser->phone);
    }
}
