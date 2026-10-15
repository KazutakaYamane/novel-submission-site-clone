<?php

use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
    User::factory()->create([
        'name' => '山田太郎',
        'email' => 'taro@example.com',
        'password' => Hash::make('password123'),
    ]);
});

it('正しい資格情報なら 200 とユーザーを返し、以後の /api/me が自分を返す', function () {
    $this->postJson('/api/auth/login', ['email' => 'taro@example.com', 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('data.email', 'taro@example.com')
        ->assertJsonMissingPath('data.password');

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'taro@example.com');
});

it('パスワード誤りと未登録のメールに、同じ 422 とメッセージを返す', function (string $email, string $password) {
    $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'メールアドレスまたはパスワードが正しくありません');

    $this->assertGuest();
})->with([
    'パスワード誤り' => ['taro@example.com', 'wrong-password'],
    '未登録のメール' => ['nobody@example.com', 'password123'],
]);

it('email と password がなければ 422 でフィールドごとのエラーを返す', function () {
    $this->postJson('/api/auth/login', [])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['email', 'password']);
});

it('同じ email と IP で 1 分に 5 回を超えると 429 を返す', function () {
    $payload = ['email' => 'taro@example.com', 'password' => 'wrong-password'];

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/auth/login', $payload)->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', $payload)->assertTooManyRequests();
});
