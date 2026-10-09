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
  text,
} from '@pdfme/schemas';

/**
 * Field types offered by the designer. Keep in sync with the renderer
 * (api/pdf/src/plugins.ts): it refuses types it does not know.
 */
export const plugins = {
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
  DataMatrix: barcodes.gs1datamatrix,
} as unknown as Plugins;
