<?php

namespace App\Domain\Demo;

use App\Domain\Auth\AuthenticatedUser;

final readonly class DemoSession
{
    public function __construct(
        public AuthenticatedUser $authenticated,
        public ?string $sampleWorkId,
    ) {}
}
