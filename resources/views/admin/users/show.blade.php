<x-layouts.app :title="$user->name">
    <x-page-header :title="$user->name" :subtitle="$self ? 'Your own account.' : $user->role->label().' account'">
        <x-slot:actions>
            @if ($user->isLocked())
                <form method="POST" action="{{ route('admin.users.unlock', $user) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning"><i class="bi bi-unlock" aria-hidden="true"></i> Unlock</button>
                </form>
            @endif
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
            @if ($deletable && ! $self)
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Delete the account of {{ $user->name }}? This cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('user')
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <x-card title="Account">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Email</dt><dd class="col-sm-7 text-break">{{ $user->email }}</dd>
                    <dt class="col-sm-5">Role</dt><dd class="col-sm-7">{{ $user->role->label() }}</dd>
                    <dt class="col-sm-5">Station</dt><dd class="col-sm-7">{{ $user->station->name ?? 'None' }}</dd>
                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7">
                        @if (! $user->is_active)<span class="badge text-bg-secondary">Inactive</span>
                        @elseif ($user->isLocked())<span class="badge text-bg-warning">Locked until {{ $user->locked_until?->format('H:i') }}</span>
                        @else<span class="badge text-bg-success">Active</span>@endif
                        @if ($user->must_change_password)<span class="badge text-bg-light border">Password change due</span>@endif
                    </dd>
                    <dt class="col-sm-5">Last login</dt><dd class="col-sm-7">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</dd>
                    <dt class="col-sm-5">Created</dt><dd class="col-sm-7 mb-0">{{ $user->created_at?->format('d M Y, H:i') }}</dd>
                </dl>
            </x-card>
        </div>
        <div class="col-12 col-lg-6">
            <x-card title="Activity" subtitle="Everything that records this account as the one who did it.">
                <dl class="row mb-0">
                    @foreach ($activity as $label => $count)
                        <dt class="col-8 fw-normal">{{ $label }}</dt><dd class="col-4 text-end fw-semibold">{{ number_format($count) }}</dd>
                    @endforeach
                </dl>
                <p class="small text-body-secondary mt-3 mb-0">
                    @if ($self)
                        You cannot delete your own account.
                    @elseif ($deletable)
                        This account has never been used, so it can be deleted.
                    @else
                        This account has a history, so it cannot be deleted: records must keep naming who did them. Deactivate it from Edit instead.
                    @endif
                </p>
            </x-card>
        </div>
        <div class="col-12">
            <x-card title="Recent actions" subtitle="The last 10 audit log entries made by this account." :flush="true">
                @if ($recent->isEmpty())
                    <p class="text-body-secondary p-3 mb-0">No actions recorded.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <caption class="visually-hidden">Recent actions by {{ $user->name }}</caption>
                            <thead><tr><th scope="col" class="ps-3">When</th><th scope="col">Action</th><th scope="col">Record</th></tr></thead>
                            <tbody>
                                @foreach ($recent as $entry)
                                    <tr>
                                        <td class="ps-3 small text-nowrap">{{ $entry->created_at?->format('d M Y, H:i') }}</td>
                                        <td><code>{{ $entry->action }}</code></td>
                                        <td class="small">{{ $entry->entity_type ? $entry->entity_type.' #'.$entry->entity_id : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
