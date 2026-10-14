<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskItem>
 */
class TaskItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => fake()->words(2, true),
            'done' => false,
            'position' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(['done' => true]);
    }
}
