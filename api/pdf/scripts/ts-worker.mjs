// Development/test bootstrap for the render worker: runs src/worker.ts through
// tsx (the production image runs the compiled dist/worker.js directly).
import { workerData } from 'node:worker_threads';
import { register } from 'tsx/esm/api';

register();
await import(workerData.entry);
