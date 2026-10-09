/**
 * Editor embed page (`/h5p/embed/edit/:id|new`): Lumi <h5p-editor> loading
 * GET {base}/contents/:id/edit and saving with POST {base}/contents (new) or
 * PATCH {base}/contents/:id. The parent triggers saving with `ulams-h5p:save`.
 */
import { defineElements, H5PEditorComponent } from '@lumieducation/h5p-webcomponents';

import {
    applyStyle,
    connect,
    errorMessage,
    readConfig,
    reportHeight,
    requestJson,
    showError
} from './common';
import { normaliseToken, swapToken, tokenTransition } from './token';

const config = readConfig();
const root = document.getElementById('root') as HTMLElement;

let token: string | null = null;
let element: H5PEditorComponent | null = null;
/** the model object handed to Lumi: H5PEditor.getAjaxUrl reads its ajaxPath per call */
let model: any;
let saving = false;

const lang = () => `language=${encodeURIComponent(config.language)}`;

function mount(bridge: ReturnType<typeof connect>): void {
    root.innerHTML = '';
    const el = document.createElement('h5p-editor') as H5PEditorComponent;
    el.setAttribute('content-id', config.contentId);
    el.addEventListener('saved', (event: Event) => {
        const { contentId, metadata } = (event as CustomEvent).detail ?? {};
        bridge.post({ type: 'ulams-h5p:saved', contentId: String(contentId), metadata });
    });
    const onFailure = (code: string) => (event: Event) => {
        bridge.post({
            type: 'ulams-h5p:error',
            message: (event as CustomEvent).detail?.message ?? 'Error',
            code
        });
    };
    el.addEventListener('save-error', onFailure('save'));
    el.addEventListener('validation-error', onFailure('validation'));
    root.appendChild(el);
    element = el;

    el.saveContentCallback = async (contentId, requestBody) => {
        const id = contentId && contentId !== 'new' ? contentId : undefined;
        return requestJson<any>(id ? `${config.base}/contents/${encodeURIComponent(id)}` : `${config.base}/contents`, token, {
            method: id ? 'PATCH' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestBody)
        });
    };
    el.loadContentCallback = async (contentId?: string) => {
        try {
            model = await requestJson<any>(
                `${config.base}/contents/${encodeURIComponent(contentId ?? 'new')}/edit?${lang()}`,
                token
            );
            bridge.post({
                type: 'ulams-h5p:loaded',
                contentId: contentId ?? 'new',
                title: model?.metadata?.title,
                library: model?.library
            });
            return model;
        } catch (error) {
            bridge.post({ type: 'ulams-h5p:error', message: errorMessage(error), code: 'load' });
            throw error;
        }
    };
}

async function save(): Promise<void> {
    if (!element || saving) {
        return;
    }
    saving = true;
    try {
        await element.save();
    } catch {
        // reported through the save-error / validation-error events
    } finally {
        saving = false;
    }
}

function main(): void {
    defineElements('h5p-editor');
    let started = false;
    const bridge = connect(config, (message) => {
        switch (message.type) {
            case 'ulams-h5p:style':
                applyStyle(config, message);
                break;
            case 'ulams-h5p:token': {
                const next = normaliseToken(message.token);
                const previous = token;
                token = next;
                const transition = tokenTransition(started, previous, next);
                if (transition === 'mount' || (transition === 'remount' && next)) {
                    // the editor needs a token; signing out keeps the open form
                    started = true;
                    mount(bridge);
                } else if (transition === 'refresh') {
                    swapToken((window as any).H5PIntegration, previous as string, next as string);
                    swapToken(model, previous as string, next as string);
                    const editorNs = (window as any).H5PEditor;
                    if (editorNs && typeof editorNs.ajaxPath === 'string') {
                        swapToken(editorNs, previous as string, next as string);
                    }
                }
                break;
            }
            case 'ulams-h5p:save':
                void save();
                break;
            default:
                break;
        }
    });
    reportHeight(bridge);
    if (!bridge.framed) {
        // opened directly: the editor needs a token, so this only shows the error
        started = true;
        mount(bridge);
    }
}

try {
    main();
} catch (error) {
    showError(root, errorMessage(error));
}
