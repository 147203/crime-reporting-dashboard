<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Enums\DuplicateStatus;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\Incident;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-02 08:00:00');
        $this->seed(ReferenceDataSeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  list<string>  $rows
     */
    private function csv(array $rows, ?string $header = null): UploadedFile
    {
        $header ??= 'category,occurred_at,reported_at,sub_county,ward,station,location_description,description,status,closed_at';

        return UploadedFile::fake()->createWithContent('incidents.csv', $header."\n".implode("\n", $rows)."\n");
    }

    /** TC-FT-40: FR-08 dry run reports per-row errors, stores nothing, keeps no cell values; commit inserts accepted rows only. */
    #[Test]
    public function a_dry_run_then_commit_imports_only_valid_rows(): void
    {
        $file = $this->csv([
            'Theft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,Near the market 5,Phone taken.,reported,',
            'Robbery,2026-08-02 21:00,2026-08-02 22:00,Kibra,Lindi,Kibra Sample Station,Opposite the stage 9,Cash taken.,closed,2026-08-10 09:00',
            'Unknown,2026-08-03 10:00,2026-08-03 11:00,Kibra,Makina,Kibra Sample Station,x,y,reported,',
            'Theft,2026-08-04 10:00,2026-08-04 09:00,Kibra,Makina,Kibra Sample Station,x,y,reported,',
            'Theft,2026-08-05 10:00,2026-08-05 11:00,Kibra,Kangemi,Kibra Sample Station,x,y,reported,',
            'Theft,2026-08-06 10:00,2026-08-06 11:00,Kibra,Makina,Kibra Sample Station,x,Call 0712 345 678,reported,',
        ]);

        $this->actingAs($this->admin)->post(route('imports.store'), ['file' => $file])->assertRedirect();
        $batch = ImportBatch::query()->sole();

        $this->assertSame([6, 2, 4], [$batch->rows_total, $batch->rows_accepted, $batch->rows_rejected]);
        $this->assertSame(0, Incident::query()->count());
        $this->assertSame(['V1', 'V3', 'V4', 'V6'], collect($batch->error_report['errors'] ?? [])->pluck('rule')->sort()->values()->all());
        $this->assertStringNotContainsString('0712', (string) json_encode($batch->error_report));
        $this->get(route('imports.show', $batch))->assertOk()->assertSee('Commit 2 rows');
        $this->get(route('imports.errors', $batch))->assertOk()->assertDownload('import-'.$batch->id.'-errors.csv');

        $this->post(route('imports.commit', $batch))->assertRedirect(route('imports.show', $batch));

        $batch->refresh();
        $this->assertSame(ImportBatch::STATUS_COMMITTED, $batch->status);
        $this->assertNull($batch->stored_path);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
        $this->assertSame(2, Incident::query()->where('import_batch_id', $batch->id)->count());
        $this->assertNotNull(Incident::query()->where('status', 'closed')->value('closed_at'));
        $this->assertSame(0, Incident::query()->whereNull('reference_no')->count());
        $this->assertSame(['import.dry_run', 'import.committed'], AuditLog::query()->where('action', 'like', 'import.%')->orderBy('id')->pluck('action')->all());

        $this->post(route('imports.commit', $batch))->assertRedirect(route('imports.show', $batch))->assertSessionHas('warning');
    }

    /** TC-FT-41: discard deletes the file and saves nothing. */
    #[Test]
    public function a_discarded_dry_run_saves_nothing(): void
    {
        $this->actingAs($this->admin)->post(route('imports.store'), ['file' => $this->csv(['Theft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,,,reported,'])]);
        $batch = ImportBatch::query()->sole();

        $this->post(route('imports.discard', $batch))->assertRedirect(route('imports.create'));

        $this->assertSame(ImportBatch::STATUS_DISCARDED, $batch->refresh()->status);
        $this->assertSame(0, Incident::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    /** TC-FT-42: a wrong header or an oversized or non-CSV file is refused before any row is read. */
    #[Test]
    public function bad_files_are_refused(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('imports.store'), ['file' => $this->csv(['a,b'], 'category,when')])->assertSessionHasErrors('file');
        $this->post(route('imports.store'), ['file' => UploadedFile::fake()->create('big.csv', 6000, 'text/csv')])->assertSessionHasErrors('file');
        $this->post(route('imports.store'), ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])->assertSessionHasErrors('file');

        $this->assertSame(0, ImportBatch::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    /** TC-FT-43: V8 on the form path, then confirm removes the record from countable totals and dismiss keeps it. */
    #[Test]
    public function a_reported_near_duplicate_is_flagged_and_reviewed(): void
    {
        $original = Incident::factory()->create(['location_description' => 'Near the market gate', 'occurred_at' => '2026-08-01 10:00', 'reported_at' => '2026-08-01 10:30']);
        $payload = [
            'category_id' => $original->category_id, 'occurred_at' => '2026-08-01 11:00', 'reported_at' => '2026-08-01 11:30',
            'sub_county_id' => $original->sub_county_id, 'ward_id' => $original->ward_id, 'station_id' => $original->station_id,
            'location_description' => 'Near the market gate.',
        ];

        $this->actingAs($this->admin)->post(route('incidents.store'), $payload)->assertRedirect();
        $flagged = Incident::query()->whereKeyNot($original->id)->sole();
        $this->assertSame(DuplicateStatus::Suspected, $flagged->duplicate_status);
        $this->assertSame($original->id, $flagged->duplicate_of_id);

        $analyst = User::factory()->analyst()->create();
        $this->actingAs($analyst)->get(route('duplicates.index'))->assertOk()->assertSee((string) $flagged->reference_no);
        $this->put(route('duplicates.resolve', $flagged), ['decision' => 'confirmed'])->assertRedirect(route('duplicates.index'));

        $this->assertSame(DuplicateStatus::Confirmed, $flagged->refresh()->duplicate_status);
        $this->assertSame(1, Incident::query()->countable()->count());
        $this->put(route('duplicates.resolve', $flagged), ['decision' => 'dismissed'])->assertSessionHasErrors('decision');
        $this->assertSame(1, AuditLog::query()->where('action', 'duplicate.confirmed')->count());

        // The surviving original is the counted record, so it cannot be deleted while a confirmed copy points to it.
        $this->actingAs($this->admin)->delete(route('incidents.destroy', $original))->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted($original);
    }

    /** TC-FT-44: FR-13 summary shows rejections by rule, duplicate states and completeness. */
    #[Test]
    public function the_quality_summary_reports_rules_duplicates_and_completeness(): void
    {
        $this->actingAs($this->admin)->post(route('imports.store'), ['file' => $this->csv([
            'Theft,2026-08-01 10:00,2026-08-01 11:00,Kibra,Makina,Kibra Sample Station,Near the market 5,,reported,',
            'Unknown,2026-08-03 10:00,2026-08-03 11:00,Kibra,Makina,Kibra Sample Station,x,y,reported,',
        ])]);
        $this->post(route('imports.commit', ImportBatch::query()->sole()));

        $this->get(route('quality.index'))->assertOk()
            ->assertSee('Category missing, unknown or inactive')
            ->assertSee('Suspected')
            ->assertSee('100 percent');
    }
}
