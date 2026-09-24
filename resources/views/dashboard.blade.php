@php
    $a4 = $vm->metrics['A4'];
    $a8 = $vm->metrics['A8'];
    $a10 = $vm->metrics['A10'];
    $trend = $vm->trend();
    $categories = $vm->categories();
    $weekdays = $vm->weekdays();
    $bands = $vm->timeBands();
    $wards = $vm->topWards();
    $statuses = $vm->statuses();
    $grid = $vm->heatGrid();
@endphp
<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="($stationScoped ? 'Your station. ' : 'County-wide. ').$vm->periodLabel().'.'">
        <x-slot:actions>
            <a href="{{ route('reports.index', $filters) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Export</a>
            @can('create', App\Models\Incident::class)
                <a href="{{ route('incidents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Report incident</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-analytics-filter-bar :action="route('home')" :filters="$filters" :options="$options" :station-scoped="$stationScoped" />
    <p class="small text-body-secondary mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ $vm->caption() }} Counts exclude deleted records and confirmed duplicates.</p>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <x-kpi label="Incidents (A1)" :value="number_format($vm->n())" icon="journal-text"
                :delta="$a4['change_pct']" :delta-text="$a4['change_pct'] === null ? 'No incidents in the previous period' : App\Services\Analytics\DashboardViewModel::signed($a4['change_pct']).' % vs previous period ('.number_format($a4['previous']).')'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-kpi label="Closure rate (A10)" :value="$a10['closure_rate_pct'] === null ? 'n/a' : number_format($a10['closure_rate_pct'], 2).' %'" icon="check2-circle" :meta="number_format($a10['status']['closed']).' closed of n = '.number_format($vm->n())" />
        </div>
        <div class="col-6 col-xl-3">
            <x-kpi label="Hotspot wards (A8)" :value="count($a8['flagged'])" icon="exclamation-diamond" :meta="'z at least '.$a8['threshold'].' and at least '.$a8['min_incidents'].' incidents'" />
        </div>
        <div class="col-6 col-xl-3">
            <x-kpi label="Median reporting delay" :value="$a10['report_delay_hours']['median'] === null ? 'n/a' : number_format($a10['report_delay_hours']['median'], 1).' h'" icon="stopwatch" :meta="$a10['report_delay_hours']['p90'] === null ? null : '90th percentile '.number_format($a10['report_delay_hours']['p90'], 1).' h'" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
 @php($partial = $vm->filters->to !== null && $vm->filters->to->lt($vm->filters->to->endOfMonth()->startOfDay()))
            <x-chart id="chart-trend" title="Monthly trend (A3)" :caption="$vm->caption().($partial ? ' The last month is incomplete.' : '')" :spec="$trend['spec']" :headers="$trend['headers']" :rows="$vm->n() > 0 ? $trend['rows'] : []" />
        </div>
        <div class="col-12 col-xl-4">
            <x-chart id="chart-categories" title="Categories (A2)" :caption="'Share of n = '.number_format($vm->n()).'.'" :spec="$categories['spec']" :headers="$categories['headers']" :rows="$categories['rows']" />
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <x-chart id="chart-weekdays" title="Day of week (A5)" :caption="'Share of n = '.number_format($vm->n()).'.'" :spec="$weekdays['spec']" :headers="$weekdays['headers']" :rows="$vm->n() > 0 ? $weekdays['rows'] : []" :small="true" />
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <x-chart id="chart-bands" title="Time of day (A6)" :caption="'Share of n = '.number_format($vm->n()).'.'" :spec="$bands['spec']" :headers="$bands['headers']" :rows="$vm->n() > 0 ? $bands['rows'] : []" :small="true" />
        </div>
        <div class="col-12 col-xl-4">
            <x-chart id="chart-status" title="Status (A10)" :caption="'Share of n = '.number_format($vm->n()).'.'" :spec="$statuses['spec']" :headers="$statuses['headers']" :rows="$vm->n() > 0 ? $statuses['rows'] : []" :small="true" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <x-chart id="chart-wards" title="Top 10 wards (A7)" :caption="'Ranked by incidents; share of n = '.number_format($vm->n()).'. Ties share a rank.'" :spec="$wards['spec']" :headers="$wards['headers']" :rows="$vm->n() > 0 ? $wards['rows'] : []" />
        </div>
        <div class="col-12 col-xl-5">
            <x-card title="Hotspot wards (A8)" :subtitle="'Flagged when a ward\'s count is at least '.$a8['threshold'].' standard deviations above the ward mean and at least '.$a8['min_incidents'].' incidents. Mean '.number_format($a8['mu'], 2).', standard deviation '.number_format($a8['sigma'], 2).'.'" :flush="true">
                @if ($a8['flagged'] === [])
                    <x-empty-state icon="shield-check" title="No ward is flagged" text="No ward stands out statistically for these filters." />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <caption class="visually-hidden">Flagged hotspot wards</caption>
                            <thead><tr><th scope="col" class="ps-3">Ward</th><th scope="col" class="text-end">Incidents</th><th scope="col" class="text-end pe-3">z score</th></tr></thead>
                            <tbody>
                                @foreach ($a8['flagged'] as $row)
                                    <tr class="hotspot-row"><td class="ps-3 fw-semibold"><i class="bi bi-exclamation-diamond-fill text-danger" aria-hidden="true"></i> {{ $row['ward'] }}</td><td class="text-end">{{ number_format($row['count']) }}</td><td class="text-end pe-3">{{ number_format($row['z'], 2) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <x-slot:footer>
                    <span class="small text-body-secondary">A statistical flag on counts, not a map. Each hotspot is a ward, not a point.
                        @if (($filters['ward_id'] ?? null) !== null) The ward filter does not apply here, because one ward has no spread. @endif
                        @if ($stationScoped || ($filters['station_id'] ?? null) !== null) Wards are compared across the whole county, so for one station the flag mostly reflects where that station's incidents occur. @endif
                    </span>
                </x-slot:footer>
            </x-card>
        </div>
    </div>

    <x-card title="Sub-county by month (A9)" :subtitle="$vm->caption().' Darker cells hold more incidents (quintiles of the non-zero cells).'" :flush="true">
        <x-slot:actions>
            <span class="heat-legend" aria-hidden="true">Fewer <span class="swatch l1"></span><span class="swatch l2"></span><span class="swatch l3"></span><span class="swatch l4"></span><span class="swatch l5"></span> More</span>
        </x-slot:actions>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0 heat-grid text-center">
                <caption class="visually-hidden">Incidents per sub-county per month</caption>
                <thead><tr><th scope="col" class="text-start ps-3">Sub-county</th>@foreach ($grid['months'] as $month)<th scope="col">{{ $month }}</th>@endforeach<th scope="col">Total</th></tr></thead>
                <tbody>
                    @foreach ($grid['rows'] as $row)
                        <tr>
                            <th scope="row" class="text-start ps-3 fw-normal text-nowrap">{{ $row['sub_county'] }}</th>
                            @foreach ($row['cells'] as $cell)<td class="heat-{{ $cell['level'] }}">{{ $cell['count'] }}</td>@endforeach
                            <td class="fw-semibold">{{ number_format($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
