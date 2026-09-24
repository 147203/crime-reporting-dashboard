@props(['title' => null, 'subtitle' => null, 'flush' => false])
<section {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                @if ($title)
                    <h2 class="card-title">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <div class="card-subtitle">{{ $subtitle }}</div>
                @endif
            </div>
            @isset($actions)
                <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['card-body' => ! $flush])>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card-footer bg-transparent">{{ $footer }}</div>
    @endisset
</section>
