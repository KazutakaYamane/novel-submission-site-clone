<?php

namespace App\UseCases\Demo;

use App\Domain\Demo\DemoRole;
use App\Domain\Demo\DemoSession;
use App\Domain\Demo\SampleEpisode;
use App\Domain\Demo\SampleWorkSourceInterface;
use App\Domain\Shared\ClockInterface;
use App\Domain\Shared\TransactionInterface;
use App\Domain\Shared\UuidGeneratorInterface;
use App\UseCases\Auth\LoginUseCase;
use App\UseCases\Auth\RegisterUserUseCase;
use App\UseCases\Work\ImportWorkUseCase;

final readonly class StartDemoSessionUseCase
{
    public function __construct(
        private RegisterUserUseCase $registerUser,
        private LoginUseCase $login,
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
        $email = "demo-{$uuid}@example.com";
        // 同じユーザーに戻る手段は持たない(ADR 0015)ので、パスワードはこの処理の中だけで使い、利用者に返さない
        $password = bin2hex(random_bytes(32));

        return $this->transaction->run(function () use ($role, $uuid, $label, $email, $password) {
            // 会員登録の UseCase もトークンを発行するが、利用者に渡すのはログインで発行したトークンだけにする
            ($this->registerUser)($label.'-'.substr($uuid, 0, 4), $email, $password);
            $authenticated = ($this->login)($email, $password);
            $sampleWorkId = $role === DemoRole::Author ? $this->importSampleWork($authenticated->user->id) : null;

            return new DemoSession($authenticated, $sampleWorkId);
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
