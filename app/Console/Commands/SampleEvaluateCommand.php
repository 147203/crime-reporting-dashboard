<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\Incident;
use Illuminate\Console\Command;

/**
 * Data-quality evaluation against the generator's ground truth (docs/04 section 4) for any loaded batch, so rejection
 * recall and duplicate precision and recall can be reported at every data volume, not only in the 1,000-record test.
 */
final class SampleEvaluateCommand extends Command
{
    protected $signature = 'sample:evaluate {dir : Directory written by sample:generate} {batch : Import batch id created by --load}';

    protected $description = 'Score a loaded sample batch against its ground-truth files (rejections and duplicates)';

    public function handle(): int
    {
        $dir = (string) $this->argument('dir');
        $batch = ImportBatch::query()->findOrFail((int) $this->argument('batch'));
        $errors = $batch->error_report['errors'] ?? [];

        if ($batch->error_report['truncated'] ?? false) {
            $this->warn('The stored error list is truncated; rejection recall below uses only the stored errors.');
        }

        $rulesByRow = [];
        foreach ($errors as $error) {
            $rulesByRow[$error['row']][] = $error['rule'];
        }

        $bad = $this->csv("{$dir}/truth/bad_records.csv");
        $recall = [];
        foreach ($bad as $row) {
            $recall[$row['rule']][] = in_array($row['rule'], $rulesByRow[(int) $row['row']] ?? [], true);
        }

        $manifest = json_decode((string) file_get_contents("{$dir}/manifest.json"), true, flags: JSON_THROW_ON_ERROR);
        $rejected = array_keys($rulesByRow);
        $accepted = array_values(array_diff(range(1, (int) $manifest['rows']), $rejected));
        $ids = Incident::withTrashed()->where('import_batch_id', $batch->id)->orderBy('id')->pluck('id')->all();
        if (count($ids) !== count($accepted)) {
            $this->error('The batch row count does not match the dataset; was it loaded from this directory?');

            return self::FAILURE;
        }
        $rowOfId = array_combine($ids, $accepted);

        $flagged = [];
        foreach (Incident::withTrashed()->where('import_batch_id', $batch->id)->whereNotNull('duplicate_of_id')->get(['id', 'duplicate_of_id']) as $incident) {
            $flagged[] = $rowOfId[$incident->id].'>'.($rowOfId[$incident->duplicate_of_id] ?? 'outside');
        }
        $true = [];
        foreach ($this->csv("{$dir}/truth/duplicates.csv") as $pair) {
            if ($pair['kind'] === 'true') {
                $true[] = $pair['row'].'>'.$pair['original_row'];
                $true[] = $pair['original_row'].'>'.$pair['row'];
            }
        }
        $hits = count(array_intersect($flagged, $true));
        $pairs = count($true) / 2;

        $this->table(['Measure', 'Result'], [
            ['Rows in file', number_format((int) $manifest['rows'])],
            ...array_map(fn (string $rule, array $caught): array => ["Rejection recall {$rule}", sprintf('%.3f (%d of %d)', array_sum($caught) / count($caught), array_sum($caught), count($caught))], array_keys($recall), $recall),
            ['False rejections (valid rows rejected)', (string) count(array_diff($rejected, array_map(fn (array $r): int => (int) $r['row'], $bad)))],
            ['Duplicates flagged', number_format(count($flagged))],
            ['Planted true pairs', number_format($pairs)],
            ['Duplicate precision', $flagged === [] ? 'n/a' : sprintf('%.3f', $hits / count($flagged))],
            ['Duplicate recall', sprintf('%.3f', $hits / max(1, $pairs))],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, string>>
     */
    private function csv(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $header = array_map('strval', str_getcsv((string) array_shift($lines), escape: ''));

        return array_map(fn (string $line): array => array_combine($header, array_map('strval', str_getcsv($line, escape: ''))), $lines);
    }
}
