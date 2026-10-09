import type { Plugins } from '@pdfme/common';
import {
    barcodes,
    ellipse,
    image,
    line,
    multiVariableText,
    rectangle,
    svg,
    table,
    text
} from '@pdfme/schemas';

/**
 * Schema plugins the renderer supports. Keep in sync with the admin designer
 * (admin/src/components/PdfEditor/plugins.ts): a field type the designer
 * offers but the renderer does not know is rejected with 422.
 */
export const plugins: Plugins = {
    Text: text,
    'Multi-Variable Text': multiVariableText,
    Image: image,
    SVG: svg,
    Line: line,
    Rectangle: rectangle,
    Ellipse: ellipse,
    Table: table,
    QR: barcodes.qrcode,
    Code128: barcodes.code128,
    Code39: barcodes.code39,
    EAN13: barcodes.ean13,
    EAN8: barcodes.ean8,
    PDF417: barcodes.pdf417,
    DataMatrix: barcodes.gs1datamatrix
} as Plugins;

/** Schema `type` values handled by the plugins above. */
export const supportedTypes = new Set(
    Object.values(plugins).map((plugin) => String(plugin.propPanel.defaultSchema.type))
);

/** Types whose value is an image (data URI only; remote URLs are refused). */
export const imageTypes = new Set(['image', 'svg', 'signature']);

/** Barcode types (an empty value renders nothing instead of failing). */
export const barcodeTypes = new Set(
    Object.values(barcodes).map((plugin) => String(plugin.propPanel.defaultSchema.type))
);
