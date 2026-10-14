<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'color' => fake()->randomElement(array_keys(Tag::COLORS)),
            'position' => 0,
        ];
    }
}
