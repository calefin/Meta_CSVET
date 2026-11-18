<?php

namespace App\Livewire\Admin;

use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public int $totalUsers = 0;

    public int $pendingApprovals = 0;

    public int $pendingConsultations = 0;

    public int $pendingFulfillments = 0;

    public float $monthlyRevenue = 0.0;

    public function mount(): void
    {
        $this->totalUsers = User::count();
        $this->pendingApprovals = User::where('status', 'pending')->count();
        $this->pendingConsultations = ConsultationRequest::where('status', 'pending')->count();

        $this->pendingFulfillments = RecommendedWork::where('status', 'approved')->count()
            + QuickPayment::where('status', 'paid')->count()
            + ConsultationRequest::where('status', 'paid')->count();

        $this->monthlyRevenue = (float) Payment::where('status', 'paid')
            ->whereMonth('paid_at', Carbon::now()->month)
            ->whereYear('paid_at', Carbon::now()->year)
            ->sum('amount');
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
