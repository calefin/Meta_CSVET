<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\NewWork;
use App\Mail\BudgetSentMail;
use App\Mail\QuickPaymentLinkMail;
use App\Models\Budget;
use App\Models\IreIbiType;
use App\Models\Odu;
use App\Models\QuickPayment;
use App\Models\RitualWork;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class NewWorkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function client(): User
    {
        return User::factory()->create(['role' => 'consulente', 'status' => 'active']);
    }

    public function test_formal_path_creates_consultation_recommended_work_and_budget(): void
    {
        Mail::fake();
        Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);

        $admin = $this->admin();
        $client = $this->client();
        $ritualWork = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        $supply = Supply::create(['name' => 'Vela branca', 'active' => true]);

        Livewire::actingAs($admin)->test(NewWork::class)
            ->set('selectedClientId', $client->id)
            ->set('destination', 'formal')
            ->set('ritualWorkId', $ritualWork->id)
            ->call('addSupplyRow', $supply->id)
            ->set('optionItems.0.0.quantity', '2')
            ->set('optionItems.0.0.unit_price', '10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('consultations', ['user_id' => $client->id]);
        $this->assertDatabaseHas('recommended_works', ['user_id' => $client->id, 'ritual_work_id' => $ritualWork->id]);
        $budget = Budget::where('user_id', $client->id)->first();
        $this->assertNotNull($budget);
        $this->assertSame('sent', $budget->status);
        $this->assertEquals(20.00, $budget->subtotal);

        Mail::assertSent(BudgetSentMail::class, fn ($m) => $m->hasTo($client->email));
    }

    public function test_quick_link_path_with_preselected_client_sets_user_id_and_emails_link(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $client = $this->client();

        Livewire::actingAs($admin)->test(NewWork::class)
            ->set('selectedClientId', $client->id)
            ->set('destination', 'quick_link')
            ->set('quickType', 'trabalhos_gerais')
            ->call('addCustomRow')
            ->set('optionItems.0.0.description', 'Ebó completo')
            ->set('optionItems.0.0.quantity', '1')
            ->set('optionItems.0.0.unit_price', '150')
            ->call('save')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertSame($client->id, $qp->user_id);
        $this->assertEquals(150.00, $qp->total);

        $this->assertDatabaseHas('payments', [
            'payable_type' => QuickPayment::class,
            'payable_id' => $qp->id,
            'status' => 'pending',
        ]);

        Mail::assertSent(QuickPaymentLinkMail::class, fn ($m) => $m->hasTo($client->email));
    }

    public function test_quick_link_path_without_client_leaves_user_id_null_and_sends_no_mail(): void
    {
        Mail::fake();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(NewWork::class)
            ->set('destination', 'quick_link')
            ->set('quickType', 'trabalhos_gerais')
            ->call('addCustomRow')
            ->set('optionItems.0.0.description', 'Ebó completo')
            ->set('optionItems.0.0.quantity', '1')
            ->set('optionItems.0.0.unit_price', '150')
            ->call('save')
            ->assertHasNoErrors();

        $qp = QuickPayment::first();
        $this->assertNotNull($qp);
        $this->assertNull($qp->user_id);

        Mail::assertNothingSent();
    }

    public function test_attaching_to_existing_recommended_work_redirects_to_budget_builder(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        Odu::create(['name' => 'Ogbè Méjì', 'number' => 1, 'category' => 'meji']);
        IreIbiType::create(['classification' => 'ire', 'name' => 'Ire Owó']);
        $ritualWork = RitualWork::create(['name' => 'Ẹbọ Riru', 'active' => true]);
        $request = $client->consultationRequests()->create(['modality' => 'online', 'description' => 'teste', 'status' => 'paid']);
        $consultation = $request->consultation()->create([
            'user_id' => $client->id, 'admin_id' => $admin->id, 'consulted_at' => now(),
            'modality' => 'online', 'odu_main_id' => Odu::first()->id, 'ire_ibi' => 'ire',
            'ire_ibi_type_id' => IreIbiType::first()->id, 'created_by' => $admin->id,
        ]);
        $work = $consultation->recommendedWorks()->create([
            'user_id' => $client->id, 'ritual_work_id' => $ritualWork->id, 'status' => 'recommended',
        ]);

        Livewire::actingAs($admin)->test(NewWork::class)
            ->set('selectedClientId', $client->id)
            ->set('destination', 'formal')
            ->set('existingRecommendedWorkId', $work->id)
            ->call('addCustomRow')
            ->set('optionItems.0.0.description', 'x')
            ->set('optionItems.0.0.quantity', '1')
            ->set('optionItems.0.0.unit_price', '10')
            ->call('save')
            ->assertRedirect(route('admin.oracular.budget', ['recommendedWork' => $work->id]));

        $this->assertDatabaseMissing('budgets', ['recommended_work_id' => $work->id]);
    }
}
