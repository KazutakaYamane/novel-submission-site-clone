<?php

use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// SPA と同じく stateful ドメインの Origin を付け、Sanctum に cookie セッションを使わせる
beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
});

function registerPayload(array $overrides = []): array
{
    return [
        'name' => '山田太郎',
        'email' => 'taro@example.com',
        'password' => 'password123',
        ...$overrides,
    ];
}

it('会員登録すると 201 とユーザーを返し、id は UUID で password を含まない', function () {
    $response = $this->postJson('/api/auth/register', registerPayload())
        ->assertCreated()
        ->assertJsonPath('data.name', '山田太郎')
        ->assertJsonPath('data.email', 'taro@example.com')
        ->assertJsonMissingPath('data.password');

    expect(Str::isUuid($response->json('data.id')))->toBeTrue();
});

it('会員登録した直後はログイン状態になる', function () {
    $this->postJson('/api/auth/register', registerPayload())->assertCreated();

    $this->assertAuthenticatedAs(User::where('email', 'taro@example.com')->firstOrFail());
});

it('email の形式が不正なら 422 で email のエラーを返す', function () {
    $this->postJson('/api/auth/register', registerPayload(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['email']);

    $this->assertGuest();
});

it('登録済みの email なら 422 で email のエラーを返す', function () {
    User::factory()->create(['email' => 'taro@example.com']);

    $this->postJson('/api/auth/register', registerPayload())
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['email']);
});

it('password が 8 文字未満なら 422 で password のエラーを返す', function () {
    $this->postJson('/api/auth/register', registerPayload(['password' => 'short12']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['password']);
});

it('password は平文ではなくハッシュで保存する', function () {
    $this->postJson('/api/auth/register', registerPayload())->assertCreated();

    $stored = User::where('email', 'taro@example.com')->firstOrFail()->getAuthPassword();

    expect($stored)->not->toBe('password123')
        ->and(Hash::check('password123', $stored))->toBeTrue();
});

it('users はデモ用やメール認証用の列を持たない', function () {
    expect(Schema::getColumnListing('users'))
        ->toEqualCanonicalizing(['id', 'name', 'email', 'password', 'created_at', 'updated_at']);
});
