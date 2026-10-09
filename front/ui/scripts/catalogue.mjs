// Prints the UI catalogue (component name → description, props JSON Schema) for prompts and tools.
import { catalogueJson } from "../src/registry.ts";

process.stdout.write(`${JSON.stringify(catalogueJson(), null, 2)}\n`);
