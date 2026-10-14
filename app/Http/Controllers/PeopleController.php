<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Person;
use App\Models\Tag;
use App\Models\Task;
use App\Support\Owner;
use App\Support\PlannerDays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeopleController extends Controller
{
    private const TASK_DEFAULT_MINUTES = 60;

    /**
     * Карточки графиков: свой и людей, за которыми следите, — что у каждого сейчас и что дальше сегодня.
     */
    public function index(): View
    {
        $now = Carbon::now();

        $cards = Owner::all()->map(fn (Owner $owner) => ['owner' => $owner] + $this->today($owner, $now));

        return view('people', [
            'cards' => $cards,
            'colors' => Tag::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $person = Person::create($this->validated($request) + [
            'position' => (int) Person::max('position') + 1,
        ]);

        return redirect()->route('people')->with('status', "График «{$person->name}» добавлен");
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $person->update($this->validated($request));

        return redirect()->route('people')->with('status', "График «{$person->name}» сохранён");
    }

    /**
     * Удаляет человека вместе с его задачами и парами.
     */
    public function destroy(Person $person): RedirectResponse
    {
        $person->delete();

        return redirect()->route('people')->with('status', "График «{$person->name}» удалён");
    }

    /**
     * Сегодняшний день графика: сколько задач и пар, что идёт сейчас и что будет следующим.
     * Выполненные задачи в «сейчас» и «дальше» не попадают.
     *
     * @return array{tasks: int, done: int, lessons: int, now: ?array{title: string, from: string, to: string}, next: ?array{title: string, from: string, to: string}}
     */
    private function today(Owner $owner, Carbon $now): array
    {
        $date = $now->copy()->startOfDay();
        $day = PlannerDays::load($owner)->between($date, $date)->first();
        $clock = $now->format('H:i');

        $spans = $day['entries']
            ->reject(fn (array $entry) => $entry['type'] === 'task' && $entry['item']->done)
            ->map(fn (array $entry) => $this->span($entry['item']))
            ->values();

        return [
            'tasks' => $day['total'],
            'done' => $day['done'],
            'lessons' => $day['lessons']->count(),
            'now' => $spans->first(fn (array $span) => $span['from'] <= $clock && $clock < $span['to']),
            'next' => $spans->first(fn (array $span) => $span['from'] > $clock),
        ];
    }

    /**
     * @return array{title: string, from: string, to: string}
     */
    private function span(Task|Lesson $item): array
    {
        if ($item instanceof Lesson) {
            return ['title' => "Пара «{$item->subject}»", 'from' => $item->starts_at, 'to' => $item->ends_at];
        }

        $start = (int) substr($item->time, 0, 2) * 60 + (int) substr($item->time, 3, 2);
        $end = min($start + self::TASK_DEFAULT_MINUTES, 23 * 60 + 59);

        return [
            'title' => $item->title,
            'from' => $item->time,
            'to' => $item->ends_at ?? sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
        ];
    }

    /**
     * @return array{name: string, color: string}
     */
    private function validated(Request $request): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:'.Person::MAX_NAME],
            'color' => ['required', Rule::in(array_keys(Tag::COLORS))],
        ], [
            'name.required' => 'Напишите имя.',
            'name.max' => 'Имя должно быть не длиннее '.Person::MAX_NAME.' символов.',
            'color.*' => 'Выберите цвет из предложенных.',
        ]);
    }
}
