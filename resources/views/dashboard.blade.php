<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" subtitle="Overview of the incidents you can see.">
        <x-slot:actions>
            @can('create', App\Models\Incident::class)
                <a href="{{ route('incidents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Report incident</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3"><x-kpi label="Incidents (all time)" :value="number_format($total)" icon="journal-text" meta="Excludes deleted and confirmed duplicates" /></div>
        @foreach ($byStatus as $label => $count)
            <div class="col-6 col-xl-3"><x-kpi :label="$label" :value="number_format($count)" icon="flag" /></div>
        @endforeach
    </div>

    <x-card title="Most recent incidents" subtitle="By date occurred" :flush="true">
        <x-slot:actions><a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-secondary">View all</a></x-slot:actions>
        @if ($recent->isEmpty())
            <x-empty-state icon="journal" title="No incidents yet" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th scope="col" class="ps-3">Reference</th><th scope="col">Category</th><th scope="col">Ward</th><th scope="col">Occurred</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $incident)
                            <tr>
                                <td class="ps-3"><a href="{{ route('incidents.show', $incident) }}">{{ $incident->reference_no }}</a></td>
                                <td>{{ $incident->category->name }}</td>
                                <td>{{ $incident->ward->name }}</td>
                                <td>{{ $incident->occurred_at->format('d M Y, H:i') }}</td>
                                <td><x-badge.status :status="$incident->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
