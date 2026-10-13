import http from "node:http";
import mjml from "mjml";

const maxBody = 1024 * 1024;
const server = http.createServer(async (request, response) => {
  const send = (status, body) => {
    response.writeHead(status, { "Content-Type": "application/json", "X-Content-Type-Options": "nosniff" });
    response.end(JSON.stringify(body));
  };
  if (request.method === "GET" && request.url === "/healthz") return send(200, { ok: true });
  if (request.method !== "POST" || request.url !== "/v1/render") return send(404, { message: "Not found" });
  try {
    let size = 0;
    const chunks = [];
    for await (const chunk of request) {
      size += chunk.length;
      if (size > maxBody) return send(413, { message: "Template exceeds 1 MiB" });
      chunks.push(chunk);
    }
    const raw = Buffer.concat(chunks).toString("utf8");
    let source = raw;
    try { source = JSON.parse(raw).mjml; } catch { /* The existing PHP client also supports plain MJML. */ }
    if (typeof source !== "string") return send(400, { message: "Expected an MJML template" });
    const result = mjml(source, { validationLevel: "soft", ignoreIncludes: true });
    return send(200, { html: result.html, errors: result.errors, mjml_version: "4.17.1" });
  } catch {
    return send(400, { message: "Invalid MJML template" });
  }
});
server.listen(Number(process.env.PORT ?? 15500), "0.0.0.0");
process.on("SIGTERM", () => server.close());
