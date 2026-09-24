# Evaluation log

Results that go into Chapter 5, each with the test or fieldwork entry it came from. Definitions: `../06_TESTING_AND_EVALUATION.md`.

## Usability (from real sessions only)

| Participant code | Group | Tasks completed of 8 | Total time | Errors | SUS score | Date |
|---|---|---|---|---|---|---|

Summary: n, mean SUS, median, range, issues found and fixed.

## Data-quality and hotspot recovery

| Measure | Result | Target | Notes |
|---|---|---|---|
| Rejection recall V1 to V6 | 1.000 for every rule at 1,000, 20,000 and 100,000 records | at least 0.95 | A row counts only when its seeded rule is reported (`sample:evaluate`) |
| False rejection rate | 0 at every size | near 0 | |
| Duplicate precision | 1.000 (1,000), 0.943 (20,000), 0.775 (100,000) | at least 0.90 | Missed at 100,000: with about 4,300 records per month, different incidents of the same category in the same ward within 2 hours often share a landmark in the generator's small vocabulary (12 landmarks, numbers 1 to 400), and V8 cannot tell them apart. Target not changed; see D-48 |
| Duplicate recall | 1.000 (1,000), 0.915 (20,000), 0.920 (100,000) | at least 0.90 | Misses are copies retyped too differently (for example a dropped word in a short location), below the 0.8 similarity threshold by design |
| Planted hotspot recovery (A8, z 1.5, n 10) | 3 of 3 planted wards, 0 unplanted (100,000 records); at 1,000 records the third planted ward sits at z 1.53, just above the threshold | report | Sensitivity below |

## Hotspot sensitivity (A8)

| Z threshold | Min incidents | Planted recovered | Unplanted flagged | Notes |
|---|---|---|---|---|
| 1.0 | 5 | 3 of 3 | 1 | 100,000 records, 24 months |
| 1.0 | 10 | 3 of 3 | 1 | |
| 1.0 | 20 | 3 of 3 | 1 | |
| 1.5 | 5 | 3 of 3 | 0 | default z (D-11) |
| 1.5 | 10 | 3 of 3 | 0 | default (D-11) |
| 1.5 | 20 | 3 of 3 | 0 | |
| 2.0 | 5 | 3 of 3 | 0 | |
| 2.0 | 10 | 3 of 3 | 0 | |
| 2.0 | 20 | 3 of 3 | 0 | |

## Findings

(none yet)
