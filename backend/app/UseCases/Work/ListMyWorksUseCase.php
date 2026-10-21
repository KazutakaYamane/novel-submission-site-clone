<?php

namespace App\UseCases\Work;

use App\Domain\Work\Work;
use App\Domain\Work\WorkRepository;

final readonly class ListMyWorksUseCase
{
    public function __construct(
        private WorkRepository $works,
    ) {}

    /**
     * @return list<Work>
     */
    public function __invoke(string $userId): array
    {
        return $this->works->listByUser($userId);
    }
}
