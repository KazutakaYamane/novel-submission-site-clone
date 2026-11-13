<?php

use App\Infrastructure\Eloquent\Models\UserModel;
use Faker\Generator;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// MySQL の DDL は暗黙にコミットするので、RefreshDatabase のロールバックではシードの行が消えず、後のテストに残る。
// テストの後にシードなしで作り直す
it('app:reset --force はユーザーとトークンを消してから Redis を全消去する', function () {
    $user = UserModel::factory()->create();
    $user->createToken('web');
    Redis::shouldReceive('flushall')->once()->andReturnUsing(function () use ($user) {
        expect(DB::table('users')->where('id', $user->id)->exists())->toBeFalse()
            ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->exists())->toBeFalse();

        return true;
    });

    $this->artisan('app:reset', ['--force' => true])->assertSuccessful();
})->after(fn () => $this->artisan('migrate:fresh'));

it('本番では確認を求め、断ると DB にも Redis にも触れない', function () {
    $this->app['env'] = 'production';
    $user = UserModel::factory()->create();
    Redis::shouldReceive('flushall')->never();

    $this->artisan('app:reset')
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    $this->assertModelExists($user);
});

it('migrate:fresh が失敗したら Redis を消さずに失敗で終わる', function () {
    FreshCommand::prohibit();
    Redis::shouldReceive('flushall')->never();

    $this->artisan('app:reset', ['--force' => true])->assertFailed();
})->after(fn () => FreshCommand::prohibit(false));

// 本番イメージは composer install --no-dev で作り、fakerphp/faker を含まない
it('シードは Faker を使わずに終わる', function () {
    $locale = config('app.faker_locale');
    foreach ([Generator::class, Generator::class.':'.$locale] as $abstract) {
        $this->app->bind($abstract, fn () => throw new LogicException('本番イメージに Faker は無い'));
    }

    $this->seed();
})->throwsNoExceptions();
