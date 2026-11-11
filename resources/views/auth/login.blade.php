@inject('preflight', App\Support\Preflight::class)
<x-layouts.guest title="Log in">
    <h1 class="h5 mb-1">Sign in</h1>
    <p class="text-body-secondary small mb-4">Use the account your administrator created for you.</p>
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <x-form.input name="email" type="email" label="Email" required autofocus autocomplete="username" />
        <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
        <button type="submit" class="btn btn-primary w-100 mt-1"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Log in</button>
    </form>
    <p class="text-body-secondary small mt-3 mb-0">Accounts are created by an administrator. There is no self-registration.</p>

    @if ($preflight->demoLoginAllowedFor(request()))
        <section class="demo-login mt-4" aria-labelledby="demo-login-title">
            <h2 class="h6 mb-1" id="demo-login-title"><i class="bi bi-easel" aria-hidden="true"></i> Demo only: sign in as a role</h2>
            <p class="small mb-3">For presentations on this computer. In real use every person signs in with their own email and password; these buttons do not exist on a live system.</p>
            <div class="d-grid gap-2">
                @foreach ([
                    'admin' => ['Administrator', 'shield-lock', 'Users, reference data, imports, audit log, all records'],
                    'officer' => ['Reporting Officer', 'person-badge', 'Reports incidents for one station and sees only that station'],
                    'analyst' => ['Analyst', 'graph-up', 'County-wide dashboards, duplicate review and exports, read-only records'],
                ] as $role => [$label, $icon, $summary])
                    <form method="POST" action="{{ route('demo.login', $role) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100 text-start d-flex align-items-center gap-3">
                            <i class="bi bi-{{ $icon }} fs-4" aria-hidden="true"></i>
                            <span><span class="d-block fw-semibold">Sign in as {{ $label }}</span><span class="d-block small text-body-secondary">{{ $summary }}</span></span>
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.guest>
