<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\DuplicateStatus;
use App\Enums\IncidentStatus;
use App\Models\ImportBatch;
use App\Models\Incident;
use App\Models\User;
use App\Rules\NoPersonalIdentifiers;
use App\Services\AuditLogger;
use App\Services\DuplicateDetector;
use App\Validation\IncidentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use SplFileObject;

/**
 * FR-08 bulk CSV import: strict header, dry run with per-row errors, then an accepted-only commit in one transaction.
 * Rows are validated with the same rules as the form (V1 to V7) and flagged for V8 duplicates at commit. Error reports
 * hold row number, column and rule only, never cell values (D-34); the uploaded file is deleted after commit or discard.
 */
final class ImportService
{
    public const HEADER = ['category', 'occurred_at', 'reported_at', 'sub_county', 'ward', 'station', 'location_description', 'description', 'status', 'closed_at'];

    // Measured: about 3 ms per row for check and insert together (TEST_LOG), so 10,000 rows stay well inside a web request.
    public const MAX_ROWS = 10000;

    public const MAX_KILOBYTES = 5120;

    private const DISK = 'local';

    // Enough for every problem in a 10,000-row upload and a 100,000-row console load, so evaluation can score all of them.
    private const MAX_REPORTED_ERRORS = 50000;

    private const DRY_RUN_TTL_HOURS = 24;

    public function __construct(private readonly DuplicateDetector $duplicates, private readonly AuditLogger $audit) {}

    public function dryRun(UploadedFile $file, User $user): ImportBatch
    {
        $this->pruneAbandoned();

        $path = Storage::disk(self::DISK)->putFileAs('imports', $file, Str::uuid()->toString().'.csv');
        if ($path === false) {
            throw new RuntimeException('The file could not be stored.');
        }

        try {
            $result = $this->evaluate($this->rows(Storage::disk(self::DISK)->path($path), self::MAX_ROWS));
        } catch (\Throwable $exception) {
            // The upload may hold rejected cells (possibly personal data); it never outlives a failed check.
            Storage::disk(self::DISK)->delete($path);
            throw $exception;
        }

        $batch = new ImportBatch([
            'user_id' => $user->id,
            'file_name' => Str::limit(basename($file->getClientOriginalName()), 140, ''),
            'stored_path' => $path,
            'status' => ImportBatch::STATUS_DRY_RUN,
            'rows_total' => $result['total'],
            'rows_accepted' => count($result['accepted']),
            'rows_rejected' => $result['rejected'],
            'error_report' => $this->report($result['errors']),
        ]);
        $batch->save();
        $this->audit->record('import.dry_run', $batch, ['rows_total' => $result['total'], 'rows_rejected' => $result['rejected']], $user);

        return $batch;
    }

    /**
     * The batch row is locked for the whole commit, so a double submit waits and then finds it already committed.
     */
    public function commit(ImportBatch $batch, User $user): ImportBatch
    {
        $committed = DB::transaction(function () use ($batch, $user): ImportBatch {
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== ImportBatch::STATUS_DRY_RUN || $locked->stored_path === null) {
                throw new ImportStateException('This import has already been committed or discarded.');
            }

            $result = $this->evaluate($this->rows(Storage::disk(self::DISK)->path($locked->stored_path), self::MAX_ROWS));
            if (count($result['accepted']) !== $locked->rows_accepted) {
                throw new ImportStateException('The reference data changed since the dry run, so the result would differ from what you reviewed. Discard and run the dry run again.');
            }

            $flagged = $this->insert($result['accepted'], $user, $locked);
            $locked->fill([
                'status' => ImportBatch::STATUS_COMMITTED,
                'rows_flagged_duplicate' => $flagged,
            ]);
            $locked->save();
            $this->audit->record('import.committed', $locked, ['rows_accepted' => count($result['accepted']), 'rows_rejected' => $result['rejected'], 'rows_flagged_duplicate' => $flagged], $user);

            return $locked;
        });

        $this->deleteFile($committed);
        $committed->save();

