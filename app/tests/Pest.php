<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature テストは Laravel の TestCase を継承する(アプリ起動・HTTP テスト等)。
| DB を使うテストを書くときは RefreshDatabase の use を有効化する。
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
