<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Содержимое дней планера: задачи (с учётом фильтра по тегу) и пары по расписанию.
 * Используется и страницей планера, и экспортом.
 */
class PlannerDays
{
    /**
     * @param  Collection<int, Lesson>  $lessons
     */
    public function __construct(
        public readonly Timetable $timetable,
        private readonly Collection $lessons,
        public readonly Owner $owner = new Owner,
    ) {}

    public static function load(?Owner $owner = null): self
    {
        $owner ??= Owner::me();

        return new self(Timetable::load(), Lesson::ownedBy($owner)->get(), $owner);
    }

    public function hasLessons(): bool
    {
        return $this->lessons->isNotEmpty();
    }

    /**
     * Дни с $from по $to включительно.
     *
     * conflicts — пары, на время которых попадает задача: id задачи => пара.
     *
     * @return Collection<int, array{date: Carbon, tasks: Collection<int, Task>, untimed: Collection<int, Task>, entries: Collection<int, array{type: string, time: string, item: Task|Lesson}>, lessons: Collection<int, Lesson>, conflicts: array<int, Lesson>, total: int, done: int}>
     */
    public function between(Carbon $from, Carbon $to, ?Tag $filter = null): Collection
    {
        $tasks = Task::between($from->toDateString(), $to->toDateString())
            ->ownedBy($this->owner)
            ->when($filter, fn ($query) => $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filter->id)))
            ->with(['tags', 'items'])
            ->ordered()
            ->get()
            ->groupBy(fn (Task $task) => $task->date->toDateString());

        $days = collect();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date = $date->copy()->addDay()) {
            $dayTasks = $tasks->get($date->toDateString(), collect());
            $dayLessons = $this->timetable->lessonsOn($this->lessons, $date);

            $days->push([
                'date' => $date,
                'tasks' => $dayTasks,
                'untimed' => $dayTasks->whereNull('time')->values(),
                'entries' => $this->timedEntries($dayTasks, $dayLessons),
                'lessons' => $dayLessons,
                'conflicts' => $this->conflicts($dayTasks, $dayLessons),
                'total' => $dayTasks->count(),
                'done' => $dayTasks->where('done', true)->count(),
            ]);
        }

        return $days;
    }

    /**
     * Добавляет к дням наложенный график другого человека (только для просмотра):
     * ghostUntimed — его задачи без времени, timeline — свои и его записи одной лентой по времени
     * (ghost = true у чужих), freeTogether — общее свободное время или null без наложения.
     * Без наложения timeline совпадает с entries.
     *
     * @param  Collection<int, array<string, mixed>>  $days
     * @return Collection<int, array<string, mixed>>
     */
    public static function overlay(Collection $days, ?Owner $overlay, int $fromHour, int $toHour): Collection
    {
        $ghostDays = $overlay !== null && $days->isNotEmpty()
            ? self::load($overlay)
                ->between($days->first()['date'], $days->last()['date'])
                ->keyBy(fn (array $day) => $day['date']->toDateString())
            : collect();

        return $days->map(function (array $day) use ($ghostDays, $fromHour, $toHour): array {
            $ghost = $ghostDays->get($day['date']->toDateString());

            return $day + [
                'ghostUntimed' => $ghost['untimed'] ?? collect(),
                'timeline' => $day['entries']->map(fn (array $entry) => $entry + ['ghost' => false])
                    ->concat(($ghost['entries'] ?? collect())->map(fn (array $entry) => $entry + ['ghost' => true]))
                    ->sortBy('time')
                    ->values(),
                'freeTogether' => $ghost ? FreeTime::common([$day, $ghost], $fromHour, $toHour) : null,
            ];
        });
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Lesson>  $lessons
     * @return array<int, Lesson>
     */
    private function conflicts(Collection $tasks, Collection $lessons): array
    {
        $conflicts = [];

        foreach ($tasks as $task) {
            $lesson = $lessons->first(fn (Lesson $lesson) => $task->overlaps($lesson));
            if ($lesson !== null) {
                $conflicts[$task->id] = $lesson;
            }
        }

        return $conflicts;
    }

    /**
     * Задачи со временем и пары одной лентой, по времени начала.
     *
     * @return Collection<int, array{type: string, time: string, item: Task|Lesson}>
     */
    private function timedEntries(Collection $tasks, Collection $lessons): Collection
    {
        return $tasks->whereNotNull('time')
            ->map(fn (Task $task) => ['type' => 'task', 'time' => $task->time, 'item' => $task])
            ->concat($lessons->map(fn (Lesson $lesson) => ['type' => 'lesson', 'time' => $lesson->starts_at, 'item' => $lesson]))
            ->sortBy('time')
            ->values();
    }
}
