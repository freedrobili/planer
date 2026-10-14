<?php

namespace App\Models;

use App\Support\Owner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    public const KINDS = [
        'lecture' => 'Лекция',
        'practice' => 'Практика',
        'lab' => 'Лабораторная',
        'seminar' => 'Семинар',
    ];

    public const WEEKS = [
        'all' => 'Каждую неделю',
        'odd' => 'По нечётным',
        'even' => 'По чётным',
    ];

    public const WEEKDAYS = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
        7 => 'Воскресенье',
    ];

    public const WEEKDAYS_SHORT = [
        1 => 'Пн',
        2 => 'Вт',
        3 => 'Ср',
        4 => 'Чт',
        5 => 'Пт',
        6 => 'Сб',
        7 => 'Вс',
    ];

    protected $fillable = ['weekday', 'starts_at', 'ends_at', 'subject', 'kind', 'room', 'group_name', 'weeks', 'person_id'];

    protected $casts = [
        'weekday' => 'integer',
    ];

    /** Пары одного графика: свои или конкретного человека. */
    public function scopeOwnedBy(Builder $query, Owner $owner): Builder
    {
        return $owner->scope($query);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('weekday')->orderBy('starts_at')->orderBy('id');
    }

    public function kindLabel(): ?string
    {
        return self::KINDS[$this->kind] ?? null;
    }

    /** Часы, которые занимает пара (для подсветки в расписании дня). */
    public function coveredHours(): array
    {
        $start = (int) substr($this->starts_at, 0, 2);
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->ends_at));
        $last = $endMinute > 0 ? $endHour : $endHour - 1;

        return range($start, max($start, $last));
    }

    public function startHour(): int
    {
        return (int) substr($this->starts_at, 0, 2);
    }
}
