<x-layouts.app title="Data quality">
    <x-page-header title="Data-quality summary" subtitle="How clean the data is: import rejections by rule, duplicate review, and completeness of optional fields." />

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Committed imports" :value="number_format($importCount)" icon="upload" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Rows imported" :value="number_format($rowsAccepted)" icon="check2-circle" :meta="'of '.number_format($rowsTotal).' rows in files'" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Rows rejected" :value="number_format($rowsRejected)" icon="x-octagon" :meta="$rowsTotal > 0 ? round(100 * $rowsRejected / $rowsTotal, 2).' percent of rows in files' : null" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Countable records" :value="number_format($countable)" icon="journal-text" :meta="'Of '.number_format($visible).' you can see; excludes deleted and confirmed duplicates'" /></div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <x-card title="Import rejections by rule" subtitle="Problems found in committed imports; one row can break more than one rule." :flush="true">
                @if ($rejections === [])
                    <x-empty-state icon="check2-all" title="No rejections recorded" />
                @else
                    <table class="table mb-0">
                        <caption class="visually-hidden">Rejections by rule</caption>
                        <thead><tr><th scope="col" class="ps-3">Rule</th><th scope="col">Meaning</th><th scope="col" class="text-end pe-3">Problems</th></tr></thead>
                        <tbody>
                            @foreach ($rejections as $rule => $count)
                                <tr><td class="ps-3">{{ $rule }}</td><td class="small">{{ $labels[$rule] ?? '' }}</td><td class="text-end pe-3">{{ number_format($count) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </div>
        <div class="col-12 col-lg-6">
            <x-card title="Duplicate review (V8)" :flush="true">
                <table class="table mb-0">
                    <caption class="visually-hidden">Records by duplicate status</caption>
                    <thead><tr><th scope="col" class="ps-3">Status</th><th scope="col" class="text-end pe-3">Records</th></tr></thead>
                    <tbody>
                        @foreach ($duplicates as $label => $count)
                            <tr><td class="ps-3">{{ $label }}</td><td class="text-end pe-3">{{ number_format($count) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <x-slot:footer><a href="{{ route('duplicates.index') }}">Open the review queue</a></x-slot:footer>
            </x-card>
            <x-card title="Completeness of optional fields" subtitle="Share of countable records with the field filled in (denominator: countable records you can see)." class="mt-3" :flush="true">
                <table class="table mb-0">
                    <caption class="visually-hidden">Completeness</caption>
                    <tbody>
                        @foreach ($completeness as $field => $pct)
                            <tr><th scope="row" class="ps-3 fw-normal">{{ $field }}</th><td class="text-end pe-3">{{ $pct === null ? 'n/a' : $pct.' percent' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-layouts.app>
