// A manual check on the running local stack: completes every topic of a course as the demo student through the progress
// API (a real finish: the CourseFinished event fires), then fetches the student's certificate PDF and checks it is a PDF.
//   node tests/try-certificate.mjs <tenant> <course-id> [out.pdf]
import { writeFileSync } from "node:fs";

const [tenant, courseId, out] = process.argv.slice(2);
if (!tenant || !courseId) {
  console.error("usage: node tests/try-certificate.mjs <tenant> <course-id> [out.pdf]");
  process.exit(1);
}
const api = `http://${tenant}.localhost`;
const login = await (await fetch(`${api}/api/demo/login`, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify({ role: "student" }) })).json();
const headers = { Authorization: `Bearer ${login.data.token}`, Accept: "application/json", "Content-Type": "application/json" };
const program = (await (await fetch(`${api}/api/courses/${courseId}/program`, { headers })).json()).data;
const topics = program.lessons.flatMap((l) => l.topics);
const done = await fetch(`${api}/api/courses/progress/${courseId}`, { method: "PATCH", headers, body: JSON.stringify({ progress: topics.map((t) => ({ topic_id: t.id, status: 1 })) }) });
console.log(`${program.title}: ${topics.length} topics marked complete, HTTP ${done.status}`);
await new Promise((r) => setTimeout(r, 4000));
const list = await (await fetch(`${api}/api/pdfs`, { headers })).json();
const pdfs = list.data ?? [];
console.log(`pdfs: ${pdfs.map((p) => `${p.id} ${p.name ?? p.title ?? ""}`).join("; ") || "none"}`);
if (!pdfs.length) process.exit(1);
const pdf = await fetch(`${api}/api/pdfs/generate/${pdfs[0].id}`, { headers });
const bytes = Buffer.from(await pdf.arrayBuffer());
console.log(`certificate: HTTP ${pdf.status}, ${pdf.headers.get("content-type")}, ${bytes.length} bytes, starts ${JSON.stringify(bytes.subarray(0, 5).toString())}`);
if (out) writeFileSync(out, bytes);
process.exit(pdf.ok && bytes.subarray(0, 4).toString() === "%PDF" ? 0 : 1);
