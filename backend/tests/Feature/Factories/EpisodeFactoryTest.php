<?php

use App\Infrastructure\Eloquent\Models\Episode;
use App\Infrastructure\Eloquent\Models\Work;

it('同じ作品にまとめて作ると position を 1 から順に振る', function () {
    $work = Work::factory()->create();

    $episodes = Episode::factory()->count(3)->state(['work_id' => $work->id])->create();

    expect($episodes->pluck('position')->all())->toBe([1, 2, 3]);
});

it('作品の既存の話の次から振る', function () {
    $work = Work::factory()->create();
    Episode::factory()->state(['work_id' => $work->id])->create();

    $episodes = Episode::factory()->count(2)->state(['work_id' => $work->id])->create();

    expect($episodes->pluck('position')->all())->toBe([2, 3]);
});
