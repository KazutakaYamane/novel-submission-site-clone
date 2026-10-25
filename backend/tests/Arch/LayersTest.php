<?php

use Illuminate\Database\Eloquent\Model;

// 層の依存の向きとシングルアクションのルール(docs/adr/0008-backend-layers.md)

arch('Domain は Laravel にもほかの層にも依存しない')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\UseCases', 'App\Http', 'App\Infrastructure']);

arch('UseCases は Domain にだけ依存する')
    ->expect('App\UseCases')
    ->not->toUse(['Illuminate', 'App\Http', 'App\Infrastructure']);

// factory・seeder(Database\*)とテストは Eloquent モデルを直接使ってよい
arch('Eloquent と DB は Infrastructure だけが使う')
    ->expect(['Illuminate\Database', 'Illuminate\Support\Facades\DB'])
    ->toOnlyBeUsedIn(['App\Infrastructure', 'Database', 'Tests']);

arch('Infrastructure を参照するのは ServiceProvider だけ')
    ->expect('App\Infrastructure')
    ->toOnlyBeUsedIn(['App\Infrastructure', 'App\Providers', 'Database', 'Tests']);

arch('UseCase のクラス名は UseCase で終わる')
    ->expect('App\UseCases')
    ->classes()
    ->toHaveSuffix('UseCase');

arch('UseCase は __invoke だけを公開する')
    ->expect('App\UseCases')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', '__invoke']);

arch('コントローラーは __invoke だけを公開する')
    ->expect('App\Http\Controllers')
    ->classes()
    ->not->toHavePublicMethodsBesides(['__construct', '__invoke']);

// entity と見分けられるよう、Domain の interface は名前で示す
arch('Domain の interface の名前は Interface で終わる')
    ->expect('App\Domain')
    ->interfaces()
    ->toHaveSuffix('Interface');

arch('Domain の例外の名前は Exception で終わる')
    ->expect('App\Domain')
    ->classes()
    ->extending(DomainException::class)
    ->toHaveSuffix('Exception');

// Domain の entity と同じ名前にならないよう、Eloquent の Model は名前で示す
arch('Eloquent の Model の名前は Model で終わる')
    ->expect('App')
    ->classes()
    ->extending(Model::class)
    ->toHaveSuffix('Model');
