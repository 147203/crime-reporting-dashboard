<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Preflight;
use Illuminate\Console\Command;

final class PreflightCommand extends Command
{
    protected $signature = 'app:preflight';

    protected $description = 'Check required secrets and credentials; exits non-zero if any is missing, a placeholder, or too short';

    public function handle(Preflight $preflight): int
    {
        $failures = $preflight->allFailures();

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures !== []) {
            $this->line(count($failures).' check(s) failed. Set the variables named above in .env.');

            return self::FAILURE;
        }

        if ($preflight->localDatabaseShortcut()) {
            $this->warn('Local database shortcut is active (D-25): root with a blank password is accepted on this machine only.');
        }

        $this->info('Preflight passed.');

        return self::SUCCESS;
    }
}
