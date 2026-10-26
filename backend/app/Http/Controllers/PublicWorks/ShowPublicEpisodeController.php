<?php

namespace App\Http\Controllers\PublicWorks;

use App\Domain\Episode\EpisodeNotFoundException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicEpisodeResource;
use App\UseCases\PublicWork\GetPublishedEpisodeUseCase;

class ShowPublicEpisodeController extends Controller
{
    public function __invoke(string $work, string $episode, GetPublishedEpisodeUseCase $getPublishedEpisodeUseCase): PublicEpisodeResource
    {
        try {
            return new PublicEpisodeResource($getPublishedEpisodeUseCase($work, $episode));
        } catch (WorkNotFoundException|EpisodeNotFoundException) {
            abort(404);
        }
    }
}
