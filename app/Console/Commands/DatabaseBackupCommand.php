<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * NFR-06: a compressed SQL dump of the application schema, including the audit-log triggers. The password is passed
 * through the environment, never on the command line, so it does not appear in the process list.
 */
final class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Write a compressed backup of the application database to storage/app/private/backups';

    public function handle(AuditLogger $audit): int
    {
        $connection = (array) config('database.connections.mysql');
        $database = (string) ($connection['database'] ?? '');

        if ($database === '' || str_ends_with($database, '_test')) {
            $this->error('Refusing: back up the application schema, not the test schema.');

            return self::FAILURE;
        }

        $directory = (string) config('backup.directory');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error("Cannot create {$directory}.");

            return self::FAILURE;
        }

        $file = sprintf('%s/%s-%s.sql.gz', $directory, $database, now()->format('Ymd-His'));
        // Owner-only from the first byte, not only once the dump is complete.
        $previousUmask = umask(0077);
        $gzip = gzopen($file, 'wb9');
        umask($previousUmask);
        if ($gzip === false) {
            $this->error("Cannot write {$file}.");

            return self::FAILURE;
        }
        chmod($file, 0600);

        $process = new Process([
            (string) config('backup.dump_binary'),
            '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username'],
            '--single-transaction', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4',
            $database,
        ], null, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')], null, 600);

        $written = true;
        $process->run(function (string $type, string $output) use ($gzip, &$written): void {
            if ($type === Process::OUT && $written) {
                $written = gzwrite($gzip, $output) === strlen($output);
            }
        });
        $written = gzclose($gzip) && $written;

        if (! $process->isSuccessful() || ! $written) {
            @unlink($file);
            $this->error('Backup failed: '.($written ? trim($process->getErrorOutput()) : 'the file could not be written completely (is the disk full?).'));

            return self::FAILURE;
        }

        $audit->record('backup.created', meta: ['file' => basename($file), 'bytes' => (int) filesize($file)]);
        $this->info('Backup written to '.$file.' ('.number_format((int) filesize($file)).' bytes).');

        return self::SUCCESS;
    }
}
