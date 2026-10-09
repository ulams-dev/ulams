/**
 * Node 24 compatibility for umi/max (ADR 0037).
 *
 * `@umijs/bundler-utils` requires `spdy` at load time, `spdy-transport` requires `http-deceiver`,
 * and `http-deceiver` reads `process.binding('http_parser')` while it is being loaded. Node 24
 * removed that binding ("No such module: http_parser"), so every `max` command, including the
 * `max setup` postinstall, died before doing anything.
 *
 * Only the HTTP/2 dev server (`https: { http2: true }`, not used by the admin) ever calls the
 * deceiver. This shim hands `http-deceiver` an inert parser when the real binding is gone, so the
 * module loads. On Node 22 the binding exists and nothing changes.
 *
 * Loaded through `scripts/max.cjs`, which also passes it to child processes via NODE_OPTIONS.
 */
const original = process.binding;

if (typeof original === 'function') {
  process.binding = function binding(name) {
    try {
      return original.call(this, name);
    } catch (error) {
      if (name !== 'http_parser') throw error;
      function HTTPParser() {}
      HTTPParser.methods = require('node:http').METHODS.slice();
      HTTPParser.kOnHeaders = 0;
      HTTPParser.kOnHeadersComplete = 1;
      HTTPParser.kOnBody = 2;
      HTTPParser.kOnMessageComplete = 3;
      return { HTTPParser, methods: HTTPParser.methods };
    }
  };
}
