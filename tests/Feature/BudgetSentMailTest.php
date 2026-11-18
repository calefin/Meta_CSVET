<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\BudgetBuilder;
use App\Mail\BudgetSentMail;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\RitualWork;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetSentMailTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecommendedWork(User $consulente, User $admin): \App\Models\RecommendedWork
    {
        $odu = Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        $ireType = IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $work = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);

        $request = $consulente->consultationRequests()->create([
            'modality' => 'online', 'description' => 'teste', 'status' => 'paid',
        ]);

        $consultation = $request->consultation()->create([
            'user_id' => $consulente->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => $odu->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => $ireType->id, 'created_by' => $admin->id,
        ]);

        return $consultation->recommendedWorks()->create([
            'user_id' => $consulente->id, 'ritual_work_id' => $work->id, 'status' => 'recommended',
        ]);
    }

    public function test_sending_a_budget_emails_the_client(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $consulente = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        $work = $this->makeRecommendedWork($consulente, $admin);
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);

        Livewire::actingAs($admin)->test(BudgetBuilder::class, ['recommendedWork' => $work])
            ->call('addSupplyRow', $supply->id)
            ->set('optionItems.0.0.quantity', '2')
            ->set('optionItems.0.0.unit_price', '10')
            ->call('send');

        Mail::assertSent(BudgetSentMail::class, fn ($mail) => $mail->hasTo($consulente->email));
    }

    public function test_saving_a_draft_does_not_email_the_client(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $consulente = User::factory()->create(['role' => 'consulente', 'status' => 'active']);
        $work = $this->makeRecommendedWork($consulente, $admin);
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);

        Livewire::actingAs($admin)->test(BudgetBuilder::class, ['recommendedWork' => $work])
            ->call('addSupplyRow', $supply->id)
            ->set('optionItems.0.0.quantity', '2')
            ->set('optionItems.0.0.unit_price', '10')
            ->call('saveDraft');

        Mail::assertNotSent(BudgetSentMail::class);
    }
}
