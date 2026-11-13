<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// デモユーザーと荒らしの投稿を毎日消す(mvp.md §4-2)。
// onOneServer はキャッシュのロックで 1 台に絞るので、全タスクが同じ Redis をキャッシュストアに使うことが前提
Schedule::command('app:reset --force')
    ->dailyAt('04:00')
    ->timezone('Asia/Tokyo')
    ->onOneServer();
