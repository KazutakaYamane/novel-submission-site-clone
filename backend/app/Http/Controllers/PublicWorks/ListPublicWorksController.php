<?php

namespace App\Http\Controllers\PublicWorks;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicWorks\ListPublicWorksRequest;
use App\Http\Resources\PublicWorkSummaryResource;
use App\UseCases\PublicWork\ListPublishedWorksUseCase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListPublicWorksController extends Controller
{
    public function __invoke(ListPublicWorksRequest $request, ListPublishedWorksUseCase $listPublishedWorksUseCase): AnonymousResourceCollection
    {
        return PublicWorkSummaryResource::collection($listPublishedWorksUseCase($request->limit()));
    }
}
