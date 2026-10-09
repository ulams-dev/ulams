import { CliError } from "../errors.ts";
import type { Ctx } from "../registry/types.ts";

export type OperationStatus = "running" | "succeeded" | "failed";

export interface OperationState {
  status: OperationStatus;
  data?: unknown;
  error?: string;
}

export interface OperationKind {
  kind: string;
  describe: string;
  get(ctx: Ctx, id: string): Promise<OperationState>;
}

const kinds = new Map<string, OperationKind>();

export function registerOperationKind(kind: OperationKind): void {
  kinds.set(kind.kind, kind);
}

export function operationKinds(): OperationKind[] {
  return [...kinds.values()];
}

export function parseHandle(handle: string): { kind: string; id: string } {
  const i = handle.indexOf(":");
  if (i < 1 || i === handle.length - 1) {
    throw new CliError("INPUT_INVALID", `"${handle}" is not an operation handle.`, { hint: "Handles look like <kind>:<id>, e.g. video:42." });
  }
  return { kind: handle.slice(0, i), id: handle.slice(i + 1) };
}

export function kindFor(handle: string): { op: OperationKind; id: string } {
  const { kind, id } = parseHandle(handle);
  const op = kinds.get(kind);
  if (!op) {
    throw new CliError("INPUT_INVALID", `Unknown operation kind "${kind}".`, { hint: `Known kinds: ${[...kinds.keys()].join(", ") || "none"}.` });
  }
  return { op, id };
}

export async function getOperation(ctx: Ctx, handle: string): Promise<OperationState & { handle: string }> {
  const { op, id } = kindFor(handle);
  return { handle, ...(await op.get(ctx, id)) };
}

/** Polls with backoff 0.5 s -> 5 s until the operation ends; exit 10 with the handle on timeout. */
export async function waitOperation(
  ctx: Ctx,
  handle: string,
  timeoutSeconds = ctx.flags.timeout,
  sleep: (ms: number) => Promise<void> = (ms) => new Promise((r) => setTimeout(r, ms))
): Promise<OperationState & { handle: string }> {
  const { op, id } = kindFor(handle);
  const deadline = Date.now() + timeoutSeconds * 1000;
  let delay = 500;
  for (;;) {
    const state = await op.get(ctx, id);
    if (state.status !== "running") {
      if (state.status === "failed") {
        throw new CliError("SERVER_ERROR", `Operation ${handle} failed${state.error ? `: ${state.error}` : "."}`, {
          retryable: false,
          details: { operation: handle, data: state.data ?? null },
        });
      }
      return { handle, ...state };
    }
    if (Date.now() + delay > deadline) {
      throw new CliError("TIMEOUT", `Operation ${handle} is still running after ${timeoutSeconds}s.`, { details: { operation: handle } });
    }
    ctx.io.stderr(`waiting for ${handle} ...`);
    await sleep(delay);
    delay = Math.min(delay * 2, 5000);
  }
}
