<?php

namespace App\UseCases\Work;

use App\Domain\Work\Genre;
use App\Domain\Work\RatingFlag;
use App\Domain\Work\Work;
use App\Domain\Work\WorkRepositoryInterface;
use App\Domain\Work\WorkStatus;

final readonly class CreateWorkUseCase
{
    public function __construct(
        private WorkRepositoryInterface $works,
    ) {}

    /**
     * @param  list<RatingFlag>  $ratingFlags
     * @param  list<string>  $tags
     */
    public function __invoke(
        string $userId,
        string $title,
        ?string $catchphrase,
        string $introduction,
        Genre $genre,
        ?WorkStatus $status,
        array $ratingFlags,
        array $tags,
    ): Work {
        return $this->works->create(
            $userId,
            $title,
            $catchphrase,
            $introduction,
            $genre,
            $status ?? WorkStatus::Ongoing,
            $ratingFlags,
            $tags,
        );
    }
}
