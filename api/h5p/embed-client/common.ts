import { EmbedConfig, EmbedToParent, MESSAGE_PREFIX, ParentToEmbed } from './protocol';

export function readConfig(): EmbedConfig {
    const el = document.getElementById('ulams-h5p-config');
    if (!el?.textContent) {
        throw new Error('Missing embed configuration.');
    }
    return JSON.parse(el.textContent) as EmbedConfig;
}

export interface Bridge {
    /** Sends to the parent once the handshake fixed its origin. */
    post(message: EmbedToParent): void;
    /** true when the page runs inside a frame */
    framed: boolean;
}

/**
 * Handshake with the parent window:
 * - announces `ulams-h5p:ready` to every allowed origin (no secrets in it)
 *   until the parent answers;
 * - accepts messages only from `window.parent` and an allowed origin, and
 *   after the first one only from that same origin.
 */
export function connect(config: EmbedConfig, onMessage: (message: ParentToEmbed) => void): Bridge {
    const framed = window.parent !== window;
    const wildcard = config.allowedOrigins.includes('*');
    const allowed = (origin: string) => wildcard || config.allowedOrigins.includes(origin);
    let parentOrigin: string | null = null;

    const post = (message: EmbedToParent) => {
        if (framed && parentOrigin) {
            window.parent.postMessage(message, parentOrigin);
        }
    };

    window.addEventListener('message', (event: MessageEvent) => {
        if (!framed || event.source !== window.parent || !allowed(event.origin)) {
            return;
        }
        if (parentOrigin && event.origin !== parentOrigin) {
            return;
        }
        const data = event.data as ParentToEmbed | undefined;
        if (!data || typeof data !== 'object' || typeof data.type !== 'string' || !data.type.startsWith(MESSAGE_PREFIX)) {
            return;
        }
        parentOrigin = event.origin;
        onMessage(data);
    });

    if (framed) {
        const ready: EmbedToParent = { type: 'ulams-h5p:ready', mode: config.mode, contentId: config.contentId };
        const targets = wildcard ? ['*'] : config.allowedOrigins;
        let tries = 0;
        const announce = () => {
            if (parentOrigin || tries++ >= 20) {
                return;
            }
            for (const origin of targets) {
                try {
                    window.parent.postMessage(ready, origin);
                } catch {
                    // invalid origin string in the allow-list
                }
            }
            window.setTimeout(announce, 500);
        };
        announce();
    }

    return { post, framed };
}

/** Reports the document height to the parent (auto-resizing iframe). */
export function reportHeight(bridge: Bridge): void {
    let last = 0;
    const send = () => {
        const height = Math.ceil(
            Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight ?? 0)
        );
        if (height !== last) {
            last = height;
            bridge.post({ type: 'ulams-h5p:resize', height });
        }
    };
    const observer = new ResizeObserver(send);
    observer.observe(document.documentElement);
    if (document.body) {
        observer.observe(document.body);
    }
    // H5P content in an inner iframe resizes without touching our layout boxes
    // in some browsers; poll cheaply as a fallback.
    window.setInterval(send, 1000);
    send();
}

export async function requestJson<T>(url: string, token: string | null, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }
    const res = await fetch(url, { ...init, headers, credentials: 'omit', cache: 'no-store' });
    let body: any;
    try {
        body = await res.json();
    } catch {
        body = undefined;
    }
    if (!res.ok || (body && body.success === false)) {
        throw new Error(body?.message || `HTTP ${res.status}`);
    }
    return (body && 'data' in body ? body.data : body) as T;
}

/** Applies parent CSS: a <style> element and <link>s on allowed origins. */
export function applyStyle(
    config: EmbedConfig,
    style: { css?: string; urls?: string[] }
): { css?: string; urls: string[] } {
    const urls = (style.urls ?? []).filter((u) => {
        try {
            const origin = new URL(u, window.location.href).origin;
            return origin === window.location.origin || config.allowedOrigins.includes(origin);
        } catch {
            return false;
        }
    });
    let el = document.getElementById('ulams-h5p-parent-style') as HTMLStyleElement | null;
    if (!el) {
        el = document.createElement('style');
        el.id = 'ulams-h5p-parent-style';
        document.head.appendChild(el);
    }
    el.textContent = typeof style.css === 'string' ? style.css : '';
    for (const url of urls) {
        if (!document.querySelector(`link[data-ulams-h5p="${CSS.escape(url)}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            link.dataset.ulamsH5p = url;
            document.head.appendChild(link);
        }
    }
    return { css: style.css, urls };
}

export const cssDataUrl = (css: string) => `data:text/css;base64,${btoa(unescape(encodeURIComponent(css)))}`;

export function errorMessage(error: unknown): string {
    if (error instanceof Error) {
        return error.message;
    }
    return String(error);
}

export function showError(root: HTMLElement, message: string): void {
    root.innerHTML = '';
    const p = document.createElement('p');
    p.className = 'ulams-h5p-error';
    p.textContent = message;
    root.appendChild(p);
}
