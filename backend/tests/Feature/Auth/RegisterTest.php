<?php

use App\Infrastructure\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

it('ユーザーの id は UUIDv7 にする', function () {
    $id = $this->postJson('/api/auth/register', registerPayload())->assertCreated()->json('data.id');

    expect(Str::isUuid($id, 7))->toBeTrue();
});

it('会員登録すると 7 日後に期限が切れるトークンを返す', function () {
    $this->freezeTime();

    $this->postJson('/api/auth/register', registerPayload())
        ->assertCreated()
        ->assertJsonPath('expires_at', now()->addDays(7)->format(DateTimeInterface::ATOM))
        ->assertJsonStructure(['token']);
});

it('会員登録で返したトークンで /api/me が自分を返す', function () {
    $token = $this->postJson('/api/auth/register', registerPayload())->json('token');

    $this->withToken($token)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'taro@example.com');
});

it('email の形式が不正なら 422 で email のエラーを返し、ユーザーもトークンも作らない', function () {
    $this->postJson('/api/auth/register', registerPayload(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['email']);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('登録済みの email なら 422 で email のエラーを返し、トークンを発行しない', function () {
    UserModel::factory()->create(['email' => 'taro@example.com']);

    $this->postJson('/api/auth/register', registerPayload())
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['email']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('password が 8 文字未満なら 422 で password のエラーを返す', function () {
    $this->postJson('/api/auth/register', registerPayload(['password' => 'short12']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['password']);
});

it('password は平文ではなくハッシュで保存する', function () {
    $this->postJson('/api/auth/register', registerPayload())->assertCreated();

    $stored = UserModel::where('email', 'taro@example.com')->firstOrFail()->getAuthPassword();

    expect($stored)->not->toBe('password123')
        ->and(Hash::check('password123', $stored))->toBeTrue();
});

it('users はデモ用やメール認証用の列を持たない', function () {
    expect(Schema::getColumnListing('users'))
        ->toEqualCanonicalizing(['id', 'name', 'email', 'password', 'created_at', 'updated_at']);
});

it('422 のメッセージは日本語の項目名で返す', function () {
    $this->postJson('/api/auth/register', registerPayload(['name' => '', 'email' => 'not-an-email', 'password' => 'short']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.name', ['名前を入力してください'])
        ->assertJsonPath('errors.email', ['メールアドレスの形式が正しくありません'])
        ->assertJsonPath('errors.password', ['パスワードは8文字以上で入力してください']);
});

it('登録済みの email なら日本語で重複を伝える', function () {
    UserModel::factory()->create(['email' => 'taro@example.com']);

    $this->postJson('/api/auth/register', registerPayload())
        ->assertJsonPath('errors.email', ['このメールアドレスは既に使われています']);
});
