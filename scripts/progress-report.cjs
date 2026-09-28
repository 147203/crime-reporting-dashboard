// Builds docs/reports/Progress_Report.docx, the supervisor progress report (updated at the end of every phase).
// Run: node scripts/progress-report.cjs
const fs = require('fs');
const path = require('path');
const { Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell, WidthType, ShadingType, LevelFormat, Footer, PageNumber, BorderStyle } = require('docx');

const FONT = 'Times New Roman';
const body = (text, opts = {}) => new Paragraph({ alignment: AlignmentType.JUSTIFIED, spacing: { line: 360, after: 120 }, children: runs(text), ...opts });
function runs(text) {
    // **bold** segments
    return text.split(/(\*\*[^*]+\*\*)/).filter(Boolean).map(p => p.startsWith('**') ? new TextRun({ text: p.slice(2, -2), bold: true, font: FONT, size: 24 }) : new TextRun({ text: p, font: FONT, size: 24 }));
}
const bullet = (text) => new Paragraph({ numbering: { reference: 'bullets', level: 0 }, alignment: AlignmentType.JUSTIFIED, spacing: { line: 360, after: 60 }, children: runs(text) });
const h1 = (text) => new Paragraph({ heading: HeadingLevel.HEADING_1, keepNext: true, alignment: AlignmentType.CENTER, spacing: { before: 240, after: 160 }, children: [new TextRun({ text, bold: true, font: FONT, size: 28, color: '000000' })] });
const h2 = (text) => new Paragraph({ heading: HeadingLevel.HEADING_2, spacing: { before: 200, after: 100 }, children: [new TextRun({ text, bold: true, font: FONT, size: 24 })] });
const caption = (text) => new Paragraph({ keepNext: true, spacing: { before: 120, after: 60 }, children: [new TextRun({ text, bold: true, font: FONT, size: 22 })] });

const border = { style: BorderStyle.SINGLE, size: 4, color: '999999' };
const borders = { top: border, bottom: border, left: border, right: border };
function table(widths, rows) {
    const total = widths.reduce((a, b) => a + b, 0);
    return new Table({
        width: { size: total, type: WidthType.DXA },
        columnWidths: widths,
        rows: rows.map((cells, r) => new TableRow({
            tableHeader: r === 0,
            children: cells.map((c, i) => new TableCell({
                width: { size: widths[i], type: WidthType.DXA },
                borders,
                shading: r === 0 ? { type: ShadingType.CLEAR, fill: 'E7ECF4', color: 'auto' } : undefined,
                margins: { top: 60, bottom: 60, left: 100, right: 100 },
                children: [new Paragraph({ spacing: { line: 276 }, children: [new TextRun({ text: c, font: FONT, size: 20, bold: r === 0 })] })],
            })),
        })),
    });
}
const W = 9026; // A4 text width with 1-inch margins

