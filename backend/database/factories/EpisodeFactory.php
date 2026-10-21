<?php

namespace Database\Factories;

use App\Infrastructure\Eloquent\Models\Episode;
use App\Infrastructure\Eloquent\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_id' => Work::factory(),
            'title' => fake()->realText(20),
            'body' => fake()->realText(500),
            'status' => 'draft',
        ];
    }

    public function configure(): static
    {
        // (work_id, position) の unique 制約に掛からないよう、作品の最後の話の次に置く。
        // count(n) は全件の属性を作ってから保存するので、DB の max に作った順の番号を足す
        return $this->sequence(function (Sequence $sequence) {
            // Sequence は属性を返した後に index を進めるので、ここで値を取っておく
            $index = $sequence->index;

            return ['position' => fn (array $attributes) => (int) Episode::where('work_id', $attributes['work_id'])->max('position') + $index + 1];
        });
    }
}
