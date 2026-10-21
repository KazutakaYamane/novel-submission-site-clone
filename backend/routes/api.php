<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MyWorks\ListWorksController;
use App\Http\Controllers\MyWorks\ShowWorkController;
use App\Http\Controllers\MyWorks\StoreWorkController;
use App\Http\Controllers\MyWorks\UpdateWorkController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class)->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', LogoutController::class);
    Route::get('/me', MeController::class);

    Route::get('/my/works', ListWorksController::class);
    Route::post('/my/works', StoreWorkController::class);
    // ルートモデル結合は使わない(ADR 0008)。UUID でない ID は UseCase に渡さず 404 にする
    Route::get('/my/works/{work}', ShowWorkController::class)->whereUuid('work');
    Route::patch('/my/works/{work}', UpdateWorkController::class)->whereUuid('work');
});