        return $committed;
    }

    /**
     * Dry runs that were never committed or discarded are closed after a day, and their files deleted.
     */
    public function pruneAbandoned(): int
    {
        $stale = ImportBatch::query()->where('status', ImportBatch::STATUS_DRY_RUN)->where('created_at', '<', now()->subHours(self::DRY_RUN_TTL_HOURS))->get();
        foreach ($stale as $batch) {
            $this->deleteFile($batch);
            $batch->status = ImportBatch::STATUS_DISCARDED;
            $batch->save();
        }

        return $stale->count();
    }

    public function discard(ImportBatch $batch, User $user): void
    {
        $discarded = DB::transaction(function () use ($batch, $user): ImportBatch {
            // Same lock as commit, so a commit and a discard sent together cannot both succeed.
            $locked = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== ImportBatch::STATUS_DRY_RUN) {
                throw new ImportStateException('Only a pending dry run can be discarded.');
            }
            $locked->status = ImportBatch::STATUS_DISCARDED;
            $locked->save();
            $this->audit->record('import.discarded', $locked, [], $user);

            return $locked;
        });

        $this->deleteFile($discarded);
        $discarded->save();
    }

    /**
     * Console path for loading generated sample data (no file-size or row limit; same validation and duplicate rules).
     *
     * @return array{total: int, accepted: int, rejected: int, flagged: int, errors: list<array{row: int, column: string, rule: string}>}
     */
    public function importFile(string $path, ?User $user): array
    {
        $result = $this->evaluate($this->rows($path, PHP_INT_MAX));

        $flagged = DB::transaction(function () use ($path, $user, $result): int {
            $batch = new ImportBatch([
                'user_id' => $user?->id,
                'file_name' => Str::limit(basename($path), 140, ''),
                'status' => ImportBatch::STATUS_COMMITTED,
                'rows_total' => $result['total'],
                'rows_accepted' => count($result['accepted']),
                'rows_rejected' => $result['rejected'],
                'error_report' => $this->report($result['errors']),
            ]);
            $batch->save();
            $flagged = $this->insert($result['accepted'], $user, $batch);
            $batch->rows_flagged_duplicate = $flagged;
            $batch->save();
            $this->audit->record('import.committed', $batch, ['rows_accepted' => count($result['accepted']), 'rows_rejected' => $result['rejected'], 'rows_flagged_duplicate' => $flagged, 'via' => 'console'], $user);

            return $flagged;
        });

        return ['total' => $result['total'], 'accepted' => count($result['accepted']), 'rejected' => $result['rejected'], 'flagged' => $flagged, 'errors' => $result['errors']];
    }

    /**
     * @return \Generator<int, array<string, string>>
     */
    private function rows(string $path, int $maxRows): \Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');

        $header = null;
        $rowNumber = 0;

        foreach ($file as $fields) {
            if (! is_array($fields) || $fields === [null]) {
                continue;
            }
            $fields = array_map(fn (mixed $value): string => is_string($value) ? $value : '', $fields);
            if ($header !== null && ! mb_check_encoding(implode('', $fields), 'UTF-8')) {
                $rowNumber++;
                yield $rowNumber => ['__malformed' => 'encoding'];

                continue;
            }

            if ($header === null) {
                $header = array_map(fn (string $h): string => strtolower(trim($h, " \t\u{FEFF}")), $fields);
                if ($header !== self::HEADER) {
                    throw new ImportFileException('The header must be exactly: '.implode(',', self::HEADER).'.');
                }

                continue;
            }

            $rowNumber++;
            if ($rowNumber > $maxRows) {
                throw new ImportFileException('The file has more than '.number_format($maxRows).' rows; split it into smaller files.');
            }
            if (count($fields) !== count(self::HEADER)) {
                yield $rowNumber => ['__malformed' => '1'];

                continue;
            }

            yield $rowNumber => array_combine(self::HEADER, $fields);
        }

        if ($header === null) {
            throw new ImportFileException('The file is empty.');
        }
    }

    /**
     * @param  iterable<int, array<string, string>>  $rows
     * @return array{total: int, rejected: int, accepted: list<array<string, mixed>>, errors: list<array{row: int, column: string, rule: string}>}
     */
    private function evaluate(iterable $rows): array
    {
        $lookup = new ReferenceLookup;
        $total = 0;
        $accepted = [];
        $errors = [];

        foreach ($rows as $rowNumber => $row) {
            $total++;
            [$rowErrors, $payload] = isset($row['__malformed']) ? [[['row' => $rowNumber, 'column' => '*', 'rule' => $row['__malformed'] === 'encoding' ? 'encoding' : 'format']], null] : $this->check($rowNumber, $row, $lookup);

            if ($payload !== null) {
                $accepted[] = $payload;
            }
            array_push($errors, ...$rowErrors);
        }

        $rejectedRows = count(array_unique(array_column($errors, 'row')));

        return ['total' => $total, 'rejected' => $rejectedRows, 'accepted' => $accepted, 'errors' => $errors];
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: list<array{row: int, column: string, rule: string}>, 1: array<string, mixed>|null} errors, and the payload when the row is valid
     */
    private function check(int $rowNumber, array $row, ReferenceLookup $lookup): array
    {
        $errors = [];
        $fail = function (string $column, string $rule) use (&$errors, $rowNumber): void {
            $errors[] = ['row' => $rowNumber, 'column' => $column, 'rule' => $rule];
        };

        $categoryId = $lookup->activeCategory($row['category']);
        $subCountyId = $lookup->subCounty($row['sub_county']);
        $wardId = $subCountyId !== null ? $lookup->ward($subCountyId, $row['ward']) : null;
        $stationId = $lookup->activeStation($row['station']);

        if ($categoryId === null) {
            $fail('category', 'V1');
        }
        if ($subCountyId === null) {
            $fail('sub_county', 'V4');
        } elseif ($wardId === null) {
            $fail('ward', 'V4');
        }
        if ($stationId === null) {
            $fail('station', 'station');
        }

        $data = [
            'category_id' => $categoryId,
            'occurred_at' => trim($row['occurred_at']),
            'reported_at' => trim($row['reported_at']),
            'sub_county_id' => $subCountyId,
            'ward_id' => $wardId,
            'station_id' => $stationId,
            'location_description' => IncidentRules::clean($row['location_description']),
            'description' => IncidentRules::clean($row['description']),
            'status' => trim($row['status']) === '' ? IncidentStatus::Reported->value : strtolower(trim($row['status'])),
            'closed_at' => trim($row['closed_at']) === '' ? null : trim($row['closed_at']),
        ];

        // Reference columns were resolved above; the remaining rules are the form's own (V2, V3, V5, V6) plus status.
        $rules = IncidentRules::rules($subCountyId);
        unset($rules['category_id'], $rules['sub_county_id'], $rules['ward_id'], $rules['station_id']);
        $rules['status'] = ['required', Rule::enum(IncidentStatus::class)];
        $rules['closed_at'] = ['nullable', 'required_if:status,closed', 'prohibited_unless:status,closed', 'date_format:Y-m-d H:i', 'after_or_equal:reported_at', 'before_or_equal:'.now()->toDateTimeString()];
        // Files use one unambiguous date format; "01/08/2026" could be 1 August or 8 January.
        array_unshift($rules['occurred_at'], 'date_format:Y-m-d H:i');
        array_unshift($rules['reported_at'], 'date_format:Y-m-d H:i');

        $validator = Validator::make($data, $rules);
        // failed() only reports; passes() runs the rules.
        $validator->passes();
        foreach ($validator->failed() as $field => $failedRules) {
            $fail(self::column((string) $field), self::ruleId((string) $field, array_map('strval', array_keys($failedRules))));
        }

        // A date that cannot be read makes the date-order checks meaningless; report only the format problem.
        if (in_array('format', array_column($errors, 'rule'), true)) {
            $errors = array_values(array_filter($errors, fn (array $e): bool => ! in_array($e['rule'], ['V2', 'V3'], true) || ! in_array($e['column'], ['occurred_at', 'reported_at'], true)));
        }

        return [$errors, $errors === [] ? $data : null];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(array $rows, ?User $user, ImportBatch $batch): int
    {
        $flagged = 0;

        DB::transaction(function () use ($rows, $user, $batch, &$flagged): void {
            foreach ($rows as $data) {
                $incident = new Incident($data);
                $incident->station_id = (int) $data['station_id'];
                $incident->status = IncidentStatus::from((string) $data['status']);
                $incident->closed_at = $data['closed_at'] !== null ? Carbon::parse((string) $data['closed_at']) : null;
                $incident->import_batch_id = $batch->id;
                $incident->created_by = $user?->id;
                $incident->updated_by = $user?->id;

                $match = $this->duplicates->findMatch($incident);
                if ($match !== null) {
                    $incident->duplicate_status = DuplicateStatus::Suspected;
                    $incident->duplicate_of_id = $match->id;
                    $flagged++;
                }

                $incident->save();
            }

            // Reference numbers follow the id, set in one statement for the whole batch.
            DB::table('incidents')->whereNull('reference_no')->where('import_batch_id', $batch->id)
                ->update(['reference_no' => DB::raw("CONCAT('NRB-', DATE_FORMAT(created_at, '%Y'), '-', LPAD(id, 6, '0'))")]);
        });

        return $flagged;
    }

    /**
     * @param  list<array{row: int, column: string, rule: string}>  $errors
     * @return array{total: int, truncated: bool, by_rule: array<string, int>, errors: list<array{row: int, column: string, rule: string}>}
     */
    private function report(array $errors): array
    {
        $byRule = array_count_values(array_column($errors, 'rule'));
        ksort($byRule);

        return ['total' => count($errors), 'truncated' => count($errors) > self::MAX_REPORTED_ERRORS, 'by_rule' => $byRule, 'errors' => array_slice($errors, 0, self::MAX_REPORTED_ERRORS)];
    }

    private function deleteFile(ImportBatch $batch): void
    {
        if ($batch->stored_path !== null) {
            Storage::disk(self::DISK)->delete($batch->stored_path);
            $batch->stored_path = null;
        }
    }

    private static function column(string $field): string
    {
        return match ($field) {
            'category_id' => 'category',
            'sub_county_id' => 'sub_county',
            'ward_id' => 'ward',
            'station_id' => 'station',
            default => $field,
        };
    }

    /**
     * @param  list<string>  $failedRules
     */
    private static function ruleId(string $field, array $failedRules): string
    {
        return match (true) {
            in_array('DateFormat', $failedRules, true), in_array('Date', $failedRules, true) => 'format',
            in_array(NoPersonalIdentifiers::class, $failedRules, true) => 'V6',
            in_array($field, ['location_description', 'description'], true) => 'V5',
            $field === 'occurred_at' => 'V2',
            $field === 'reported_at' => 'V3',
            in_array($field, ['status', 'closed_at'], true) => 'status',
            default => 'format',
        };
    }
}
