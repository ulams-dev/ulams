import { main, nodeDeps } from "./cli/run.ts";

main(nodeDeps()).then(
  (code) => {
    process.exitCode = code;
  },
  (error) => {
    process.stderr.write(`ulams: unexpected failure: ${(error as Error).message}\n`);
    process.exitCode = 1;
  }
);
