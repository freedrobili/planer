<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Tag;
use App\Models\Task;
use App\Support\Appearance;
use App\Support\Owner;
use App\Support\PlannerDays;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PlannerController extends Controller
{
    private const VIEWS = ['day', 'week'];

    public function index(Request $request, Appearance $appearance)
    {
        $today = Carbon::today();
        $selected = $this->parseDate($request->query('date')) ?? $today;
        $view = $this->resolveView($request);

        $weekStart = $selected->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $tags = Tag::withCount('tasks')->orderBy('name')->get();
        $filter = $tags->firstWhere('id', $request->integer('tag'));

        $owner = Owner::current($request);
        $overlay = Owner::overlay($request, $owner);

        // Наложенный график показывается целиком, без фильтра по тегу, и только для просмотра.
        $planner = PlannerDays::load($owner);
        $days = PlannerDays::overlay($planner->between($weekStart, $weekEnd, $filter), $overlay, $appearance->dayStart(), $appearance->dayEnd())
            ->map(fn (array $day) => $day + [
                'isToday' => $day['date']->isSameDay($today),
                'isSelected' => $day['date']->isSameDay($selected),
            ]);

        $selectedDay = $days->firstWhere('isSelected', true);
        $dayTasks = $selectedDay['tasks'];
        $untimed = $selectedDay['untimed'];
        $lessons = $selectedDay['lessons'];

        $byHour = $selectedDay['timeline']
            ->groupBy(fn (array $entry) => (int) substr($entry['time'], 0, 2));

        // Часы, занятые парами, — для полосы слева в расписании.
        $busyHours = $lessons->flatMap(fn (Lesson $l) => $l->coveredHours())->unique()->values();
        $plannedHours = $dayTasks->flatMap(fn (Task $task) => $task->coveredHours())->unique()->values();
        $usedHours = $byHour->keys()->concat($busyHours)->concat($plannedHours);

        $hours = range(
            min($appearance->dayStart(), $usedHours->min() ?? $appearance->dayStart()),
            max($appearance->dayEnd(), $usedHours->max() ?? $appearance->dayEnd()),
        );

        return view('planner', [
            'view' => $view,
            'today' => $today,
            'selected' => $selected,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'untimed' => $untimed,
            'ghostUntimed' => $selectedDay['ghostUntimed'],
            'freeTogether' => $selectedDay['freeTogether'],
            'byHour' => $byHour,
            'busyHours' => $busyHours->all(),
            'plannedHours' => $plannedHours->all(),
            'conflicts' => $days->pluck('conflicts')->reduce(fn (array $all, array $day) => $all + $day, []),
            'lessonCount' => $lessons->count(),
            'weekNumber' => $planner->timetable->weekNumber($selected),
            'isOddWeek' => $planner->timetable->isOddWeek($selected),
            'hasLessons' => $planner->hasLessons(),
            'hours' => $hours,
            'dayTotal' => $dayTasks->count(),
            'dayDone' => $dayTasks->where('done', true)->count(),
            'weekTotal' => $days->sum('total'),
            'weekDone' => $days->sum('done'),
            'tags' => $tags,
            'filter' => $filter,
            'plannerUrl' => fn (Carbon $date, array $override = []) => route('planner', array_filter([
                'date' => $date->toDateString(),
                'tag' => $filter?->id,
                ...$override,
            ])),
            'colors' => Tag::COLORS,
            'owner' => $owner,
            'overlay' => $overlay,
            'owners' => Owner::all(),
        ]);
    }

    /**
     * Режим «день» или «неделя». Выбор из адреса запоминается в сессии,
     * чтобы после добавления задачи или перехода по вкладке режим не сбрасывался.
     */
    private function resolveView(Request $request): string
    {
        $requested = $request->query('view');

        if (is_string($requested) && in_array($requested, self::VIEWS, true)) {
            $request->session()->put('planner.view', $requested);

            return $requested;
        }

        $remembered = $request->session()->get('planner.view');

        return in_array($remembered, self::VIEWS, true) ? $remembered : 'day';
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
