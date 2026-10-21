<?php

namespace App\Http\Controllers\MyWorks;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkResource;
use App\UseCases\Work\ListMyWorksUseCase;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ListWorksController extends Controller
{
    public function __invoke(ListMyWorksUseCase $listMyWorksUseCase): AnonymousResourceCollection
    {
        return WorkResource::collection($listMyWorksUseCase((string) Auth::id()));
    }
}
