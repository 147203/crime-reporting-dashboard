<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\PreflightCommand;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PreflightCommandTest extends TestCase
{
    /**
     * Points public_path() at a scratch folder, with or without a built manifest, so the asset check does not depend
     * on whether npm run build has been run on this checkout.
     */
    private function usePublicFolder(bool $built): void
    {
        $folder = sys_get_temp_dir().'/preflight-public-'.bin2hex(random_bytes(4));
        mkdir($folder.'/build', 0700, true);
        if ($built) {
            file_put_contents($folder.'/build/manifest.json', '{}');
        }
        $this->app->usePublicPath($folder);
        $this->beforeApplicationDestroyed(function () use ($folder): void {
            @unlink($folder.'/build/manifest.json');
            @rmdir($folder.'/build');
            @rmdir($folder);
        });
    }

    private function useValidSecrets(): void
    {
        $this->usePublicFolder(built: true);
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'session.secure' => true,
            'session.driver' => 'database',
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'admin.initial_password' => 'admin-password-long-enough',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'crime_dashboard',
            'database.connections.mysql.username' => 'crime_app',
            'database.connections.mysql.password' => 'app-password-long-enough',
            'database.connections.mysql_testing.username' => 'crime_test',
            'database.connections.mysql_testing.password' => 'test-password-long-enough',
        ]);
    }

    /** TC-FT-01 */
    #[Test]
    public function it_exits_non_zero_and_names_the_variable_without_printing_its_value(): void
    {
        $this->useValidSecrets();
        config(['database.connections.mysql.password' => 'Xq9-too-short']);

        $exitCode = Artisan::call('app:preflight');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('DB_PASSWORD is shorter than 16 characters.', $output);
        $this->assertStringNotContainsString('Xq9-too-short', $output);
    }

    /** TC-FT-02 */
    #[Test]
    public function it_exits_zero_when_every_check_passes(): void
    {
        $this->useValidSecrets();
        // The secrets above are placeholders, so the server check is answered by a stand-in MariaDB connection.
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('scalar')->with('select version()')->andReturn('10.4.32-MariaDB');
        DB::shouldReceive('connection')->with('mysql')->andReturn($connection);

        $exitCode = Artisan::call('app:preflight');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Preflight passed.', $output);
    }

    /** TC-FT-61: a MySQL server answering instead of MariaDB fails with the fix. */
    #[Test]
    public function a_mysql_server_instead_of_mariadb_fails_with_the_fix(): void
    {
        $this->useValidSecrets();
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('scalar')->with('select version()')->andReturn('8.4.11');
        DB::shouldReceive('connection')->with('mysql')->andReturn($connection);

        $this->assertSame(1, Artisan::call('app:preflight'));
        $this->assertStringContainsString('not XAMPP\'s MariaDB', Artisan::output());
    }

    /** TC-FT-62: unbuilt front-end assets fail with the command that fixes them. */
    #[Test]
    public function unbuilt_assets_fail_with_the_fix(): void
    {
        $this->useValidSecrets();
        $this->usePublicFolder(built: false);
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('scalar')->with('select version()')->andReturn('10.4.32-MariaDB');
        DB::shouldReceive('connection')->with('mysql')->andReturn($connection);

        $this->assertSame(1, Artisan::call('app:preflight'));
        $this->assertStringContainsString('npm run build', Artisan::output());
    }

    /** TC-FT-63: on a new machine the missing application database is reported as created by migrate, not a failure. */
    #[Test]
    public function a_missing_application_database_does_not_fail_preflight(): void
    {
        $this->useValidSecrets();
        $connection = \Mockery::mock(Connection::class);
        $calls = 0;
        $connection->shouldReceive('scalar')->with('select version()')->twice()->andReturnUsing(function () use (&$calls): string {
            if ($calls++ === 0) {
                throw new \RuntimeException("SQLSTATE[HY000] [1049] Unknown database 'crime_dashboard'");
            }

            return '10.4.32-MariaDB';
        });
        DB::shouldReceive('connection')->with('mysql')->andReturn($connection);
        DB::shouldReceive('purge')->with('mysql')->twice();

        $exitCode = Artisan::call('app:preflight');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('php artisan migrate will create it', $output);
        $this->assertSame('crime_dashboard', config('database.connections.mysql.database'));
    }

    /** TC-FT-60: connection problems are explained with the fix, including the Ubuntu MySQL port clash. */
    #[Test]
    public function connection_errors_are_explained_with_a_fix(): void
    {
        $this->assertStringContainsString('sudo systemctl stop mysql', PreflightCommand::explainConnectionError("SQLSTATE[HY000] [1698] Access denied for user 'root'@'localhost'"));
        $this->assertStringContainsString('lampp startmysql', PreflightCommand::explainConnectionError('SQLSTATE[HY000] [2002] Connection refused'));
        $this->assertStringContainsString('does not exist', PreflightCommand::explainConnectionError("SQLSTATE[HY000] [1049] Unknown database 'x'"));
        $this->assertStringContainsString('XAMPP Control Panel', PreflightCommand::explainConnectionError('SQLSTATE[HY000] [2002] No connection could be made', 'Windows'));
        $this->assertStringContainsString('Stop-Service MySQL80', PreflightCommand::explainConnectionError("SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)", 'Windows'));
    }
}