const children = [
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 }, children: [new TextRun({ text: 'PROGRESS REPORT', bold: true, font: FONT, size: 32 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 240 }, children: [new TextRun({ text: 'Web-Based Crime Data Reporting, Analysis and Visualization Platform for Nairobi County', bold: true, font: FONT, size: 26 })] }),
    table([2600, W - 2600], [
        ['Item', 'Detail'],
        ['Student', 'Mathai, Margaret Elsie Wairimu, BBIT 4D, admission number 147203'],
        ['Supervisor', 'Mr Nicodemus Maingi'],
        ['Report date', '28 September 2026 (updated at the end of every phase)'],
        ['Period covered', 'From the start of implementation (24 September 2026) to date'],
        ['Method', 'Object-oriented analysis and design (OOAD), prototyping development'],
    ]),

    h1('1. Summary'),
    body('Since my proposal defence I have set up the project, designed the database, and built and tested a working prototype of the platform. Following the panel decision, the system has **no GIS component**: there are no maps, coordinates or spatial queries. Location is recorded as sub-county, ward and a free-text landmark, and a "hotspot" is a statistical flag on a ward, shown in tables and charts.'),
    body('The prototype now covers secure sign-in with three roles, incident reporting with validation, bulk CSV import, duplicate detection and review, a data-quality summary, the ten descriptive analytics measures (A1 to A10) on a dashboard, and CSV and PDF reports. It is covered by 284 automated tests and 16 browser tests, all passing. The main work still outstanding is the **corrected proposal**, the **fieldwork** (interviews, questionnaire and usability sessions with real participants), and the **writing of Chapters 4 to 6**.'),

    h1('2. How I am working'),
    body('I follow the prototyping approach in my proposal: build a thin working version, test it, review it and refine it, in planned phases, each ending with a checkpoint (a "gate") that must pass before the next phase starts. Every design decision is recorded with its reason in a decision log, every test run is recorded in a test log, and I keep plain-language notes on each phase so that I can explain every part of the system at my defence.'),
    body('**Use of AI assistance.** I used an AI coding assistant (Claude Code) to help write the program code, the automated tests and the technical reviews, working under my direction: I set the scope and made the design decisions recorded in the decision log. I keep a private log of where the assistant was used so that I can disclose it as the university policy requires; I would like your guidance on what that disclosure should look like (open question Q3). All thesis text, including this report\'s interpretations and the future chapters, is my own.'),

    h1('3. Progress by stage'),
    caption('Table 1. Stages of the project and their status'),
    table([1500, 1600, 4426, 1500], [
        ['Stage', 'Planned dates', 'What was done', 'Status'],
        ['0. Setup', '24 to 27 Sep', 'Development environment, private GitHub repository under my own account, Laravel project, start-up checks that stop the system running without its required secrets, code-quality tools, first tests.', 'Done'],
        ['1. Proposal correction', '24 Sep to 4 Oct', 'Every correction needed (marker comments plus GIS removal) is listed in a tracked checklist of 46 items, in priority order. The rewriting itself has not started yet.', 'Pending'],
        ['2. Requirements and design', '28 Sep to 11 Oct', 'Database design (9 tables with integrity rules), reference data for 17 sub-counties and 85 wards, and design diagrams: use case, class, entity relationship, sequence, activity, architecture, and a redrawn conceptual framework without GIS. Fieldwork has not started.', 'Design done; fieldwork pending'],
        ['3. Prototype 1: core system', '28 Sep to 11 Oct', 'Sign-in with lockout after five wrong passwords, forced password change on first login, three roles (Administrator, Reporting Officer limited to own station, Analyst), incident form, list, detail and status workflow, user and reference-data administration, audit log, responsive interface with light and dark themes.', 'Done'],
        ['4. Prototype 2: data quality', '5 to 18 Oct', 'Validation rules V1 to V8, a check that refuses phone and ID numbers in free text, duplicate detection with a review screen, CSV import with a check-first step, a data-quality summary, and a sample-data generator that plants known errors, duplicates and hotspots.', 'Done'],
        ['5. Prototype 3: analytics and reports', '12 to 25 Oct', 'Analytics A1 to A10 computed in the database, dashboard with charts and matching tables, statistical hotspot table, sub-county by month grid, CSV and PDF exports.', 'Done'],
        ['6. Testing and evaluation', '26 Oct to 8 Nov', 'Security, performance, accuracy and browser tests done (results in Section 4). Usability sessions with real participants not yet held; the session kit is drafted.', 'Automated tests done; usability pending'],
        ['7. Documentation', 'Continuous; lock 9 to 15 Nov', 'Logs and evidence kept as I go. Chapters 4 to 6 not yet written.', 'Pending'],
        ['8. Demonstration', '16 to 22 Nov', 'Not started.', 'Pending'],
    ]),

    h1('4. Key results so far'),
    body('All results come from synthetic sample data, clearly labelled as such in the system; no real police records or personal data are used.', { keepNext: true }),
    caption('Table 2. Test and evaluation results'),
    table([3400, 3326, 2300], [
        ['Measure', 'Result', 'Target'],
        ['Automated tests', '284 tests and 16 browser tests, all passing', 'All pass'],
        ['Accuracy of analytics A1 to A10', '5,387 of 5,387 values equal to an independent calculation written separately from the system', '100 percent match'],
        ['Access control', 'Every page tested for a guest, an administrator, an officer and an analyst; an officer cannot open another station\'s records', 'All correct'],
        ['Bad records rejected', '100 percent for every rule, at 1,000, 20,000 and 100,000 records; no valid record rejected', 'At least 95 percent'],
        ['Duplicate detection (precision / recall)', '1.000 / 1.000 at 1,000 records; 0.943 / 0.915 at 20,000; 0.775 / 0.920 at 100,000', 'At least 0.90 each'],
        ['Planted hotspots found', 'All 3 planted hotspot wards flagged, no false flags (100,000 records)', 'Report'],
        ['Dashboard speed (100,000 records)', '95 percent of requests within 0.69 s (was 3.5 s before I added a database index)', 'Within 2 s'],
        ['Import speed', '10,000 records in 34 seconds', 'Within 60 s'],
        ['Independent security review', 'No critical or high issues; all issues found were fixed and retested', 'No open critical or high'],
    ]),
    body('One target was missed: duplicate precision drops to 0.775 at 100,000 records, because many different incidents of the same type in the same ward within two hours share similar landmark text in the sample data. I have kept the target unchanged and recorded two options (keep the rule and report the limitation, or also compare house or plot numbers); I would value your view on which to take.'),

    h1('5. Changes from the proposal'),
    bullet('**GIS removed** as directed by the panel: location is sub-county, ward and landmark; hotspots are statistical flags shown in tables and charts, and maps are listed as future work.'),
    bullet('**Framework and database:** PHP with the Laravel framework and a MySQL-compatible database (MariaDB, supplied with XAMPP) on my local machine. The proposal names PHP and MySQL; I will add a note explaining that MariaDB is MySQL-compatible.'),
    bullet('**Roles:** three roles (Administrator, Reporting Officer, Analyst) instead of two, so that access control can be demonstrated and tested.'),
    bullet('**Accuracy evaluation:** accuracy is measured against planted ground truth in synthetic data rather than asserted.'),

    h1('6. Items I need your guidance on'),
    bullet('**Final title** (three options prepared, all without GIS) and the abstract word range (180 to 210 or 200 to 250).'),
    bullet('**Resubmission** of the corrected proposal: deadline, format and to whom.'),
    bullet('**Fieldwork:** whether ethics clearance or a research permit is needed, and which participant groups are acceptable (security or ICT staff, analysts, students).'),
    bullet('**AI-use disclosure:** what the university requires.'),
    bullet('**Demonstration and final submission dates.**'),
    bullet('**Duplicate rule** option described in Section 4.'),

    h1('7. Next steps'),
    caption('Table 3. Planned work for the coming weeks'),
    table([2200, 6826], [
        ['When', 'What I will do'],
        ['This week (28 Sep to 4 Oct)', 'Rewrite the title, aim and objectives first, then the rest of Chapter 1, and send the corrected proposal; ask for fieldwork permission and confirm participants; revise the interview guide and questionnaire so each has one type of data.'],
        ['5 to 11 Oct', 'Complete Chapters 2 and 3 corrections; start interviews and the requirements questionnaire; freeze the requirements; show you the prototype screens as the interface design.'],
        ['12 Oct to 1 Nov', 'Analyse fieldwork results and apply any changes they call for; verify the ward list against the official IEBC list; run usability sessions with 5 to 8 real participants.'],
        ['2 to 15 Nov', 'Write Chapters 4 to 6 from the logged evidence; fix references; replace the Gantt chart.'],
        ['16 to 22 Nov', 'Rehearse the demonstration from a fresh installation and record a backup video.'],
    ]),
    body('I would be grateful for a short meeting this week to agree the title, the resubmission process and the fieldwork permissions, since these decide the order of the remaining work.'),

    h1('8. Report history'),
    caption('Table 4. Versions of this report'),
    table([2200, 6826], [
        ['Date', 'Change'],
        ['28 September 2026', 'First report: stages 0, 2 (design), 3, 4, 5 and 6 (automated testing) complete; proposal correction, fieldwork, usability, chapters and demonstration pending.'],
    ]),
];

const doc = new Document({
    styles: { default: { document: { run: { font: FONT, size: 24 } } } },
    numbering: { config: [{ reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720, hanging: 360 } } } }] }] },
    sections: [{
        properties: { page: { margin: { top: 1440, bottom: 1440, left: 1440, right: 1440 } } },
        footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ children: [PageNumber.CURRENT], font: FONT, size: 20 })] })] }) },
        children,
    }],
});

Packer.toBuffer(doc).then((buf) => fs.writeFileSync(path.join(__dirname, '..', 'docs', 'reports', 'Progress_Report.docx'), buf)).then(() => console.log('written'));
