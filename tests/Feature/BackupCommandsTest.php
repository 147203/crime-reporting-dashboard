<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TC-FT-59: backup and restore guards (NFR-06). The full backup, restore and checksum comparison is logged in TEST_LOG.
 */
final class BackupCommandsTest extends TestCase
{
    #[Test]
    public function backup_refuses_the_test_schema(): void
    {
        config(['database.connections.mysql.database' => 'crime_dashboard_test']);

        $this->assertSame(1, Artisan::call('db:backup'));
        $this->assertStringContainsString('not the test schema', Artisan::output());
    }

    #[Test]
    public function restore_refuses_outside_local_and_needs_a_valid_target_and_file(): void
    {
        $this->assertSame(1, Artisan::call('db:restore', ['file' => 'missing.sql.gz', '--target' => 'bad name;drop']));
        $this->assertStringContainsString('Give a target schema', Artisan::output());

        $this->assertSame(1, Artisan::call('db:restore', ['file' => 'missing.sql.gz', '--target' => 'crime_restore_e2e']));
        $this->assertStringContainsString('No such file', Artisan::output());

        $this->assertSame(1, Artisan::call('db:restore', ['file' => 'missing.sql.gz', '--target' => 'MySQL']));
        $this->assertStringContainsString('database server schema', Artisan::output());

        config(['database.connections.mysql.database' => 'crime_dashboard']);
        $this->assertSame(1, Artisan::call('db:restore', ['file' => 'missing.sql.gz', '--target' => 'crime_dashboard']));
        $this->assertStringContainsString('--replace-live', Artisan::output());

        $this->app['env'] = 'production';
        $this->assertSame(1, Artisan::call('db:restore', ['file' => 'missing.sql.gz', '--target' => 'crime_restore_e2e']));
        $this->assertStringContainsString('only in the local or testing environment', Artisan::output());
    }
}
