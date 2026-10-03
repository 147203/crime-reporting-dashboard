<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * NFR-06: restores a backup into a named schema. Local or testing environments only; the target is created if missing
 * and its existing tables are replaced, so the command asks first.
 */
final class DatabaseRestoreCommand extends Command
{
    protected $signature = 'db:restore {file : Path to a .sql.gz backup} {--target= : Schema to restore into} {--replace-live : Allow the target to be the application schema}';

    private const SYSTEM_SCHEMAS = ['mysql', 'sys', 'information_schema', 'performance_schema'];

    protected $description = 'Restore a backup into a named schema (local or testing only)';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $target = (string) $this->option('target');

        if (! app()->environment('local', 'testing')) {
            $this->error('Refusing: restores run only in the local or testing environment.');

            return self::FAILURE;
        }
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $target) !== 1) {
            $this->error('Give a target schema with --target (letters, digits and underscores).');

            return self::FAILURE;
        }
        if (in_array(strtolower($target), self::SYSTEM_SCHEMAS, true)) {
            $this->error("Refusing: {$target} is a database server schema.");

            return self::FAILURE;
        }
        if ($target === config('database.connections.mysql.database') && ! $this->option('replace-live')) {
            $this->error("Refusing: {$target} is the application schema. Restore into another schema, or add --replace-live to overwrite it on purpose.");

            return self::FAILURE;
        }
        if (! is_file($file)) {
            $this->error("No such file: {$file}");

            return self::FAILURE;
        }
        $gzip = gzopen($file, 'rb');
        if ($gzip === false) {
            $this->error("Cannot read {$file}.");

            return self::FAILURE;
        }
        if (! $this->confirm("Restore {$file} into schema {$target}? Existing tables in {$target} are replaced.")) {
            gzclose($gzip);

            return self::FAILURE;
        }

        DB::statement("CREATE DATABASE IF NOT EXISTS `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $connection = (array) config('database.connections.mysql');
        // Streamed in 1 MB pieces so a large dump is never held in memory whole.
        $chunks = (function () use ($gzip): \Generator {
            while (! gzeof($gzip)) {
                yield (string) gzread($gzip, 1 << 20);
            }
            gzclose($gzip);
        })();
        $process = new Process([
            (string) config('backup.client_binary'),
            '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'],
            '--default-character-set=utf8mb4', $target,
        ], null, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')], $chunks, 600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Restore failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info("Restored into {$target}.");

        return self::SUCCESS;
    }
}
