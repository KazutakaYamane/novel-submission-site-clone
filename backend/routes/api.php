<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Demo\StartDemoSessionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MyWorks\ListEpisodesController;
use App\Http\Controllers\MyWorks\ListWorksController;
use App\Http\Controllers\MyWorks\PublishEpisodeController;
use App\Http\Controllers\MyWorks\ShowEpisodeController;
use App\Http\Controllers\MyWorks\ShowWorkController;
use App\Http\Controllers\MyWorks\StoreEpisodeController;
use App\Http\Controllers\MyWorks\StoreWorkController;
use App\Http\Controllers\MyWorks\UnpublishEpisodeController;
use App\Http\Controllers\MyWorks\UpdateEpisodeController;
use App\Http\Controllers\MyWorks\UpdateWorkController;
use App\Http\Controllers\PublicWorks\ShowPublicEpisodeController;
use App\Http\Controllers\PublicWorks\ShowPublicWorkController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/auth/register', RegisterController::class);
Route::post('/auth/login', LoginController::class)->middleware('throttle:login');
Route::post('/demo/sessions', StartDemoSessionController::class)->middleware('throttle:demo');

Route::get('/works/{work}', ShowPublicWorkController::class)->whereUuid('work');
Route::get('/works/{work}/episodes/{episode}', ShowPublicEpisodeController::class)->whereUuid(['work', 'episode']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', LogoutController::class);
    Route::get('/me', MeController::class);

    Route::get('/my/works', ListWorksController::class);
    Route::post('/my/works', StoreWorkController::class);
    // ルートモデル結合は使わない(ADR 0008)。UUID でない ID は UseCase に渡さず 404 にする
    Route::get('/my/works/{work}', ShowWorkController::class)->whereUuid('work');
    Route::patch('/my/works/{work}', UpdateWorkController::class)->whereUuid('work');

    Route::get('/my/works/{work}/episodes', ListEpisodesController::class)->whereUuid('work');
    Route::post('/my/works/{work}/episodes', StoreEpisodeController::class)->whereUuid('work');
    Route::get('/my/works/{work}/episodes/{episode}', ShowEpisodeController::class)->whereUuid(['work', 'episode']);
    Route::patch('/my/works/{work}/episodes/{episode}', UpdateEpisodeController::class)->whereUuid(['work', 'episode']);
    Route::post('/my/works/{work}/episodes/{episode}/publish', PublishEpisodeController::class)->whereUuid(['work', 'episode']);
    Route::post('/my/works/{work}/episodes/{episode}/unpublish', UnpublishEpisodeController::class)->whereUuid(['work', 'episode']);
});
