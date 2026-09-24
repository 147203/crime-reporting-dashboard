# Accuracy oracle (`scripts/oracle.php`)

## Purpose

Evidence for NFR-05 (accuracy). The script recomputes analytics A1 to A10 from the raw sample CSV with plain PHP arrays and writes the expected values to JSON. Feature tests load the same CSV into MySQL through the application and assert that the application's numbers equal the oracle's. Two separate implementations agreeing on the same data is the accuracy claim.

## Independence

| Reads | Never reads |
|---|---|
| `<dataset>/incidents.csv` | `app/`, `routes/`, `resources/`, `config/` |
| `<dataset>/truth/bad_records.csv` (rows to exclude) | `database/migrations/`, the database itself |
| `database/seeders/data/wards.csv` (85 wards, 17 sub-counties, so zero-count areas are listed) | `tests/Feature/` and the application's analytics code |
| `tests/Fixtures/oracle/filters.json` (filter cases) | `.env` |
| `docs/02_REQUIREMENTS_AND_SCOPE.md` section 4 (the metric contract, used by the author of the script) | |

Plain PHP 8.4, no Composer, no framework, no SQL. Hotspot parameters (z at least 1.5, count at least 10) are constants in the script, taken from the documented defaults, not read from the app's config.

Row handling: rows listed in `truth/bad_records.csv` are excluded (the app rejects them). Near-duplicates in `truth/duplicates.csv` are kept, because the app stores them as `suspected` and they stay countable until a reviewer confirms them.

## Command

```bash
php scripts/oracle.php \
  --input <dataset dir> \
  --filters tests/Fixtures/oracle/filters.json \
  --out tests/Fixtures/oracle/expected_metrics_1000.json
```

Output is deterministic: two runs on the same input are byte-identical (checked with `cmp`).

## Rules

| Topic | Rule |
|---|---|
| Period filter | `occurred_at` from `from 00:00` to `to 23:59` inclusive; times are naive Africa/Nairobi wall-clock (no DST) |
| Shares and percentages | `round(100 * part / whole, 2)`, half away from zero; `null` when the denominator is 0 (A5, A6, A7 shares use 0.0 instead) |
| A3 moving average | mean of the month and the two before it in the listed months, 2 dp; `null` for the first two months |
| A3 month-on-month | `null` for the first month or when the previous month is 0 |
| A4 previous period | same length L (days, inclusive) ending the day before `from`, same other filters |
| A7 rank | 1 + number of areas with a strictly greater count (ties share a rank) |
| A8 | universe = all wards (restricted by the sub-county filter only; the ward filter is ignored), zeros included; population standard deviation (divide by N); thresholds compared before rounding; mu, sigma and z rounded to 4 dp in output |
| A10 percentiles | nearest rank: sort ascending, take the element at 1-based position ceil(p x count), computed in integer arithmetic; the median of an even count is the lower middle value |
| A10 units | report delay in hours (minutes / 60), closure time in days (minutes / 1440), both 2 dp; closure only over closed incidents |
| Name ordering | byte order (`strcmp`), not locale collation |

## Sanity checks (dataset of 1000, seed 20260924)

1. `rows_total` 1090 = 1000 valid + 60 bad + 30 duplicate or near-miss rows (matches `manifest.json`); `rows_excluded_bad` 60, `rows_accepted` 1030.
2. Case `all` covers the whole generated period, so A1 = 1030 = `rows_accepted` (no accepted row falls outside it), and A4 `previous` is 0, so `change_pct` is `null`.
3. For every case, the counts in A2, A3, A5, A6, A10 status, A7 sub-counties and A9 each sum to A1. Without a ward filter the A7 ward counts also sum to A1.
4. Case `closed` has A1 = 511, which equals the `closed` status count in case `all`; closure rate in `all` is 100 x 511 / 1030 = 49.61.
5. Case `all` A8 flags Laini Saba (57) and Kwa Njenga (34), two of the three planted ward hotspots in `truth/hotspots.json`; Woodley/Kenyatta Golf Course (23) ranks third in A7 but stays below z = 1.5.
