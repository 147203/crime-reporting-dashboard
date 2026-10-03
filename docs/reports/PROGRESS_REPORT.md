# Progress Report

## Web-Based Crime Data Reporting, Analysis and Visualization Platform for Nairobi County

| Item | Detail |
|---|---|
| Student | Mathai, Margaret Elsie Wairimu, BBIT 4D, admission number 147203 |
| Supervisor | Mr Nicodemus Maingi |
| Report date | 3 October 2026 (updated at the end of every phase) |
| Period covered | Development from 24 September 2026 to date |
| Method | Object-oriented analysis and design (OOAD), prototyping development |
| Repository | Private GitHub repository under my own account (147203) |

# 1. Summary

The development of the platform is well advanced. I have built and tested a working prototype that covers every module in my revised scope: secure sign-in with three roles, incident reporting with validation, bulk CSV import, duplicate detection and review, a data-quality summary, ten descriptive analytics measures on an interactive dashboard, CSV, Excel and PDF reports, and a tested backup and restore procedure. Following the panel decision there is **no GIS component**: location is recorded as sub-county, ward and a free-text landmark, and a "hotspot" is a statistical flag on a ward shown in tables and charts.

The prototype is covered by 305 automated tests and 20 browser tests, all passing. Every module promised in my proposal, apart from the GIS component the panel removed, is now built. What remains is the **corrected proposal**, the **fieldwork** with real participants, the development changes that will come out of the fieldwork and usability sessions (Section 6), the **writing of Chapters 4 to 6**, and the **demonstration**.

# 2. How I am working

I follow the prototyping approach in my proposal: build a thin working version, test it, review it and refine it, in planned phases. Each phase ends with a checkpoint (a "gate") that must pass before the next phase starts. Every design decision is recorded with its reason in a decision log, every test run in a test log, and I keep plain-language notes on each phase so that I can explain every part of the system at my defence. Each phase was also checked by an independent review for security and correctness problems before it was accepted.

**Use of AI assistance.** I used an AI coding assistant (Claude Code) to help write the program code, the automated tests and the technical reviews, working under my direction: I set the scope and made the design decisions recorded in the decision log. I keep a private log of where the assistant was used so that I can disclose it as the university policy requires; I would like your guidance on what that disclosure should look like. All thesis text is my own.

# 3. Technology and architecture

| Layer | What I used | Why |
|---|---|---|
| Presentation | Server-rendered pages (Laravel Blade), Bootstrap 5, Chart.js | Works on desktop and phone browsers; every chart has a table view for accessibility |
| Application | PHP 8.4 with the Laravel 12 framework | Tested building blocks for sign-in, validation, access control and database changes |
| Data | MariaDB 10.4 (MySQL-compatible, supplied with XAMPP) | The proposal names MySQL; MariaDB is compatible and already installed on my machine |
| Testing | PHPUnit (automated tests), Playwright (browser tests) | Tests run against the same database engine as the application |

The system follows the three-layer design in my proposal (presentation, application, data). Design diagrams (use case, class, entity relationship, sequence, activity, architecture and a redrawn conceptual framework without GIS) are prepared as sources for Chapter 4.

# 4. Development phases completed

**Table 1. Development phases**

| Phase | What I built | How it was checked | Status |
|---|---|---|---|
| 0. Setup | Development environment, private repository, Laravel project, start-up checks that stop the system running without its required secrets, code-quality tools | 32 tests; security review of the set-up | Done |
| 2. Design (database) | Nine tables with integrity rules enforced by the database itself (a ward must belong to its sub-county, only valid status values, an audit log that cannot be edited); reference data for 17 sub-counties and 85 wards; design diagrams | 45 tests; migrations rolled back and re-applied cleanly | Done |
| 3. Prototype 1: core system | Sign-in with lockout after five wrong passwords, forced password change at first login, three roles (Administrator, Reporting Officer limited to own station, Analyst), incident form, list with filters, detail page and status workflow, user and reference-data administration, audit log, responsive interface with light and dark themes | 198 tests, including every page tried as a guest, an administrator, an officer and an analyst | Done |
| 4. Prototype 2: data quality | Validation rules V1 to V8, a check that refuses phone and ID numbers in free text, duplicate detection with a review screen, CSV import with a check-first step, data-quality summary, sample-data generator that plants known errors, duplicates and hotspots | 252 tests; accuracy measured against the planted errors | Done |
| 5. Prototype 3: analytics and reports | Analytics A1 to A10 computed in the database, dashboard with charts and matching tables, statistical hotspot table, sub-county by month grid, CSV and PDF exports | 272 tests; results compared with an independent calculation | Done |
| 6. Hardening and evaluation (automated part) | Security fixes from an independent review, a database index that made the dashboard five times faster, browser tests of the eight usability tasks on laptop and phone screens, evaluation at 1,000, 20,000 and 100,000 records, usability session kit | 284 tests and 16 browser tests | Done |
| 6b. Development completion | Closed gaps I found when using the system: an administrator can no longer change their own password, role or status from the user screen, the last administrator cannot be removed, new users get a generated temporary password shown once, locked accounts can be unlocked, and password fields have a show or hide button. Added the remaining items from my proposal: Excel export, charts that open the matching incidents when clicked, backup and restore, and an accessibility check. Applied the stricter duplicate rule (house or plot numbers must match) | 310 tests and 28 browser tests; backup restored into a separate database and compared table by table; accessibility scan of 13 pages in both themes on laptop and phone screens | Done |

# 5. Key results

