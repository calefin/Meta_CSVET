<?php

namespace App\Livewire\Admin\Oracular;

use App\Models\Payment;
use App\Models\QuickPayment;
use App\Models\RecommendedWork;
use App\Services\PaymentMarkPaidService;
use App\Services\PaymentResendService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class WorksDashboard extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $search = '';

    public function markPaid(string $source, int $id): void
    {
        $payableType = $source === 'recommended_work' ? RecommendedWork::class : QuickPayment::class;

        PaymentMarkPaidService::markPaidForPayable($payableType, $id);
    }

    public function resend(string $source, int $id): void
    {
        $payableType = $source === 'recommended_work' ? RecommendedWork::class : QuickPayment::class;

        $payment = Payment::where('payable_type', $payableType)
            ->where('payable_id', $id)
            ->where('status', 'pending')
            ->with('user')
            ->latest()
            ->first();

        if (! $payment) {
            session()->flash('error', 'Não há cobrança pendente para reenviar.');

            return;
        }

        if (PaymentResendService::resend($payment)) {
            session()->flash('success', 'Reenviado com sucesso.');
        } else {
            session()->flash('error', 'Não foi possível reenviar esta cobrança.');
        }
    }

    public function cancel(string $source, int $id): void
    {
        if ($source === 'recommended_work') {
            RecommendedWork::where('id', $id)->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'cancelled']);
        } else {
            QuickPayment::where('id', $id)->where('status', 'pending')->update(['status' => 'cancelled']);
        }
    }

    public function render()
    {
        $recommendedWorks = RecommendedWork::with(['user', 'ritualWork', 'budgets' => fn ($q) => $q->latest()])
            ->get()
            ->map(function (RecommendedWork $work) {
                $budget = $work->budgets->first();

                return [
                    'source' => 'recommended_work',
                    'id' => $work->id,
                    'client' => $work->user,
                    'tipo' => $work->ritualWork?->name ?? 'Trabalho',
                    'valor' => $budget?->total,
                    'status' => $work->status,
                    'status_group' => self::recommendedWorkStatusGroup($work->status),
                    'status_label' => self::recommendedWorkStatusLabel($work->status),
                    'data' => $work->created_at,
                    'link' => route('admin.oracular.budget', $work),
                    'checkout_url' => null,
                ];
            });

        $quickPayments = QuickPayment::with('user')
            ->get()
            ->map(fn (QuickPayment $qp) => [
                'source' => 'quick_payment',
                'id' => $qp->id,
                'client' => $qp->user,
                'tipo' => 'Link rápido — '.self::quickPaymentTypeLabel($qp->type),
                'valor' => $qp->total,
                'status' => $qp->status,
                'status_group' => self::quickPaymentStatusGroup($qp->status),
                'status_label' => self::quickPaymentStatusLabel($qp->status),
                'data' => $qp->created_at,
                'link' => null,
                'checkout_url' => route('quick-checkout', $qp->uuid),
            ]);

        $merged = $recommendedWorks->concat($quickPayments)
            ->when($this->statusFilter !== '', fn (Collection $c) => $c->filter(fn ($item) => $item['status_group'] === $this->statusFilter))
            ->when(trim($this->search) !== '', function (Collection $c) {
                $needle = strtolower(trim($this->search));

                return $c->filter(fn ($item) => $item['client'] && str_contains(strtolower($item['client']->name), $needle));
            })
            ->sortByDesc('data')
            ->values();

        $perPage = 25;
        $page = $this->getPage();

        $items = new LengthAwarePaginator(
            $merged->slice(($page - 1) * $perPage, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.admin.oracular.works-dashboard', [
            'items' => $items,
        ]);
    }

    public static function recommendedWorkStatusGroup(string $status): string
    {
        return match ($status) {
            'approved', 'completed' => 'paid',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    public static function recommendedWorkStatusLabel(string $status): string
    {
        return match ($status) {
            'recommended' => 'Recomendado',
            'budget_requested' => 'Orçamento solicitado',
            'approved' => 'Aprovado/Pago',
            'in_progress' => 'Em andamento',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    public static function quickPaymentStatusGroup(string $status): string
    {
        return match ($status) {
            'paid', 'completed' => 'paid',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    public static function quickPaymentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    public static function quickPaymentTypeLabel(string $type): string
    {
        return match ($type) {
            'consulta' => 'Consulta',
            'trabalhos_gerais' => 'Trabalhos gerais',
            'iniciacao' => 'Iniciação',
            'aborisa' => 'Aborisa',
            default => $type,
        };
    }
}
