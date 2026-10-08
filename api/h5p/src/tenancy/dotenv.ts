/**
 * Small .env parser for the Laravel tenant files (the subset phpdotenv
 * accepts in practice): `KEY=value`, optional `export `, blank lines and
 * `#` comments, single quotes (literal), double quotes (with \n \r \t \" \\
 * escapes and ${VAR} expansion), unquoted values with inline ` #` comments
 * and ${VAR} expansion. Later keys win. Never evaluates anything.
 */
export function parseDotenv(content: string): Record<string, string> {
    const out: Record<string, string> = {};
    const expand = (value: string): string =>
        value.replace(/\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/g, (_m, name: string) => out[name] ?? '');

    const lines = content.replace(/\r\n?/g, '\n').split('\n');
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const match = /^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*(.*)$/.exec(line);
        if (!match) {
            continue;
        }
        const key = match[1];
        let raw = match[2];
        let value: string;
        if (raw.startsWith('"')) {
            // Double-quoted values may span lines.
            let body = raw.slice(1);
            let end = findClosingQuote(body);
            while (end < 0 && i + 1 < lines.length) {
                body += `\n${lines[++i]}`;
                end = findClosingQuote(body);
            }
            body = end < 0 ? body : body.slice(0, end);
            value = expand(
                body.replace(/\\([nrt"\\$])/g, (_m, c: string) =>
                    c === 'n' ? '\n' : c === 'r' ? '\r' : c === 't' ? '\t' : c
                )
            );
        } else if (raw.startsWith("'")) {
            const end = raw.indexOf("'", 1);
            value = end < 0 ? raw.slice(1) : raw.slice(1, end);
        } else {
            const hash = raw.search(/\s#/);
            if (hash >= 0) {
                raw = raw.slice(0, hash);
            }
            value = expand(raw.trim());
        }
        out[key] = value;
    }
    return out;
}

function findClosingQuote(s: string): number {
    for (let i = 0; i < s.length; i++) {
        if (s[i] === '\\') {
            i++;
        } else if (s[i] === '"') {
            return i;
        }
    }
    return -1;
}
