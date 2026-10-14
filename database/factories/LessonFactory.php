<?php

namespace Database\Factories;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'weekday' => 1,
            'starts_at' => '10:10',
            'ends_at' => '11:40',
            'subject' => fake()->words(2, true),
            'kind' => 'lecture',
            'room' => (string) fake()->numberBetween(100, 500),
            'group_name' => null,
            'weeks' => 'all',
        ];
    }
}
