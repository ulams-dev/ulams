import type { z } from "zod";
import type { AnyCommand, CommandDef } from "../registry/types.ts";

/** Defaults for hand-written commands; keeps definitions short. */
export function defineCommand<I extends z.ZodObject, O extends z.ZodType>(
  def: Partial<CommandDef<I, O>> & Pick<CommandDef<I, O>, "id" | "summary" | "input" | "output" | "run" | "kind">
): AnyCommand {
  const full = {
    idempotent: def.kind === "read",
    scopes: [] as string[],
    audience: ["any"] as CommandDef["audience"],
    endpoints: [] as string[],
    stability: "stable" as const,
    since: "0.1.0",
    examples: [] as CommandDef["examples"],
    ...def,
  };
  return full as unknown as AnyCommand;
}
