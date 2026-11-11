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
    <div class="col-12">
        @php($options = fn (string $enum) => collect($enum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all())
        <x-card title="Victim" subtitle="Describe the victim without identifying them: never record names, phone numbers, ID numbers or home addresses." data-victim-form>
            <div class="row">
                <div class="col-12 col-md-6 col-xl-4">
                    <x-form.select name="victim_type" label="Victim type" :options="$options(\App\Enums\VictimType::class)" :value="$incident->victim_type?->value ?? 'not_recorded'" placeholder="Not recorded" />
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <x-form.input name="victim_count" type="number" label="Number of victims" :value="$incident->victim_count" min="0" max="50" inputmode="numeric" help="Leave empty if not known." />
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <x-form.select name="victim_injury" label="Injury" :options="$options(\App\Enums\InjuryLevel::class)" :value="$incident->victim_injury?->value ?? 'not_recorded'" placeholder="Not recorded" help="The most serious injury to any victim." />
                </div>
            </div>
            <fieldset class="border rounded-3 p-3" data-person-fields>
                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Main victim (individuals only)</legend>
                <p class="small text-body-secondary mb-3" data-person-note hidden>These details apply only when the victim is an individual person.</p>
                <div class="row">
                    <div class="col-12 col-md-6 col-xl-3">
                        <x-form.select name="victim_sex" label="Sex" :options="$options(\App\Enums\VictimSex::class)" :value="$incident->victim_sex?->value ?? 'not_recorded'" placeholder="Not recorded" />
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <x-form.input name="victim_age" type="number" label="Age in years" :value="$incident->victim_age" min="0" max="120" inputmode="numeric" help="If known. The age band is then set from it." />
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <x-form.select name="victim_age_band" label="Age band" :options="$options(\App\Enums\AgeBand::class)" :value="$incident->victim_age_band?->value ?? 'not_recorded'" placeholder="Not recorded" help="Use when only an approximate age is known." />
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <x-form.select name="offender_relationship" label="Relationship to the suspect" :options="$options(\App\Enums\OffenderRelationship::class)" :value="$incident->offender_relationship?->value ?? 'not_known'" placeholder="Not known" />
                    </div>
                </div>
            </fieldset>
        </x-card>
    </div>
</div>
