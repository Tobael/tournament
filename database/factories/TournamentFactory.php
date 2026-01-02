<?php

namespace Database\Factories;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tournament>
 */
class TournamentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'status' => Status::IN_PROGRESS,
        ];
    }
}
