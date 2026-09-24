<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class SampleClearCommand extends Command
{
    protected $signature = 'sample:clear';

    protected $description = 'Remove synthetic incidents (is_synthetic = true) and their import batches; refuses in production';

    public function handle(AuditLogger $audit): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        if (! $this->confirm('Remove every synthetic incident from '.config('database.connections.mysql.database').'?')) {
            return self::FAILURE;
        }

        foreach (DB::table('import_batches')->whereNotNull('stored_path')->pluck('stored_path') as $path) {
            Storage::disk('local')->delete((string) $path);
        }

        $deleted = DB::transaction(function () use ($audit): int {
            DB::table('incidents')->where('is_synthetic', true)->update(['duplicate_of_id' => null]);
            $deleted = DB::table('incidents')->where('is_synthetic', true)->delete();
            DB::table('import_batches')->whereNotExists(fn ($q) => $q->from('incidents')->whereColumn('incidents.import_batch_id', 'import_batches.id'))->delete();
            $audit->record('sample.cleared', meta: ['incidents_removed' => $deleted]);

            return $deleted;
        });

        $this->info("Removed {$deleted} synthetic incidents.");

        return self::SUCCESS;
    }
}
