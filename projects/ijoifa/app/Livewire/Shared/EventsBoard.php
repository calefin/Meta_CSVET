<?php

namespace App\Livewire\Shared;

use App\Models\Event;
use App\Support\OseCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventsBoard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $start = Carbon::today();
        $end = Carbon::today()->addDays(60);

        $manual = Event::where(function ($q) use ($start) {
            $q->where(function ($q) use ($start) {
                $q->whereNotNull('ends_on')->where('ends_on', '>=', $start);
            })->orWhere(function ($q) use ($start) {
                $q->whereNull('ends_on')->where('starts_on', '>=', $start);
            });
        })
            ->where(function ($q) use ($user) {
                $q->where('target_group', 'all')
                    ->orWhere('target_group', $user->role === 'iniciado' ? 'iniciados' : 'consulentes');
            })
            ->orderBy('starts_on')
            ->get()
            ->map(fn ($event) => [
                'date' => $event->starts_on,
                'ends_on' => $event->ends_on,
                'label' => $event->title,
                'description' => $event->description,
                'type' => 'manual',
            ]);

        $computed = collect(OseCalendar::specialOccurrencesBetween($start, $end))
            ->map(fn ($occurrence) => [...$occurrence, 'description' => null]);

        $items = $manual->concat($computed)->sortBy('date')->values();

        return view('livewire.shared.events-board', ['items' => $items]);
    }
}
