<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Раскладка записей дня по сетке часов: блок начинается в своё время и по высоте
 * равен длительности. Пересекающиеся записи встают рядом, каждая в свою дорожку.
 * При наложении графиков свои записи занимают левую половину, чужие — правую.
 */
final class TimelineLayout
{
    /** Задача без времени окончания рисуется часовым блоком (так же она считается занятой в FreeTime). */
    private const TASK_DEFAULT_MINUTES = 60;

    /** Самый короткий блок: чтобы в нём помещалась строка с временем и названием. */
    private const MIN_MINUTES = 45;

    /**
     * Первый и последний час, которые занимают записи (с учётом минимальной высоты блоков).
     *
     * @param  Collection<int, array{type: string, time: string, item: Task|Lesson, ghost: bool}>  $entries
     * @return array{0: int, 1: int}|null
     */
    public static function hourBounds(Collection $entries): ?array
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $spans = $entries->map(fn (array $entry) => self::span($entry));

        return [
            intdiv($spans->min('start'), 60),
            intdiv(max($spans->max('end') - 1, 0), 60),
        ];
    }

    /**
     * @param  Collection<int, array{type: string, time: string, item: Task|Lesson, ghost: bool}>  $entries
     *                                                                                                       stack — порядок по началу: на узком экране записи ложатся внахлёст, и более поздняя лежит сверху.
     * @return Collection<int, array{type: string, time: string, item: Task|Lesson, ghost: bool, top: float, span: float, lane: int, lanes: int, column: string, stack: int}>
     */
    public static function place(Collection $entries, int $firstHour, bool $split = false): Collection
    {
        return $entries
            ->map(fn (array $entry) => $entry + self::span($entry) + [
                'column' => $split && $entry['ghost'] ? 'ghost' : 'own',
            ])
            ->groupBy('column')
            ->flatMap(fn (Collection $column) => self::lanes($column->sortBy('start')->values()))
            ->map(fn (array $entry) => [
                'top' => round(($entry['start'] - $firstHour * 60) / 60, 4),
                'span' => round(($entry['end'] - $entry['start']) / 60, 4),
            ] + $entry)
            ->sortBy([['column', 'desc'], ['start', 'asc']])
            ->values()
            ->map(fn (array $entry, int $index) => $entry + ['stack' => $index]);
    }

    /**
     * Дорожки внутри одной колонки: группы пересекающихся записей делят ширину поровну.
     *
     * @param  Collection<int, array<string, mixed>>  $entries  отсортированы по началу
     * @return list<array<string, mixed>>
     */
    private static function lanes(Collection $entries): array
    {
        $placed = [];
        $group = [];
        $laneEnds = [];
        $groupEnd = -1;

        $closeGroup = function () use (&$placed, &$group, &$laneEnds): void {
            foreach ($group as $entry) {
                $placed[] = $entry + ['lanes' => count($laneEnds)];
            }
            $group = [];
            $laneEnds = [];
        };

        foreach ($entries as $entry) {
            if ($entry['start'] >= $groupEnd) {
                $closeGroup();
            }

            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $entry['start']) {
                $lane++;
            }
            $laneEnds[$lane] = $entry['end'];
            $groupEnd = max($groupEnd, $entry['end']);
            $group[] = $entry + ['lane' => $lane];
        }
        $closeGroup();

        return $placed;
    }

    /**
     * Начало и конец блока в минутах от полуночи.
     *
     * @param  array{type: string, item: Task|Lesson}  $entry
     * @return array{start: int, end: int}
     */
    private static function span(array $entry): array
    {
        $item = $entry['item'];

        if ($item instanceof Lesson) {
            $start = self::minutes($item->starts_at);
            $end = self::minutes($item->ends_at);
        } else {
            $start = self::minutes($item->time);
            $end = $item->ends_at ? self::minutes($item->ends_at) : $start + self::TASK_DEFAULT_MINUTES;
        }

        return ['start' => $start, 'end' => min(max($end, $start + self::MIN_MINUTES), 24 * 60)];
    }

    private static function minutes(string $clock): int
    {
        return (int) substr($clock, 0, 2) * 60 + (int) substr($clock, 3, 2);
    }
}
