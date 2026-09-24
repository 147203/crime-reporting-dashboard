@php
    $m = $vm->metrics;
    $weekdays = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $pct = fn ($v) => $v === null ? 'n/a' : number_format($v, 2);
    $rate = fn ($v) => $v === null ? 'n/a' : number_format($v, 2).' %';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Crime data summary</title>
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #0f172a; }
    h1 { font-size: 15pt; margin: 0 0 4pt; color: #1f3a68; }
    h2 { font-size: 11pt; margin: 14pt 0 4pt; color: #1f3a68; border-bottom: 1px solid #dde3ec; padding-bottom: 2pt; }
    .meta { color: #5b6b82; font-size: 8.5pt; margin-bottom: 8pt; }
    .banner { background: #fdf3e1; border: 1px solid #f0c98a; padding: 4pt 6pt; font-size: 8.5pt; margin-bottom: 8pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
    th, td { border: 1px solid #dde3ec; padding: 3pt 5pt; text-align: left; }
    th { background: #eef1f6; font-size: 8.5pt; }
    td.num, th.num { text-align: right; }
    .kpis td { width: 25%; vertical-align: top; }
    .kpi-value { font-size: 14pt; font-weight: bold; }
    .small { font-size: 8pt; color: #5b6b82; }
    .flag { background: #fbe4e3; }
</style>
</head>
<body>
    <h1>Crime data summary, Nairobi County</h1>
    <div class="meta">{{ $vm->caption() }} Generated {{ $generatedAt->format('j M Y, H:i') }} by {{ $user->name }} ({{ $user->role->label() }}).</div>
    <div class="banner">{{ $realRecords === 0 ? 'Sample data: every record in this report is synthetic and for demonstration only.' : number_format($realRecords).' records in this report are not labelled as sample data.' }} Counts exclude deleted records and confirmed duplicates. Covers A1 to A8 and A10; the sub-county by month grid (A9) is on the dashboard.</div>

    <table class="kpis">
        <tr>
            <td>Incidents (A1)<div class="kpi-value">{{ number_format($vm->n()) }}</div></td>
            <td>Change vs previous period (A4)<div class="kpi-value">{{ $m['A4']['change_pct'] === null ? 'n/a' : App\Services\Analytics\DashboardViewModel::signed($m['A4']['change_pct']).' %' }}</div><span class="small">previous {{ number_format($m['A4']['previous']) }}</span></td>
            <td>Closure rate (A10)<div class="kpi-value">{{ $rate($m['A10']['closure_rate_pct']) }}</div><span class="small">closed of n</span></td>
            <td>Hotspot wards (A8)<div class="kpi-value">{{ count($m['A8']['flagged']) }}</div><span class="small">z at least {{ $m['A8']['threshold'] }}, at least {{ $m['A8']['min_incidents'] }} incidents</span></td>
        </tr>
    </table>

    <h2>Categories (A2)</h2>
    <table><tr><th>Category</th><th class="num">Incidents</th><th class="num">Share of n (%)</th></tr>
        @foreach ($m['A2'] as $r)<tr><td>{{ $r['category'] }}</td><td class="num">{{ number_format($r['count']) }}</td><td class="num">{{ $pct($r['share_pct']) }}</td></tr>@endforeach
    </table>

    <h2>Monthly trend (A3)</h2>
    <table><tr><th>Month</th><th class="num">Incidents</th><th class="num">3-month average</th><th class="num">Change on previous month (%)</th></tr>
        @foreach ($m['A3'] as $r)<tr><td>{{ date('M Y', strtotime($r['month'].'-01')) }}</td><td class="num">{{ number_format($r['count']) }}</td><td class="num">{{ $r['ma3'] === null ? 'n/a' : number_format($r['ma3'], 2) }}</td><td class="num">{{ $r['mom_pct'] === null ? 'n/a' : $pct($r['mom_pct']) }}</td></tr>@endforeach
    </table>

    <h2>Day of week (A5) and time of day (A6)</h2>
    <table><tr><th>Day</th><th class="num">Incidents</th><th class="num">Share of n (%)</th><th>Time of day</th><th class="num">Incidents</th><th class="num">Share of n (%)</th></tr>
        @for ($i = 0; $i < 7; $i++)
            <tr>
                <td>{{ $weekdays[$m['A5'][$i]['weekday']] }}</td><td class="num">{{ number_format($m['A5'][$i]['count']) }}</td><td class="num">{{ $pct($m['A5'][$i]['share_pct']) }}</td>
                @if (isset($m['A6'][$i]))<td>{{ $m['A6'][$i]['band'] }}</td><td class="num">{{ number_format($m['A6'][$i]['count']) }}</td><td class="num">{{ $pct($m['A6'][$i]['share_pct']) }}</td>@else<td></td><td></td><td></td>@endif
            </tr>
        @endfor
    </table>

    <h2>Area ranking (A7) and hotspot flags (A8)</h2>
    <table><tr><th class="num">Rank</th><th>Ward</th><th class="num">Incidents</th><th class="num">Share of n (%)</th></tr>
        @php($flagged = array_column($m['A8']['flagged'], 'z', 'ward'))
        @foreach (array_slice($m['A7']['wards'], 0, 15) as $r)
            <tr @class(['flag' => isset($flagged[$r['name']])])><td class="num">{{ $r['rank'] }}</td><td>{{ $r['name'] }}{{ isset($flagged[$r['name']]) ? ' (hotspot, z = '.number_format($flagged[$r['name']], 2).')' : '' }}</td><td class="num">{{ number_format($r['count']) }}</td><td class="num">{{ $pct($r['share_pct']) }}</td></tr>
        @endforeach
    </table>
    <p class="small">Top 15 wards shown.</p>

    <h3 style="font-size:10pt;margin:8pt 0 4pt">Hotspot wards (A8)</h3>
    @if ($m['A8']['flagged'] === [])
        <p>No ward is flagged for these filters.</p>
    @else
        <table><tr><th>Ward</th><th class="num">Incidents</th><th class="num">z score</th></tr>
            @foreach ($m['A8']['flagged'] as $r)<tr class="flag"><td>{{ $r['ward'] }}</td><td class="num">{{ number_format($r['count']) }}</td><td class="num">{{ number_format($r['z'], 2) }}</td></tr>@endforeach
        </table>
    @endif
    <p class="small">{{ $vm->filters->wardId !== null ? 'A ward filter does not apply to A8, because one ward has no spread; wards are compared within the sub-county filter. ' : '' }}Hotspot rule: ward count at least {{ $m['A8']['threshold'] }} standard deviations above the ward mean ({{ number_format($m['A8']['mu'], 2) }}, standard deviation {{ number_format($m['A8']['sigma'], 2) }}) and at least {{ $m['A8']['min_incidents'] }} incidents.</p>

    <h2>Sub-counties (A7)</h2>
    <table><tr><th class="num">Rank</th><th>Sub-county</th><th class="num">Incidents</th><th class="num">Share of n (%)</th></tr>
        @foreach ($m['A7']['sub_counties'] as $r)<tr><td class="num">{{ $r['rank'] }}</td><td>{{ $r['name'] }}</td><td class="num">{{ number_format($r['count']) }}</td><td class="num">{{ $pct($r['share_pct']) }}</td></tr>@endforeach
    </table>

    <h2>Status and timeliness (A10)</h2>
    <table>
        <tr><th>Reported</th><th>Under investigation</th><th>Closed</th><th>Median reporting delay</th><th>90th percentile delay</th><th>Median days to close</th></tr>
        <tr>
            <td>{{ number_format($m['A10']['status']['reported']) }}</td><td>{{ number_format($m['A10']['status']['under_investigation']) }}</td><td>{{ number_format($m['A10']['status']['closed']) }}</td>
            <td>{{ $m['A10']['report_delay_hours']['median'] === null ? 'n/a' : number_format($m['A10']['report_delay_hours']['median'], 2).' h' }}</td>
            <td>{{ $m['A10']['report_delay_hours']['p90'] === null ? 'n/a' : number_format($m['A10']['report_delay_hours']['p90'], 2).' h' }}</td>
            <td>{{ $m['A10']['closure_days']['median'] === null ? 'n/a' : number_format($m['A10']['closure_days']['median'], 2).' days' }}</td>
        </tr>
    </table>
    <p class="small">Percentiles use the nearest-rank method; the median of an even count is the lower middle value.</p>
</body>
</html>
