<x-layouts.app :title="'Edit '.$user->name">
    <x-page-header :title="'Edit '.$user->name" :subtitle="$self ? 'Your own account. Changes are recorded in the audit log.' : 'Changes are recorded in the audit log.'" />
    <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.users._form')
        <div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit">Save changes</button><a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</x-layouts.app>
