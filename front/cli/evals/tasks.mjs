// Scripted tasks and their checks. A check gets `ulams(args)` that runs the CLI with the eval profile
// and returns the parsed envelope, plus the agent's final answer.
export const stamp = Date.now().toString(36);
export const COURSE = `Eval Kubernetes ${stamp}`;

export const tasks = [
  {
    id: "build-and-publish",
    prompt:
      `Create a course titled "${COURSE}" with two lessons, "Install" and "Run". Add a quiz topic with one multiple-choice question to the "Install" lesson. ` +
      `Publish the course and give the user student1@coffee.ulams.app access to it. Answer with the course id.`,
    async check({ ulams }) {
      const list = (await ulams(["courses", "list", "--title", COURSE])).data ?? [];
      const course = list.find((c) => c.title === COURSE);
      if (!course) return "course not found";
      const full = (await ulams(["courses", "get", String(course.id)])).data;
      if (full.status !== "published") return `status is ${full.status}`;
      if ((full.lessons ?? []).length !== 2) return `expected 2 lessons, got ${(full.lessons ?? []).length}`;
      const topics = (full.lessons ?? []).flatMap((l) => l.topics ?? []);
      if (!topics.some((t) => String(t.topicable_type).endsWith("GiftQuiz"))) return "no quiz topic";
      const access = (await ulams(["access", "list", "--course", String(course.id)])).data;
      if (!(access.users ?? []).some((u) => u.email === "student1@coffee.ulams.app")) return "student1 has no access";
      return null;
    },
  },
  {
    id: "report-drafts",
    prompt: "How many courses are in draft status on this instance right now? List their titles. Do not change anything.",
    async check({ ulams, answer }) {
      const all = (await ulams(["courses", "list", "--all"])).data ?? [];
      const drafts = all.filter((c) => c.status === "draft" || c.status === null);
      for (const c of drafts) if (!answer.includes(c.title)) return `answer misses "${c.title}"`;
      if (!answer.includes(String(drafts.length))) return `answer lacks the count ${drafts.length}`;
      return null;
    },
  },
  {
    id: "delete-with-confirmation",
    prompt: `Delete the course titled "${COURSE}". It is destructive: show me what will be deleted first and only then delete it.`,
    async check({ ulams, trace }) {
      const list = (await ulams(["courses", "list", "--title", COURSE])).data ?? [];
      if (list.some((c) => c.title === COURSE)) return "course still exists";
      const confirmed = trace.some((t) => t.error === "CONFIRMATION_REQUIRED");
      if (!confirmed) return "the agent never hit the confirmation step";
      return null;
    },
  },
];
