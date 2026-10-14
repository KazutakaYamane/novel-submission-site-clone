<?php

namespace App\Http\Controllers\Auth;

use App\Domain\User\EmailAlreadyTaken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\UseCases\Auth\RegisterUserUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUserUseCase $registerUserUseCase): JsonResponse
    {
        try {
            $user = $registerUserUseCase(
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        } catch (EmailAlreadyTaken) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        // SessionGuard::login がセッション ID を振り直すので、ここで regenerate しない
        Auth::loginUsingId($user->id);

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
