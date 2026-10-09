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
    showError
} from './common';
import { normaliseToken, RefreshSequence, refreshIntegrations, tokenTransition } from './token';

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

const refreshes = new RefreshSequence();

/** Token refreshed: re-fetch the model and swap the AJAX URLs in place (no reload). */
async function refreshToken(oldToken: string, newToken: string): Promise<void> {
    const ticket = refreshes.start();
    const integrations = new Set<any>();
    if ((window as any).H5PIntegration) integrations.add((window as any).H5PIntegration);
    try {
        const inner = element?.h5pWindow?.H5PIntegration;
        if (inner) integrations.add(inner);
    } catch {
        // ignore
    }
    let model: any;
    try {
        model = await requestJson<any>(playUrl(config.contentId), newToken);
    } catch {
        model = undefined;
    }
    if (!refreshes.isLatest(ticket) || token !== newToken) {
        return;
    }
    refreshIntegrations(integrations, model, oldToken, newToken);
}

function main(): void {
    defineElements('h5p-player');
    const bridge = connect(config, (message) => {
        switch (message.type) {
            case 'ulams-h5p:style':
                style = applyStyle(config, message);
                break;
            case 'ulams-h5p:token': {
                const next = normaliseToken(message.token);
                const previous = token;
                token = next;
                switch (tokenTransition(started, previous, next)) {
                    case 'mount':
                    case 'remount':
                        started = true;
                        mount(bridge);
                        break;
                    case 'refresh':
                        void refreshToken(previous as string, next as string);
                        break;
                    default:
                        break;
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
