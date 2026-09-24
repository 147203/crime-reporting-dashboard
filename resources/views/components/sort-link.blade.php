@props(['column', 'label'])
@php
    $current = request('sort');
    $direction = request('direction', 'desc');
    $next = $current === $column && $direction === 'asc' ? 'desc' : 'asc';
@endphp
<a class="sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]) }}"
   @if ($current === $column) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }}
    @if ($current === $column)
        <i class="bi bi-caret-{{ $direction === 'asc' ? 'up' : 'down' }}-fill" aria-hidden="true"></i>
    @endif
</a>
