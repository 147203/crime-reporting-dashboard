<x-layouts.app title="Reports and export">
    <x-page-header title="Reports and export" subtitle="Download exactly what the dashboard shows for the same filters: a PDF summary or the matching records as CSV." />

    <x-analytics-filter-bar :action="route('reports.index')" :filters="$filters" :options="$options" :station-scoped="$stationScoped" />

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <x-card title="What will be exported" :subtitle="$vm->caption()">
                <p class="mb-2"><span class="display-6 fw-bold">{{ number_format($vm->n()) }}</span> incidents match these filters.</p>
                @if ($vm->n() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <caption class="visually-hidden">Incidents by category</caption>
                            <thead><tr><th scope="col">Category</th><th scope="col" class="text-end">Incidents</th><th scope="col" class="text-end">Share of n (%)</th></tr></thead>
                            <tbody>@foreach ($vm->categories()['rows'] as $row)<tr><td>{{ $row[0] }}</td><td class="text-end">{{ $row[1] }}</td><td class="text-end">{{ $row[2] }}</td></tr>@endforeach</tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
        <div class="col-12 col-lg-5">
            <x-card title="Download">
                <div class="d-grid gap-2">
                    <a class="btn btn-primary" href="{{ route('reports.pdf', $filters) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF summary (A1 to A10)</a>
                    <a class="btn btn-outline-primary" href="{{ route('reports.csv', $filters) }}"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV of the {{ number_format($vm->n()) }} records</a>
                </div>
                <p class="small text-body-secondary mt-3 mb-0">{{ $realRecords === 0 ? 'Every exported record is sample data.' : number_format($realRecords).' records are not labelled as sample data.' }} Exports are limited to what your role may see and are recorded in the audit log.</p>
            </x-card>
            @if ($recent->isNotEmpty())
                <x-card title="Your recent exports" class="mt-3" :flush="true">
                    <ul class="list-group list-group-flush small">
                        @foreach ($recent as $export)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ strtoupper($export->type) }}, {{ number_format($export->row_count) }} records</span><span class="text-body-secondary">{{ $export->created_at?->format('d M Y, H:i') }}</span></li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
