<?php

namespace App\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class EloquentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 一覧で関連を 1 件ずつ読み込むと作品の数だけクエリが増えるので、本番以外では例外にする
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
