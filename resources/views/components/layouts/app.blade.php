@props(['title' => null])
@php($user = auth()->user())
<x-layouts.base :title="$title">
    <a class="skip-link" href="#main">Skip to content</a>
    <div class="app-shell">
        <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-label="Main navigation">
            <div class="offcanvas-header px-3 pt-3 pb-2">
                <a class="app-brand" href="{{ route('home') }}">
                    <span class="app-brand-mark" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <span>Crime Data<br><small class="fw-normal opacity-75">Nairobi County</small></span>
                </a>
                <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close menu"></button>
            </div>
            <div class="offcanvas-body d-block p-0 pb-4">
                <nav class="app-nav">
                    <div class="nav-section">Overview</div>
                    <x-nav-link route="home" icon="speedometer2">Dashboard</x-nav-link>
                    <x-nav-link route="incidents.index" match="incidents.*" icon="journal-text">Incidents</x-nav-link>
                    @can('create', App\Models\Incident::class)
                        <x-nav-link route="incidents.create" icon="plus-circle">Report incident</x-nav-link>
                    @endcan
                    @canany(['reviewDuplicates', 'viewDataQuality', 'import'], App\Models\Incident::class)
                        <div class="nav-section">Data quality</div>
                    @endcanany
                    @can('reviewDuplicates', App\Models\Incident::class)
                        @if (Route::has('duplicates.index'))
                            <x-nav-link route="duplicates.index" match="duplicates.*" icon="files">Duplicate review</x-nav-link>
                        @endif
                    @endcan
                    @can('import', App\Models\Incident::class)
                        @if (Route::has('imports.create'))
                            <x-nav-link route="imports.create" match="imports.*" icon="upload">CSV import</x-nav-link>
                        @endif
                    @endcan
                    @can('viewDataQuality', App\Models\Incident::class)
                        @if (Route::has('quality.index'))
                            <x-nav-link route="quality.index" icon="clipboard2-check">Quality summary</x-nav-link>
                        @endif
                    @endcan
                    @if (Route::has('reports.index'))
                        <div class="nav-section">Reports</div>
                        <x-nav-link route="reports.index" icon="file-earmark-bar-graph">Reports and export</x-nav-link>
                    @endif
                    @if ($user?->hasRole(App\Enums\Role::Admin))
                        <div class="nav-section">Administration</div>
                        <x-nav-link route="admin.users.index" match="admin.users.*" icon="people">Users</x-nav-link>
                        <x-nav-link route="admin.reference.index" match="admin.reference.*" icon="diagram-3">Reference data</x-nav-link>
                        <x-nav-link route="admin.audit.index" icon="clock-history">Audit log</x-nav-link>
                    @endif
                </nav>
            </div>
        </aside>

        <div class="app-main">
            <div class="sample-banner px-3 py-1 text-center" role="note">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                Sample data: all records are synthetic and for demonstration only.
            </div>
            <header class="app-topbar d-flex align-items-center gap-2 px-3 px-md-4">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-theme-toggle aria-pressed="false" aria-label="Switch theme"><i class="bi bi-moon-stars"></i></button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">{{ $user?->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li class="px-3 py-2 small">
                                <div class="fw-semibold">{{ $user?->role->label() }}</div>
                                @if ($user?->station)
                                    <div class="text-body-secondary">{{ $user->station->name }}</div>
                                @endif
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key me-2" aria-hidden="true"></i>Change password</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Log out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main id="main" class="app-content" tabindex="-1">
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>
    <x-confirm-modal />
</x-layouts.base>
