<?php

namespace App\Support;

use App\Models\Habit;
use Illuminate\Support\Carbon;

/**
 * Прогресс привычки: текущая и лучшая серия и доля выполненных дней за 30 дней.
 *
 * Считаются только запланированные дни: пропуск дня, когда привычка не нужна
 * (например, выходного для «по будням»), серию не прерывает. Сегодняшний день,
 * пока не выполнен, серию тоже не обнуляет — он ещё не закончился.
 */
class HabitProgress
{
    public const WINDOW_DAYS = 30;

    /**
     * @param  array<string, int>  $counts  дата «Y-m-d» => сколько раз выполнено
     * @return array{current: int, best: int, rate: ?int}
     */
    public static function for(Habit $habit, array $counts, Carbon $today): array
    {
        $today = $today->copy()->startOfDay();
        $isDone = fn (Carbon $day): bool => ($counts[$day->toDateString()] ?? 0) >= $habit->target;
        $firstDay = self::firstDay($habit, $counts, $today);

        $current = 0;
        $cursor = $today->copy();
        if ($habit->isScheduledOn($cursor) && ! $isDone($cursor)) {
            $cursor->subDay();
        }
        for (; $cursor->gte($firstDay); $cursor->subDay()) {
            if (! $habit->isScheduledOn($cursor)) {
                continue;
            }
            if (! $isDone($cursor)) {
                break;
            }
            $current++;
        }

        $best = 0;
        $run = 0;
        for ($day = $firstDay->copy(); $day->lte($today); $day->addDay()) {
            if (! $habit->isScheduledOn($day)) {
                continue;
            }
            if ($isDone($day)) {
                $best = max($best, ++$run);
            } elseif ($day->lt($today)) {
                $run = 0;
            }
        }

        $scheduled = 0;
        $done = 0;
        $windowStart = $today->copy()->subDays(self::WINDOW_DAYS - 1)->max($firstDay);
        for ($day = $windowStart->copy(); $day->lte($today); $day->addDay()) {
            if (! $habit->isScheduledOn($day) || ($day->eq($today) && ! $isDone($day))) {
                continue;
            }
            $scheduled++;
            $done += $isDone($day) ? 1 : 0;
        }

        return [
            'current' => $current,
            'best' => $best,
            'rate' => $scheduled > 0 ? (int) round($done / $scheduled * 100) : null,
        ];
    }

    /**
     * С какого дня считать: создание привычки или самая ранняя отметка, если она раньше.
     *
     * @param  array<string, int>  $counts
     */
    private static function firstDay(Habit $habit, array $counts, Carbon $today): Carbon
    {
        $created = ($habit->created_at ?? $today)->copy()->startOfDay();
        $earliest = $counts === [] ? null : Carbon::createFromFormat('!Y-m-d', min(array_keys($counts)));

        return $earliest && $earliest->lt($created) ? $earliest : $created;
    }
}
