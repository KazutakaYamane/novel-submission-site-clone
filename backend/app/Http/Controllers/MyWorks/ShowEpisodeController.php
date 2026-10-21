<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EpisodeNotFound;
use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpisodeResource;
use App\UseCases\Episode\GetMyEpisodeUseCase;
use Illuminate\Support\Facades\Auth;

class ShowEpisodeController extends Controller
{
    public function __invoke(string $work, string $episode, GetMyEpisodeUseCase $getMyEpisodeUseCase): EpisodeResource
    {
        try {
            return new EpisodeResource($getMyEpisodeUseCase((string) Auth::id(), $work, $episode));
        } catch (WorkNotFound|EpisodeNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        }
    }
}
