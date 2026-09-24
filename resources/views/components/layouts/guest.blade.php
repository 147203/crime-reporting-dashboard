@props(['title' => null])
<x-layouts.base :title="$title">
    <main class="auth-shell">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="auth-mark mb-2" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                <div class="fw-semibold">{{ config('app.name') }}</div>
                <div class="small text-body-secondary">Nairobi County</div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    {{ $slot }}
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 small text-body-secondary">
                <span><i class="bi bi-info-circle" aria-hidden="true"></i> Sample data only</span>
                <button type="button" class="btn btn-sm btn-link text-body-secondary" data-theme-toggle aria-pressed="false" aria-label="Switch theme"><i class="bi bi-moon-stars"></i></button>
            </div>
        </div>
    </main>
</x-layouts.base>
