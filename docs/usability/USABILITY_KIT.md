# Usability evaluation kit (DRAFT for the author to review and adapt)

Supports TC-US-01 to TC-US-08 (docs/06 section 7) and NFR-03. The author runs every session with real participants; results go to `docs/logs/EVAL_LOG.md` as aggregates only. Nothing here may be filled in on a participant's behalf.

## 1. Before each session

| Item | Check |
|---|---|
| Consent | Written or recorded consent collected (text agreed with the supervisor, Q4) |
| Participant code | P01, P02, ... (no names in any file in this repository) |
| Account | A dedicated account for the role being tested (Reporting Officer or Analyst); password set by the administrator and changed at first login before the session starts |
| Data | Demo dataset loaded (`php artisan sample:generate --size=20000 --load`); sample-data banner visible |
| Device | Note desktop or phone, browser, screen width |
| Timing | Stopwatch or screen recording (with consent) |

## 2. Task sheet (read aloud; one task at a time)

| Task | Scenario given to the participant | Success when |
|---|---|---|
| TC-US-01 | Sign in with the account on the card. | The dashboard is shown |
| TC-US-02 | A theft was reported to you an hour ago near the market in Makina ward (Kibra). Record it. | A reference number (NRB-...) is shown |
| TC-US-03 | Record another incident; when the form shows an error, correct it and save. (The facilitator's card asks the participant to include a phone number in the description.) | The error is understood and the record is saved without the number |
| TC-US-04 | Find last month's closed burglaries and open one of them. | The detail page of a matching record is open |
| TC-US-05 | (Analyst) Open the duplicate review and decide on the first suspected duplicate. | A decision is recorded |
| TC-US-06 | On the dashboard, answer: which crime category is most common in the last 12 months, and which ward is flagged as a hotspot? | Both answers match the screen |
| TC-US-07 | Download a report of this year's incidents as a PDF. | The PDF file is saved |
| TC-US-08 | Sign out. | The login page is shown |

Record per task: completed (yes, with help, no), time in seconds, number of errors, observer notes.

## 3. System Usability Scale (Brooke, 1996), unchanged

Scale for each item: 1 Strongly disagree, 2, 3, 4, 5 Strongly agree.

1. I think that I would like to use this system frequently.
2. I found the system unnecessarily complex.
3. I thought the system was easy to use.
4. I think that I would need the support of a technical person to be able to use this system.
5. I found the various functions in this system were well integrated.
6. I thought there was too much inconsistency in this system.
7. I would imagine that most people would learn to use this system very quickly.
8. I found the system very cumbersome to use.
9. I felt very confident using the system.
10. I needed to learn a lot of things before I could get going with this system.

Scoring: odd items score (response minus 1); even items score (5 minus response); sum the ten and multiply by 2.5 to get 0 to 100. Report mean, median, range and n; compare with the commonly used benchmark of about 68 and state that a small n limits the conclusion.

Two open comments (analysed separately, coded qualitatively):
- What did you find most useful?
- What was hardest or most confusing?

## 4. Observer sheet (one per participant)

| Task | Completed | Time (s) | Errors | Notes (behaviour, hesitations, quotes without names) |
|---|---|---|---|---|
| TC-US-01 | | | | |
| TC-US-02 | | | | |
| TC-US-03 | | | | |
| TC-US-04 | | | | |
| TC-US-05 | | | | |
| TC-US-06 | | | | |
| TC-US-07 | | | | |
| TC-US-08 | | | | |

Issues found: description, severity (cosmetic, minor, major, blocker), task, suggested change.

## 5. After the sessions

1. Store raw sheets outside the repository (`fieldwork-data/`, git-ignored).
2. Enter one row per participant in `docs/logs/EVAL_LOG.md` (code, group, tasks completed, total time, errors, SUS).
3. List issues by severity; fix major and blocker issues; re-test the affected task (prototype refinement, Chapter 5).
