<x-layouts.app title="Duplicate review">
    <x-page-header title="Duplicate review" subtitle="Records flagged by rule V8: same category and ward, within {{ $window }} minutes, and similar location text (at least {{ (int) ($threshold * 100) }} percent). Confirming removes the record from analytics; nothing is deleted." />

    @error('decision')
        <div class="alert alert-warning" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $message }}</div>
    @enderror

    @forelse ($suspected as $incident)
        @php($original = $incident->duplicateOf)
        <x-card class="mb-3" :title="'Suspected duplicate '.$incident->reference_no" :subtitle="'Location similarity '.$similarity($incident, $original).' percent'">
            <x-slot:actions>
                <form method="POST" action="{{ route('duplicates.resolve', $incident) }}" class="d-flex gap-2">
                    @csrf
                    @method('PUT')
                    <button class="btn btn-sm btn-outline-secondary" name="decision" value="dismissed" type="submit"><i class="bi bi-x-lg" aria-hidden="true"></i> Not a duplicate</button>
                    <button class="btn btn-sm btn-danger" name="decision" value="confirmed" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Confirm duplicate</button>
                </form>
            </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <caption class="visually-hidden">Comparison of {{ $incident->reference_no }} with the earlier record</caption>
                    <thead><tr><th scope="col"></th><th scope="col">Flagged record</th><th scope="col">Earlier record</th></tr></thead>
                    <tbody>
                        <tr><th scope="row">Reference</th><td><a href="{{ route('incidents.show', $incident) }}">{{ $incident->reference_no }}</a></td><td>{{ $original?->reference_no ?? 'Deleted' }}</td></tr>
                        <tr><th scope="row">Category</th><td>{{ $incident->category->name }}</td><td>{{ $original?->category->name }}</td></tr>
                        <tr><th scope="row">Ward</th><td>{{ $incident->ward->name }}</td><td>{{ $original?->ward->name }}</td></tr>
                        <tr><th scope="row">Occurred</th><td>{{ $incident->occurred_at->format('d M Y, H:i') }}</td><td>{{ $original?->occurred_at->format('d M Y, H:i') }}</td></tr>
                        <tr><th scope="row">Street or landmark</th><td>{{ $incident->location_description }}</td><td>{{ $original?->location_description }}</td></tr>
                        <tr><th scope="row">Station</th><td>{{ $incident->station->name }}</td><td>{{ $original?->station->name }}</td></tr>
                        <tr><th scope="row">Description</th><td class="small">{{ $incident->description }}</td><td class="small">{{ $original?->description }}</td></tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    @empty
        <x-card><x-empty-state icon="check2-all" title="Nothing to review" text="New suspected duplicates appear here when records are reported or imported." /></x-card>
    @endforelse
    {{ $suspected->links() }}
</x-layouts.app>
