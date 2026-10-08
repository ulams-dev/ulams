import pino from 'pino';

/**
 * Removes credentials from URLs before they are logged. H5P core sends the
 * access token as `?_token=` on its AJAX calls, so request URLs must never be
 * logged verbatim.
 */
export function redactUrl(url: string | undefined): string | undefined {
    if (!url) {
        return url;
    }
    return url.replace(/([?&]_token=)[^&#]*/g, '$1[REDACTED]');
}

// CLI tools print JSON on stdout, so their logs go to stderr.
const toStderr = process.env.LOG_STDERR === 'true' || /[\\/]cli[\\/]/.test(process.argv[1] ?? '');

export const logger = pino({
    level: process.env.LOG_LEVEL ?? 'info',
    base: { service: 'api-h5p' },
    redact: {
        paths: [
            'req.headers.authorization',
            'req.headers["x-internal-token"]',
            'req.headers.cookie',
            'token'
        ],
        censor: '[REDACTED]'
    }
}, pino.destination({ dest: toStderr ? 2 : 1, sync: true }));

export type Logger = typeof logger;
