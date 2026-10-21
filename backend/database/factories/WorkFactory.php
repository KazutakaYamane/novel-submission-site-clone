<?php

namespace Database\Factories;

use App\Domain\Work\Genre;
use App\Infrastructure\Eloquent\Models\User;
use App\Infrastructure\Eloquent\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->realText(30),
            'catchphrase' => null,
            'introduction' => fake()->realText(200),
            'genre' => fake()->randomElement(Genre::cases())->value,
            'status' => 'ongoing',
            'rating_flags' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }
}
