<?php

use App\Infrastructure\Eloquent\Models\User;

beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('ログイン中なら自分のユーザーを返す', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('data.password');
});

it('未ログインなら Accept ヘッダーがなくてもリダイレクトせず 401 の JSON を返す', function () {
    $this->get('/api/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});
