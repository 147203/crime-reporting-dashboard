<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Enums\DuplicateStatus;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\Incident;
use App\Models\User;
use App\Services\DuplicateDetector;
use App\Services\DuplicateReviewService;
use App\Services\Import\ImportService;
use App\Services\Import\ImportStateException;
use App\Services\PiiGuard;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for the Phase 4 independent review findings.
 */
final class ReviewRegressionPhase4Test extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'category,occurred_at,reported_at,sub_county,ward,station,location_description,description,status,closed_at';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-02 08:00:00');
        $this->seed(ReferenceDataSeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
    }

    private function upload(string $content): ImportBatch
    {
        return app(ImportService::class)->dryRun(UploadedFile::fake()->createWithContent('f.csv', $content), $this->admin);
    }

    /** TC-SE-17: a second commit of the same batch is refused; rows are inserted once. */
    #[Test]
    public function a_batch_cannot_be_committed_twice(): void
    {
        $batch = $this->upload(self::HEADER."\nTheft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,,reported,\n");
        $service = app(ImportService::class);
        $stale = ImportBatch::query()->findOrFail($batch->id);

        $service->commit($batch, $this->admin);

        try {
            $service->commit($stale, $this->admin);
            $this->fail('The second commit was allowed.');
        } catch (ImportStateException) {
            $this->assertSame(1, Incident::query()->count());
        }

        $this->actingAs($this->admin)->post(route('imports.commit', $batch))->assertRedirect(route('imports.show', $batch))->assertSessionHas('warning');
        $this->assertSame(1, Incident::query()->count());
    }

    /** TC-FT-45: day-first or other date formats are rejected as "format", never guessed. */
    #[Test]
    public function ambiguous_dates_are_rejected_as_format_errors(): void
    {
        $batch = $this->upload(self::HEADER."\n".implode("\n", [
            'Theft,01/08/2026 10:00,01/08/2026 11:00,Kibra,Makina,Kibra Sample Station,,,reported,',
            'Theft,2026-08-01T10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,,reported,',
            'Theft,yesterday,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,,reported,',
        ])."\n");

        $this->assertSame(0, $batch->rows_accepted);
        $this->assertSame(['format' => 4], $batch->error_report['by_rule'] ?? []);
    }

    /** TC-SE-18: a non-UTF-8 row is rejected and the PII guard still reads Windows-1252 text. */
    #[Test]
    public function non_utf8_text_is_rejected_and_cannot_hide_a_phone_number(): void
    {
        $batch = $this->upload(self::HEADER."\nTheft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,Caf\xe9,Call 0712345678,reported,\n");

        $this->assertSame(['encoding' => 1], $batch->error_report['by_rule'] ?? []);
        $this->assertSame(['phone number'], app(PiiGuard::class)->findings("Caf\xe9 call 0712345678"));
    }

    /** TC-FT-46: a UTF-8 byte-order mark before the header is accepted. */
    #[Test]
    public function a_byte_order_mark_is_accepted(): void
    {
        $batch = $this->upload("\u{FEFF}".self::HEADER."\nTheft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,,reported,\n");

        $this->assertSame(1, $batch->rows_accepted);
    }

    /** TC-FT-47: the downloaded error report has row, column, rule and meaning, and never the cell value. */
    #[Test]
    public function the_error_report_download_has_no_cell_values(): void
    {
        $batch = $this->upload(self::HEADER."\nTheft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,Call 0712345678,reported,\n");

        $csv = $this->actingAs($this->admin)->get(route('imports.errors', $batch))->streamedContent();

        $this->assertStringContainsString("row,column,rule,meaning\n1,description,V6,", $csv);
        $this->assertStringNotContainsString('0712', $csv);
    }

    /** TC-UT-15: V8 window edges, dismissed candidates remain comparable, confirmed ones do not. */
    #[Test]
    public function the_duplicate_window_edges_and_candidate_states_follow_the_rule(): void
    {
        $original = Incident::factory()->create(['location_description' => 'Near the market gate', 'occurred_at' => '2026-08-01 10:00', 'reported_at' => '2026-08-01 10:00']);
        $probe = fn (string $at): ?Incident => app(DuplicateDetector::class)->findMatch(Incident::factory()->make([
            'category_id' => $original->category_id, 'ward_id' => $original->ward_id, 'sub_county_id' => $original->sub_county_id,
            'location_description' => 'Near the market gate', 'occurred_at' => $at, 'reported_at' => $at,
        ]));

        $this->assertNotNull($probe('2026-08-01 12:00'));
        $this->assertNull($probe('2026-08-01 12:01'));
        $this->assertNotNull($probe('2026-08-01 08:00'));

        $original->duplicate_status = DuplicateStatus::Dismissed;
        $original->save();
        $this->assertNotNull($probe('2026-08-01 11:00'));

        $original->duplicate_status = DuplicateStatus::Confirmed;
        $original->save();
        $this->assertNull($probe('2026-08-01 11:00'));
    }

    /** TC-FT-48: confirming is refused when the earlier record is deleted or itself a confirmed duplicate (D-46). */
    #[Test]
    public function confirming_cannot_remove_an_incident_from_every_count(): void
    {
        $service = app(DuplicateReviewService::class);
        $original = Incident::factory()->create();
        $copy = Incident::factory()->create();
        $copy->duplicate_status = DuplicateStatus::Suspected;
        $copy->duplicate_of_id = $original->id;
        $copy->save();

        $original->delete();
        try {
            $service->resolve($copy, DuplicateStatus::Confirmed, $this->admin);
            $this->fail('Confirmed against a deleted record.');
        } catch (ValidationException) {
            $this->assertSame(DuplicateStatus::Suspected, $copy->refresh()->duplicate_status);
        }

        $service->resolve($copy, DuplicateStatus::Dismissed, $this->admin);
        $this->assertSame(DuplicateStatus::Dismissed, $copy->refresh()->duplicate_status);
    }

    /** TC-FT-49: editing a record never links it back to its own duplicate. */
    #[Test]
    public function an_edited_original_is_not_linked_to_its_own_duplicate(): void
    {
        $original = Incident::factory()->create(['location_description' => 'Near the market gate', 'occurred_at' => '2026-08-01 10:00', 'reported_at' => '2026-08-01 10:30']);
        $copy = Incident::factory()->create(['category_id' => $original->category_id, 'ward_id' => $original->ward_id, 'sub_county_id' => $original->sub_county_id, 'station_id' => $original->station_id, 'location_description' => 'Near the market gate', 'occurred_at' => '2026-08-01 10:30', 'reported_at' => '2026-08-01 11:00']);
        $copy->duplicate_status = DuplicateStatus::Suspected;
        $copy->duplicate_of_id = $original->id;
        $copy->save();

        $this->actingAs($this->admin)->put(route('incidents.update', $original), [
            'category_id' => $original->category_id, 'occurred_at' => '2026-08-01 10:05', 'reported_at' => '2026-08-01 10:30',
            'sub_county_id' => $original->sub_county_id, 'ward_id' => $original->ward_id, 'station_id' => $original->station_id,
            'location_description' => 'Near the market gate',
        ])->assertSessionHasNoErrors();

        $this->assertSame(DuplicateStatus::None, $original->refresh()->duplicate_status);
        $this->assertNull($original->duplicate_of_id);
    }

    /** TC-FT-53: abandoned dry runs are closed after a day and their files deleted. */
    #[Test]
    public function abandoned_dry_runs_are_pruned(): void
    {
        $batch = $this->upload(self::HEADER."\nTheft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,,reported,\n");
        $this->assertNotSame([], Storage::disk('local')->allFiles('imports'));

        $this->travel(25)->hours();
        $this->assertSame(1, app(ImportService::class)->pruneAbandoned());

        $this->assertSame(ImportBatch::STATUS_DISCARDED, $batch->refresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    /** TC-FT-54: sample:clear removes synthetic records, cleans batches and is audited. */
    #[Test]
    public function sample_clear_removes_synthetic_records_and_is_audited(): void
    {
        Incident::factory()->count(3)->create();

        $command = $this->artisan('sample:clear');
        if (! $command instanceof PendingCommand) {
            $this->fail('Console output mocking is off.');
        }
        $command->expectsConfirmation('Remove 3 synthetic incidents from '.config('database.connections.mysql.database').'?', 'yes')->assertExitCode(0);
        $command->run();

        $this->assertSame(0, Incident::withTrashed()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'sample.cleared')->count());
    }
}
