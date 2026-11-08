<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * ألف ياء has its own (in-memory, in tests) database: keep it migrated between tests
     * and roll it back after each one, just like the default connection.
     */
    protected $connectionsToTransact = [null, 'alefya'];

    protected function setUp(): void
    {
        parent::setUp();

        // Pages use @vite; tests shouldn't need a front-end build.
        $this->withoutVite();
    }
}
