<?php

use App\Infrastructure\Eloquent\Models\UserModel;
use App\Infrastructure\Eloquent\Models\WorkModel;

it('自分の作品だけを作成日時の新しい順に返す', function () {
    $user = UserModel::factory()->create();
    $old = WorkModel::factory()->state(['user_id' => $user->id])->create(['created_at' => now()->subDays(2)]);
    $new = WorkModel::factory()->state(['user_id' => $user->id])->create(['created_at' => now()->subDay()]);
    WorkModel::factory()->create();

    $this->withToken($user->createToken('web')->plainTextToken)
        ->getJson('/api/my/works')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
});

// created_at は秒単位なので、同じ秒に作った作品の順は id で決める
it('同じ時刻に作った作品は、後に作った方を先に返す', function () {
    $this->freezeSecond();
    $user = UserModel::factory()->create();
    $this->withToken($user->createToken('web')->plainTextToken);

    $ids = collect(range(1, 5))->map(fn (int $i) => $this->postJson('/api/my/works', [
        'title' => "作品{$i}",
        'introduction' => 'あらすじ',
        'genre' => 'fantasy',
    ])->json('data.id'));

    $this->getJson('/api/my/works')
        ->assertOk()
        ->assertJsonPath('data.*.id', $ids->reverse()->values()->all());
});

it('作品が無ければ空の配列を返す', function () {
    $user = UserModel::factory()->create();

    $this->withToken($user->createToken('web')->plainTextToken)
        ->getJson('/api/my/works')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('未ログインなら 401 を返す', function () {
    $this->getJson('/api/my/works')->assertUnauthorized();
});
