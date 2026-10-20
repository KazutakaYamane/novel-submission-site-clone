<?php

use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Support\Facades\Hash;

// ブラウザからの直接アクセスと同じ Origin を付けても、セッション cookie と XSRF-TOKEN を発行しないことを確かめる
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('会員登録の応答に cookie を付けない', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => '山田太郎',
        'email' => 'taro@example.com',
        'password' => 'password123',
    ])->assertCreated();

    expect($response->headers->getCookies())->toBeEmpty();
});

it('ログイン、/api/me、ログアウトの応答に cookie を付けない', function () {
    User::factory()->create(['email' => 'taro@example.com', 'password' => Hash::make('password123')]);

    $login = $this->postJson('/api/auth/login', ['email' => 'taro@example.com', 'password' => 'password123'])
        ->assertOk();
    $token = $login->json('token');

    $me = $this->withToken($token)->getJson('/api/me')->assertOk();

    $this->app['auth']->forgetGuards();
    $logout = $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

    expect($login->headers->getCookies())->toBeEmpty()
        ->and($me->headers->getCookies())->toBeEmpty()
        ->and($logout->headers->getCookies())->toBeEmpty();
});
