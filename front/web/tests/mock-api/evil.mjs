/**
 * A listener that records every request it receives (the "attacker's server" of the containment checks of the
 * code exercises, ADR 0170). `GET /__hits` lists them, `POST /__reset` forgets them. A request to any other path
 * is a hit; nothing a sandboxed exercise does may produce one.
 */
import { createServer } from "node:http";

const port = Number(process.env.A11Y_EVIL_PORT ?? 4399);
let hits = [];

createServer((req, res) => {
  const path = new URL(req.url ?? "/", "http://evil").pathname;
  res.setHeader("Access-Control-Allow-Origin", "*");
  if (path === "/__hits") return void res.setHeader("Content-Type", "application/json").end(JSON.stringify(hits));
  if (path === "/__reset") {
    hits = [];
    return void res.end("reset");
  }
  req.resume();
  hits.push({ method: req.method, path, at: Date.now() });
  res.end("evil heard you");
}).listen(port, "127.0.0.1", () => console.log(`evil listener on :${port}`));
