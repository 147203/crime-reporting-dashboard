<x-layouts.app title="CSV import">
    <x-page-header title="Bulk CSV import" subtitle="Upload, check every row, then decide. Nothing is saved until you confirm." />

    <div class="stepper mb-4" aria-label="Import steps">
        <span class="step current"><span class="dot" aria-hidden="true"></span>1. Upload</span>
        <span class="step"><span class="dot" aria-hidden="true"></span>2. Review dry run</span>
        <span class="step"><span class="dot" aria-hidden="true"></span>3. Commit or discard</span>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <x-card title="Upload a file">
                <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <x-form.input name="file" type="file" label="CSV file" required accept=".csv,text/csv"
                        help="Up to 5 MB and 10,000 rows. Save as CSV UTF-8. The first line must be the header shown on the right." />
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Check file (dry run)</button>
                </form>
            </x-card>
        </div>
        <div class="col-12 col-lg-6">
            <x-card title="File format">
                <p class="small mb-2">Header, in this order:</p>
                <pre class="small bg-body-tertiary border rounded p-2 text-wrap mb-3"><code>{{ $header }}</code></pre>
                <ul class="small mb-0">
                    <li>Dates exactly as <code>YYYY-MM-DD HH:MM</code>, Nairobi time (other formats are rejected, because 01/08 could mean January or August).</li>
                    <li>Category, sub-county, ward and station by name, as in Reference data.</li>
                    <li><code>status</code> is reported, under_investigation or closed (blank means reported); <code>closed_at</code> only for closed records.</li>
                    <li>Rows that break the same rules as the form (V1 to V6) are rejected and listed by row, column and rule. Cell values are never stored in the report.</li>
                    <li>Near-duplicates (V8) are imported but flagged for review.</li>
                </ul>
            </x-card>
        </div>
    </div>

    <x-card title="Recent imports" class="mt-3" :flush="true">
        @if ($batches->isEmpty())
            <x-empty-state icon="upload" title="No imports yet" />
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <caption class="visually-hidden">Recent imports</caption>
                    <thead><tr><th scope="col" class="ps-3">File</th><th scope="col">Status</th><th scope="col" class="text-end">Rows</th><th scope="col" class="text-end">Accepted</th><th scope="col" class="text-end">Rejected</th><th scope="col" class="d-none d-md-table-cell">By</th><th scope="col" class="d-none d-md-table-cell">When</th></tr></thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            <tr>
                                <td class="ps-3"><a href="{{ route('imports.show', $batch) }}">{{ $batch->file_name }}</a></td>
                                <td><span @class(['badge', 'text-bg-warning' => $batch->status === 'dry_run', 'text-bg-success' => $batch->status === 'committed', 'text-bg-secondary' => $batch->status === 'discarded'])>{{ str_replace('_', ' ', $batch->status) }}</span></td>
                                <td class="text-end">{{ number_format($batch->rows_total) }}</td>
                                <td class="text-end">{{ number_format($batch->rows_accepted) }}</td>
                                <td class="text-end">{{ number_format($batch->rows_rejected) }}</td>
                                <td class="d-none d-md-table-cell">{{ $batch->user->name ?? '' }}</td>
                                <td class="d-none d-md-table-cell small">{{ $batch->created_at?->format('d M Y, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
