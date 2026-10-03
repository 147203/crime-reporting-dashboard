<x-layouts.app title="Incidents">
    <x-page-header title="Incidents" :subtitle="$stationScoped ? 'Records for your station.' : 'All records in the county.'">
        <x-slot:actions>
            @can('create', App\Models\Incident::class)
                <a href="{{ route('incidents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Report incident</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-3 filter-bar">
        <form method="GET" action="{{ route('incidents.index') }}" class="row g-2 align-items-end" role="search" aria-label="Filter incidents">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference or landmark" maxlength="60">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="from">From</label>
                <input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="to">To</label>
                <input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">All</option>
                    @foreach ($options->categories() as $id => $name)<option value="{{ $id }}" @selected(($filters['category_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label" for="sub_county_id">Sub-county</label>
                <select class="form-select" id="sub_county_id" name="sub_county_id">
                    <option value="">All</option>
                    @foreach ($options->subCounties() as $id => $name)<option value="{{ $id }}" @selected(($filters['sub_county_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label" for="ward_id">Ward</label>
                <select class="form-select" id="ward_id" name="ward_id" data-ward-select="sub_county_id">
                    <option value="">All</option>
                    @foreach ($options->wards() as $ward)<option value="{{ $ward['value'] }}" data-sub-county="{{ $ward['group'] }}" @selected(($filters['ward_id'] ?? '') == $ward['value'])>{{ $ward['label'] }}</option>@endforeach
                </select>
            </div>
            @unless ($stationScoped)
                <div class="col-6 col-md-4 col-xl-3">
                    <label class="form-label" for="station_id">Station</label>
                    <select class="form-select" id="station_id" name="station_id">
                        <option value="">All</option>
                        @foreach ($options->stations() as $id => $name)<option value="{{ $id }}" @selected(($filters['station_id'] ?? '') == $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endunless
            @unless ($stationScoped)
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label" for="duplicate_status">Duplicate flag</label>
                <select class="form-select" id="duplicate_status" name="duplicate_status">
                    <option value="">Any</option>
                    @foreach ($duplicateStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['duplicate_status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
                </select>
            </div>
            @endunless
            <div class="col-12 col-xl d-flex gap-2 justify-content-xl-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply</button>
                <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </x-card>

    @if ($drillDown !== [])
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 py-2" role="status">
            <span><i class="bi bi-funnel" aria-hidden="true"></i> From the dashboard: {{ implode(', ', $drillDown) }}.</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('incidents.index') }}">Clear</a>
        </div>
    @endif
    <x-card :flush="true" :title="number_format($incidents->total()).' '.Str::plural('incident', $incidents->total())" subtitle="Deleted records are not shown.">
        @if ($incidents->isEmpty())
            <x-empty-state icon="search" title="No incidents match these filters" text="Change or reset the filters to see more records." />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Incidents matching the filters</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="ps-3"><x-sort-link column="reference_no" label="Reference" /></th>
                            <th scope="col">Category</th>
                            <th scope="col" class="d-none d-md-table-cell">Ward</th>
                            <th scope="col" class="d-none d-lg-table-cell">Station</th>
                            <th scope="col"><x-sort-link column="occurred_at" label="Occurred" /></th>
                            <th scope="col"><x-sort-link column="status" label="Status" /></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('incidents.show', $incident) }}" class="fw-semibold">{{ $incident->reference_no }}</a>
                                    @if (! $stationScoped || $incident->duplicate_of_id === null || $incident->duplicateOf?->station_id === auth()->user()?->station_id)
                                        <div><x-badge.duplicate :status="$incident->duplicate_status" /></div>
                                    @endif
                                </td>
                                <td>{{ $incident->category->name }}</td>
                                <td class="d-none d-md-table-cell">{{ $incident->ward->name }}</td>
                                <td class="d-none d-lg-table-cell">{{ $incident->station->name }}</td>
                                <td class="text-nowrap">{{ $incident->occurred_at->format('d M Y, H:i') }}</td>
                                <td><x-badge.status :status="$incident->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-slot:footer>{{ $incidents->onEachSide(1)->links() }}</x-slot:footer>
    </x-card>
</x-layouts.app>
