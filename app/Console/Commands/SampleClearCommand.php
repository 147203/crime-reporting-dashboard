<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SampleClearCommand extends Command
{
    protected $signature = 'sample:clear';

    protected $description = 'Remove synthetic incidents (is_synthetic = true) and their import batches; refuses in production';

    public function handle(AuditLogger $audit): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Refusing: sample:clear runs only in the local or testing environment.');

            return self::FAILURE;
        }

        $count = DB::table('incidents')->where('is_synthetic', true)->count();
        if (! $this->confirm("Remove {$count} synthetic incidents from ".config('database.connections.mysql.database').'?')) {
            return self::FAILURE;
        }

        $deleted = DB::transaction(function () use ($audit): int {
            DB::table('incidents')->where('is_synthetic', true)->update(['duplicate_of_id' => null]);
            $deleted = DB::table('incidents')->where('is_synthetic', true)->delete();
            // Pending dry runs are left alone (their files are handled by imports:prune).
            DB::table('import_batches')->where('status', '!=', 'dry_run')->whereNotExists(fn ($q) => $q->from('incidents')->whereColumn('incidents.import_batch_id', 'import_batches.id'))->delete();
            $audit->record('sample.cleared', meta: ['incidents_removed' => $deleted]);

            return $deleted;
        });

        $this->info("Removed {$deleted} synthetic incidents.");

        return self::SUCCESS;
    }
}
