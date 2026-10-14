<?php

namespace Database\Factories;

use App\Models\Habit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'color' => 'green',
            'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'target' => 1,
            'position' => 0,
        ];
    }

    /**
     * Привычка «со счётом», например 8 стаканов воды.
     */
    public function counted(int $target = 8): static
    {
        return $this->state(['target' => $target]);
    }

    /**
     * @param  list<int>  $weekdays
     */
    public function on(array $weekdays): static
    {
        return $this->state(['weekdays' => $weekdays]);
    }
}
