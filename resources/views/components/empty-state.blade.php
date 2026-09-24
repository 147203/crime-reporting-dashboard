@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->class(['empty-state']) }}>
    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    <p class="fw-semibold mb-1 mt-2">{{ $title }}</p>
    @if ($text)
        <p class="small mb-2">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
