<?php

namespace App\Domain\Health;

interface DependencyCheckerInterface
{
    public function database(): CheckResult;

    public function redis(): CheckResult;
}
