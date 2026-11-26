<?php

use Illuminate\Database\Eloquent\Relations\Relation;

// Model のクラス名を変えても保存済みの tokenable_type が壊れないよう、DB にはクラス名ではなく別名を保存する
it('トークンの tokenable_type にクラス名ではなく user を保存する', function () {
    $this->postJson('/api/demo/sessions', ['role' => 'reader'])->assertCreated();

    $this->assertDatabaseHas('personal_access_tokens', ['tokenable_type' => 'user']);
});

it('別名のない Model を polymorphic に保存しようとすると例外にする', function () {
    expect(Relation::requiresMorphMap())->toBeTrue();
});
