// Builds the pdfme certificate templates shipped with the templates-pdf package:
//   node api/pdf/scripts/certificate-templates.mjs
// writes api/packages/templates-pdf/resources/pdfme/certificate-*.json.
//
// Field names are the template variables (e.g. "@VarUserName"); Laravel fills
// them from the CourseFinished event. Read-only text may also contain variables
// ("Issued by @VarAppName"), which Laravel replaces before rendering.
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const out = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../packages/templates-pdf/resources/pdfme');

const W = 297;
const H = 210;

const text = (name, content, x, y, width, height, style = {}, readOnly = false) => ({
    name,
    type: 'text',
    content,
    position: { x, y },
    width,
    height,
    rotate: 0,
    alignment: 'center',
    verticalAlignment: 'middle',
    fontSize: 12,
    lineHeight: 1,
    characterSpacing: 0,
    fontColor: '#000000',
    backgroundColor: '',
    opacity: 1,
    strikethrough: false,
    underline: false,
    ...(readOnly ? { readOnly: true } : {}),
    ...style
});

const rect = (name, x, y, width, height, { color = '', borderColor = '#000000', borderWidth = 0, radius = 0 } = {}) => ({
    name,
    type: 'rectangle',
    position: { x, y },
    width,
    height,
    rotate: 0,
    opacity: 1,
    borderWidth,
    borderColor,
    color,
    radius,
    readOnly: true
});

const line = (name, x, y, width, color, height = 0.4) => ({
    name,
    type: 'line',
    position: { x, y },
    width,
    height,
    rotate: 0,
    opacity: 1,
    color,
    readOnly: true
});

const qr = (name, x, y, size, barColor, backgroundColor) => ({
    name,
    type: 'qrcode',
    content: 'https://example.com/certificates/verify/0b7f6f0e-sample',
    position: { x, y },
    width: size,
    height: size,
    rotate: 0,
    opacity: 1,
    barColor,
    backgroundColor
});

const certificate = (theme) => {
    const t = theme;
    const fields = [];
    if (t.background) {
        fields.push(rect('background', 0, 0, W, H, { color: t.background }));
    }
    fields.push(rect('frame', 10, 10, W - 20, H - 20, { borderColor: t.accent, borderWidth: 0.8 }));
    fields.push(rect('frameInner', 13, 13, W - 26, H - 26, { borderColor: t.rule, borderWidth: 0.3 }));
    fields.push(line('accentBar', W / 2 - 20, 49, 40, t.accent, 1.2));

    fields.push(text('title', t.title, 30, 26, W - 60, 18, { fontName: t.display, fontSize: t.titleSize ?? 30, characterSpacing: 1.5, fontColor: t.ink }, true));
    fields.push(text('intro', t.intro, 40, 58, W - 80, 9, { fontName: t.body, fontSize: 13, fontColor: t.muted }, true));
    fields.push(text('@VarUserName', 'Małgorzata Wiśniewska', 30, 69, W - 60, 18, {
        fontName: t.display, fontSize: 32, fontColor: t.name ?? t.accent,
        dynamicFontSize: { min: 18, max: 32, fit: 'horizontal' }
    }));
    fields.push(text('completed', t.completed, 40, 92, W - 80, 9, { fontName: t.body, fontSize: 13, fontColor: t.muted }, true));
    fields.push(text('@VarCourseTitle', 'Bezpieczeństwo pracy — szkolenie okresowe', 35, 103, W - 70, 22, {
        fontName: t.display, fontSize: 22, lineHeight: 1.1, fontColor: t.ink,
        dynamicFontSize: { min: 12, max: 22, fit: 'vertical' }
    }));

    // bottom row: date | signature | verification QR
    fields.push(text('dateLabel', t.dateLabel, 30, 150, 75, 6, { fontName: t.body, fontSize: 9, fontColor: t.muted, alignment: 'left' }, true));
    fields.push(text('@VarToday', '08.10.2026', 30, 157, 75, 8, { fontName: t.body, fontSize: 14, fontColor: t.ink, alignment: 'left' }));
    fields.push(text('idLabel', t.idLabel, 30, 170, 120, 5, { fontName: t.body, fontSize: 8, fontColor: t.muted, alignment: 'left' }, true));
    fields.push(text('@VarCertificateId', '0b7f6f0e-3c1a-4d2e-9f51-6a2b8c7d9e10', 30, 175.5, 120, 5, { fontName: t.mono ?? t.body, fontSize: 8, fontColor: t.ink, alignment: 'left' }));

    fields.push(line('signatureLine', W / 2 - 35, 163, 70, t.ink, 0.3));
    fields.push(text('signatureLabel', t.signatureLabel, W / 2 - 35, 165, 70, 6, { fontName: t.body, fontSize: 9, fontColor: t.muted }, true));
    fields.push(text('@VarAppName', 'Ulams', W / 2 - 35, 171, 70, 7, { fontName: t.body, fontSize: 11, fontColor: t.ink }));

    fields.push(qr('@VarCertificateVerifyUrl', W - 30 - 28, 146, 28, t.qrBar, t.qrBackground));
    fields.push(text('verifyLabel', t.verifyLabel, W - 30 - 40, 175.5, 52, 5, { fontName: t.body, fontSize: 7, fontColor: t.muted, alignment: 'right' }, true));

    return {
        basePdf: { width: W, height: H, padding: [0, 0, 0, 0] },
        schemas: [fields],
        pdfmeVersion: '6.2.4'
    };
};

