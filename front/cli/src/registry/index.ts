import type { AnyCommand } from "./types.ts";
import { generatedCommands } from "../generated/commands.ts";
import { handWritten } from "../commands/index.ts";

let cache: AnyCommand[] | null = null;

/** Generated commands first; hand-written commands with the same id win (plan 4.4). */
export function buildRegistry(): AnyCommand[] {
  const byId = new Map<string, AnyCommand>();
  for (const cmd of generatedCommands) byId.set(cmd.id, cmd);
  for (const cmd of handWritten) byId.set(cmd.id, cmd);
  return [...byId.values()];
}

export function getRegistry(): AnyCommand[] {
  return (cache ??= buildRegistry());
}
