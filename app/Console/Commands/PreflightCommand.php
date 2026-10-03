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
            return [self::explainConnectionError($exception->getMessage())];
        }

        if (! str_contains(strtolower($version), 'mariadb')) {
            return ["The database server on port 3306 is {$version}, not XAMPP's MariaDB. Another MySQL server is running; stop it with: sudo systemctl stop mysql && sudo systemctl disable mysql, then start MariaDB with: sudo /opt/lampp/lampp startmysql"];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function assetFailures(): array
    {
        return is_file(public_path('build/manifest.json')) ? [] : ['Front-end assets are not built. Run: npm install && npm run build'];
    }

    public static function explainConnectionError(string $message): string
    {
        return match (true) {
            str_contains($message, '[1698]') => "The database refused root (error 1698): Ubuntu's own MySQL server is answering instead of XAMPP's MariaDB. Run: sudo systemctl stop mysql && sudo systemctl disable mysql && sudo /opt/lampp/lampp startmysql",
            str_contains($message, '[2002]') => 'The database is not running (connection refused). Run: sudo /opt/lampp/lampp startmysql, wait a few seconds, then try again.',
            str_contains($message, '[1049]') => 'The database named in DB_DATABASE does not exist. Create it (see the README, section 4.1).',
            default => 'Cannot connect to the database: '.$message,
        };
    }
}
