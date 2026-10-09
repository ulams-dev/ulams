import { z } from "zod";
import { defineCommand } from "../define.ts";
import { getRegistry } from "../../registry/index.ts";
import { commandPath } from "../../registry/schema-export.ts";

export const completion = defineCommand({
  id: "completion",
  summary: "Print a shell completion script (bash, zsh or fish)",
  kind: "local",
  idempotent: true,
  anonymous: true,
  mcp: { expose: false },
  positionals: ["shell"],
  input: z.object({ shell: z.enum(["bash", "zsh", "fish"]) }),
  output: z.string(),
  examples: [{ title: "Enable in bash", argv: "completion bash" }],
  async run(_ctx, i) {
    const words = [...new Set(getRegistry().map((c) => commandPath(c)[0] as string))].sort().join(" ");
    const script =
      i.shell === "fish"
        ? `complete -c ulams -f -a "${words}"`
        : i.shell === "zsh"
          ? `#compdef ulams\n_ulams() { _arguments '1: :(${words})' '*::arg:_files' }\ncompdef _ulams ulams`
          : `_ulams() { COMPREPLY=( $(compgen -W "${words}" -- "\${COMP_WORDS[COMP_CWORD]}") ); }\ncomplete -F _ulams ulams`;
    return { data: script };
  },
});
