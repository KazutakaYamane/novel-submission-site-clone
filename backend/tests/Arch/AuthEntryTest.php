<?php

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\User\UserRepositoryInterface;
use App\UseCases\Auth\LoginUseCase;
use App\UseCases\Auth\RegisterUserUseCase;
use App\UseCases\Demo\StartDemoSessionUseCase;

// 会員登録とログインの入口は閉じ、処理はデモログインだけが使う(ADR 0015)
arch('会員登録とログインの UseCase を呼ぶのはデモログインだけ')
    ->expect([RegisterUserUseCase::class, LoginUseCase::class])
    ->toOnlyBeUsedIn([StartDemoSessionUseCase::class]);

// デモ専用の認証を持たず、通常の会員登録とログインの経路を通す
arch('デモログインはユーザーの作成とトークンの発行を会員登録とログインの UseCase に任せる')
    ->expect(StartDemoSessionUseCase::class)
    ->toUse([RegisterUserUseCase::class, LoginUseCase::class])
    ->not->toUse([UserRepositoryInterface::class, PasswordHasherInterface::class, AccessTokensInterface::class]);
