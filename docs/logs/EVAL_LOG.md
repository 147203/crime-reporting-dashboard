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
| Duplicate precision after D-48 (b) | 1.000 (1,000), 1.000 (20,000; 356 flagged), 0.998 (100,000; 1,769 flagged) | at least 0.90 | Numbers in the two locations must now match, which removes nearly all false matches between different addresses on one landmark |
| Duplicate recall after D-48 (b) | 1.000 (1,000), 0.890 (20,000; 356 of 400), 0.883 (100,000; 1,766 of 2,000) | at least 0.90 | Missed. Every miss is explained; see the breakdown below. Target not changed |
| Planted hotspot recovery (A8, z 1.5, n 10) | 3 of 3 planted wards, 0 unplanted (100,000 records); at 1,000 records the third planted ward sits at z 1.53, just above the threshold | report | Sensitivity below |

## Why duplicate recall is below 0.90 (D-48 b)

`sample:evaluate` classifies every planted pair that was not flagged, using the detector's own normalisation, similarity and number rule. Reproduced on 2026-10-03 with fresh loads (20,000 in a new schema, 100,000 in `crime_dashboard_eval_e2e`).

| Records | Planted pairs | Found | Missed: similarity below 0.8 | Missed: numbers differ (D-48) | Missed: other | Recall |
|---|---|---|---|---|---|---|
| 1,000 | 20 | 20 | 0 | 0 | 0 | 1.000 |
| 20,000 | 400 | 356 | 34 (8.5 %) | 10 (2.5 %) | 0 | 0.890 |
| 100,000 | 2,000 | 1,766 | 155 (7.8 %) | 79 (4.0 %) | 0 | 0.883 |

- The detector finds every pair the rule allows ("other" is 0): the shortfall comes from the rule, not from the search, the time window or the candidate limit.
- **Similarity below 0.8 (the larger cause, present before D-48 too):** the generator retypes each copy in one of six ways; two of them change a short location by a whole word: adding " gate" ("near the market 276" to "near the market 276 gate", 0.792) or dropping "the" ("near the church 121" to "near church 121", 0.789). On short locations this falls just under 0.8. Before D-48 this alone gave recall 0.915 and 0.920.
- **Numbers differ (the D-48 cost):** a typo that lands on a digit changes the house or plot number ("31" to "13", "170" to "10"). D-48 treats these as different places, which is what removed the false matches (precision 0.775 to 0.998 at 100,000). Recall fell by exactly these pairs (0.915 to 0.890, 0.920 to 0.883).
- **Why 1,000 records showed 1.000:** 20 planted pairs is a small sample; none happened to get a whole-word change on a short location.
- The causes come from the way the test data retypes copies; whether real officers retype locations this way is unknown until real data or fieldwork shows it (VERIFY in the usability and fieldwork stage). The generator and the threshold were not changed after seeing these results; options are in D-51.

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

- 2026-10-03, D-48 (b): precision recovered at every size; recall now just under 0.90 at 20,000 and 100,000 records, fully explained by the breakdown above. Reported as measured.
