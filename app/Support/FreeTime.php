<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Свободное время одновременно в нескольких графиках одного дня.
 * Занятым считается время пар и задач со временем; задача без окончания занимает час.
 */
final class FreeTime
{
    private const TASK_DEFAULT_MINUTES = 60;

    /**
     * Общие свободные промежутки с $fromHour:00 до $toHour:00 длиной не меньше $minMinutes.
     *
     * @param  array<int, array{tasks: Collection<int, Task>, lessons: Collection<int, Lesson>}>  $days
     * @return list<array{from: string, to: string}>
     */
    public static function common(array $days, int $fromHour, int $toHour, int $minMinutes = 30): array
    {
        $busy = collect($days)
            ->flatMap(fn (array $day) => self::busyIntervals($day['tasks'], $day['lessons']))
            ->sortBy(0)
            ->values();

        $free = [];
        $cursor = $fromHour * 60;
        $end = $toHour * 60;

        foreach ($busy as [$start, $finish]) {
            if ($start - $cursor >= $minMinutes && $cursor < $end) {
                $free[] = [$cursor, min($start, $end)];
            }
            $cursor = max($cursor, $finish);
        }

        if ($end - $cursor >= $minMinutes) {
            $free[] = [$cursor, $end];
        }

        return array_values(array_map(
            fn (array $interval) => ['from' => self::clock($interval[0]), 'to' => self::clock($interval[1])],
            array_filter($free, fn (array $interval) => $minMinutes <= $interval[1] - $interval[0]),
        ));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Lesson>  $lessons
     * @return Collection<int, array{0: int, 1: int}>
     */
    private static function busyIntervals(Collection $tasks, Collection $lessons): Collection
    {
        $taskIntervals = $tasks->whereNotNull('time')->map(function (Task $task): array {
            $start = self::minutes($task->time);

            return [$start, $task->ends_at ? self::minutes($task->ends_at) : $start + self::TASK_DEFAULT_MINUTES];
        });

        $lessonIntervals = $lessons->map(fn (Lesson $lesson) => [
            self::minutes($lesson->starts_at),
            self::minutes($lesson->ends_at),
        ]);

        return $taskIntervals->concat($lessonIntervals)->values();
    }

    private static function minutes(string $clock): int
    {
        return (int) substr($clock, 0, 2) * 60 + (int) substr($clock, 3, 2);
    }

    private static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
