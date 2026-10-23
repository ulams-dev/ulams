// Unit tests for the playback fixtures, the zip helper and the trace normaliser (MIT, written for ulams).
// Run: node --test api/packages/scorm/tests/fixtures/playback/playback.test.mjs (also part of `yarn workspace front test`).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, it } from 'node:test';
import { buildZip, packageFiles, packageNames, packagesDir } from './build.mjs';
import { firstDifference, matchGolden, normalise, normaliseString } from './trace.mjs';
import { readZip, writeZip } from './zip.mjs';

describe('fixture packages', () => {
  it('has the three playback packages', () => {
    assert.deepEqual(packageNames(), ['scorm12-mastery', 'scorm12-session', 'scorm2004-session']);
  });

  for (const name of packageNames()) {
    it(`${name}.zip is up to date (run build.mjs after editing a fixture)`, () => {
      assert.ok(readFileSync(join(packagesDir, `${name}.zip`)).equals(buildZip(name)));
    });

    it(`${name} manifest lists every file it ships and the SCO scripts it runs`, () => {
      const files = readZip(buildZip(name));
      const manifest = files['imsmanifest.xml'].toString('utf8');
      assert.match(manifest, /<resource [^>]*href="index\.html"/);
      for (const file of Object.keys(files).filter((f) => f !== 'imsmanifest.xml')) {
        assert.ok(manifest.includes(`<file href="${file}"`), `${file} missing from the manifest`);
      }
      assert.match(files['index.html'].toString('utf8'), /runner\.js[\s\S]*sco\.js/);
    });

    it(`${name} has no external URLs in its scripts and pages`, () => {
      for (const [file, data] of Object.entries(packageFiles(name))) {
        if (file === 'imsmanifest.xml') continue;
        assert.doesNotMatch(data.toString('utf8'), /https?:\/\//, file);
      }
    });
  }
});

describe('zip helper', () => {
  it('round-trips files and is deterministic', () => {
    const files = { 'b.txt': 'two', 'a.txt': 'one', 'dir/c.txt': Buffer.from('three') };
    const first = writeZip(files);
    assert.ok(first.equals(writeZip(files)));
    const back = readZip(first);
    assert.deepEqual(Object.keys(back), ['a.txt', 'b.txt', 'dir/c.txt']);
    assert.equal(back['dir/c.txt'].toString(), 'three');
  });
});

describe('trace normaliser', () => {
  it('replaces ISO dates and keeps other text', () => {
    assert.equal(normaliseString('2026-10-23T09:15:30.123Z'), '<date>');
    assert.equal(normaliseString('started 2026-10-23T09:15:30+02:00 ok'), 'started <date> ok');
    assert.equal(normaliseString('chapter-2/page-3'), 'chapter-2/page-3');
  });

  it('drops fractions of a second from durations', () => {
    assert.equal(normaliseString('PT1M30.55S'), 'PT1M30S');
    assert.equal(normaliseString('PT1M30S'), 'PT1M30S');
    assert.equal(normaliseString('0000:01:30.55'), '0000:01:30');
    assert.equal(normaliseString('P'), 'P');
  });

  it('replaces listed origins', () => {
    assert.equal(normaliseString('http://127.0.0.1:4500/api/x', ['http://127.0.0.1:4500']), '<origin>/api/x');
  });

  it('sorts keys deeply and leaves numbers, booleans and null', () => {
    const out = normalise({ b: [{ z: 1, a: null }], a: true });
    assert.equal(JSON.stringify(out), '{"a":true,"b":[{"a":null,"z":1}]}');
  });
});

describe('golden helper', () => {
  it('reports a missing golden file and the first difference', () => {
    const missing = matchGolden('nonexistent-player', 'nothing', {}, { update: false });
    assert.equal(missing.ok, false);
    assert.match(missing.message, /UPDATE_GOLDEN=1/);
    assert.equal(firstDifference({ a: [1, 2] }, { a: [1, 3] }), '$.a[1]: expected 2, got 3');
    assert.equal(firstDifference({ a: 1 }, { a: 1 }), '');
  });
});
