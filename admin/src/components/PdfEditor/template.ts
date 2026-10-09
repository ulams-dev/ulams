/**
 * pdfme template helpers for the PDF designer. Kept free of @pdfme imports so
 * they stay cheap to load and easy to test.
 *
 * Template variables (e.g. "@VarUserName") are used as field names: the API
 * fills a field named after a variable with its value when the PDF is issued.
 * Read-only text may also contain variables ("Issued by @VarAppName").
 */

export type PdfmeField = {
  name: string;
  type: string;
  content?: string;
  position: { x: number; y: number };
  width: number;
  height: number;
  readOnly?: boolean;
  [key: string]: unknown;
};

export type PdfmeTemplate = {
  basePdf: { width: number; height: number; padding: [number, number, number, number] } | string;
  schemas: PdfmeField[][];
  [key: string]: unknown;
};

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

export const isPdfmeTemplate = (value: unknown): value is PdfmeTemplate =>
  isObject(value) && Array.isArray(value.schemas) && 'basePdf' in value;

/** Templates made with ReportBro, the designer used before pdfme. */
export const isReportBroTemplate = (value: unknown): boolean =>
  isObject(value) && Array.isArray(value.docElements) && isObject(value.documentProperties);

export const parseTemplate = (json?: string | null): unknown => {
  if (!json) {
    return undefined;
  }
  try {
    return JSON.parse(json);
  } catch {
    return undefined;
  }
};

export const BLANK_A4: PdfmeTemplate = {
  basePdf: { width: 210, height: 297, padding: [0, 0, 0, 0] },
  schemas: [[]],
};

const variableKey = (name: string) => name.replace(/^\$\{(.+)\}$/, '$1').replace(/^@/, '');

/** Variables (as "@VarName") that appear as field names or inside read-only text. */
export const usedVariables = (template: PdfmeTemplate, variables: string[]): string[] => {
  const fields = template.schemas.flat();
  const names = new Set(fields.map((field) => variableKey(field.name)));
  const texts = fields
    .filter((field) => field.readOnly && typeof field.content === 'string')
    .map((field) => field.content as string)
    .join('\n');
  return variables.filter((variable) => {
    const key = variableKey(variable);
    return names.has(key) || texts.includes(`@${key}`) || texts.includes(`\${${key}}`);
  });
};

/** A field type that suits the variable: QR code for URLs, text otherwise. */
export const fieldTypeFor = (variable: string): 'qrcode' | 'text' =>
  /url$/i.test(variable) ? 'qrcode' : 'text';

const SAMPLE_VALUES: Record<string, string> = {
  VarUserName: 'Małgorzata Wiśniewska',
  VarCourseTitle: 'Bezpieczeństwo pracy — szkolenie okresowe',
  VarToday: '08.10.2026',
  VarAppName: 'Ulams',
  VarCertificateId: '0b7f6f0e-3c1a-4d2e-9f51-6a2b8c7d9e10',
  VarCertificateVerifyUrl: 'https://example.com/certificates/verify/0b7f6f0e',
};

/** Sample shown in the designer; the real value is filled in when the PDF is issued. */
export const sampleValue = (variable: string): string =>
  SAMPLE_VALUES[variableKey(variable)] ?? variableKey(variable);

/**
 * Adds a field named after the variable to the given page, below the existing
 * fields (or at the top). Returns the template unchanged when the variable
 * already has a field.
 */
export const addVariableField = (
  template: PdfmeTemplate,
  variable: string,
  page = 0,
): PdfmeTemplate => {
  const schemas = template.schemas.map((fields) => [...fields]);
  if (schemas.flat().some((field) => variableKey(field.name) === variableKey(variable))) {
    return template;
  }
  while (schemas.length <= page) {
    schemas.push([]);
  }
  const pageWidth = typeof template.basePdf === 'string' ? 210 : template.basePdf.width;
  const pageHeight = typeof template.basePdf === 'string' ? 297 : template.basePdf.height;
  const bottom = schemas[page].reduce(
    (max, field) => Math.max(max, field.position.y + field.height),
    10,
  );
  const type = fieldTypeFor(variable);
  const width = type === 'qrcode' ? 28 : Math.min(120, pageWidth - 20);
  const height = type === 'qrcode' ? 28 : 10;
  const y = Math.min(bottom + 4, Math.max(pageHeight - height - 10, 0));

  const field: PdfmeField =
    type === 'qrcode'
      ? {
          name: variable,
          type,
          content: sampleValue(variable),
          position: { x: 10, y },
          width,
          height,
          rotate: 0,
          opacity: 1,
          backgroundColor: '#ffffff',
          barColor: '#000000',
        }
      : {
          name: variable,
          type,
          content: sampleValue(variable),
          position: { x: 10, y },
          width,
          height,
          rotate: 0,
          alignment: 'left',
          verticalAlignment: 'middle',
          fontSize: 14,
          lineHeight: 1,
          characterSpacing: 0,
          fontColor: '#000000',
          fontName: 'NotoSans-Regular',
          backgroundColor: '',
          opacity: 1,
        };
  schemas[page].push(field);
  return { ...template, schemas };
};
