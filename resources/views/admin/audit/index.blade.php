<x-layouts.app title="Audit log">
    <x-page-header title="Audit log" subtitle="Who did what and when. Entries cannot be edited or deleted, and never contain the content of sensitive fields." />
    <x-card class="mb-3 filter-bar">
        <form method="GET" class="row g-2 align-items-end" role="search" aria-label="Filter audit log">
            <div class="col-12 col-md-3">
                <label class="form-label" for="action">Action</label>
                <select class="form-select" id="action" name="action"><option value="">All</option>@foreach ($actions as $action)<option @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach</select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="user_id">User</label>
                <select class="form-select" id="user_id" name="user_id"><option value="">All</option>@foreach ($users as $id => $name)<option value="{{ $id }}" @selected(($filters['user_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
            </div>
            <div class="col-6 col-md-2"><label class="form-label" for="from">From</label><input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-6 col-md-2"><label class="form-label" for="to">To</label><input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-outline-secondary" href="{{ route('admin.audit.index') }}">Reset</a></div>
        </form>
    </x-card>
    <x-card :flush="true">
        @if ($entries->isEmpty())
            <x-empty-state icon="clock-history" title="No entries" />
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Audit entries</caption>
                    <thead><tr><th scope="col" class="ps-3">When</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col" class="d-none d-lg-table-cell">Details</th><th scope="col" class="d-none d-md-table-cell">IP</th></tr></thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="ps-3 text-nowrap small">{{ $entry->created_at?->format('d M Y, H:i:s') }}</td>
                                <td>{{ $entry->user->name ?? 'Unknown' }}</td>
                                <td><code>{{ $entry->action }}</code></td>
                                <td class="small">{{ $entry->entity_type }} {{ $entry->entity_id ? '#'.$entry->entity_id : '' }}</td>
                                <td class="d-none d-lg-table-cell small text-body-secondary">{{ $entry->meta ? json_encode($entry->meta) : '' }}</td>
                                <td class="d-none d-md-table-cell small">{{ $entry->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-slot:footer>{{ $entries->links() }}</x-slot:footer>
    </x-card>
</x-layouts.app>
