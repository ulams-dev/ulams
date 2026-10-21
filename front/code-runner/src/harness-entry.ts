// Entry of the harness bundle (scripts/build.mjs embeds the result in exec.js): installs the harness in the Worker's scope.
import { installHarness } from "./harness.ts";

installHarness(self as unknown as Record<string, unknown>);
