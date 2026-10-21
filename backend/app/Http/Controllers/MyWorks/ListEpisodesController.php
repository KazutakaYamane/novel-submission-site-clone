<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
        } catch (WorkNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        }
    }
}
