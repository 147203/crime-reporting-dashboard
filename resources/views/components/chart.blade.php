{{--
    A chart with an always-present accessible table (project rule: every chart has a table alternative).
    $spec: ['type' => 'bar|line|doughnut', 'labels' => [...], 'datasets' => [['label' => ..., 'data' => [...]]], 'horizontal' => bool, 'percent' => bool]
    $headers / $rows: the table alternative. $caption: period, filters and n.
--}}
@props(['id', 'title', 'spec', 'headers', 'rows', 'caption' => null, 'small' => false])
<x-card :title="$title" :subtitle="$caption" {{ $attributes }}>
    <x-slot:actions>
        <div class="btn-group btn-group-sm" role="group" aria-label="{{ $title }} view" data-view-toggle="{{ $id }}-view">
            <button type="button" class="btn btn-outline-secondary" data-view="chart" aria-pressed="true"><i class="bi bi-bar-chart" aria-hidden="true"></i> Chart</button>
            <button type="button" class="btn btn-outline-secondary" data-view="table" aria-pressed="false"><i class="bi bi-table" aria-hidden="true"></i> Table</button>
        </div>
    </x-slot:actions>
    <div id="{{ $id }}-view">
        <div data-view-chart>
            @if (count($rows) === 0)
                <x-empty-state icon="bar-chart" title="No data for these filters" />
            @else
                <div @class(['chart-box', 'chart-box-sm' => $small])>
                    <canvas data-chart="{{ $id }}-data" role="img" aria-label="{{ $title }}. The same data is available in the table view."></canvas>
                </div>
                <script type="application/json" id="{{ $id }}-data">@json($spec)</script>
            @endif
        </div>
        <div data-view-table class="d-none">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <caption class="visually-hidden">{{ $title }}{{ $caption ? '. '.$caption : '' }}</caption>
                    <thead><tr>@foreach ($headers as $header)<th scope="col" @class(['text-end' => ! $loop->first])>{{ $header }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>@foreach ($row as $cell)<td @class(['text-end' => ! $loop->first])>{{ $cell }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($headers) }}" class="text-body-secondary">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-card>