const themes = {
    default: {
        title: 'CERTIFICATE OF COMPLETION', intro: 'This certifies that', completed: 'has successfully completed the course',
        dateLabel: 'Date of completion', idLabel: 'Certificate ID', signatureLabel: 'Signature', verifyLabel: 'Scan to verify',
        display: 'PlusJakartaSans-Bold', body: 'NotoSans-Regular', mono: 'NotoSans-Regular',
        background: null, ink: '#1F2937', muted: '#6B7280', accent: '#2F5D8A', rule: '#CBD5E1', name: '#1F2937',
        qrBar: '#1F2937', qrBackground: '#FFFFFF'
    },
    // The Coffee Atlas: editorial magazine (paper, ink, terracotta accent, Playfair Display)
    coffee: {
        title: 'Certificate of Completion', titleSize: 32, intro: 'The Coffee Atlas certifies that', completed: 'has travelled from seed to cup and completed',
        dateLabel: 'Completed on', idLabel: 'Certificate no.', signatureLabel: 'Head of the Atlas', verifyLabel: 'Scan to verify',
        display: 'PlayfairDisplay-Bold', body: 'PlusJakartaSans-Regular',
        background: '#F6F1E9', ink: '#2B1D14', muted: '#7A8B6F', accent: '#C2552D', rule: '#D9CFC2', name: '#C2552D',
        qrBar: '#2B1D14', qrBackground: '#F6F1E9'
    },
    // On-Call: dark incident tool (Space Grotesk, JetBrains Mono, blue/green on near-black)
    oncall: {
        title: 'INCIDENT COMMAND // CERTIFIED', titleSize: 26, intro: '$ certify --responder', completed: 'completed the cohort and the live game-day',
        dateLabel: 'resolved_at', idLabel: 'certificate_id', signatureLabel: 'incident commander', verifyLabel: 'scan to verify',
        display: 'SpaceGrotesk-Bold', body: 'JetBrainsMono-Regular', mono: 'JetBrainsMono-Regular',
        background: '#0B0F14', ink: '#E6EDF3', muted: '#8B98A5', accent: '#58A6FF', rule: '#1E2733', name: '#3FB950',
        qrBar: '#0B0F14', qrBackground: '#E6EDF3'
    },
    // Night Sky Explorers: playful kids' mission map (Baloo 2, Nunito, yellow/mint on night blue)
    nightsky: {
        title: 'Mission Complete!', titleSize: 34, intro: 'This Junior Astronomer badge goes to', completed: 'for finishing every mission of',
        dateLabel: 'Mission date', idLabel: 'Badge number', signatureLabel: 'Mission control', verifyLabel: 'Scan to check the badge',
        display: 'Baloo2-Bold', body: 'Nunito-Regular',
        background: '#13153A', ink: '#FFFFFF', muted: '#3DDC97', accent: '#FFD23F', rule: '#9B8CFF', name: '#FFD23F',
        qrBar: '#13153A', qrBackground: '#FFFFFF'
    },
    // Gravity Lab: dark space (Space Grotesk, JetBrains Mono, cyan and gold on near-black)
    gravity: {
        title: 'Escape Velocity Reached', titleSize: 30, intro: 'Gravity Lab certifies that', completed: 'has completed every module of',
        dateLabel: 'Completed on', idLabel: 'Certificate no.', signatureLabel: 'Mission control', verifyLabel: 'Scan to verify',
        display: 'SpaceGrotesk-Bold', body: 'JetBrainsMono-Regular', mono: 'JetBrainsMono-Regular',
        background: '#05070F', ink: '#E8ECF5', muted: '#8E9BB5', accent: '#3DD6F5', rule: '#1B2742', name: '#FFB547',
        qrBar: '#05070F', qrBackground: '#E8ECF5'
    },
    // Poland, Measured: cartographic paper (Playfair Display, Noto Sans, red on warm paper)
    poland: {
        title: 'Certificate of Completion', titleSize: 32, intro: 'Poland, Measured certifies that', completed: 'has read the map, checked the sources and completed',
        dateLabel: 'Completed on', idLabel: 'Certificate no.', signatureLabel: 'Cartographer', verifyLabel: 'Scan to verify',
        display: 'PlayfairDisplay-Bold', body: 'NotoSans-Regular',
        background: '#F4EFE6', ink: '#1B2A3A', muted: '#5E8C8A', accent: '#C8102E', rule: '#D6CDBB', name: '#C8102E',
        qrBar: '#1B2A3A', qrBackground: '#F4EFE6'
    },
    // The Scottish Book: archival notebook (Playfair Display, JetBrains Mono, blue ink with a red margin)
    ulam: {
        title: 'Problem Solved', titleSize: 34, intro: 'The Scottish Book records that', completed: 'has worked through the notebook of',
        dateLabel: 'Solved on', idLabel: 'Entry no.', signatureLabel: 'Keeper of the book', verifyLabel: 'Scan to verify',
        display: 'PlayfairDisplay-Bold', body: 'JetBrainsMono-Regular', mono: 'JetBrainsMono-Regular',
        background: '#F7F3E8', ink: '#1E2230', muted: '#6B5B4A', accent: '#1D3B8F', rule: '#D9D2BE', name: '#1D3B8F',
        qrBar: '#1E2230', qrBackground: '#F7F3E8'
    }
};

for (const [key, theme] of Object.entries(themes)) {
    const file = path.join(out, `certificate-${key}.json`);
    writeFileSync(file, JSON.stringify(certificate(theme), null, 2) + '\n');
    console.log('wrote', path.relative(process.cwd(), file));
}
