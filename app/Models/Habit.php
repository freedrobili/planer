<?php

namespace App\Models;

use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Привычка: что делать, в какие дни недели и сколько раз в день.
 */
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    public const MAX_TARGET = 50;

    protected $fillable = ['name', 'color', 'weekdays', 'target', 'position'];

    protected $casts = [
        'weekdays' => 'array',
        'target' => 'integer',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(HabitCheck::class);
    }

    public function isScheduledOn(Carbon $date): bool
    {
        return in_array($date->dayOfWeekIso, $this->weekdays, true);
    }

    public function isCounted(): bool
    {
        return $this->target > 1;
    }

    /** «Каждый день», «По будням», «Пн, Ср, Пт». */
    public function scheduleLabel(): string
    {
        $days = collect($this->weekdays)->sort()->values()->all();

        return match ($days) {
            [1, 2, 3, 4, 5, 6, 7] => 'каждый день',
            [1, 2, 3, 4, 5] => 'по будням',
            [6, 7] => 'по выходным',
            default => collect($days)->map(fn (int $day) => Lesson::WEEKDAYS_SHORT[$day])->implode(', '),
        };
    }
}