All results come from synthetic sample data, clearly labelled as such in the system; no real police records or personal data are used.

**Table 2. Test and evaluation results**

| Measure | Result | Target |
|---|---|---|
| Automated tests | 310 tests and 28 browser tests, all passing | All pass |
| Accuracy of analytics A1 to A10 | 5,387 of 5,387 values equal to an independent calculation written separately from the system | 100 percent match |
| Access control | Every page tested for four kinds of user; an officer cannot open another station's records | All correct |
| Bad records rejected | 100 percent for every rule at 1,000, 20,000 and 100,000 records; no valid record rejected | At least 95 percent |
| Duplicate detection after the stricter rule (precision / recall) | 1.000 / 1.000 at 1,000 records; 1.000 / 0.890 at 20,000; 0.998 / 0.883 at 100,000 (before: 0.943 / 0.915 and 0.775 / 0.920) | At least 0.90 each |
| Planted hotspots found | All 3 planted hotspot wards flagged, no false flags (100,000 records) | Report |
| Dashboard speed at 100,000 records | 95 percent of requests within 0.69 s (3.5 s before I added a database index) | Within 2 s |
| Import speed | 10,000 records in 34 seconds | Within 60 s |
| Independent security review | No critical or high issues; every issue found was fixed and retested | No open critical or high |
| Accessibility (WCAG 2.1 AA, automated) | No serious or critical issue on any page, light and dark themes, laptop and phone width, after fixing the contrast and keyboard problems the first scan found | None |
| Backup and restore | Restored copy matched the original in every table (the audit log differs by the backup's own entry) | Tested once |

In the first evaluation duplicate precision fell to 0.775 at 100,000 records, because different incidents of the same type in the same ward within two hours often share a landmark in the sample data. I decided to also require any house or plot numbers in the two locations to match. Precision is now 0.998, but recall falls just below the target (0.890 and 0.883 at the larger sizes). I traced every missed pair: the system finds every pair the rule allows, and the misses have two causes in the way my test data retypes copies. About 8 percent of copies add or drop a whole word in a short location (for example "near the church 121" typed as "near church 121"), which falls just under the 0.8 similarity threshold; this was the case before the change too. About 3 to 4 percent have a typing error in the house number, which the new rule correctly treats as a different place. I have kept the target unchanged, reported both results as measured, and recorded an option (ignoring filler words such as "the") to test on new data if you advise it.

# 6. What is yet to be done

## 6.1 Development items still open

| Item | What is needed | Depends on |
|---|---|---|
| Ward and sub-county list | Check the 85 wards and 17 sub-counties against the official IEBC list and correct any differences | Official source |
| Changes from fieldwork | Apply requirement changes that come out of the interviews and questionnaire | Fieldwork |
| Changes from usability sessions | Fix problems found by real users, then re-test the affected tasks | Usability sessions |
| Keyboard walk-through | The automated accessibility scan passes; I still need to go through the main tasks using only the keyboard and record the result | None |
| Final diagrams | Redraw the design diagrams in StarUML and Visual Paradigm as the faculty requires | None |

## 6.2 Research and documentation still open

| Item | Status |
|---|---|
| Corrected proposal | All 46 corrections (marker comments and GIS removal) listed in a tracked checklist; rewriting not yet started |
| Fieldwork | Interview guide and questionnaire to be revised so each has one type of data; permission and participants to be confirmed |
| Usability study | Session kit drafted; sessions with 5 to 8 real participants not yet held |
| Chapters 4 to 6 | Evidence and logs are ready; writing not yet started |
| Demonstration | Rehearsal from a fresh installation and backup recording not yet done |

## 6.3 Items I need your guidance on

- **Final title** (three options prepared, all without GIS) and the abstract word range (180 to 210 or 200 to 250).
- **Resubmission** of the corrected proposal: deadline, format and to whom.
- **Fieldwork:** whether ethics clearance or a research permit is needed, and which participant groups are acceptable.
- **AI-use disclosure:** what the university requires.
- **Demonstration and final submission dates.**
- **Duplicate rule:** whether the shortfall in recall (Section 5) is acceptable as a reported limitation, or whether I should test the filler-word option on new data.

# 7. Next steps

**Table 3. Planned work for the coming weeks**

| When | What I will do |
|---|---|
| 28 Sep to 4 Oct | Rewrite the title, aim and objectives, then the rest of Chapter 1, and send the corrected proposal; request fieldwork permission; revise the interview guide and questionnaire; verify the ward list |
| 5 to 11 Oct | Complete the Chapter 2 and 3 corrections; start interviews and the questionnaire |
| 12 Oct to 1 Nov | Apply fieldwork findings to the system; do the keyboard walk-through; hold usability sessions and fix what they reveal |
| 2 to 15 Nov | Write Chapters 4 to 6 from the logged evidence; redraw the final diagrams; fix references; replace the Gantt chart |
| 16 to 22 Nov | Rehearse the demonstration from a fresh installation and record a backup video |

I would be grateful for a short meeting this week to agree the title, the resubmission process and the fieldwork permissions, since these decide the order of the remaining work.

# 8. Report history

**Table 4. Versions of this report**

| Date | Change |
|---|---|
| 28 September 2026 | First report: development phases 0 and 2 to 6 complete; open development items, proposal correction, fieldwork, usability, chapters and demonstration pending |
| 3 October 2026 | Phase 6b complete: account rules, Excel export, clickable dashboard, backup and restore, accessibility check and the stricter duplicate rule; development items now limited to fieldwork and usability changes, ward-list check, keyboard walk-through and final diagrams |
