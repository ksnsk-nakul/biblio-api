<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The API only starts a session for requests it recognises as coming
        // from the SPA frontend (Sanctum's EnsureFrontendRequestsAreStateful
        // checks the Referer/Origin header against config('sanctum.stateful')).
        // Real browser requests always send this; simulate it here so
        // session-backed auth (register/login/logout) works under test.
        $this->withHeader('Referer', config('app.url'));
    }
}
