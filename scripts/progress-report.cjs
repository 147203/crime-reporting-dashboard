// Builds docs/reports/Progress_Report.docx from docs/reports/PROGRESS_REPORT.md, the supervisor progress report
// (updated at the end of every phase). The Markdown file is the only source: edit it, then run
//   node scripts/progress-report.cjs
// Supported Markdown: "# " report title (first) and section headings, "## " subtitle (first) and subsections, "- "
// bullets, **bold**, tables, and "**Table N. ...**" captions. Word layout: Times New Roman 12, 1.5 spacing, justified.
const fs = require('fs');
const path = require('path');
const {
    AlignmentType, BorderStyle, Document, Footer, HeadingLevel, LevelFormat, Packer, PageNumber, Paragraph, ShadingType,
    Table, TableCell, TableRow, TextRun, WidthType,
} = require('docx');

const FONT = 'Times New Roman';
const TEXT_WIDTH = 9026; // A4 with 1-inch margins, in twentieths of a point
const source = path.join(__dirname, '..', 'docs', 'reports', 'PROGRESS_REPORT.md');
const target = path.join(__dirname, '..', 'docs', 'reports', 'Progress_Report.docx');

function runs(text, size = 24, forceBold = false) {
    return text.split(/(\*\*[^*]+\*\*)/).filter(Boolean).map((part) => {
        const bold = part.startsWith('**') && part.endsWith('**');
        return new TextRun({ text: bold ? part.slice(2, -2) : part, bold: bold || forceBold, font: FONT, size });
    });
}

const paragraph = (text) => new Paragraph({ alignment: AlignmentType.JUSTIFIED, spacing: { line: 360, after: 120 }, children: runs(text) });
const bullet = (text) => new Paragraph({ numbering: { reference: 'bullets', level: 0 }, alignment: AlignmentType.JUSTIFIED, spacing: { line: 360, after: 60 }, children: runs(text) });
const caption = (text) => new Paragraph({ keepNext: true, spacing: { before: 120, after: 60 }, children: runs(text.replace(/\*\*/g, ''), 22, true) });
const title = (text, size) => new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 160 }, children: [new TextRun({ text, bold: true, font: FONT, size })] });
const heading = (text) => new Paragraph({ heading: HeadingLevel.HEADING_1, keepNext: true, alignment: AlignmentType.CENTER, spacing: { before: 240, after: 160 }, children: [new TextRun({ text, bold: true, font: FONT, size: 28, color: '000000' })] });
const subheading = (text) => new Paragraph({ heading: HeadingLevel.HEADING_2, keepNext: true, spacing: { before: 200, after: 100 }, children: [new TextRun({ text, bold: true, font: FONT, size: 24, color: '000000' })] });

function table(rows) {
    const columns = rows[0].length;
    // Width in proportion to the longest text in each column, with a floor so short columns stay readable.
    const longestWord = (c) => Math.max(...rows.map((r) => Math.max(...(r[c] || '').replace(/\*\*/g, '').split(/\s+/).map((w) => w.length))));
    const weights = Array.from({ length: columns }, (_, c) => Math.max(14, longestWord(c) + 4, ...rows.map((r) => Math.min(45, (r[c] || '').length))));
    const sum = weights.reduce((a, b) => a + b, 0);
    const widths = weights.map((w) => Math.floor((TEXT_WIDTH * w) / sum));
    widths[columns - 1] += TEXT_WIDTH - widths.reduce((a, b) => a + b, 0);
    const line = { style: BorderStyle.SINGLE, size: 4, color: '999999' };

    return new Table({
        width: { size: TEXT_WIDTH, type: WidthType.DXA },
        columnWidths: widths,
        rows: rows.map((cells, r) => new TableRow({
            tableHeader: r === 0,
            children: cells.map((cell, c) => new TableCell({
                width: { size: widths[c], type: WidthType.DXA },
                borders: { top: line, bottom: line, left: line, right: line },
                shading: r === 0 ? { type: ShadingType.CLEAR, fill: 'E7ECF4', color: 'auto' } : undefined,
                margins: { top: 60, bottom: 60, left: 100, right: 100 },
                children: [new Paragraph({ spacing: { line: 276 }, children: runs(cell, 20, r === 0) })],
            })),
        })),
    });
}

function build(markdown) {
    const lines = markdown.split('\n');
    const children = [];
    let titleDone = false;
    let subtitleDone = false;
    let buffer = [];
    let tableRows = [];

    const flushParagraph = () => {
        if (buffer.length) {
            children.push(paragraph(buffer.join(' ')));
            buffer = [];
        }
    };
    const flushTable = () => {
        if (tableRows.length) {
            children.push(table(tableRows));
            children.push(new Paragraph({ spacing: { after: 120 }, children: [] }));
            tableRows = [];
        }
    };

    for (const raw of lines) {
        const line = raw.trimEnd();

        if (line.startsWith('|')) {
            flushParagraph();
            const cells = line.slice(1, -1).split('|').map((c) => c.trim());
            if (!cells.every((c) => /^-+$/.test(c))) {
                tableRows.push(cells);
            }
            continue;
        }
        flushTable();

        if (line === '') {
            flushParagraph();
        } else if (line.startsWith('# ')) {
            flushParagraph();
            children.push(titleDone ? heading(line.slice(2)) : title(line.slice(2).toUpperCase(), 32));
            titleDone = true;
        } else if (line.startsWith('## ')) {
            flushParagraph();
            children.push(subtitleDone ? subheading(line.slice(3)) : title(line.slice(3), 26));
            subtitleDone = true;
        } else if (line.startsWith('- ')) {
            flushParagraph();
            children.push(bullet(line.slice(2)));
        } else if (/^\*\*Table \d+\./.test(line)) {
            flushParagraph();
            children.push(caption(line));
        } else {
            buffer.push(line);
        }
    }
    flushParagraph();
    flushTable();

    return children;
}

const markdown = fs.readFileSync(source, 'utf8');
if (markdown.includes('—')) {
    throw new Error('The report contains an em dash; replace it with a comma, colon or hyphen.');
}

const doc = new Document({
    styles: { default: { document: { run: { font: FONT, size: 24 } } } },
    numbering: { config: [{ reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720, hanging: 360 } } } }] }] },
    sections: [{
        properties: { page: { margin: { top: 1440, bottom: 1440, left: 1440, right: 1440 } } },
        footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ children: [PageNumber.CURRENT], font: FONT, size: 20 })] })] }) },
        children: build(markdown),
    }],
});

Packer.toBuffer(doc).then((buffer) => {
    fs.writeFileSync(target, buffer);
    console.log(`Wrote ${path.relative(process.cwd(), target)}`);
});
