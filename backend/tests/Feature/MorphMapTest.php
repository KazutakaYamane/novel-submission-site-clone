<?php

use App\Infrastructure\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Hash;

// Model のクラス名を変えても保存済みの tokenable_type が壊れないよう、DB にはクラス名ではなく別名を保存する
it('トークンの tokenable_type にクラス名ではなく user を保存する', function () {
    UserModel::factory()->create(['email' => 'taro@example.com', 'password' => Hash::make('password123')]);

    $this->postJson('/api/auth/login', ['email' => 'taro@example.com', 'password' => 'password123'])
        ->assertOk();

    $this->assertDatabaseHas('personal_access_tokens', ['tokenable_type' => 'user']);
});

it('別名のない Model を polymorphic に保存しようとすると例外にする', function () {
    expect(Relation::requiresMorphMap())->toBeTrue();
});
