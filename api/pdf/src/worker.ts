import { parentPort, workerData } from 'node:worker_threads';
import { loadFonts } from './fonts.js';
import { generatePdf, type Inputs } from './render.js';
import type { Template } from '@pdfme/common';

export interface WorkerTask {
    id: number;
    template: Template;
    inputs: Inputs;
}

export type WorkerReply =
    | { id: number; ok: true; pdf: Uint8Array }
    | { id: number; ok: false; message: string };

const { fontsDir } = workerData as { fontsDir: string };
const fonts = loadFonts(fontsDir);

parentPort?.on('message', async (task: WorkerTask) => {
    try {
        const pdf = await generatePdf(task.template, task.inputs, fonts.font);
        parentPort?.postMessage({ id: task.id, ok: true, pdf } satisfies WorkerReply, [pdf.buffer as ArrayBuffer]);
    } catch (error) {
        parentPort?.postMessage({ id: task.id, ok: false, message: String((error as Error)?.message ?? error) } satisfies WorkerReply);
    }
});

parentPort?.postMessage('ready');
