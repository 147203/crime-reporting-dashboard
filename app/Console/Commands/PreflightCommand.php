<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Preflight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PreflightCommand extends Command
{
    protected $signature = 'app:preflight';

    protected $description = 'Check secrets, the database server and built assets; exits non-zero with a plain fix when something is wrong';

    public function handle(Preflight $preflight): int
    {
        $failures = $preflight->allFailures();

        // Only meaningful once the settings themselves are valid.
        if ($failures === []) {
            $failures = [...$this->databaseFailures(), ...$this->assetFailures()];
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures !== []) {
            $this->line(count($failures).' check(s) failed. Fix the items named above and run php artisan app:preflight again.');

            return self::FAILURE;
        }

        if ($preflight->localDatabaseShortcut()) {
            $this->warn('Local database shortcut is active (D-25): root with a blank password is accepted on this machine only.');
        }

        $this->info('Preflight passed.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function databaseFailures(): array
    {
        try {
            $version = (string) DB::connection('mysql')->scalar('select version()');
        } catch (Throwable $exception) {
            if (! str_contains($exception->getMessage(), '[1049]')) {
                return [self::explainConnectionError($exception->getMessage())];
            }

            // A new machine has no application database yet; php artisan migrate creates it. The server is still
            // checked, connected without a database.
            $this->warn('The database '.config('database.connections.mysql.database').' does not exist yet. php artisan migrate will create it.');
            $version = $this->serverVersionWithoutDatabase();
            if ($version === null) {
                return [self::explainConnectionError($exception->getMessage())];
            }
        }

        if (! str_contains(strtolower($version), 'mariadb')) {
            return ["The database server on port 3306 is {$version}, not XAMPP's MariaDB. ".self::fix('other-server')];
        }

        return [];
    }

    private function serverVersionWithoutDatabase(): ?string
    {
        $database = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => null]);
        DB::purge('mysql');

        try {
            return (string) DB::connection('mysql')->scalar('select version()');
        } catch (Throwable) {
            return null;
        } finally {
            config(['database.connections.mysql.database' => $database]);
            DB::purge('mysql');
        }
    }

    /**
     * @return list<string>
     */
    private function assetFailures(): array
    {
        return is_file(public_path('build/manifest.json')) ? [] : ['Front-end assets are not built. Run: npm install && npm run build'];
    }

    public static function explainConnectionError(string $message, string $os = PHP_OS_FAMILY): string
    {
        return match (true) {
            str_contains($message, '[1698]'), str_contains($message, '[1045]') => 'The database refused the account in DB_USERNAME (error '.(str_contains($message, '[1698]') ? '1698' : '1045').'). Usually another MySQL server is answering on port 3306 instead of XAMPP\'s MariaDB. '.self::fix('other-server', $os),
            str_contains($message, '[2002]') => 'The database is not running (connection refused). '.self::fix('start', $os),
            str_contains($message, '[1049]') => 'The database named in DB_DATABASE does not exist. Run php artisan migrate, which creates it.',
            default => 'Cannot connect to the database: '.$message,
        };
    }

    private static function fix(string $problem, string $os = PHP_OS_FAMILY): string
    {
        $start = match ($os) {
            'Windows' => 'Start MySQL in the XAMPP Control Panel (C:\\xampp\\xampp-control.exe) and wait until it turns green',
            'Darwin' => 'Run: sudo /Applications/XAMPP/xamppfiles/xampp startmysql',
            default => 'Run: sudo /opt/lampp/lampp startmysql',
        };

        if ($problem === 'start') {
            return $start.', then try again.';
        }

        return match ($os) {
            'Windows' => 'Stop the other server (often the MySQL80 Windows service): in an administrator PowerShell run Stop-Service MySQL80; Set-Service MySQL80 -StartupType Manual. Then: '.$start.'.',
            'Darwin' => 'Stop the other server (for example: brew services stop mysql). Then: '.$start.'.',
            default => 'Stop it and keep it off: sudo systemctl stop mysql && sudo systemctl disable mysql. Then: '.$start.'.',
        };
    }
}
