@php($value = fn (string $field) => $incident->{$field} instanceof \DateTimeInterface ? $incident->{$field}->format('Y-m-d\TH:i') : $incident->{$field})
<div class="row g-3">
    <div class="col-12 col-lg-8">
        <x-card title="What happened">
            <div class="row">
                <div class="col-12 col-md-6">
                    <x-form.select name="category_id" label="Category" :options="$categories" :value="$incident->category_id" required />
                </div>
                <div class="col-12 col-md-6"></div>
                <div class="col-12 col-md-6">
                    <x-form.input name="occurred_at" type="datetime-local" label="Date and time occurred" :value="$value('occurred_at')" required max="{{ now()->format('Y-m-d\TH:i') }}" />
                </div>
                <div class="col-12 col-md-6">
                    <x-form.input name="reported_at" type="datetime-local" label="Date and time reported" :value="$value('reported_at')" required max="{{ now()->format('Y-m-d\TH:i') }}" />
                </div>
            </div>
            <x-form.textarea name="description" label="Description" :value="$incident->description" rows="5" maxlength="1000"
                help="What happened, without names, phone numbers or ID numbers. Up to 1,000 characters." />
        </x-card>
    </div>
    <div class="col-12 col-lg-4">
        <x-card title="Where">
            <x-form.select name="sub_county_id" label="Sub-county" :options="$subCounties" :value="$incident->sub_county_id" required />
            <x-form.select name="ward_id" label="Ward" :options="$wards" :value="$incident->ward_id" required data-ward-select="sub_county_id" group-attribute="sub-county" help="Only wards in the chosen sub-county are listed." />
            <x-form.input name="location_description" label="Street or landmark" :value="$incident->location_description" maxlength="150" help="Optional, up to 150 characters. No coordinates or personal details." />
            @if ($stationScoped)
                <div class="mb-3">
                    <span class="form-label d-block">Station</span>
                    <span class="badge text-bg-light border">{{ $ownStation }}</span>
                    <div class="form-text">Reports are recorded against your own station.</div>
                </div>
            @else
                <x-form.select name="station_id" label="Station" :options="$stations" :value="$incident->station_id" required />
            @endif
        </x-card>
    </div>
</div>
