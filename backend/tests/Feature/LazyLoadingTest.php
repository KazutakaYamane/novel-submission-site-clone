<?php

use Illuminate\Database\Eloquent\Model;

// 一覧で関連を 1 件ずつ読み込むと作品の数だけクエリが増えるので、本番以外では例外にして気づけるようにする
it('本番以外では関連の遅延読み込みを禁止する', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
});
