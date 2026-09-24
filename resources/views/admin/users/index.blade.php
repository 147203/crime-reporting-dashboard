<x-layouts.app title="Users">
    <x-page-header title="Users" subtitle="Accounts are created here; there is no self-registration. Users are deactivated, never deleted.">
        <x-slot:actions><a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus" aria-hidden="true"></i> New user</a></x-slot:actions>
    </x-page-header>
    <x-card :flush="true">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">User accounts</caption>
                <thead><tr><th scope="col" class="ps-3">Name</th><th scope="col">Role</th><th scope="col" class="d-none d-md-table-cell">Station</th><th scope="col">Status</th><th scope="col" class="d-none d-lg-table-cell">Last login</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="ps-3"><div class="fw-semibold">{{ $user->name }}</div><div class="small text-body-secondary">{{ $user->email }}</div></td>
                            <td>{{ $user->role->label() }}</td>
                            <td class="d-none d-md-table-cell">{{ $user->station->name ?? '' }}</td>
                            <td>
                                @if (! $user->is_active)<span class="badge text-bg-secondary">Inactive</span>
                                @elseif ($user->isLocked())<span class="badge text-bg-warning">Locked</span>
                                @else<span class="badge text-bg-success">Active</span>@endif
                                @if ($user->must_change_password)<span class="badge text-bg-light border">Password change due</span>@endif
                            </td>
                            <td class="d-none d-lg-table-cell small">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</td>
                            <td class="text-end pe-3"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.edit', $user) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-slot:footer>{{ $users->links() }}</x-slot:footer>
    </x-card>
</x-layouts.app>
