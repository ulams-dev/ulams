import { checkTemplate, type Font, type Schema, type Template } from '@pdfme/common';
import { generate } from '@pdfme/generator';
import { FALLBACK_FONT, type FontStore } from './fonts.js';
import { barcodeTypes, imageTypes, plugins, supportedTypes } from './plugins.js';

export class RenderError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly details?: unknown
    ) {
        super(message);
    }
}

export interface RenderLimits {
    maxInputs: number;
    maxPages: number;
    maxFieldsPerPage: number;
    renderTimeoutMs: number;
}

export interface RenderRequest {
    template: unknown;
    inputs?: unknown;
}

export type Inputs = Record<string, string>[];

const isObject = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const PDF_DATA_URI = /^data:application\/pdf;base64,/;
const IMAGE_DATA_URI = /^data:image\/(png|jpe?g|svg\+xml);base64,/;

const pagesOf = (template: Record<string, unknown>): Schema[][] => {
    if (!Array.isArray(template.schemas)) {
        throw new RenderError(400, 'invalid_template', 'template.schemas must be an array of pages');
    }
    return template.schemas.map((page, index) => {
        if (!Array.isArray(page)) {
            throw new RenderError(400, 'invalid_template', `template.schemas[${index}] must be an array of fields`);
        }
        return page as Schema[];
    });
};

/**
 * Validates the template and returns a copy that is safe to render: only the
 * bundled fonts, no remote base PDF or images (no outbound requests from the
 * service), known field types only.
 */
export const sanitizeTemplate = (
    raw: unknown,
    fonts: FontStore,
    limits: RenderLimits,
    warnings: string[]
): Template => {
    if (!isObject(raw)) {
        throw new RenderError(400, 'invalid_template', 'template must be a pdfme template object');
    }
    const template = structuredClone(raw);

    const basePdf = template.basePdf;
    if (typeof basePdf === 'string') {
        if (!PDF_DATA_URI.test(basePdf)) {
            throw new RenderError(400, 'invalid_template', 'basePdf must be a blank page definition or a base64 PDF data URI');
        }
    } else if (!isObject(basePdf)) {
        throw new RenderError(400, 'invalid_template', 'template.basePdf is required');
    }

    const pages = pagesOf(template);
    if (pages.length === 0) {
        throw new RenderError(400, 'invalid_template', 'template has no pages');
    }
    if (pages.length > limits.maxPages) {
        throw new RenderError(413, 'too_many_pages', `template has ${pages.length} pages, the limit is ${limits.maxPages}`);
    }

    for (const page of pages) {
        if (page.length > limits.maxFieldsPerPage) {
            throw new RenderError(413, 'too_many_fields', `a page has ${page.length} fields, the limit is ${limits.maxFieldsPerPage}`);
        }
        for (const schema of page) {
            if (!isObject(schema) || typeof schema.name !== 'string' || typeof schema.type !== 'string') {
                throw new RenderError(400, 'invalid_template', 'every field needs a string name and type');
            }
            if (!supportedTypes.has(schema.type)) {
                throw new RenderError(422, 'unsupported_field_type', `field "${schema.name}" has unsupported type "${schema.type}"`);
            }
            const fontName = (schema as Record<string, unknown>).fontName;
            if (typeof fontName === 'string' && !fonts.has(fontName)) {
                warnings.push(`unknown font "${fontName}" in field "${schema.name}", using ${FALLBACK_FONT}`);
                (schema as Record<string, unknown>).fontName = FALLBACK_FONT;
            }
            if (imageTypes.has(schema.type) && typeof schema.content === 'string' && schema.content !== '' && !IMAGE_DATA_URI.test(schema.content)) {
                throw new RenderError(422, 'remote_image', `field "${schema.name}" must embed its image as a data URI`);
            }
        }
    }

    try {
        checkTemplate(template);
    } catch (error) {
        throw new RenderError(400, 'invalid_template', 'template does not match the pdfme schema', String((error as Error)?.message ?? error));
    }
    return template as Template;
};

const stringify = (value: unknown): string => {
    if (value === null || value === undefined) {
        return '';
    }
    if (typeof value === 'string') {
        return value;
    }
    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }
    // tables and multi-variable text take JSON strings
    return JSON.stringify(value);
};

/**
 * Turns the request inputs into pdfme inputs: every editable field gets a
 * string (missing values become ''), unknown keys are dropped. Missing values
 * for fields marked `required` are reported with 422 instead of a pdfme error.
 */
export const normalizeInputs = (template: Template, raw: unknown, limits: RenderLimits, warnings: string[]): Inputs => {
    const list = raw === undefined ? [{}] : raw;
    if (!Array.isArray(list) || list.length === 0 || !list.every(isObject)) {
        throw new RenderError(400, 'invalid_inputs', 'inputs must be a non-empty array of objects');
    }
    if (list.length > limits.maxInputs) {
        throw new RenderError(413, 'too_many_inputs', `${list.length} inputs, the limit is ${limits.maxInputs}`);
    }

    const fields = (template.schemas as Schema[][]).flat().filter((schema) => !schema.readOnly);
    const missingRequired = new Set<string>();
    const missingOptional = new Set<string>();

    const inputs = list.map((input) => {
        const normalized: Record<string, string> = {};
        for (const field of fields) {
            const value = stringify(input[field.name]);
            if (value === '') {
                (field.required ? missingRequired : missingOptional).add(field.name);
            }
            if (imageTypes.has(field.type) && value !== '' && !IMAGE_DATA_URI.test(value)) {
                throw new RenderError(422, 'remote_image', `input for "${field.name}" must be an image data URI`);
            }
            normalized[field.name] = value;
        }
        return normalized;
    });

    if (missingRequired.size > 0) {
        throw new RenderError(422, 'missing_required_fields', 'required fields have no value', [...missingRequired]);
    }
    if (missingOptional.size > 0) {
        warnings.push(`empty fields: ${[...missingOptional].join(', ')}`);
    }
    return inputs;
};

/**
 * Fields with an empty value that pdfme cannot draw (barcodes) are removed
 * from the template so a missing verification URL does not fail the render.
 */
const dropEmptyBarcodes = (template: Template, inputs: Inputs): Template => {
    const emptyEverywhere = (name: string) => inputs.every((input) => input[name] === '');
    return {
        ...template,
        schemas: (template.schemas as Schema[][]).map((page) =>
            page.filter((schema) => !(barcodeTypes.has(schema.type) && !schema.readOnly && emptyEverywhere(schema.name)))
        )
    } as Template;
};

/** Validated, normalized job ready for a render worker. */
export interface RenderJob {
    template: Template;
    inputs: Inputs;
    warnings: string[];
}

export const prepare = (request: RenderRequest, fonts: FontStore, limits: RenderLimits): RenderJob => {
    const warnings: string[] = [];
    const template = sanitizeTemplate(request.template, fonts, limits, warnings);
    const inputs = normalizeInputs(template, request.inputs, limits, warnings);
    return { template: dropEmptyBarcodes(template, inputs), inputs, warnings };
};

/** Runs pdfme. Called inside a render worker (see worker.ts). */
export const generatePdf = (template: Template, inputs: Inputs, font: Font): Promise<Uint8Array> =>
    generate({
        template,
        inputs,
        plugins,
        options: { font, creator: 'Ulams', producer: 'Ulams PDF (pdfme)' }
    });
