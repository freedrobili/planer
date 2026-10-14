<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\HabitCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitCheck>
 */
class HabitCheckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'date' => now()->toDateString(),
            'count' => 1,
        ];
    }
}
