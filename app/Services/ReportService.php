<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Incident;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\Analytics\DashboardViewModel;
use App\Support\Filters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * FR-11 exports. Both honour the viewer's role scope and the same filters as the dashboard, so the CSV row count equals
 * A1 on screen. Each export is recorded (report_exports) and audited.
 */
final class ReportService
{
    public const CSV_COLUMNS = ['reference_no', 'category', 'occurred_at', 'reported_at', 'sub_county', 'ward', 'station', 'status', 'closed_at', 'duplicate_status', 'location_description', 'description', 'is_synthetic'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function csv(User $user, Filters $filters): StreamedResponse
    {
        $analytics = new Analytics($user, $filters);
        $count = $analytics->a1()['n'];
        $this->record($user, 'csv', $filters, $count);

        $query = $analytics->base()
            ->with(['category:id,name', 'subCounty:id,name', 'ward:id,name', 'station:id,name'])
            ->orderBy('incidents.occurred_at')->orderBy('incidents.id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\u{FEFF}");
            fputcsv($out, self::CSV_COLUMNS, escape: '');

            // Offset paging over a total order (date, then id); paging by id while sorting by date skips and repeats rows.
            foreach ($query->lazy(1000) as $incident) {
                fputcsv($out, self::row($incident), escape: '');
            }
            fclose($out);
        }, $this->fileName('csv', $filters), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The same rows as the CSV, as an Excel workbook (proposal: PDF, Excel and CSV reports). Every cell is written as
     * text, so nothing a user typed can become a formula.
     */
    public function xlsx(User $user, Filters $filters): BinaryFileResponse
    {
        $analytics = new Analytics($user, $filters);
        $this->record($user, 'xlsx', $filters, $analytics->a1()['n']);

        $path = (string) tempnam(sys_get_temp_dir(), 'export');
        // Text cells already stop formulas; the values keep the CSV's leading apostrophe as well, so the workbook
        // matches the CSV cell for cell and stays safe if someone saves it back to CSV.
        $text = fn (array $values): Row => new Row(array_values(array_map(fn (string $value): StringCell => new StringCell($value, null), $values)));

        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow($text(self::CSV_COLUMNS));

            $query = $analytics->base()
                ->with(['category:id,name', 'subCounty:id,name', 'ward:id,name', 'station:id,name'])
                ->orderBy('incidents.occurred_at')->orderBy('incidents.id');
            foreach ($query->lazy(1000) as $incident) {
                $writer->addRow($text(self::row($incident)));
            }
            $writer->close();
        } catch (Throwable $e) {
            @unlink($path);

            throw $e;
        }

        return response()->download($path, $this->fileName('xlsx', $filters), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * @return list<string>
     */
    private static function row(Incident $incident): array
    {
        return array_map(self::cell(...), [
            $incident->reference_no,
            $incident->category?->name,
            $incident->occurred_at->format('Y-m-d H:i'),
            $incident->reported_at->format('Y-m-d H:i'),
            $incident->subCounty?->name,
            $incident->ward?->name,
            $incident->station?->name,
            $incident->status->value,
            $incident->closed_at?->format('Y-m-d H:i'),
            $incident->duplicate_status->value,
            $incident->location_description,
            $incident->description,
            $incident->is_synthetic ? 'sample' : 'real',
        ]);
    }

    public function pdf(User $user, Filters $filters): Response
    {
        $vm = new DashboardViewModel((new Analytics($user, $filters))->all(), $filters);
        $this->record($user, 'pdf', $filters, $vm->n());

        return Pdf::loadView('reports.pdf', ['vm' => $vm, 'user' => $user, 'generatedAt' => now(), 'realRecords' => $this->realRecords($user, $filters)])
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => resource_path('views')])
            ->setPaper('a4')
            ->download($this->fileName('pdf', $filters));
    }

    /**
     * Records in scope that are not labelled synthetic, so the "sample data" notice is never claimed wrongly.
     */
    public function realRecords(User $user, Filters $filters): int
    {
        return (new Analytics($user, $filters))->base()->where('incidents.is_synthetic', false)->count();
    }

    /**
     * Spreadsheet programs run cells that start with = + - @ or a control character as formulas; a leading apostrophe
     * keeps them as text.
     */
    public static function cell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }

    private function record(User $user, string $type, Filters $filters, int $rows): void
    {
        $export = ReportExport::query()->create(['user_id' => $user->id, 'type' => $type, 'filters' => $filters->toArray(), 'row_count' => $rows]);
        $this->audit->record('report.exported', $export, ['type' => $type, 'rows' => $rows], $user);
    }

    private function fileName(string $extension, Filters $filters): string
    {
        return sprintf('incidents-%s-to-%s.%s', $filters->from?->format('Ymd'), $filters->to?->format('Ymd'), $extension);
    }
}
