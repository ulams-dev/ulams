// Empty stand-in for Node built-ins (node:fs/promises, node:zlib, ...) that
// clawpdf, a dependency of the pdfme designer, imports lazily only under Node.
module.exports = {};
