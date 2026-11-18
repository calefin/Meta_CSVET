<?php

namespace App\Livewire\Admin\Oracular;

use App\Models\ConsultationRequest;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Everything already paid but not yet performed — the admin's actual "to do"
 * queue, as opposed to Trabalhos (which also lists unpaid/cancelled items).
 * Combines the three payable sources, each with its own idea of "done":
 * RecommendedWork/QuickPayment get a plain "completed" flip here, while
 * consultations route to ConsultationDetail's full registration flow instead
 * of a raw status flip (registering a consultation involves recording the
 * actual oracular reading, not just a checkbox).
 */
#[Layout('components.layouts.admin')]
class PendingFulfillments extends Component
{
    public function markRecommendedWorkDone(int $id): void
    {
        RecommendedWork::where('id', $id)->where('status', 'approved')->update(['status' => 'completed']);
    }

    public function markQuickPaymentDone(int $id): void
    {
        QuickPayment::where('id', $id)->where('status', 'paid')->update(['status' => 'completed']);
    }

    public function render()
    {
        $works = RecommendedWork::where('status', 'approved')
            ->with(['user', 'ritualWork'])
            ->get()
            ->map(fn (RecommendedWork $work) => [
                'source' => 'recommended_work',
                'id' => $work->id,
                'name' => $work->user->name,
                'phone' => $work->user->phone,
                'request' => $work->ritualWork->name.($work->notes ? ' — '.$work->notes : ''),
                'paid_at' => $work->updated_at,
            ]);

        $quickPayments = QuickPayment::where('status', 'paid')
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->map(fn (QuickPayment $qp) => [
                'source' => 'quick_payment',
                'id' => $qp->id,
                'name' => $qp->user->name,
                'phone' => $qp->user->phone,
                'request' => $qp->notes ?: WorksDashboard::quickPaymentTypeLabel($qp->type),
                'paid_at' => $qp->updated_at,
            ]);

        $consultations = ConsultationRequest::where('status', 'paid')
            ->with('user')
            ->get()
            ->map(fn (ConsultationRequest $request) => [
                'source' => 'consultation',
                'id' => $request->id,
                'name' => $request->user->name,
                'phone' => $request->user->phone,
                'request' => $request->description,
                'paid_at' => $request->updated_at,
            ]);

        $items = $works->concat($quickPayments)->concat($consultations)
            ->sortBy('paid_at')
            ->values();

        return view('livewire.admin.oracular.pending-fulfillments', [
            'items' => $items,
        ]);
    }
}
