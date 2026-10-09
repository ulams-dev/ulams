/**
 * Player embed page (`/h5p/embed/play/:id`): Lumi <h5p-player> fed from
 * GET {base}/contents/:id/play with the token received from the parent.
 */
import { defineElements, H5PPlayerComponent } from '@lumieducation/h5p-webcomponents';

import {
    applyStyle,
    connect,
    cssDataUrl,
    errorMessage,
    readConfig,
    reportHeight,
    requestJson,
    showError,
    swapToken
} from './common';

const config = readConfig();
const root = document.getElementById('root') as HTMLElement;

let token: string | null = null;
let started = false;
let element: H5PPlayerComponent | null = null;
let style: { css?: string; urls: string[] } = { urls: [] };

const playUrl = (id: string) => {
    const q = new URLSearchParams({ language: config.language });
    if (config.contextId) q.set('contextId', config.contextId);
    if (config.readOnlyState) q.set('readOnlyState', 'yes');
    return `${config.base}/contents/${encodeURIComponent(id)}/play?${q}`;
};

/** Parent CSS also has to reach the content iframe (embed type "iframe"). */
function decorate(model: any): any {
    const extra = [...style.urls, ...(style.css ? [cssDataUrl(style.css)] : [])];
    const cid = `cid-${model.contentId}`;
    const content = model.integration?.contents?.[cid];
    if (content) {
        content.styles = [...(content.styles ?? []), ...extra];
        if (config.hideActions) {
            content.displayOptions = {
                ...content.displayOptions,
                frame: false,
                export: false,
                embed: false,
                copyright: false,
                icon: false
            };
        }
    }
    model.styles = [...(model.styles ?? []), ...extra];
    return model;
}

function mount(bridge: ReturnType<typeof connect>): void {
    element?.remove();
    root.innerHTML = '';
    const el = document.createElement('h5p-player') as H5PPlayerComponent;
    el.setAttribute('content-id', config.contentId);
    el.addEventListener('xAPI', (event: Event) => {
        const detail = (event as CustomEvent).detail;
        if (detail?.statement) {
            bridge.post({ type: 'ulams-h5p:xapi', statement: detail.statement, contentId: config.contentId });
        }
    });
    root.appendChild(el);
    element = el;
    el.loadContentCallback = async (contentId: string) => {
        try {
            const model = await requestJson<any>(playUrl(contentId), token);
            const content = model.integration?.contents?.[`cid-${model.contentId}`];
            bridge.post({
                type: 'ulams-h5p:loaded',
                contentId: String(model.contentId),
                title: content?.metadata?.title ?? content?.title,
                library: content?.library
            });
            return decorate(model);
        } catch (error) {
            bridge.post({ type: 'ulams-h5p:error', message: errorMessage(error), code: 'load' });
            throw error;
        }
    };
}

/** Token refreshed: re-fetch the model and swap the AJAX URLs in place. */
async function refreshToken(oldToken: string, newToken: string): Promise<void> {
    const integrations = new Set<any>();
    if ((window as any).H5PIntegration) integrations.add((window as any).H5PIntegration);
    try {
        const inner = element?.h5pWindow?.H5PIntegration;
        if (inner) integrations.add(inner);
    } catch {
        // ignore
    }
    try {
        const model = await requestJson<any>(playUrl(config.contentId), newToken);
        for (const integration of integrations) {
            integration.ajax = { ...integration.ajax, ...model.integration?.ajax };
            if (model.integration?.ajaxPath) {
                integration.ajaxPath = model.integration.ajaxPath;
            }
        }
    } catch {
        for (const integration of integrations) {
            swapToken(integration, oldToken, newToken);
        }
    }
}

function main(): void {
    defineElements('h5p-player');
    const bridge = connect(config, (message) => {
        switch (message.type) {
            case 'ulams-h5p:style':
                style = applyStyle(config, message);
                break;
            case 'ulams-h5p:token': {
                const next = typeof message.token === 'string' && message.token ? message.token : null;
                const previous = token;
                token = next;
                if (!started) {
                    started = true;
                    mount(bridge);
                } else if (Boolean(previous) !== Boolean(next)) {
                    // anonymous <-> signed in changes what may be saved: reload
                    mount(bridge);
                } else if (previous && next && previous !== next) {
                    void refreshToken(previous, next);
                }
                break;
            }
            default:
                break;
        }
    });
    reportHeight(bridge);
    // Opened directly (not framed) or the parent never answers: play anonymously.
    window.setTimeout(
        () => {
            if (!started) {
                started = true;
                mount(bridge);
            }
        },
        bridge.framed ? 5000 : 0
    );
}

try {
    main();
} catch (error) {
    showError(root, errorMessage(error));
}
