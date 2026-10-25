<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
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
        } catch (WorkNotFoundException|EpisodeNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        }
    }
}
