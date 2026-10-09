// Minimal ZIP writer (deflate, no ZIP64) for the build output: Node built-ins only.
import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { crc32, deflateRawSync } from 'node:zlib';

const MAX_ENTRIES = 65535;
const MAX_BYTES = 0xffffffff;

function dosDateTime(date) {
    const time = (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2);
    const day = ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();
    return { time: time & 0xffff, date: day & 0xffff };
}

/**
 * Builds a ZIP archive from entries `{ name, data }` (names use `/`, no leading slash).
 * @param {{ name: string, data: Buffer }[]} entries
 * @param {Date} [mtime]
 * @returns {Buffer}
 */
export function zipEntries(entries, mtime = new Date(1980, 0, 1)) {
    if (entries.length > MAX_ENTRIES) {
        throw new Error('Too many files for a ZIP without ZIP64');
    }
    const { time, date } = dosDateTime(mtime);
    const locals = [];
    const centrals = [];
    let offset = 0;
    for (const { name, data } of entries) {
        if (!name || name.startsWith('/') || name.split('/').includes('..')) {
            throw new Error(`Invalid entry name ${name}`);
        }
        const nameBuf = Buffer.from(name, 'utf8');
        const deflated = deflateRawSync(data);
        const useDeflate = deflated.length < data.length;
        const body = useDeflate ? deflated : data;
        const crc = crc32(data) >>> 0;
        if (data.length > MAX_BYTES || offset > MAX_BYTES) {
            throw new Error('Archive too large for a ZIP without ZIP64');
        }

        const local = Buffer.alloc(30);
        local.writeUInt32LE(0x04034b50, 0);
        local.writeUInt16LE(20, 4); // version needed
        local.writeUInt16LE(0x0800, 6); // UTF-8 names
        local.writeUInt16LE(useDeflate ? 8 : 0, 8);
        local.writeUInt16LE(time, 10);
        local.writeUInt16LE(date, 12);
        local.writeUInt32LE(crc, 14);
        local.writeUInt32LE(body.length, 18);
        local.writeUInt32LE(data.length, 22);
        local.writeUInt16LE(nameBuf.length, 26);
        local.writeUInt16LE(0, 28);
        locals.push(local, nameBuf, body);

        const central = Buffer.alloc(46);
        central.writeUInt32LE(0x02014b50, 0);
        central.writeUInt16LE(0x0314, 4); // made by: unix, 2.0
        central.writeUInt16LE(20, 6);
        central.writeUInt16LE(0x0800, 8);
        central.writeUInt16LE(useDeflate ? 8 : 0, 10);
        central.writeUInt16LE(time, 12);
        central.writeUInt16LE(date, 14);
        central.writeUInt32LE(crc, 16);
        central.writeUInt32LE(body.length, 20);
        central.writeUInt32LE(data.length, 24);
        central.writeUInt16LE(nameBuf.length, 28);
        central.writeUInt32LE((0o100644 << 16) >>> 0, 38); // regular file, rw-r--r--
        central.writeUInt32LE(offset, 42);
        centrals.push(central, nameBuf);

        offset += local.length + nameBuf.length + body.length;
    }
    const centralSize = centrals.reduce((n, b) => n + b.length, 0);
    const end = Buffer.alloc(22);
    end.writeUInt32LE(0x06054b50, 0);
    end.writeUInt16LE(entries.length, 8);
    end.writeUInt16LE(entries.length, 10);
    end.writeUInt32LE(centralSize, 12);
    end.writeUInt32LE(offset, 16);
    return Buffer.concat([...locals, ...centrals, end]);
}

/** Every regular file under `dir`, as ZIP entries with paths relative to it (sorted). */
export async function directoryEntries(dir) {
    const out = [];
    const walk = async (rel) => {
        const items = await readdir(path.join(dir, rel), { withFileTypes: true });
        items.sort((a, b) => a.name.localeCompare(b.name));
        for (const item of items) {
            const childRel = rel ? `${rel}/${item.name}` : item.name;
            if (item.isDirectory()) {
                await walk(childRel);
            } else if (item.isFile()) {
                out.push({ name: childRel, data: await readFile(path.join(dir, childRel)) });
            }
            // symlinks and special files are never packaged
        }
    };
    await walk('');
    return out;
}

/** Zips a directory; refuses archives over `maxBytes` of input. */
export async function zipDirectory(dir, maxBytes = 512 * 1024 * 1024) {
    const entries = await directoryEntries(dir);
    const total = entries.reduce((n, e) => n + e.data.length, 0);
    if (total > maxBytes) {
        throw new Error(`Build output is ${total} bytes, over the ${maxBytes} byte limit`);
    }
    return zipEntries(entries, (await stat(dir)).mtime);
}
