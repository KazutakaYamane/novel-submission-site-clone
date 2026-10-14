<?php

use Illuminate\Support\Facades\DB;

it('テストは開発用とは別の _test DB に接続する', function () {
    expect(DB::connection()->getDatabaseName())->toEndWith('_test');
});
