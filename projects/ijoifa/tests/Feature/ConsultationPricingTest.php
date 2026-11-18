<?php

namespace Tests\Feature;

use App\Livewire\Admin\Oracular\RequestsQueue;
use App\Models\ConsultationPriceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConsultationPricingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['mother_maiden_name' => 'Teste', 'role' => 'admin', 'status' => 'active']);
    }

    public function test_current_falls_back_to_100_plus_6_percent_when_nothing_configured(): void
    {
        $current = ConsultationPriceSetting::current();

        $this->assertSame(100.0, (float) $current->base_price);
        $this->assertSame(6.0, (float) $current->fee_percent);
        $this->assertSame(106.38, $current->chargeAmount());
    }

    public function test_admin_can_change_price_and_it_becomes_current(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(RequestsQueue::class)
            ->call('openPriceForm')
            ->set('newBasePrice', '150')
            ->set('newFeePercent', '8')
            ->call('savePricing');

        $current = ConsultationPriceSetting::current();
        $this->assertSame(150.0, (float) $current->base_price);
        $this->assertSame(8.0, (float) $current->fee_percent);
        $this->assertSame($admin->id, $current->created_by);

        $this->assertDatabaseHas('consultation_price_settings', [
            'base_price' => 150,
            'fee_percent' => 8,
            'created_by' => $admin->id,
        ]);
    }

    public function test_price_changes_accumulate_as_history_not_overwrite(): void
    {
        $admin = $this->admin();

        ConsultationPriceSetting::create(['base_price' => 100, 'fee_percent' => 6, 'created_by' => $admin->id]);
        ConsultationPriceSetting::create(['base_price' => 120, 'fee_percent' => 6, 'created_by' => $admin->id]);

        $this->assertSame(2, ConsultationPriceSetting::count());
        $this->assertSame(120.0, (float) ConsultationPriceSetting::current()->base_price);
    }
}
