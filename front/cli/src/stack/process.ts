import { spawn } from "node:child_process";
import { CliError } from "../errors.ts";

export interface ProcessOptions { cwd?: string; signal?: AbortSignal; env?: NodeJS.ProcessEnv; stdin?: string; secrets?: string[]; log?: (line: string)=>void; }
export function cleanOutput(text: string, secrets: string[] = []): string {
  for (const value of secrets.filter(Boolean).sort((a,b)=>b.length-a.length)) text = text.split(value).join("[REDACTED]");
  return text;
}
/** No shell, no credential arguments; preserve cancellation and a bounded diagnostic tail. */
export async function runProcess(binary: string, args: string[], options: ProcessOptions = {}): Promise<string> {
  if (options.signal?.aborted) throw new CliError("TIMEOUT", "Stack operation cancelled; generated files and persistent data were retained.", { hint: "Repeat stack up with the same --directory to resume." });
  return new Promise((resolve,reject)=>{
    let output = "", diagnostics = "";
    const child = spawn(binary,args,{ cwd: options.cwd, env: options.env ?? process.env, stdio: ["pipe","pipe","pipe"], ...(options.signal ? { signal: options.signal } : {}) });
    child.stdout.on("data", chunk=>{output = (output + String(chunk)).slice(-8*1024*1024);});
    child.stderr.on("data", chunk=>{diagnostics = (diagnostics + String(chunk)).slice(-16000);});
    child.on("error",error=>reject(new CliError(error.name === "AbortError" ? "TIMEOUT" : "USAGE", error.name === "AbortError" ? "Stack operation cancelled; data was retained." : `Cannot run ${binary}. Install the required runtime and check PATH.`, { details: { binary }, hint: "Repeat stack up with the same --directory after restoring the required runtime." })));
    child.on("close",code=>{
      if(code === 0) resolve(cleanOutput(output,options.secrets));
      else reject(new CliError("SERVER_ERROR", `${binary} failed (exit ${code ?? "signal"}).`, { details: { command: args.slice(0,4), diagnostic: cleanOutput(diagnostics || output,options.secrets).slice(-6000) }, hint: "Fix the reported runtime problem and repeat stack up with the same --directory. Existing credentials and data are preserved." }));
    });
    child.stdin.on("error",()=>undefined);
    child.stdin.end(options.stdin);
  });
}
export interface StreamOptions extends Omit<ProcessOptions,"stdin"> { stdinFile?: string; stdoutFile?: string; }
/**
 * Like runProcess, but streams: stdout goes to `stdoutFile` (mode 600, sha256 and size returned) and
 * `stdinFile` feeds stdin. Database dumps and archives are larger than the 8 MiB output buffer.
 */
export async function runStream(binary: string, args: string[], options: StreamOptions): Promise<{ sha256?: string; size?: number }> {
  if (options.signal?.aborted) throw new CliError("TIMEOUT", "Stack operation cancelled; generated files and persistent data were retained.");
  const { createReadStream, createWriteStream } = await import("node:fs");
  const { createHash } = await import("node:crypto");
  return new Promise((resolve,reject)=>{
    let diagnostics = "", size = 0, settled = false;
    const hash = createHash("sha256");
    const fail = (error: CliError) => { if(!settled){settled = true;reject(error);} };
    const child = spawn(binary,args,{ cwd: options.cwd, env: options.env ?? process.env, stdio: [options.stdinFile ? "pipe" : "ignore","pipe","pipe"], ...(options.signal ? { signal: options.signal } : {}) });
    const out = options.stdoutFile ? createWriteStream(options.stdoutFile,{ mode: 0o600, flags: "wx" }) : undefined;
    out?.on("error",error=>fail(new CliError("USAGE",`Cannot write ${options.stdoutFile}: ${error.message}`)));
    const stdout = child.stdout!, stderr = child.stderr!;
    stdout.on("data",(chunk: Buffer)=>{ if(out){size += chunk.length;hash.update(chunk);} });
    if(out) stdout.pipe(out); else stdout.resume();
    stderr.on("data", chunk=>{diagnostics = (diagnostics + String(chunk)).slice(-16000);});
    if(options.stdinFile && child.stdin) {
      const input = createReadStream(options.stdinFile);
      input.on("error",error=>fail(new CliError("USAGE",`Cannot read ${options.stdinFile}: ${error.message}`)));
      child.stdin.on("error",()=>undefined);
      input.pipe(child.stdin);
    }
    child.on("error",error=>fail(new CliError(error.name === "AbortError" ? "TIMEOUT" : "USAGE", error.name === "AbortError" ? "Stack operation cancelled; data was retained." : `Cannot run ${binary}. Install the required runtime and check PATH.`, { details: { binary } })));
    child.on("close",code=>{
      const finish = () => {
        if(settled)return;
        settled = true;
        if(code === 0) resolve(out ? { sha256: hash.digest("hex"), size } : {});
        else reject(new CliError("SERVER_ERROR", `${binary} failed (exit ${code ?? "signal"}).`, { details: { command: args.slice(0,4), diagnostic: cleanOutput(diagnostics,options.secrets).slice(-6000) } }));
      };
      if(out && !out.writableFinished) out.on("finish",finish); else finish();
    });
  });
}
