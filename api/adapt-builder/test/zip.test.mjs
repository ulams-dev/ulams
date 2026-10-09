import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { inflateRawSync } from 'node:zlib';

import { zipEntries } from '../src/zip.mjs';

/** Reads a ZIP through its central directory (enough to check what zipEntries writes). */
export function readZip(buf) {
    const eocd = buf.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
    assert.ok(eocd >= 0, 'end of central directory');
    const count = buf.readUInt16LE(eocd + 10);
    let p = buf.readUInt32LE(eocd + 16);
    const files = {};
    for (let i = 0; i < count; i++) {
        assert.equal(buf.readUInt32LE(p), 0x02014b50);
        const method = buf.readUInt16LE(p + 10);
        const compressed = buf.readUInt32LE(p + 20);
        const nameLen = buf.readUInt16LE(p + 28);
        const extraLen = buf.readUInt16LE(p + 30);
        const commentLen = buf.readUInt16LE(p + 32);
        const offset = buf.readUInt32LE(p + 42);
        const name = buf.subarray(p + 46, p + 46 + nameLen).toString('utf8');
        const localNameLen = buf.readUInt16LE(offset + 26);
        const localExtraLen = buf.readUInt16LE(offset + 28);
        const start = offset + 30 + localNameLen + localExtraLen;
        const body = buf.subarray(start, start + compressed);
        files[name] = method === 8 ? inflateRawSync(body) : Buffer.from(body);
        p += 46 + nameLen + extraLen + commentLen;
    }
    return files;
}

describe('zipEntries', () => {
    it('writes stored and deflated entries that read back', () => {
        const big = Buffer.from('adapt '.repeat(1000));
        const zip = zipEntries([
            { name: 'imsmanifest.xml', data: Buffer.from('<manifest/>') },
            { name: 'course/en/course.json', data: big },
            { name: 'empty.txt', data: Buffer.alloc(0) }
        ]);
        const files = readZip(zip);
        assert.deepEqual(Object.keys(files), ['imsmanifest.xml', 'course/en/course.json', 'empty.txt']);
        assert.equal(files['imsmanifest.xml'].toString(), '<manifest/>');
        assert.ok(files['course/en/course.json'].equals(big));
        assert.ok(zip.length < big.length, 'large text is deflated');
    });

    it('refuses names that escape the archive', () => {
        for (const name of ['../x', '/abs', 'a/../../b', '']) {
            assert.throws(() => zipEntries([{ name, data: Buffer.from('x') }]), /Invalid entry name/);
        }
    });
});
