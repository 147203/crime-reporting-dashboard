# 05. Fieldwork and instruments (revised)

Marker's comment: the questionnaires and interview questions need revising because analysis may not be easy, and they mix qualitative and quantitative data. Fix: **one instrument, one data type, one analysis plan.** The submitted instruments also contain GIS items that must go (Appendices 1 to 5).

## 1. Instrument set

| Instrument | Purpose | Data type | Analysis |
|---|---|---|---|
| A. Semi-structured interview guide | Understand current reporting process, challenges, needs | Qualitative | Thematic analysis (Braun and Clarke, 2006): familiarise, code, group into themes, map themes to requirements |
| B. Structured observation checklist | Record what the current process actually does | Categorical (yes, no, notes) | Frequency table per item, notes coded to the same theme list |
| C. System requirements questionnaire | Rate and prioritise candidate features | Quantitative (5-point Likert, ranked features) | Frequencies, median and mode per item, priority ranking |
| D. Usability tasks | Measure task success on the prototype | Quantitative | Completion rate, time, errors per task |
| E. Usability questionnaire | Perceived usability | Quantitative | System Usability Scale scoring (Brooke, 1996), plus 2 open comments coded qualitatively |

Do not put open-ended and Likert items in the same instrument except the two short comment boxes in E, which are analysed separately.

## 2. Respondents and access (feasibility first)

Access to police officers may be hard to get. Plan for the achievable set and say so honestly in Chapter 3 limitations:

| Group | Use | Note |
|---|---|---|
| Police or security-sector staff, if permission is granted | Interviews A, observation B, questionnaire C | Requires written permission; ask early |
| ICT staff or administrators of any organisation that records incidents (for example campus or estate security offices, private security firms) | Interviews A, questionnaire C | Proxy users for the reporting and administration roles |
| Analysts or students with data-analysis background | Usability D and E for the analyst role | Representative of dashboard users |

Target sizes (PROVISIONAL, confirm with the supervisor): 5 to 8 interviews, 15 or more questionnaire responses, 5 to 8 usability participants. Say what sampling was used (purposive) and what it means for generalisation.

## 3. Ethics and permissions (VERIFY with the supervisor)

- Ask whether ethics clearance from the university is required and whether an external research permit applies for the planned participants.
- Consent: written or recorded consent, right to withdraw, anonymity, purpose stated as academic, data storage and deletion stated.
- No personal data of victims or suspects is collected or discussed in identifiable form.
- Store raw responses outside the repo (`fieldwork-data/`, ignored); only aggregated results appear in the thesis.
- Never fabricate or "fill in" responses. If numbers are small, report them as small.

## 4. Revised instrument design (structure, not final wording; the author writes the questions)

**A. Interview guide**

| Section | Focus | Sample probe types (author drafts) |
|---|---|---|
| A1 Background | Role, experience, unit | Closed background items kept minimal |
| A2 Current process | How incidents are recorded and shared | Walk me through the last case you recorded |
| A3 Data quality | Duplicates, missing fields, retrieval difficulty | What goes wrong most often? |
| A4 Analysis and reporting | How trends and peak times are found today | Which reports do you produce, how often, how long do they take? |
| A5 Needs | Features, roles, security, reports | What would make a system trustworthy and usable? |

No item mentions maps, GIS, or GPS. If a participant raises maps, record it as a theme and note it as future work.

**C. Requirements questionnaire**

Items are statements rated 1 to 5 (strongly disagree to strongly agree), grouped by requirement area (reporting, data quality, analytics, dashboards, reports, security, usability), plus one ranking question (order the features by priority). Include reverse-worded items sparingly. State the analysis: frequencies and medians per item; features ranked by mean rank.

**E. Usability questionnaire**

The 10 standard SUS items (cite Brooke, 1996), unchanged, scored 0 to 100, plus two open comment boxes. Report mean, median, range, and n. Interpret against the commonly used benchmark of about 68 with a caution about small samples.

**D. Usability tasks** (GIS task removed, others kept and reordered)

1. Log in. 2. Report a new incident. 3. Correct a validation error the form shows. 4. Find a previous record using filters. 5. Review a suspected duplicate (analyst role). 6. Open the dashboard and answer two questions from the charts (for example which category is highest, which ward is flagged). 7. Generate and export a report. 8. Log out. Record success, time, errors, and observer notes per task.

## 5. Analysis plan (write into Chapter 3 and 5)

| Instrument | Steps |
|---|---|
| Interviews | Transcribe or write structured notes; code; theme table with quote support (anonymised); map each theme to a requirement ID |
| Observation | Tally per checklist item; note discrepancies with what interviewees said |
| Requirements questionnaire | Data cleaning rule for incomplete responses stated in advance; frequencies and medians; priority ranking; link to FR IDs |
| Usability | Task metrics table; SUS score; issues list with severity; changes made in response (prototype refinement stage) |

Keep a `docs/logs/FIELDWORK_LOG.md` entry for each activity: date, group, n, instrument, permission reference, where raw data is stored, any deviation.

## 6. Timeline

| Week | Activity |
|---|---|
| W1 | Draft instruments; request permissions; choose respondents |
| W2 to W3 | Interviews and observation; questionnaire C sent out |
| W3 | Requirements frozen from the evidence |
| W6 | Usability sessions D and E on the near-final build |
| W7 | Analysis and write-up of results |
