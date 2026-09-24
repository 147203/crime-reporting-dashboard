@props(['status'])
@if ($status !== App\Enums\DuplicateStatus::None)
    @php($tone = match ($status) { App\Enums\DuplicateStatus::Suspected => 'warning', App\Enums\DuplicateStatus::Confirmed => 'danger', default => 'secondary' })
    <span class="badge rounded-pill border border-{{ $tone }} text-{{ $tone }}-emphasis bg-{{ $tone }}-subtle"><i class="bi bi-files" aria-hidden="true"></i> {{ $status->label() }} duplicate</span>
@endif
