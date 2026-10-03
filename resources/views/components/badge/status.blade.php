@props(['status'])
@php($tone = match ($status) { App\Enums\IncidentStatus::Reported => 'primary', App\Enums\IncidentStatus::UnderInvestigation => 'warning', App\Enums\IncidentStatus::Closed => 'success' })
<span class="badge rounded-pill text-bg-{{ $tone }}">{{ $status->label() }}</span>
