import { describe, expect, it } from 'vitest';

import {
    normaliseToken,
    RefreshSequence,
    refreshIntegrations,
    swapToken,
    tokenTransition
} from '../embed-client/token';

const OLD = 'eyJ.old+token/=';
const NEW = 'eyJ.new+token/=';
const enc = encodeURIComponent;

/** The part of H5PIntegration that carries the token, as the service builds it. */
function integration(token: string) {
    return {
        url: '/h5p',
        ajax: {
            contentUserData: `/h5p/contentUserData/:contentId/:dataType/:subContentId?_token=${enc(token)}`,
            setFinished: `/h5p/finishedData?_token=${enc(token)}`
        },
        ajaxPath: `/h5p/ajax?_token=${enc(token)}&action=`,
        editor: { ajaxPath: `/h5p/ajax?_token=${enc(token)}&action=` },
        contents: { 'cid-1': { jsonContent: `{"text":"${OLD}"}` } },
        l10n: { H5P: { fullscreen: OLD } }
    };
}

describe('tokenTransition', () => {
    it('mounts on the first message, signed in or anonymous', () => {
        expect(tokenTransition(false, null, 'a')).toBe('mount');
        expect(tokenTransition(false, null, null)).toBe('mount');
    });

    it('refreshes in place when the 5-minute token rotates', () => {
        expect(tokenTransition(true, 'a', 'b')).toBe('refresh');
    });

    it('remounts when the user signs in or out', () => {
        expect(tokenTransition(true, null, 'a')).toBe('remount');
        expect(tokenTransition(true, 'a', null)).toBe('remount');
    });

    it('does nothing when the token did not change', () => {
        expect(tokenTransition(true, 'a', 'a')).toBe('none');
        expect(tokenTransition(true, null, null)).toBe('none');
    });

    it('treats empty and non-string tokens as anonymous', () => {
        expect(normaliseToken('')).toBeNull();
        expect(normaliseToken(undefined)).toBeNull();
        expect(normaliseToken(42)).toBeNull();
        expect(normaliseToken('t')).toBe('t');
    });
});

describe('swapToken', () => {
    it('replaces the encoded and the raw token in URL strings', () => {
        const target = integration(OLD);
        swapToken(target, OLD, NEW);
        expect(target.ajax.contentUserData).toContain(`_token=${enc(NEW)}`);
        expect(target.ajax.setFinished).toBe(`/h5p/finishedData?_token=${enc(NEW)}`);
        expect(target.editor.ajaxPath).toContain(enc(NEW));
        expect(JSON.stringify(target)).not.toContain(enc(OLD));
    });

    it('leaves content parameters and translations alone', () => {
        const target = integration(OLD);
        swapToken(target, OLD, NEW);
        expect(target.contents['cid-1'].jsonContent).toContain(OLD);
        expect(target.l10n.H5P.fullscreen).toBe(OLD);
    });
});

describe('refreshIntegrations', () => {
    it('takes the AJAX URLs from the re-fetched play model', () => {
        const page = integration(OLD);
        const inner = integration(OLD);
        const fresh = integration(NEW);
        fresh.ajax.setFinished = `/h5p/finishedData?v=2&_token=${enc(NEW)}`;
        refreshIntegrations([page, inner], { integration: fresh }, OLD, NEW);
        for (const target of [page, inner]) {
            expect(target.ajax.setFinished).toBe(`/h5p/finishedData?v=2&_token=${enc(NEW)}`);
            expect(target.ajaxPath).toBe(fresh.ajaxPath);
            expect(target.editor.ajaxPath).toContain(enc(NEW));
        }
    });

    it('swaps the token in place when the re-fetch failed', () => {
        const page = integration(OLD);
        refreshIntegrations([page, undefined], undefined, OLD, NEW);
        expect(page.ajax.contentUserData).toContain(`_token=${enc(NEW)}`);
        expect(page.ajax.setFinished).toContain(`_token=${enc(NEW)}`);
    });
});

describe('RefreshSequence', () => {
    it('drops a refresh that finishes after a newer one started', () => {
        const seq = new RefreshSequence();
        const first = seq.start();
        const second = seq.start();
        expect(seq.isLatest(first)).toBe(false);
        expect(seq.isLatest(second)).toBe(true);
    });
});
