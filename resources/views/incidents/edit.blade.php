<x-layouts.app title="Edit incident">
    <x-page-header :title="'Edit '.$incident->reference_no" subtitle="Changes are recorded in the audit log." />
    <form method="POST" action="{{ route('incidents.update', $incident) }}" novalidate>
        @csrf
        @method('PUT')
        @include('incidents._form')
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Save changes</button>
            <a href="{{ route('incidents.show', $incident) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
