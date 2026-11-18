<?php

namespace App\Livewire\Admin;

use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class FinancialDashboard extends Component
{
    public string $startDate;

    public string $endDate;

    // 'monthly' | 'yearly' — drives which of the two selectors below is
    // shown and how startDate/endDate get computed from it.
    public string $periodView = 'monthly';

    public int $selectedMonth;

    public int $selectedYear;

    public bool $showExpenseForm = false;

    public string $expenseCategory = '';

    public string $expenseDescription = '';

    public string $expenseAmount = '';

    public string $expenseDate = '';

    public function mount(): void
    {
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->syncDateRange();
    }

    private function syncDateRange(): void
    {
        if ($this->periodView === 'yearly') {
            $this->startDate = now()->setDate($this->selectedYear, 1, 1)->startOfYear()->format('Y-m-d');
            $this->endDate = now()->setDate($this->selectedYear, 1, 1)->endOfYear()->format('Y-m-d');

            return;
        }

        $reference = now()->setDate($this->selectedYear, $this->selectedMonth, 1);
        $this->startDate = $reference->copy()->startOfMonth()->format('Y-m-d');
        $this->endDate = $reference->copy()->endOfMonth()->format('Y-m-d');
    }

    public function updatedPeriodView(): void
    {
        $this->syncDateRange();
    }

    public function updatedSelectedMonth(): void
    {
        $this->syncDateRange();
    }

    public function updatedSelectedYear(): void
    {
        $this->syncDateRange();
    }

    public function previousMonth(): void
    {
        $reference = now()->setDate($this->selectedYear, $this->selectedMonth, 1)->subMonthNoOverflow();
        $this->selectedMonth = $reference->month;
        $this->selectedYear = $reference->year;
        $this->syncDateRange();
    }

    public function nextMonth(): void
    {
        $reference = now()->setDate($this->selectedYear, $this->selectedMonth, 1)->addMonthNoOverflow();
        $this->selectedMonth = $reference->month;
        $this->selectedYear = $reference->year;
        $this->syncDateRange();
    }

    public function openExpenseForm(): void
    {
        $this->reset(['expenseCategory', 'expenseDescription', 'expenseAmount']);
        $this->expenseDate = now()->format('Y-m-d');
        $this->showExpenseForm = true;
    }

    public function saveExpense(): void
    {
        $validated = $this->validate([
            'expenseCategory' => ['required', 'string', 'max:100'],
            'expenseDescription' => ['required', 'string', 'max:255'],
            'expenseAmount' => ['required', 'numeric', 'min:0.01'],
            'expenseDate' => ['required', 'date'],
        ]);

        Expense::create([
            'category' => $validated['expenseCategory'],
            'description' => $validated['expenseDescription'],
            'amount' => $validated['expenseAmount'],
            'expense_date' => $validated['expenseDate'],
            'created_by' => Auth::id(),
        ]);

        $this->showExpenseForm = false;
    }

    public function deleteExpense(int $id): void
    {
        Expense::destroy($id);
    }

    /**
     * Last 6 months of revenue vs expenses, independent of the cash-flow
     * date filter above, for the trend chart.
     */
    private function monthlyTrend(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $labels = $months->map(fn ($m) => $m->translatedFormat('M/y'))->all();

        $revenue = $months->map(fn ($m) => (float) Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
            ->sum('amount'))->all();

        $expenses = $months->map(fn ($m) => (float) Expense::whereBetween('expense_date', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
            ->sum('amount'))->all();

        return [$labels, $revenue, $expenses];
    }

    public function render()
    {
        $revenues = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$this->startDate, $this->endDate.' 23:59:59'])
            ->with('user')
            ->latest('paid_at')
            ->get();

        // whereDate() (not whereBetween on the raw strings) because the
        // "date"-cast column can be stored with a time component depending
        // on the DB driver — a plain string comparison against the bare
        // "Y-m-d" endDate then silently excludes same-day expenses.
        $expenses = Expense::whereDate('expense_date', '>=', $this->startDate)
            ->whereDate('expense_date', '<=', $this->endDate)
            ->latest('expense_date')
            ->get();

        $totalRevenue = $revenues->sum('amount');
        $totalExpenses = $expenses->sum('amount');
        $totalMpFees = $revenues->sum('mp_fee_amount');

        $expensesByCategory = $expenses->groupBy('category')
            ->map(fn ($group) => $group->sum('amount'))
            ->sortByDesc(fn ($amount) => $amount);

        [$chartLabels, $chartRevenue, $chartExpenses] = $this->monthlyTrend();

        return view('livewire.admin.financial-dashboard', [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'expensesByCategory' => $expensesByCategory,
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'totalMpFees' => $totalMpFees,
            'net' => $totalRevenue - $totalExpenses,
            'chartLabels' => $chartLabels,
            'chartRevenue' => $chartRevenue,
            'chartExpenses' => $chartExpenses,
        ]);
    }
}
