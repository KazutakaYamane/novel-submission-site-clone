<?php

use App\Infrastructure\Eloquent\Models\User;

it('有効なトークンなら自分のユーザーを返す', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('web')->plainTextToken)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('data.password');
});

it('トークンがなければ Accept ヘッダーがなくてもリダイレクトせず 401 の JSON を返す', function () {
    $this->get('/api/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('発行していないトークンなら 401 を返す', function () {
    $this->withToken('1|not-issued-token')
        ->getJson('/api/me')
        ->assertUnauthorized();
});

it('期限切れのトークンなら 401 を返す', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web', ['*'], now()->subMinute());

    $this->withToken($token->plainTextToken)
        ->getJson('/api/me')
        ->assertUnauthorized();
});
