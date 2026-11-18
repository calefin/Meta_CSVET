<?php

namespace Tests\Feature;

use App\Livewire\Admin\AdminNotes;
use App\Livewire\Admin\ClientProfile;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\QuickPayment;
use App\Models\RitualWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_consultations_quick_payments_and_notes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $client = User::factory()->create(['role' => 'consulente', 'status' => 'active', 'name' => 'Carla Consulente']);

        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $ritualWork = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        $request = $client->consultationRequests()->create(['modality' => 'online', 'description' => 'teste', 'status' => 'paid']);
        $consultation = $request->consultation()->create([
            'user_id' => $client->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);
        $consultation->recommendedWorks()->create([
            'user_id' => $client->id, 'ritual_work_id' => $ritualWork->id, 'status' => 'recommended',
        ]);

        QuickPayment::create([
            'type' => 'trabalhos_gerais', 'user_id' => $client->id,
            'items' => [], 'markup_percent' => 0, 'fee_percent' => 0,
            'subtotal' => 80, 'total' => 80, 'status' => 'pending',
        ]);

        Livewire::actingAs($admin)->test(AdminNotes::class, ['targetUserId' => $client->id])
            ->set('body', 'Cliente muito atencioso')
            ->call('save');

        Livewire::actingAs($admin)->test(ClientProfile::class, ['user' => $client])
            ->assertSee('Ẹbọ Riru')
            ->assertSee('R$ 80,00')
            ->assertSeeLivewire(AdminNotes::class);
    }
}
