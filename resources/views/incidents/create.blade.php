<x-layouts.app title="Report incident">
    <x-page-header title="Report an incident" subtitle="Fields marked * are required. The record is labelled as sample data." />
    <form method="POST" action="{{ route('incidents.store') }}" novalidate>
        @csrf
        @include('incidents._form')
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Save incident</button>
            <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
