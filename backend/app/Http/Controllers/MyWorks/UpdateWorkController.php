<?php

namespace App\Http\Controllers\MyWorks;

use App\Domain\Work\WorkAccessDenied;
use App\Domain\Work\WorkNotFound;
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
        } catch (WorkNotFound) {
            abort(404);
        } catch (WorkAccessDenied) {
            abort(403);
        }
    }
}
