<x-layouts.app title="Import result">
    <x-page-header :title="$batch->file_name" :subtitle="$batch->status === 'dry_run' ? 'Dry run: nothing has been saved yet.' : ($batch->status === 'committed' ? 'Committed.' : 'Discarded: nothing was saved.')">
        <x-slot:actions>
            @if ($batch->status === 'dry_run')
                <form method="POST" action="{{ route('imports.discard', $batch) }}" data-confirm="Discard this import? Nothing will be saved.">
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit">Discard</button>
                </form>
                <form method="POST" action="{{ route('imports.commit', $batch) }}" data-confirm="Import {{ number_format($batch->rows_accepted) }} accepted rows? Rejected rows are never saved.">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($batch->rows_accepted === 0)><i class="bi bi-check2-circle" aria-hidden="true"></i> Commit {{ number_format($batch->rows_accepted) }} rows</button>
                </form>
            @endif
            @if ($batch->rows_rejected > 0)
                <a class="btn btn-outline-primary" href="{{ route('imports.errors', $batch) }}"><i class="bi bi-download" aria-hidden="true"></i> Error report (CSV)</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="stepper mb-4" aria-label="Import steps">
        <span class="step done"><span class="dot" aria-hidden="true"></span>1. Upload</span>
        <span @class(['step', 'current' => $batch->status === 'dry_run', 'done' => $batch->status !== 'dry_run'])><span class="dot" aria-hidden="true"></span>2. Review dry run</span>
        <span @class(['step', 'done' => $batch->status !== 'dry_run'])><span class="dot" aria-hidden="true"></span>3. {{ $batch->status === 'discarded' ? 'Discarded' : 'Commit' }}</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Rows in file" :value="number_format($batch->rows_total)" icon="file-earmark-text" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Accepted" :value="number_format($batch->rows_accepted)" icon="check2-circle" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Rejected" :value="number_format($batch->rows_rejected)" icon="x-octagon" meta="Rows breaking V1 to V6" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Flagged duplicates" :value="$batch->status === 'committed' ? number_format($batch->rows_flagged_duplicate) : 'After commit'" icon="files" /></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <x-card title="Rejections by rule" :flush="true">
                @if ($byRule === [])
                    <x-empty-state icon="check2-all" title="No rejected rows" />
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($byRule as $rule => $count)
                            <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                                <div><span class="badge text-bg-light border me-1">{{ $rule }}</span><span class="small">{{ $labels[$rule] ?? $rule }}</span></div>
                                <span class="fw-semibold">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
        <div class="col-12 col-lg-8">
            <x-card title="Row errors" :subtitle="($batch->error_report['truncated'] ?? false) ? 'Showing the first 2,000 problems; the counts by rule cover all of them.' : 'Every problem found. Record 1 is the first line after the header.'" :flush="true">
                @if ($rowErrors->isEmpty())
                    <x-empty-state icon="check2-all" title="Every row passed validation" />
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <caption class="visually-hidden">Row errors</caption>
                            <thead><tr><th scope="col" class="ps-3">Record</th><th scope="col">Column</th><th scope="col">Rule</th><th scope="col">Meaning</th></tr></thead>
                            <tbody>
                                @foreach ($rowErrors as $error)
                                    <tr><td class="ps-3">{{ $error['row'] }}</td><td><code>{{ $error['column'] }}</code></td><td>{{ $error['rule'] }}</td><td class="small">{{ $labels[$error['rule']] ?? '' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <x-slot:footer>{{ $rowErrors->links() }}</x-slot:footer>
            </x-card>
        </div>
    </div>
    <p class="mt-3"><a href="{{ route('imports.create') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to imports</a></p>
</x-layouts.app>
