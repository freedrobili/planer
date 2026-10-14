<?php

namespace App\Support;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Показатели продуктивности. День задачи — её дата в планере.
 * Серия — подряд идущие дни, в которые выполнена хотя бы одна задача.
 */
class Statistics
{
    public const WEEKS = 8;

    public function __construct(private readonly Carbon $today) {}

    /**
     * @return array{done: int, total: int}
     */
    public function period(Carbon $from, Carbon $to): array
    {
        $tasks = Task::between($from->toDateString(), $to->toDateString())->ownedBy(Owner::me())->get(['id', 'done']);

        return ['done' => $tasks->where('done', true)->count(), 'total' => $tasks->count()];
    }

    /**
     * Текущая серия (засчитывается и вчерашний день, если сегодня ещё ничего не сделано) и лучшая за всё время.
     *
     * @return array{current: int, best: int}
     */
    public function streaks(): array
    {
        $days = Task::ownedBy(Owner::me())
            ->where('done', true)
            ->where('date', '<=', $this->today->toDateString().' 23:59:59')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();

        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $run = $previous !== null && Carbon::parse($previous)->addDay()->toDateString() === $day ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        $set = $days->flip();
        $cursor = $this->today->copy();
        if (! $set->has($cursor->toDateString())) {
            $cursor->subDay();
        }
        $current = 0;
        while ($set->has($cursor->toDateString())) {
            $current++;
            $cursor->subDay();
        }

        return ['current' => $current, 'best' => $best];
    }

    /**
     * Последние недели (текущая — последней): подпись, выполнено, всего.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon, label: string, done: int, total: int, isCurrent: bool}>
     */
    public function weeks(): Collection
    {
        $tasks = $this->windowTasks();
        $currentStart = $this->today->copy()->startOfWeek(Carbon::MONDAY);

        return collect(range(self::WEEKS - 1, 0))->map(function (int $ago) use ($tasks, $currentStart): array {
            $start = $currentStart->copy()->subWeeks($ago);
            $end = $start->copy()->addDays(6);
            $inWeek = $tasks->filter(fn (Task $task) => $task->date->betweenIncluded($start, $end));

            return [
                'start' => $start,
                'end' => $end,
                'label' => $start->month === $end->month
                    ? $start->format('j').'–'.$end->translatedFormat('j M')
                    : $start->translatedFormat('j M').' – '.$end->translatedFormat('j M'),
                'done' => $inWeek->where('done', true)->count(),
                'total' => $inWeek->count(),
                'isCurrent' => $ago === 0,
            ];
        });
    }

    /**
     * Выполнено по тегам за те же недели. Задача с несколькими тегами считается в каждом.
     *
     * @return Collection<int, array{name: string, color: ?string, done: int, total: int}>
     */
    public function byTag(): Collection
    {
        $tasks = $this->windowTasks();

        $rows = Tag::orderBy('name')->get()->map(function (Tag $tag) use ($tasks): array {
            $tagged = $tasks->filter(fn (Task $task) => $task->tags->contains('id', $tag->id));

            return ['name' => $tag->name, 'color' => $tag->color, 'done' => $tagged->where('done', true)->count(), 'total' => $tagged->count()];
        });

        $untagged = $tasks->filter(fn (Task $task) => $task->tags->isEmpty());
        $rows->push(['name' => 'Без тега', 'color' => null, 'done' => $untagged->where('done', true)->count(), 'total' => $untagged->count()]);

        return $rows->filter(fn (array $row) => $row['total'] > 0)->sortByDesc('done')->values();
    }

    /**
     * Выполнено по дням недели за те же недели: 1 — понедельник … 7 — воскресенье.
     *
     * @return Collection<int, array{weekday: int, done: int}>
     */
    public function byWeekday(): Collection
    {
        $done = $this->windowTasks()->where('done', true);

        return collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'done' => $done->filter(fn (Task $task) => $task->date->dayOfWeekIso === $weekday)->count(),
        ]);
    }

    /** @var Collection<int, Task>|null */
    private ?Collection $window = null;

    /**
     * @return Collection<int, Task>
     */
    private function windowTasks(): Collection
    {
        $from = $this->today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);
        $to = $this->today->copy()->endOfWeek(Carbon::SUNDAY);

        return $this->window ??= Task::between($from->toDateString(), $to->toDateString())
            ->ownedBy(Owner::me())
            ->with('tags:tags.id')
            ->get(['id', 'date', 'done']);
    }
}
