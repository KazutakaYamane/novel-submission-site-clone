<?php

namespace App\UseCases\Demo;

use App\Domain\Auth\AccessTokensInterface;
use App\Domain\Auth\AuthenticatedUser;
use App\Domain\Demo\DemoRole;
use App\Domain\Demo\DemoSession;
use App\Domain\Demo\SampleEpisode;
use App\Domain\Demo\SampleWorkSourceInterface;
use App\Domain\Shared\ClockInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\Shared\UuidGeneratorInterface;
use App\Domain\User\UserRepositoryInterface;
use App\UseCases\Work\ImportWorkUseCase;

final readonly class StartDemoSessionUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher,
        private AccessTokensInterface $tokens,
        private TransactionInterface $transaction,
        private UuidGeneratorInterface $uuids,
        private SampleWorkSourceInterface $sampleWorks,
        private ImportWorkUseCase $importWork,
        private ClockInterface $clock,
    ) {}

    public function __invoke(DemoRole $role): DemoSession
    {
        $uuid = $this->uuids->generate();
        $label = match ($role) {
            DemoRole::Author => 'デモ作者',
            DemoRole::Reader => 'デモ読者',
        };
        // 再ログインはボタンを押し直してもらうので、パスワードはユーザーに見せずに捨てる
        $hashedPassword = $this->hasher->hash(bin2hex(random_bytes(32)));

        return $this->transaction->run(function () use ($role, $uuid, $label, $hashedPassword) {
            // UUID の重複は起こらない前提で、existsByEmail で確かめない
            $user = $this->users->create($label.'-'.substr($uuid, 0, 4), "demo-{$uuid}@example.com", $hashedPassword);
            $sampleWorkId = $role === DemoRole::Author ? $this->importSampleWork($user->id) : null;

            return new DemoSession(new AuthenticatedUser($user, $this->tokens->issue($user->id)), $sampleWorkId);
        });
    }

    private function importSampleWork(string $userId): string
    {
        $sample = $this->sampleWorks->load();
        $now = $this->clock->now();

        return ($this->importWork)(
            userId: $userId,
            title: $sample->title,
            catchphrase: $sample->catchphrase,
            introduction: $sample->introduction,
            genre: $sample->genre,
            status: $sample->status,
            ratingFlags: $sample->ratingFlags,
            tags: $sample->tags,
            episodes: array_map(fn (SampleEpisode $episode) => [
                'title' => $episode->title,
                'body' => $episode->body,
                'publishedAt' => $episode->publishedBefore === null ? null : $now->sub($episode->publishedBefore),
            ], $sample->episodes),
        )->id;
    }
}
