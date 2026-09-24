<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\ImportService;
use Illuminate\Console\Command;

final class ImportsPruneCommand extends Command
{
    protected $signature = 'imports:prune';

    protected $description = 'Close dry-run imports older than 24 hours and delete their uploaded files (D-34, D-45)';

    public function handle(ImportService $imports): int
    {
        $this->info('Closed '.$imports->pruneAbandoned().' abandoned dry runs.');

        return self::SUCCESS;
    }
}
