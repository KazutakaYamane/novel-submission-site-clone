<?php

namespace App\Domain\Health;

interface DependencyChecker
{
    public function database(): CheckResult;

    public function redis(): CheckResult;
}
