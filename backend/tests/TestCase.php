<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 手元で動いている Next.js などに、テストからリクエストが届かないようにする
        Http::preventStrayRequests();
    }
}
