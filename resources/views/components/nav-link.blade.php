@props(['route', 'icon', 'match' => null])
@php($active = request()->routeIs($match ?? $route))
<a {{ $attributes->class(['nav-link', 'active' => $active]) }} href="{{ route($route) }}" @if ($active) aria-current="page" @endif>
    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    <span>{{ $slot }}</span>
</a>
