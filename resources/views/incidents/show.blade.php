<x-layouts.app :title="$incident->reference_no">
    <x-page-header :title="$incident->reference_no" :subtitle="$incident->category->name.' in '.$incident->ward->name.', '.$incident->subCounty->name">
        <x-slot:actions>
            @can('update', $incident)
                <a href="{{ route('incidents.edit', $incident) }}" class="btn btn-outline-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
            @endcan
            @can('delete', $incident)
                <form method="POST" action="{{ route('incidents.destroy', $incident) }}" data-confirm="Delete incident {{ $incident->reference_no }}? It will be removed from lists and analytics.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <x-card title="Details">
                <dl class="row detail-list mb-0">
                    <div class="col-sm-6"><dt>Occurred</dt><dd>{{ $incident->occurred_at->format('l d M Y, H:i') }}</dd></div>
                    <div class="col-sm-6"><dt>Reported</dt><dd>{{ $incident->reported_at->format('l d M Y, H:i') }}</dd></div>
                    <div class="col-sm-6"><dt>Station</dt><dd>{{ $incident->station->name }}</dd></div>
                    <div class="col-sm-6"><dt>Street or landmark</dt><dd>{{ $incident->location_description ?? 'Not given' }}</dd></div>
                    <div class="col-12"><dt>Description</dt><dd class="text-break">{{ $incident->description ?? 'Not given' }}</dd></div>
                    <div class="col-sm-6"><dt>Recorded by</dt><dd>{{ $incident->creator->name ?? 'Import or system' }}</dd></div>
                    <div class="col-sm-6"><dt>Data</dt><dd>{{ $incident->is_synthetic ? 'Sample data' : 'Real record' }}</dd></div>
                </dl>
            </x-card>
        </div>
        <div class="col-12 col-lg-4">
            <x-card title="Status">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-badge.status :status="$incident->status" />
                    <x-badge.duplicate :status="$incident->duplicate_status" />
                </div>
                <div class="stepper mb-3" aria-label="Status workflow">
                    @foreach (App\Enums\IncidentStatus::cases() as $step)
                        @php($reached = array_search($step, App\Enums\IncidentStatus::cases(), true) <= array_search($incident->status, App\Enums\IncidentStatus::cases(), true))
                        <span @class(['step', 'current' => $step === $incident->status, 'done' => $reached && $step !== $incident->status])><span class="dot" aria-hidden="true"></span>{{ $step->label() }}</span>
                    @endforeach
                </div>
                @if ($incident->closed_at)
                    <p class="small text-body-secondary">Closed {{ $incident->closed_at->format('d M Y, H:i') }}</p>
                @endif
                @if ($incident->duplicateOf)
                    @can('view', $incident->duplicateOf)
                        <p class="small">Possible duplicate of <a href="{{ route('incidents.show', $incident->duplicateOf) }}">{{ $incident->duplicateOf->reference_no }}</a></p>
                    @else
                        <p class="small">Possible duplicate of a record held by another station.</p>
                    @endcan
                @endif
                @can('changeStatus', $incident)
                    @if ($incident->status->next() !== [])
                        <form method="POST" action="{{ route('incidents.status', $incident) }}" class="d-flex gap-2 flex-wrap">
                            @csrf
                            @method('PUT')
                            <label for="status" class="visually-hidden">New status</label>
                            <select id="status" name="status" class="form-select form-select-sm w-auto @error('status') is-invalid @enderror">
                                @foreach ($incident->status->next() as $next)
                                    <option value="{{ $next->value }}">{{ $next->label() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Update status</button>
                            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </form>
                    @endif
                @endcan
            </x-card>
        </div>
    </div>
</x-layouts.app>
