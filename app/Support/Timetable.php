<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Расписание пар: какие пары идут в конкретный день с учётом
 * чётности недели и границ семестра.
 */
class Timetable
{
    public function __construct(
        public readonly ?Carbon $semesterStart,
        public readonly ?Carbon $semesterEnd,
    ) {}

    public static function load(): self
    {
        $parse = fn (?string $v) => $v ? Carbon::createFromFormat('!Y-m-d', $v) : null;

        return new self($parse(Setting::get('semester_start')), $parse(Setting::get('semester_end')));
    }

    /** Номер учебной недели: от начала семестра, а без него — номер недели в году. */
    public function weekNumber(Carbon $date): int
    {
        if (! $this->semesterStart) {
            return $date->isoWeek();
        }

        $from = $this->semesterStart->copy()->startOfWeek(Carbon::MONDAY);
        $to = $date->copy()->startOfWeek(Carbon::MONDAY);

        return intdiv((int) round($from->diffInDays($to, false)), 7) + 1;
    }

    public function isOddWeek(Carbon $date): bool
    {
        return $this->weekNumber($date) % 2 !== 0;
    }

    public function inSemester(Carbon $date): bool
    {
        return (! $this->semesterStart || $date->gte($this->semesterStart))
            && (! $this->semesterEnd || $date->lte($this->semesterEnd));
    }

    /** Пары на дату из уже загруженного списка, по времени начала. */
    public function lessonsOn(Collection $lessons, Carbon $date): Collection
    {
        if (! $this->inSemester($date)) {
            return collect();
        }

        $odd = $this->isOddWeek($date);

        return $lessons
            ->filter(fn (Lesson $l) => $l->weekday === $date->dayOfWeekIso
                && ($l->weeks === 'all' || ($l->weeks === 'odd') === $odd))
            ->sortBy('starts_at')
            ->values();
    }
}
