@props(['label', 'value', 'icon' => 'bar-chart', 'delta' => null, 'deltaText' => null, 'meta' => null])
<div {{ $attributes->class(['card kpi h-100']) }}>
    <div class="card-body d-flex gap-3 align-items-start">
        <span class="kpi-icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
        <div class="min-w-0">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ $value }}</div>
            @if ($deltaText !== null)
                <div @class(['small fw-semibold', 'delta-up' => $delta !== null && $delta > 0, 'delta-down' => $delta !== null && $delta < 0, 'text-body-secondary' => $delta === null || $delta == 0])>
                    @if ($delta !== null && $delta > 0)<i class="bi bi-arrow-up-right" aria-hidden="true"></i>@elseif ($delta !== null && $delta < 0)<i class="bi bi-arrow-down-right" aria-hidden="true"></i>@endif
                    {{ $deltaText }}
                </div>
            @endif
            @if ($meta)
                <div class="kpi-meta mt-1">{{ $meta }}</div>
            @endif
        </div>
    </div>
</div>
