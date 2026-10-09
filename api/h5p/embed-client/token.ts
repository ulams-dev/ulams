/**
 * Token handling shared by the player and editor embed pages. No DOM access,
 * so it is unit-tested in Node (test/embed-token.test.ts).
 *
 * Passport access tokens live about 5 minutes and the LMS frontends refresh
 * them before they expire. H5P core cannot send an Authorization header, so
 * the service puts the token into the model's AJAX URLs as `?_token=`; after a
 * refresh those URLs must carry the new token or state saves and results are
 * sent anonymously (and rejected).
 */

/** What a page does with an `ulams-h5p:token` message. */
export type TokenTransition =
    /** first token (or anonymous): load the content */
    | 'mount'
    /** anonymous <-> signed in: what may be saved changed, reload the content */
    | 'remount'
    /** same user, new token: swap it into the running content */
    | 'refresh'
    | 'none';

export function normaliseToken(value: unknown): string | null {
    return typeof value === 'string' && value !== '' ? value : null;
}

export function tokenTransition(started: boolean, previous: string | null, next: string | null): TokenTransition {
    if (!started) {
        return 'mount';
    }
    if (Boolean(previous) !== Boolean(next)) {
        return 'remount';
    }
    if (previous && next && previous !== next) {
        return 'refresh';
    }
    return 'none';
}

/**
 * Replaces the old token with the new one in every URL string of an object
 * tree (H5PIntegration, editor model). Skips the content parameters and
 * translations, which can be large and never hold the token.
 */
export function swapToken(target: unknown, oldToken: string, newToken: string): void {
    const pairs: [string, string][] = [
        [encodeURIComponent(oldToken), encodeURIComponent(newToken)],
        [oldToken, newToken]
    ];
    const visit = (obj: any, depth: number) => {
        if (!obj || typeof obj !== 'object' || depth > 4) {
            return;
        }
        for (const key of Object.keys(obj)) {
            if (key === 'contents' || key === 'l10n' || key === 'jsonContent') {
                continue;
            }
            const value = obj[key];
            if (typeof value === 'string') {
                let next = value;
                for (const [a, b] of pairs) {
                    next = next.split(a).join(b);
                }
                if (next !== value) {
                    obj[key] = next;
                }
            } else if (value && typeof value === 'object' && !Array.isArray(value)) {
                visit(value, depth + 1);
            }
        }
    };
    visit(target, 0);
}

/**
 * Applies a refreshed token to the running player's H5PIntegration objects
 * (the page's and, for content in an inner iframe, the iframe's). With a
 * freshly fetched play model the AJAX URLs are taken from it; without one
 * (the fetch failed) the token is swapped in place.
 */
export function refreshIntegrations(
    integrations: Iterable<any>,
    model: { integration?: { ajax?: Record<string, string>; ajaxPath?: string } } | undefined,
    oldToken: string,
    newToken: string
): void {
    for (const integration of integrations) {
        if (!integration || typeof integration !== 'object') {
            continue;
        }
        if (model?.integration?.ajax) {
            integration.ajax = { ...integration.ajax, ...model.integration.ajax };
            if (model.integration.ajaxPath) {
                integration.ajaxPath = model.integration.ajaxPath;
            }
        }
        // anything else that still carries the old token
        swapToken(integration, oldToken, newToken);
    }
}

/**
 * Serialises refreshes: a refresh that finishes after a newer one started is
 * dropped, so a slow re-fetch never writes an older token back.
 */
export class RefreshSequence {
    private current = 0;

    start(): number {
        this.current += 1;
        return this.current;
    }

    isLatest(ticket: number): boolean {
        return ticket === this.current;
    }
}
