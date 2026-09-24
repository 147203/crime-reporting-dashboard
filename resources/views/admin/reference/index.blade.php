@php($tabs = ['categories' => 'Crime categories', 'sub-counties' => 'Sub-counties', 'wards' => 'Wards', 'stations' => 'Stations'])
<x-layouts.app title="Reference data">
    <x-page-header title="Reference data" subtitle="Lists used by the incident form. Items are renamed or deactivated, never deleted, so old records keep their meaning." />

    <ul class="nav nav-tabs mb-3" role="tablist">
        @foreach ($tabs as $key => $label)
            <li class="nav-item" role="presentation">
                <a @class(['nav-link', 'active' => $tab === $key]) href="{{ route('admin.reference.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    @php($rows = match ($tab) { 'categories' => $categories, 'sub-counties' => $subCounties, 'wards' => $wards, 'stations' => $stations })
    @php($hasParent = in_array($tab, ['wards', 'stations'], true))
    @php($hasActive = in_array($tab, ['categories', 'stations'], true))

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-card :title="$tabs[$tab]" :subtitle="$rows->count().' items'" :flush="true">
                <x-slot:actions>
                    <label for="reference-filter" class="visually-hidden">Filter this list</label>
                    <input type="search" id="reference-filter" class="form-control form-control-sm" placeholder="Filter this list" data-list-filter="reference-table" autocomplete="off">
                    <span id="reference-table-count" class="visually-hidden" aria-live="polite"></span>
                </x-slot:actions>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="reference-table">
                        <caption class="visually-hidden">{{ $tabs[$tab] }}</caption>
                        <thead><tr><th scope="col" class="ps-3">Name</th>@if ($hasParent)<th scope="col">Sub-county</th>@endif @if ($hasActive)<th scope="col">Active</th>@endif<th scope="col"><span class="visually-hidden">Save</span></th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="ps-3" colspan="{{ 1 + ($hasParent ? 1 : 0) + ($hasActive ? 1 : 0) + 1 }}">
                                        <form method="POST" action="{{ route('admin.reference.update', ['type' => $tab, 'item' => $row->id]) }}" class="row g-2 align-items-center">
                                            @csrf
                                            @method('PUT')
                                            <div class="col-12 col-md">
                                                <label class="visually-hidden" for="name-{{ $row->id }}">Name</label>
                                                <input class="form-control form-control-sm" id="name-{{ $row->id }}" name="name" value="{{ $row->name }}" required maxlength="120">
                                            </div>
                                            @if ($hasParent)
                                                <div class="col-8 col-md-4">
                                                    <label class="visually-hidden" for="parent-{{ $row->id }}">Sub-county</label>
                                                    <select class="form-select form-select-sm" id="parent-{{ $row->id }}" name="sub_county_id">
                                                        @foreach ($subCountyOptions as $id => $name)<option value="{{ $id }}" @selected($row->sub_county_id === $id)>{{ $name }}</option>@endforeach
                                                    </select>
                                                </div>
                                            @endif
                                            @if ($hasActive)
                                                <div class="col-auto">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <div class="form-check form-switch m-0">
                                                        <input class="form-check-input" type="checkbox" role="switch" id="active-{{ $row->id }}" name="is_active" value="1" @checked($row->is_active)>
                                                        <label class="form-check-label small" for="active-{{ $row->id }}">Active</label>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="col-auto"><button class="btn btn-sm btn-outline-primary" type="submit">Save</button></div>
                                            @if ($tab === 'categories')<div class="col-12 small text-body-secondary">{{ $row->incidents_count }} incidents</div>@endif
                                            @if ($tab === 'sub-counties')<div class="col-12 small text-body-secondary">{{ $row->wards_count }} wards, {{ $row->stations_count }} stations</div>@endif
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-12 col-xl-4">
            <x-card title="Add item">
                <form method="POST" action="{{ route('admin.reference.store', ['type' => $tab]) }}" novalidate>
                    @csrf
                    <x-form.input name="name" label="Name" required maxlength="120" />
                    @if ($hasParent)
                        <x-form.select name="sub_county_id" label="Sub-county" :options="$subCountyOptions" required />
                    @endif
                    @if ($hasActive)
                        <x-form.checkbox name="is_active" label="Active" :checked="true" />
                    @endif
                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add</button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.app>
