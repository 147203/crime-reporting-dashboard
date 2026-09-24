<x-layouts.app title="New user">
    <x-page-header title="New user" />
    <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
        @csrf
        @include('admin.users._form')
        <div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit">Create user</button><a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</x-layouts.app>
