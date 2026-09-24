<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ImportUploadRequest;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Import\ImportFileException;
use App\Services\Import\ImportService;
use App\Services\Import\ImportStateException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportController extends Controller
{
    public const RULE_LABELS = [
        'V1' => 'Category missing, unknown or inactive',
        'V2' => 'Date occurred in the future or more than 10 years ago',
        'V3' => 'Date reported before occurrence or in the future',
        'V4' => 'Sub-county unknown or ward not in the sub-county',
        'V5' => 'Text longer than allowed',
        'V6' => 'Phone number or ID number in free text',
        'station' => 'Station unknown or inactive',
        'status' => 'Status or closing date invalid',
        'format' => 'Row format (wrong number of columns, or a date not written as YYYY-MM-DD HH:MM)',
        'encoding' => 'Text is not UTF-8 (save the file as CSV UTF-8)',
    ];

    public function __construct(private readonly ImportService $imports) {}

    public function create(): View
    {
        $this->imports->pruneAbandoned();

        return view('imports.create', [
            'batches' => ImportBatch::query()->with('user:id,name')->latest('id')->limit(10)->get(),
            'header' => implode(',', ImportService::HEADER),
        ]);
    }

    public function store(ImportUploadRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        try {
            $batch = $this->imports->dryRun($file, $this->user($request));
        } catch (ImportFileException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('imports.show', $batch);
    }

    public function show(Request $request, ImportBatch $batch): View
    {
        $problems = $batch->error_report['errors'] ?? [];
        $page = max(1, $request->integer('page', 1));
        $paginator = new LengthAwarePaginator(array_slice($problems, ($page - 1) * 25, 25), count($problems), 25, $page, ['path' => $request->url()]);

        return view('imports.show', ['batch' => $batch, 'rowErrors' => $paginator, 'byRule' => $batch->error_report['by_rule'] ?? [], 'labels' => self::RULE_LABELS]);
    }

    public function commit(Request $request, ImportBatch $batch): RedirectResponse
    {
        try {
            $batch = $this->imports->commit($batch, $this->user($request));
        } catch (ImportStateException $exception) {
            return redirect()->route('imports.show', $batch)->with('warning', $exception->getMessage());
        }

        return redirect()->route('imports.show', $batch)->with('status', "{$batch->rows_accepted} rows imported; {$batch->rows_flagged_duplicate} flagged as suspected duplicates for review.");
    }

    public function discard(Request $request, ImportBatch $batch): RedirectResponse
    {
        try {
            $this->imports->discard($batch, $this->user($request));
        } catch (ImportStateException $exception) {
            return redirect()->route('imports.show', $batch)->with('warning', $exception->getMessage());
        }

        return redirect()->route('imports.create')->with('status', 'The import was discarded; nothing was saved.');
    }

    public function errors(ImportBatch $batch): StreamedResponse
    {
        return response()->streamDownload(function () use ($batch): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['row', 'column', 'rule', 'meaning'], escape: '');
            foreach ($batch->error_report['errors'] ?? [] as $error) {
                fputcsv($out, [$error['row'], $error['column'], $error['rule'], self::RULE_LABELS[$error['rule']] ?? ''], escape: '');
            }
            fclose($out);
        }, "import-{$batch->id}-errors.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
