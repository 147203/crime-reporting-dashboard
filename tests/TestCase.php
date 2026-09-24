<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Runs before RefreshDatabase migrates, so a misconfigured environment is stopped before any table is dropped.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $this->assertSafeTestDatabase();

        return parent::setUpTraits();
    }

    private function assertSafeTestDatabase(): void
    {
        $testDatabase = config('database.connections.mysql_testing.database');

        if (config('database.default') !== 'mysql_testing'
            || ! is_string($testDatabase)
            || ! str_ends_with($testDatabase, '_test')
            || $testDatabase === config('database.connections.mysql.database')) {
            throw new RuntimeException('Refusing to run tests: they must use the mysql_testing connection and a *_test database separate from the application database (DB_TEST_DATABASE).');
        }
    }
}
