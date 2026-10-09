/**
 * admin/scripts/node-compat.cjs: Node 24 removed process.binding('http_parser'), which umi/max
 * reaches through spdy -> http-deceiver while loading (ADR 0037).
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';

const shim = path.resolve(__dirname, '../../scripts/node-compat.cjs');

const run = (code: string) =>
  spawnSync(process.execPath, ['--no-deprecation', '-e', code], { encoding: 'utf8' });

describe('node-compat shim', () => {
  it('hands out an inert http_parser when the real binding is gone', () => {
    const result = run(`
      const real = process.binding;
      process.binding = function (name) { if (name === 'http_parser') throw new Error('No such module: http_parser'); return real.call(this, name); };
      require(${JSON.stringify(shim)});
      const b = process.binding('http_parser');
      console.log(JSON.stringify({ methods: b.HTTPParser.methods.includes('GET'), k: b.HTTPParser.kOnBody }));
    `);
    expect(result.status).toBe(0);
    expect(JSON.parse(result.stdout)).toEqual({ methods: true, k: 2 });
  });

  it('rethrows other missing bindings', () => {
    const result = run(`
      require(${JSON.stringify(shim)});
      try { process.binding('definitely_not_a_binding'); } catch (e) { console.log('threw'); }
    `);
    expect(result.stdout.trim()).toBe('threw');
  });

  it('lets http-deceiver-style code load', () => {
    const result = run(`
      const real = process.binding;
      process.binding = function (name) { if (name === 'http_parser') throw new Error('No such module: http_parser'); return real.call(this, name); };
      require(${JSON.stringify(shim)});
      const { HTTPParser } = process.binding('http_parser');
      HTTPParser.methods.forEach(function () {});
      console.log('loaded');
    `);
    expect(result.stdout.trim()).toBe('loaded');
  });
});
