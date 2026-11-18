<?php

namespace App\Livewire\Shared;

use App\Models\LiturgicalSetting;
use App\Support\OseCalendar;
use App\Support\YorubaWeek;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OseCalendarView extends Component
{
    public int $year;

    public int $month;

    public bool $showAnchorModal = false;

    public string $orunAnchor = '';

    public function mount(): void
    {
        $today = Carbon::today();
        $this->year = $today->year;
        $this->month = $today->month;
        $this->orunAnchor = LiturgicalSetting::current()->orun_anchor->toDateString();
    }

    public function editAnchor(): void
    {
        $this->orunAnchor = LiturgicalSetting::current()->orun_anchor->toDateString();
        $this->showAnchorModal = true;
    }

    public function saveAnchor(): void
    {
        $this->validate([
            'orunAnchor' => ['required', 'date'],
        ]);

        if (! OseCalendar::isIfaPhase($this->orunAnchor)) {
            $this->addError('orunAnchor', 'A data de Òrún Awó precisa ser um dia de Ọjọ́ Ọ̀sẹ̀ Ifá.');

            return;
        }

        LiturgicalSetting::current()->update(['orun_anchor' => $this->orunAnchor]);
        LiturgicalSetting::forget();

        $this->showAnchorModal = false;
    }

    public function prevMonth(): void
    {
        $current = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $current->year;
        $this->month = $current->month;
    }

    public function nextMonth(): void
    {
        $current = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $current->year;
        $this->month = $current->month;
    }

    public function render()
    {
        $firstDay = Carbon::create($this->year, $this->month, 1);
        $daysInMonth = $firstDay->daysInMonth;
        $startOfWeek = $firstDay->dayOfWeek; // 0 (Sunday) to 6 (Saturday)

        $weeks = [];
        $currentWeek = array_fill(0, $startOfWeek, null);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($this->year, $this->month, $day);
            $oseData = OseCalendar::forDate($date);

            $currentWeek[] = [
                'day' => $day,
                'date' => $date,
                'ose' => $oseData,
                'yoruba' => YorubaWeek::forDate($date),
                'isToday' => $date->isToday(),
            ];

            if (count($currentWeek) === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }

        if (count($currentWeek) > 0) {
            $currentWeek = array_pad($currentWeek, 7, null);
            $weeks[] = $currentWeek;
        }

        $monthName = $firstDay->translatedFormat('F \d\e Y');

        $user = Auth::user();
        if ($user->role === 'admin') {
            $layout = 'components.layouts.admin';
        } elseif ($user->role === 'iniciado') {
            $layout = 'components.layouts.iniciado';
        } else {
            $layout = 'components.layouts.consulente';
        }

        return view('livewire.shared.ose-calendar-view', [
            'weeks' => $weeks,
            'monthName' => $monthName,
            'isAdmin' => $user->role === 'admin',
            'yorubaWeek' => YorubaWeek::all(),
        ])->layout($layout, ['title' => 'Calendário Ọ̀sẹ̀']);
    }
}
