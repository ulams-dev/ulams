import type { McpServer, ResourceTemplate as RT } from "@modelcontextprotocol/server";
import type { AnyCommand, Ctx } from "../registry/types.ts";
import { execute } from "../registry/executor.ts";

interface Course {
  id?: number;
  title?: string;
  status?: string;
  summary?: string;
  lessons?: Array<{ id?: number; title?: string; topics?: Array<{ id?: number; title?: string; topicable_type?: string }> }>;
}

export function courseMarkdown(course: Course): string {
  const lines = [`# ${course.title ?? "Course"}`, "", `Status: ${course.status ?? "unknown"}`];
  if (course.summary) lines.push("", course.summary);
  for (const lesson of course.lessons ?? []) {
    lines.push("", `## ${lesson.title ?? "Lesson"} (lesson ${lesson.id})`);
    for (const topic of lesson.topics ?? []) {
      const kind = (topic.topicable_type ?? "").split("\\").pop() ?? "topic";
      lines.push(`- ${topic.title ?? "Topic"} (topic ${topic.id}, ${kind})`);
    }
  }
  return lines.join("\n");
}

export function registerResources(
  server: McpServer,
  deps: { ctx: () => Ctx; read: AnyCommand; list: AnyCommand | undefined; template: typeof RT }
): void {
  const { ctx, read, list, template } = deps;
  const fetchCourse = async (id: string): Promise<Course> => (await execute(read, { id: Number(id) }, ctx(), { confirmed: true })).data as Course;
  server.registerResource(
    "course",
    new template("ulams://courses/{id}", {
      list: async () => {
        if (!list) return { resources: [] };
        const res = await execute(list, { per_page: 100 }, ctx(), { confirmed: true });
        const items = (Array.isArray(res.data) ? res.data : []) as Course[];
        return { resources: items.slice(0, 100).map((c) => ({ uri: `ulams://courses/${c.id}`, name: c.title ?? `Course ${c.id}`, mimeType: "text/markdown" })) };
      },
    }),
    { title: "Course outline", description: "Title, status, lessons and topics of a course, as Markdown.", mimeType: "text/markdown" },
    async (uri, variables) => {
      const course = await fetchCourse(String(variables.id));
      return { contents: [{ uri: uri.href, mimeType: "text/markdown", text: courseMarkdown(course) }] };
    }
  );
  server.registerResource(
    "course-json",
    new template("ulams://courses/{id}/program.json", { list: undefined }),
    { title: "Course program (JSON)", description: "The full course object as the API returns it.", mimeType: "application/json" },
    async (uri, variables) => {
      const course = await fetchCourse(String(variables.id));
      return { contents: [{ uri: uri.href, mimeType: "application/json", text: JSON.stringify(course) }] };
    }
  );
}
