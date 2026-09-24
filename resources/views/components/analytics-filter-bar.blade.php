{{-- Shared period and area filters for the dashboard and reports. Presets are plain links, so the page works without JS. --}}
@props(['action', 'filters', 'options', 'stationScoped' => false])
@php
    $presets = [
        'Last 30 days' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        'Last 3 months' => [now()->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), now()->toDateString()],
        'Last 12 months' => [now()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(), now()->toDateString()],
        'This year' => [now()->startOfYear()->toDateString(), now()->toDateString()],
        'Last 24 months' => [now()->subMonthsNoOverflow(23)->startOfMonth()->toDateString(), now()->toDateString()],
    ];
    $keep = collect($filters)->except(['from', 'to'])->filter()->all();
@endphp
<x-card class="mb-3 filter-bar">
    <div class="d-flex flex-wrap gap-1 mb-3" role="group" aria-label="Period presets">
        @foreach ($presets as $label => [$from, $to])
            @php($active = ($filters['from'] ?? '') === $from && ($filters['to'] ?? '') === $to)
            <a href="{{ $action.'?'.http_build_query([...$keep, 'from' => $from, 'to' => $to]) }}" @class(['btn btn-sm', 'btn-primary' => $active, 'btn-outline-secondary' => ! $active]) @if ($active) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <form method="GET" action="{{ $action }}" class="row g-2 align-items-end" aria-label="Filters">
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="f-from">From</label><input class="form-control @error('from') is-invalid @enderror" type="date" id="f-from" name="from" value="{{ $filters['from'] ?? '' }}">@error('from')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label" for="f-to">To</label><input class="form-control @error('to') is-invalid @enderror" type="date" id="f-to" name="to" value="{{ $filters['to'] ?? '' }}">@error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="f-category">Category</label>
            <select class="form-select" id="f-category" name="category_id"><option value="">All</option>@foreach ($options->categories() as $id => $name)<option value="{{ $id }}" @selected(($filters['category_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="f-sub-county">Sub-county</label>
            <select class="form-select" id="f-sub-county" name="sub_county_id"><option value="">All</option>@foreach ($options->subCounties() as $id => $name)<option value="{{ $id }}" @selected(($filters['sub_county_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="f-ward">Ward</label>
            <select class="form-select" id="f-ward" name="ward_id" data-ward-select="f-sub-county"><option value="">All</option>@foreach ($options->wards() as $ward)<option value="{{ $ward['value'] }}" data-sub-county="{{ $ward['group'] }}" @selected(($filters['ward_id'] ?? '') == $ward['value'])>{{ $ward['label'] }}</option>@endforeach</select>
        </div>
        @unless ($stationScoped)
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="f-station">Station</label>
                <select class="form-select" id="f-station" name="station_id"><option value="">All</option>@foreach ($options->stations() as $id => $name)<option value="{{ $id }}" @selected(($filters['station_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
            </div>
        @endunless
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="f-status">Status</label>
            <select class="form-select" id="f-status" name="status"><option value="">All</option>@foreach (App\Enums\IncidentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        </div>
        <div class="col-12 col-xl d-flex gap-2 justify-content-xl-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply</button>
            <a href="{{ $action }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</x-card>
