import { Worker } from 'node:worker_threads';
import type { Logger } from 'pino';
import type { Template } from '@pdfme/common';
import { RenderError, type Inputs } from './render.js';
import type { WorkerReply, WorkerTask } from './worker.js';

export interface PoolOptions {
    size: number;
    maxQueue: number;
    timeoutMs: number;
    fontsDir: string;
    logger: Logger;
}

interface Slot {
    worker: Worker;
    busy: boolean;
}

interface Pending {
    task: WorkerTask;
    resolve: (pdf: Uint8Array) => void;
    reject: (error: Error) => void;
}

const workerEntry = (): { url: URL; entry?: string } => {
    // compiled: dist/worker.js; tests and `yarn dev` run the TypeScript source through tsx
    if (import.meta.url.endsWith('.ts')) {
        return {
            url: new URL('../scripts/ts-worker.mjs', import.meta.url),
            entry: new URL('./worker.ts', import.meta.url).href
        };
    }
    return { url: new URL('./worker.js', import.meta.url) };
};

/**
 * Fixed set of worker threads running pdfme. pdfme work is CPU-bound and
 * cannot be interrupted in-process, so a render that exceeds the time limit
 * terminates its worker, which is then replaced.
 */
export class RenderPool {
    private readonly slots: Slot[] = [];
    private readonly queue: Pending[] = [];
    private nextId = 1;
    private closed = false;

    constructor(private readonly options: PoolOptions) {
        for (let i = 0; i < options.size; i++) {
            this.slots.push(this.spawn());
        }
    }

    get active(): number {
        return this.slots.filter((slot) => slot.busy).length;
    }

    render(template: Template, inputs: Inputs): Promise<Uint8Array> {
        if (this.closed) {
            return Promise.reject(new RenderError(503, 'shutting_down', 'renderer is shutting down'));
        }
        const free = this.slots.find((slot) => !slot.busy);
        if (!free && this.queue.length >= this.options.maxQueue) {
            return Promise.reject(new RenderError(503, 'busy', 'too many renders in progress, retry shortly'));
        }
        return new Promise<Uint8Array>((resolve, reject) => {
            const pending = { task: { id: this.nextId++, template, inputs }, resolve, reject };
            if (free) {
                this.run(free, pending);
            } else {
                this.queue.push(pending);
            }
        });
    }

    async close(): Promise<void> {
        this.closed = true;
        await Promise.all(this.slots.map((slot) => slot.worker.terminate()));
    }

    private spawn(): Slot {
        const { url, entry } = workerEntry();
        const worker = new Worker(url, { workerData: { fontsDir: this.options.fontsDir, entry } });
        worker.unref();
        const slot: Slot = { worker, busy: false };
        worker.on('error', (error) => this.options.logger.error({ err: error }, 'render worker crashed'));
        return slot;
    }

    private run(slot: Slot, pending: Pending): void {
        slot.busy = true;
        const { worker } = slot;
        let settled = false;

        const finish = () => {
            settled = true;
            clearTimeout(timer);
            worker.off('message', onMessage);
            worker.off('exit', onExit);
        };
        const onMessage = (reply: WorkerReply | 'ready') => {
            if (reply === 'ready' || reply.id !== pending.task.id) {
                return;
            }
            finish();
            if (reply.ok) {
                pending.resolve(reply.pdf);
            } else {
                pending.reject(new RenderError(422, 'render_failed', 'pdfme could not render the template', reply.message));
            }
            this.release(slot);
        };
        const onExit = () => {
            if (settled) {
                return;
            }
            finish();
            pending.reject(new RenderError(500, 'worker_exited', 'render worker exited unexpectedly'));
            this.replace(slot);
        };
        const timer = setTimeout(() => {
            if (settled) {
                return;
            }
            finish();
            pending.reject(new RenderError(504, 'render_timeout', `rendering took longer than ${this.options.timeoutMs} ms`));
            this.options.logger.warn({ timeoutMs: this.options.timeoutMs }, 'render timed out, replacing worker');
            void worker.terminate();
            this.replace(slot);
        }, this.options.timeoutMs);

        worker.on('message', onMessage);
        worker.on('exit', onExit);
        worker.postMessage(pending.task);
    }

    private replace(slot: Slot): void {
        const index = this.slots.indexOf(slot);
        if (index === -1 || this.closed) {
            return;
        }
        const fresh = this.spawn();
        this.slots[index] = fresh;
        this.release(fresh);
    }

    private release(slot: Slot): void {
        slot.busy = false;
        const next = this.queue.shift();
        if (next) {
            this.run(slot, next);
        }
    }
}
