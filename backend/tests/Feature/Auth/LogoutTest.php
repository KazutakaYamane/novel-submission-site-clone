<?php

use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('ログアウトすると 204 を返し、以後の /api/me は 401 になる', function () {
    User::factory()->create(['email' => 'taro@example.com', 'password' => Hash::make('password123')]);
    $this->postJson('/api/auth/login', ['email' => 'taro@example.com', 'password' => 'password123'])->assertOk();

    $this->postJson('/api/auth/logout')->assertNoContent();

    $this->assertGuest('web');
    // テストではアプリが使い回され、sanctum ガードが解決済みのユーザーを持ち越すため、次のリクエストと同じくガードを作り直す
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/me')->assertUnauthorized();
});

it('未ログインでログアウトすると 401 を返す', function () {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});
