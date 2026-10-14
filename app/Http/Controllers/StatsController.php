<?php

namespace App\Http\Controllers;

use App\Support\Statistics;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __invoke(): View
    {
        $today = Carbon::today();
        $statistics = new Statistics($today);
        $weeks = $statistics->weeks();
        $byWeekday = $statistics->byWeekday();

        return view('stats', [
            'week' => $statistics->period($today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfWeek(Carbon::SUNDAY)),
            'month' => $statistics->period($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'monthName' => $today->translatedFormat('F'),
            'streaks' => $statistics->streaks(),
            'weeks' => $weeks,
            'weeksMax' => max(1, $weeks->max('total')),
            'byTag' => $statistics->byTag(),
            'byWeekday' => $byWeekday,
            'weekdayMax' => max(1, $byWeekday->max('done')),
            'windowLabel' => $weeks->first()['start']->translatedFormat('j F').' – '.$weeks->last()['end']->translatedFormat('j F'),
        ]);
    }
}
