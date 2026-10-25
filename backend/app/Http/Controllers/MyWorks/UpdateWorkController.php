<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDeniedException;
use App\Domain\Work\WorkNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MyWorks\UpdateWorkRequest;
use App\Http\Resources\WorkResource;
use App\UseCases\Work\UpdateMyWorkUseCase;
use Illuminate\Support\Facades\Auth;

class UpdateWorkController extends Controller
{
    public function __invoke(string $work, UpdateWorkRequest $request, UpdateMyWorkUseCase $updateMyWorkUseCase): WorkResource
    {
        try {
            return new WorkResource($updateMyWorkUseCase((string) Auth::id(), $work, $request->changes()));
        } catch (WorkNotFoundException) {
            abort(404);
        } catch (WorkAccessDeniedException) {
            abort(403);
        }
    }
}
