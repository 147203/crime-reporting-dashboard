<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\ImportService;
use App\Services\SampleData\SampleDataGenerator;
use Illuminate\Console\Command;

final class SampleGenerateCommand extends Command
{
    protected $signature = 'sample:generate
        {--size=1000 : Number of valid base records (bad rows and duplicates are added on top)}
        {--seed= : Random seed (default SAMPLE_DATA_SEED)}
        {--end= : Last month of data, YYYY-MM (default: the last complete month)}
        {--dir= : Output directory (default storage/app/sample/<size>-<seed>-<end>)}
        {--load : Also load the file into the database through the import pipeline}';

    protected $description = 'Generate deterministic synthetic incidents with ground-truth files (FR-14)';

    public function handle(SampleDataGenerator $generator, ImportService $import): int
    {
        $size = (int) $this->option('size');
        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : (int) config('sample_data.seed');
        $end = is_string($this->option('end')) ? $this->option('end') : now()->subMonthNoOverflow()->format('Y-m');
        $directory = is_string($this->option('dir')) ? $this->option('dir') : storage_path("app/sample/{$size}-{$seed}-{$end}");

        $manifest = $generator->generate($size, $seed, $end, $directory);
        $this->info("Generated {$manifest['rows']} rows in {$directory}");
        $this->table(['valid', 'bad', 'duplicate', 'near_miss'], [array_values($manifest['counts'])]);

        if ($this->option('load')) {
            $started = microtime(true);
            $result = $import->importFile($directory.'/incidents.csv', null);
            $this->info(sprintf('Loaded %d accepted rows, rejected %d, flagged %d suspected duplicates in %.1f s.', $result['accepted'], $result['rejected'], $result['flagged'], microtime(true) - $started));
        }

        return self::SUCCESS;
    }
}
