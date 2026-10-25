<?php

namespace App\Http\Controllers\Auth;

use App\Domain\User\EmailAlreadyTakenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\UseCases\Auth\RegisterUserUseCase;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUserUseCase $registerUserUseCase): JsonResponse
    {
        try {
            $authenticated = $registerUserUseCase(
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        } catch (EmailAlreadyTakenException) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => __('validation.attributes.email')]),
            ]);
        }

        return (new UserResource($authenticated->user))
            ->additional([
                'token' => $authenticated->token->token,
                'expires_at' => $authenticated->token->expiresAt->format(DateTimeInterface::ATOM),
            ])
            ->response()
            ->setStatusCode(201);
    }
}
