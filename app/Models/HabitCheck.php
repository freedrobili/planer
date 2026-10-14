<?php

namespace App\Models;

use Database\Factories\HabitCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Отметка привычки за день: сколько раз выполнено (для простой привычки — 0 или 1).
 */
class HabitCheck extends Model
{
    /** @use HasFactory<HabitCheckFactory> */
    use HasFactory;

    protected $fillable = ['date', 'count'];

    protected $casts = [
        'count' => 'integer',
    ];

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
