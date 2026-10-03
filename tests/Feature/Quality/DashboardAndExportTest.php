<?php

declare(strict_types=1);

namespace Tests\Feature\Quality;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\ReportExport;
use App\Models\Station;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\ReportService;
use App\Support\Filters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

final class DashboardAndExportTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private Station $otherStation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-02 08:00:00');
        $this->station = Station::factory()->create();
        $this->otherStation = Station::factory()->create();
        Incident::factory()->atStation($this->station)->count(4)->create(['occurred_at' => '2026-08-10 10:00', 'reported_at' => '2026-08-10 12:00']);
        Incident::factory()->atStation($this->otherStation)->count(3)->create(['occurred_at' => '2026-08-11 22:00', 'reported_at' => '2026-08-12 01:00']);
        Incident::factory()->atStation($this->station)->create(['occurred_at' => '2025-01-01 10:00', 'reported_at' => '2025-01-01 11:00']);
    }

    /** TC-FT-50: FR-10 the dashboard renders every metric with its caption and table alternative. */
    #[Test]
    public function the_dashboard_shows_all_metrics_with_period_filters_and_n(): void
    {
        $this->actingAs(User::factory()->analyst()->create())
            ->get(route('home', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Monthly trend (A3)')->assertSee('Categories (A2)')->assertSee('Day of week (A5)')->assertSee('Time of day (A6)')
            ->assertSee('Top 10 wards (A7)')->assertSee('Hotspot wards (A8)')->assertSee('Sub-county by month (A9)')->assertSee('Status (A10)')
            ->assertSee('1 Aug 2026 to 31 Aug 2026')->assertSee('n = 7')
            ->assertSee('data-view-table', false)
            ->assertSee('type="application/json"', false);
    }

    /** TC-SE-15: an officer's analytics are limited to their station, whatever filter they send. */
    #[Test]
    public function an_officers_analytics_equal_their_station_only(): void
    {
        $officer = User::factory()->officer($this->station)->create();
        $filters = Filters::fromValidated(['from' => '2026-08-01', 'to' => '2026-08-31']);

        $this->assertSame(4, (new Analytics($officer, $filters))->a1()['n']);
        $this->assertSame(7, (new Analytics(User::factory()->admin()->create(), $filters))->a1()['n']);

        $this->actingAs($officer)->get(route('home', ['from' => '2026-08-01', 'to' => '2026-08-31', 'station_id' => $this->otherStation->id]))
            ->assertOk()->assertSee('n = 4');
    }

    /** TC-AC-05: FR-11 export fidelity: the CSV has exactly A1 rows for the same filters and scope, and is audited. */
    #[Test]
    public function the_csv_export_matches_the_screen_and_is_recorded(): void
    {
        $officer = User::factory()->officer($this->station)->create();

        $response = $this->actingAs($officer)->get(route('reports.csv', ['from' => '2026-08-01', 'to' => '2026-08-31']));
        $response->assertOk();
        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));

        $this->assertSame(1 + 4, count($lines));
        $this->assertStringContainsString('reference_no,category,occurred_at', $lines[0]);
        $this->assertSame(4, ReportExport::query()->where('type', 'csv')->value('row_count'));
        $this->assertSame(1, AuditLog::query()->where('action', 'report.exported')->count());
    }

    /** TC-AC-06: a large export has every record exactly once, in date order (paging must not skip or repeat rows). */
    #[Test]
    public function a_large_export_contains_each_record_exactly_once(): void
    {
        $template = Incident::query()->firstOrFail();
        $rows = [];
        for ($i = 0; $i < 2500; $i++) {
            $rows[] = [
                'reference_no' => sprintf('NRB-BULK-%06d', $i), 'category_id' => $template->category_id,
                'occurred_at' => sprintf('2026-08-%02d %02d:%02d:00', 1 + ($i * 7) % 28, ($i * 5) % 24, $i % 60), 'reported_at' => '2026-08-30 23:00:00',
                'sub_county_id' => $template->sub_county_id, 'ward_id' => $template->ward_id, 'station_id' => $template->station_id,
                'status' => 'reported', 'duplicate_status' => 'none', 'is_synthetic' => true,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('incidents')->insert($chunk);
        }
        $admin = User::factory()->admin()->create();
        $expected = (new Analytics($admin, Filters::fromValidated(['from' => '2026-08-01', 'to' => '2026-08-31'])))->a1()['n'];

        $lines = array_slice(array_values(array_filter(explode("\n", $this->actingAs($admin)->get(route('reports.csv', ['from' => '2026-08-01', 'to' => '2026-08-31']))->streamedContent()))), 1);
        $references = array_map(fn (string $line): string => str_getcsv($line, escape: '')[0] ?? '', $lines);
        $dates = array_map(fn (string $line): string => str_getcsv($line, escape: '')[2] ?? '', $lines);

        $this->assertSame($expected, count($references));
        $this->assertSame($expected, count(array_unique($references)));
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates);
    }

    /** TC-AC-07: the Excel workbook has the same records as the CSV, with formulas kept as text. */
    #[Test]
    public function the_excel_export_matches_the_csv_and_keeps_formulas_as_text(): void
    {
        Incident::query()->firstOrFail()->forceFill(['description' => '=HYPERLINK("http://x","click")'])->save();
        $admin = User::factory()->admin()->create();
        $filters = ['from' => '2026-08-01', 'to' => '2026-08-31'];

        $csvLines = array_values(array_filter(explode("\n", $this->actingAs($admin)->get(route('reports.csv', $filters))->streamedContent())));
        $response = $this->get(route('reports.xlsx', $filters));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $download = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $download);
        $file = $download->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($file);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row;
            }
        }
        $reader->close();

        $this->assertCount(count($csvLines), $rows);
        $this->assertSame('reference_no', $rows[0]->cells[0]->getValue());
        $cells = array_merge(...array_map(fn ($r) => $r->cells, $rows));
        $this->assertSame([], array_filter($cells, fn ($cell) => $cell instanceof FormulaCell));
        $this->assertSame(1, ReportExport::query()->where('type', 'xlsx')->count());
    }

    /** TC-AC-07: an officer's workbook holds only their station's records. */
    #[Test]
    public function an_officers_excel_export_holds_only_their_station(): void
    {
        $officer = User::factory()->officer($this->station)->create();

        $response = $this->actingAs($officer)->get(route('reports.xlsx', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();
        $download = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $download);
        $reader = new Reader;
        $reader->open($download->getFile()->getPathname());
        $rows = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows++;
            }
        }
        $reader->close();

        $this->assertSame(1 + 4, $rows);
    }

    /** TC-SE-16: cells a spreadsheet would run as formulas are neutralised. */
    #[Test]
    public function exported_cells_cannot_start_a_formula(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", ReportService::cell('=HYPERLINK("x")'));
        $this->assertSame("'+1", ReportService::cell('+1'));
        $this->assertSame("'-2", ReportService::cell('-2'));
        $this->assertSame("'@SUM(A1)", ReportService::cell('@SUM(A1)'));
        $this->assertSame('Near the market', ReportService::cell('Near the market'));
        $this->assertSame('', ReportService::cell(null));
    }

    /** TC-FT-51: the PDF summary is produced for the same filters. */
    #[Test]
    public function the_pdf_summary_downloads(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('reports.pdf', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertSame(7, ReportExport::query()->where('type', 'pdf')->value('row_count'));
    }

    /** TC-FT-52: an impossible period is rejected with a message, not an error page. */
    #[Test]
    public function an_invalid_period_is_rejected(): void
    {
        $this->actingAs(User::factory()->analyst()->create())
            ->get(route('home', ['from' => '2026-08-31', 'to' => '2026-08-01']))
            ->assertSessionHasErrors('to');
    }
}
