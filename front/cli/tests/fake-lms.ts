import type { Handler, RecordedRequest, Reply } from "./helpers.ts";

type Row = Record<string, unknown> & { id: number };

/** A tiny in-memory LMS: courses, lessons, topics, settings, groups, users, access. Enough for apply and curated commands. */
export function fakeLms() {
  let next = 100;
  const db = {
    courses: [] as Row[],
    lessons: [] as Row[],
    topics: [] as Row[],
    settings: [] as Row[],
    groups: [] as Row[],
    users: [{ id: 3, email: "student1@x.test", name: "S1" }] as Row[],
    access: new Map<number, { users: number[]; groups: number[] }>(),
    writes: 0,
  };
  const ok = (data: unknown, meta?: unknown): Reply => ({ body: { success: true, data, ...(meta ? { meta } : {}) } });
  const page = (rows: unknown[]) => ok(rows, { current_page: 1, per_page: 100, total: rows.length, last_page: 1 });
  const courseView = (c: Row) => ({
    ...c,
    lessons: db.lessons.filter((l) => l.course_id === c.id).map((l) => ({ ...l, topics: db.topics.filter((t) => t.lesson_id === l.id) })),
  });

  const handler: Handler = (req: RecordedRequest): Reply => {
    const path = req.path;
    const m = req.method;
    const body = (req.body ?? {}) as Record<string, unknown>;
    let r: RegExpMatchArray | null;
    if (m === "GET" && path === "/api/profile/me") return ok({ id: 1, email: "admin@x.test", roles: ["admin"] });
    if (m === "GET" && path === "/api/admin/courses") return page(db.courses.filter((c) => !req.query.title || String(c.title).includes(req.query.title)));
    if (m === "POST" && path === "/api/admin/courses") {
      db.writes++;
      const c = { id: next++, ...body } as Row;
      db.courses.push(c);
      return ok(c);
    }
    if ((r = path.match(/^\/api\/admin\/courses\/(\d+)$/))) {
      const c = db.courses.find((x) => x.id === Number(r![1]));
      if (!c) return { status: 404, body: { message: "Course not found" } };
      if (m === "GET") return ok(courseView(c));
      if (m === "POST") {
        db.writes++;
        Object.assign(c, body);
        return ok(c);
      }
      if (m === "DELETE") {
        db.writes++;
        db.courses.splice(db.courses.indexOf(c), 1);
        return ok(null);
      }
    }
    if (m === "POST" && path === "/api/admin/lessons") {
      db.writes++;
      const l = { id: next++, ...body } as Row;
      db.lessons.push(l);
      return ok(l);
    }
    if ((r = path.match(/^\/api\/admin\/lessons\/(\d+)$/))) {
      const l = db.lessons.find((x) => x.id === Number(r![1]));
      if (!l) return { status: 404, body: { message: "Lesson not found" } };
      if (m === "GET") return ok(l);
      if (m === "PUT") {
        db.writes++;
        Object.assign(l, body);
        return ok(l);
      }
    }
    if (m === "POST" && path === "/api/admin/topics") {
      db.writes++;
      const { value, topicable_type, ...rest } = body;
      const t = { id: next++, ...rest, topicable_type, topicable: { id: next++, value } } as Row;
      db.topics.push(t);
      return ok(t);
    }
    if ((r = path.match(/^\/api\/admin\/topics\/(\d+)$/))) {
      const t = db.topics.find((x) => x.id === Number(r![1]));
      if (!t) return { status: 404, body: { message: "Topic not found" } };
      if (m === "GET") return ok(t);
      if (m === "PUT") {
        db.writes++;
        const { value, ...rest } = body;
        Object.assign(t, rest, { topicable: { ...(t.topicable as object), value } });
        return ok(t);
      }
    }
    if (m === "GET" && path === "/api/admin/settings") return page(db.settings.filter((s) => !req.query.group || s.group === req.query.group));
    if (m === "POST" && path === "/api/admin/settings") {
      db.writes++;
      const s = { id: next++, ...body } as Row;
      db.settings.push(s);
      return ok(s);
    }
    if ((r = path.match(/^\/api\/admin\/settings\/(\d+)$/)) && m === "PUT") {
      db.writes++;
      const s = db.settings.find((x) => x.id === Number(r![1]))!;
      Object.assign(s, body);
      return ok(s);
    }
    if (m === "GET" && path === "/api/admin/user-groups/") return page(db.groups);
    if (m === "POST" && path === "/api/admin/user-groups/") {
      db.writes++;
      const g = { id: next++, ...body } as Row;
      db.groups.push(g);
      return ok(g);
    }
    if (m === "GET" && path === "/api/admin/users") return page(db.users);
    if ((r = path.match(/^\/api\/admin\/courses\/(\d+)\/access$/)) && m === "GET") {
      const a = db.access.get(Number(r[1])) ?? { users: [], groups: [] };
      return ok({ users: a.users.map((id) => db.users.find((u) => u.id === id)), groups: a.groups.map((id) => db.groups.find((g) => g.id === id)) });
    }
    if ((r = path.match(/^\/api\/admin\/courses\/(\d+)\/access\/(add|remove|set)$/)) && m === "POST") {
      db.writes++;
      const id = Number(r[1]);
      const a = db.access.get(id) ?? { users: [], groups: [] };
      const u = (body.users as number[]) ?? [];
      const g = (body.groups as number[]) ?? [];
      if (r[2] === "add") {
        a.users = [...new Set([...a.users, ...u])];
        a.groups = [...new Set([...a.groups, ...g])];
      } else if (r[2] === "remove") {
        a.users = a.users.filter((x) => !u.includes(x));
        a.groups = a.groups.filter((x) => !g.includes(x));
      } else {
        a.users = u;
        a.groups = g;
      }
      db.access.set(id, a);
      return ok(a);
    }
    return { status: 404, body: { success: false, message: `fake LMS has no ${m} ${path}` } };
  };
  return { db, routes: { "*": handler } as Record<string, Handler> };
}
