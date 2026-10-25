<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EpisodeSummaryResource;
use App\UseCases\Episode\ListMyEpisodesUseCase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ListEpisodesController extends Controller
{
    public function __invoke(string $work, ListMyEpisodesUseCase $listMyEpisodesUseCase): AnonymousResourceCollection
    {
        try {
            return EpisodeSummaryResource::collection($listMyEpisodesUseCase((string) Auth::id(), $work));
        } catch (WorkNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        }
    }
}
