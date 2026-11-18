<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class ClientProfile extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function render()
    {
        $consultations = $this->user->consultations()
            ->with([
                'recommendedWorks.ritualWork',
                'recommendedWorks.budgets' => fn ($q) => $q->latest(),
                'oduMain',
            ])
            ->latest('consulted_at')
            ->get();

        $quickPayments = QuickPayment::where('user_id', $this->user->id)->latest()->get();

        $payments = Payment::where('user_id', $this->user->id)->latest()->get();

        return view('livewire.admin.client-profile', [
            'user' => $this->user,
            'consultations' => $consultations,
            'quickPayments' => $quickPayments,
            'payments' => $payments,
        ]);
    }
}
