<?php

namespace App\Infrastructure\Eloquent;

use App\Infrastructure\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class EloquentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 一覧で関連を 1 件ずつ読み込むと作品の数だけクエリが増えるので、本番以外では例外にする
        Model::preventLazyLoading(! $this->app->isProduction());

        // polymorphic な *_type 列にクラス名を保存すると、Model の名前を変えたときに保存済みの行が壊れる
        Relation::enforceMorphMap([
            'user' => UserModel::class,
        ]);
    }
}
