<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// 本番の app:reset からも実行する。本番イメージには dev 依存(Faker)が無いので、ファクトリーを使わない
class DatabaseSeeder extends Seeder
{
    public function run(): void {}
}
