import { describe, expect, it } from '@jest/globals';
import {
  addVariableField,
  BLANK_A4,
  fieldTypeFor,
  isPdfmeTemplate,
  isReportBroTemplate,
  parseTemplate,
  usedVariables,
  type PdfmeTemplate,
} from './template';

const template = (): PdfmeTemplate => ({
  basePdf: { width: 297, height: 210, padding: [0, 0, 0, 0] },
  schemas: [
    [
      {
        name: '@VarUserName',
        type: 'text',
        content: 'x',
        position: { x: 0, y: 20 },
        width: 50,
        height: 10,
      },
      {
        name: 'footer',
        type: 'text',
        content: 'Issued by @VarAppName on ${VarToday}',
        position: { x: 0, y: 40 },
        width: 50,
        height: 10,
        readOnly: true,
      },
    ],
  ],
});

describe('PdfEditor template helpers', () => {
  it('tells pdfme templates from ReportBro reports', () => {
    expect(isPdfmeTemplate(template())).toBe(true);
    expect(isPdfmeTemplate(BLANK_A4)).toBe(true);
    expect(isReportBroTemplate({ docElements: [], documentProperties: {}, parameters: [] })).toBe(
      true,
    );
    expect(isPdfmeTemplate({ docElements: [], documentProperties: {} })).toBe(false);
    expect(parseTemplate('not json')).toBeUndefined();
    expect(parseTemplate(JSON.stringify(BLANK_A4))).toEqual(BLANK_A4);
  });

  it('finds variables used as field names and in read-only text', () => {
    expect(
      usedVariables(template(), ['@VarUserName', '@VarAppName', '@VarToday', '@VarCourseTitle']),
    ).toEqual(['@VarUserName', '@VarAppName', '@VarToday']);
  });

  it('adds a field named after the variable below the existing fields', () => {
    const updated = addVariableField(template(), '@VarCourseTitle');
    const field = updated.schemas[0][2];
    expect(field.name).toBe('@VarCourseTitle');
    expect(field.type).toBe('text');
    expect(field.position.y).toBe(54);
    // adding it again changes nothing
    expect(addVariableField(updated, '@VarCourseTitle')).toBe(updated);
    expect(addVariableField(updated, '@VarUserName')).toBe(updated);
  });

  it('uses a QR code for URL variables', () => {
    expect(fieldTypeFor('@VarCertificateVerifyUrl')).toBe('qrcode');
    const updated = addVariableField(BLANK_A4, '@VarCertificateVerifyUrl');
    expect(updated.schemas[0][0]).toMatchObject({ type: 'qrcode', width: 28, height: 28 });
  });
});
