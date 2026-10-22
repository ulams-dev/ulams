/* A seeded generator of Blueprint v3 documents for the round-trip property tests. No library: a small PRNG and
 * pools of awkward strings (YAML scalars that look like numbers or booleans, colons, quotes, unicode, directive
 * lines inside Markdown) are enough to shake the format. */

export type Rng = () => number;

export function mulberry32(seed: number): Rng {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const ALNUM = "0123456789abcdefghijklmnopqrstuvwxyz";
const FRAG = "abcdefghijklmnopqrstuvwxyz234567";

const WORDS = ["kubectl", "pod", "cluster", "Zażółć", "gęślą", "jaźń", "über", "日本語", "naïve", "café", "🙂", "deploy", "scale", "node", "true", "null", "123", "1e5", "0x1F", "yes", "no", "~"];
const TRICKY = [
  "a: b",
  "- dash first",
  "# not a comment",
  "key: value # trailing",
  "  leading spaces",
  "trailing spaces  ",
  "'single' and \"double\" quotes",
  "*star *and &anchor !tag",
  "%percent @at `tick`",
  "| pipe > gt { brace } [ bracket ] , comma",
  "true",
  "null",
  "12345",
  "1e5",
  "2026-10-22",
  "Zażółć gęślą jaźń",
  "tab\tinside",
  "colon:no-space",
  "question? mark",
  "back\\slash",
  "---",
];

export class Gen {
  private n = 0;
  constructor(readonly rnd: Rng) {}

  int(min: number, max: number): number {
    return min + Math.floor(this.rnd() * (max - min + 1));
  }
  chance(p: number): boolean {
    return this.rnd() < p;
  }
  pick<T>(list: readonly T[]): T {
    return list[this.int(0, list.length - 1)] as T;
  }
  some<T>(list: readonly T[], min: number, max: number): T[] {
    const copy = [...list];
    const out: T[] = [];
    const count = Math.min(this.int(min, max), copy.length);
    while (out.length < count) out.push(copy.splice(this.int(0, copy.length - 1), 1)[0] as T);
    return out;
  }

  id(): string {
    const n = ++this.n;
    const digits = String(n).padStart(6, "0");
    // Sometimes digit-only or float-looking, which YAML must quote to keep them strings.
    if (this.chance(0.05)) return `${"0".repeat(20)}${digits}`;
    if (this.chance(0.03)) return `${"0".repeat(18)}1e${digits}`;
    let s = "";
    for (let i = 0; i < 20; i++) s += ALNUM[this.int(0, 35)];
    return s + n.toString(36).padStart(6, "z");
  }
  fragment(): string {
    let s = "frg_";
    for (let i = 0; i < 12; i++) s += FRAG[this.int(0, FRAG.length - 1)];
    return s;
  }
  citations(min: number, max: number): string[] {
    return Array.from({ length: this.int(min, max) }, () => this.fragment());
  }

  /** Text of `min`..`max` characters (code points, as JSON Schema counts them). */
  text(min: number, max: number): string {
    const size = (t: string) => [...t].length;
    let s = this.chance(0.4) ? this.pick(TRICKY) : this.pick(WORDS);
    while (size(s) < min || (size(s) < max / 3 && this.chance(0.6))) s += ` ${this.pick(WORDS)}`;
    s = [...s].slice(0, max).join("");
    while (size(s) < min) s += "x";
    return s;
  }
  slug(): string {
    return `${this.pick(["intro", "setup", "pods", "deploy", "scale", "tips"])}-${this.n++}`;
  }

  markdown(): string {
    const parts: string[] = [];
    for (let i = this.int(1, 3); i > 0; i--) {
      parts.push(
        this.pick([
          () => this.text(5, 120),
          () => `- ${this.text(3, 40)}\n- ${this.text(3, 40)}`,
          () => "```bash\nkubectl get pods\n:::\n```",
          () => "```\n:::note{#x}\n\n\ntwo blank lines inside code\n```",
          () => "~~~yaml\nkey: value\n::::\n~~~",
          () => ":::",
          () => "::::",
          () => ":::paragraph{#oops}",
          () => `**bold** and _italic_\n\n> quote ${this.text(3, 30)}`,
          () => `| a | b |\n|---|---|\n| ${this.text(1, 8)} | ${this.text(1, 8)} |`,
        ])()
      );
    }
    let md = parts.join(this.chance(0.5) ? "\n\n" : "\n");
    if (this.chance(0.15)) md = `\n${md}\n\n`;
    return md;
  }

  meta(): { meta?: { createdBy: string; updatedAt: string } } {
    return this.chance(0.2) ? { meta: { createdBy: this.text(2, 12), updatedAt: "2026-10-21T10:00:00Z" } } : {};
  }

  question(objectiveIds: string[], type?: string): Record<string, unknown> {
    const t = type ?? this.pick(["single", "multiple", "truefalse", "short"]);
    const texts = Array.from({ length: 6 }, (_, i) => `${this.text(2, 30)} #${i}`);
    let options: Array<{ text: string; correct: boolean }>;
    if (t === "single") options = [{ text: texts[0] as string, correct: true }, { text: texts[1] as string, correct: false }, { text: texts[2] as string, correct: false }];
    else if (t === "multiple") options = [{ text: texts[0] as string, correct: true }, { text: texts[1] as string, correct: true }, { text: texts[2] as string, correct: false }, { text: texts[3] as string, correct: false }];
    else if (t === "truefalse") options = [{ text: "True", correct: true }, { text: "False", correct: false }];
    else options = [{ text: texts[0] as string, correct: true }, { text: texts[1] as string, correct: true }];
    const author = this.chance(0.5);
    return {
      id: this.id(),
      type: t,
      stem: this.text(3, 160),
      options: options.map((o) => ({ id: this.id(), ...o, ...this.meta() })),
      explanation: this.text(3, 200),
      citations: author ? this.citations(0, 2) : this.citations(1, 2),
      objectiveIds: objectiveIds.filter(() => this.chance(0.6)),
      ...(author ? { origin: "author" } : this.chance(0.2) ? { origin: "ai" } : {}),
      ...this.meta(),
    };
  }

  quiz(objectiveIds: string[]): Record<string, unknown> {
    return {
      id: this.id(),
      ...(this.chance(0.7) ? { passScore: this.int(0, 100) } : {}),
      questions: Array.from({ length: this.int(0, 3) }, () => this.question(objectiveIds)),
      ...this.meta(),
    };
  }

  document(): Record<string, unknown> {
    const courseObjectives = Array.from({ length: this.int(0, 3) }, () => ({ id: this.id(), text: this.text(3, 100), citations: this.citations(0, 2), ...this.meta() }));
    const modules = Array.from({ length: this.int(1, 3) }, (_, mi) => {
      const moduleKey = this.chance(0.5) ? `m${mi}-${this.slug()}` : undefined;
      return {
        id: this.id(),
        ...(moduleKey ? { key: moduleKey } : {}),
        title: this.text(2, 80),
        ...(this.chance(0.6) ? { summary: this.text(0, 200) } : {}),
        lessons: Array.from({ length: this.int(1, 3) }, (_, li) => this.lesson(li)),
        ...this.meta(),
      };
    });
    return {
      schemaVersion: 3,
      sources: this.chance(0.5) ? [] : [{ id: this.id(), title: this.text(2, 40), fragmentCount: this.int(1, 40) }],
      course: {
        id: this.id(),
        title: this.text(3, 100),
        ...(this.chance(0.5) ? { subtitle: this.text(0, 100) } : {}),
        ...(this.chance(0.5) ? { description: this.text(0, 300) } : {}),
        ...(this.chance(0.3) ? { seo: { title: this.text(1, 60), description: this.text(1, 150) } } : {}),
        language: this.pick(["en", "pl", "de", "zh"]),
        ...(this.chance(0.4) ? { audience: this.text(1, 100) } : {}),
        objectives: courseObjectives,
        ...(this.chance(0.4) ? { faq: Array.from({ length: this.int(0, 2) }, () => ({ question: this.text(3, 100), answer: this.text(3, 200), citations: this.citations(0, 2), ...(this.chance(0.5) ? { origin: "author" } : {}) })) } : {}),
        ...this.meta(),
      },
      modules,
      finalTest: this.chance(0.5) ? null : this.quiz([]),
      pages: this.chance(0.5) ? {} : { landing: null, header: this.chance(0.5) ? { blocks: [{ type: "hero", title: this.text(1, 20) }] } : null },
    };
  }

  lesson(index: number): Record<string, unknown> {
    const objectives = Array.from({ length: this.int(1, 3) }, () => ({ id: this.id(), text: this.text(3, 100), citations: this.citations(0, 2), ...this.meta() }));
    const objectiveIds = objectives.map((o) => o.id);
    const contentType = this.pick(["richtext", "richtext", "liascript", "h5p", "interactive", "video", "embed", "scorm", "cmi5", "file"]);
    const author = this.chance(0.4);
    const lesson: Record<string, unknown> = {
      id: this.id(),
      ...(this.chance(0.5) ? { key: `l${index}-${this.slug()}` } : {}),
      title: this.text(2, 100),
      ...(this.chance(0.6) ? { summary: this.text(0, 200) } : {}),
      minutes: this.int(1, 600),
      objectives,
      citations: author ? this.citations(0, 2) : this.citations(1, 3),
      contentType,
      status: this.pick(["generated", "generated", "planned", "failed"]),
      ...(author ? { origin: "author" } : this.chance(0.1) ? { origin: "ai" } : {}),
      flags: this.chance(0.3) ? [this.text(1, 60)] : [],
      blocks: Array.from({ length: this.int(0, 4) }, () => {
        const blockAuthor = this.chance(0.5);
        return {
          id: this.id(),
          kind: this.pick(["paragraph", "callout", "steps", "code", "table", "example"]),
          markdown: this.markdown(),
          citations: blockAuthor ? this.citations(0, 2) : this.citations(1, 3),
          objectiveIds: objectiveIds.filter(() => this.chance(0.5)),
          ...(blockAuthor ? { origin: "author" } : this.chance(0.15) ? { origin: "ai" } : {}),
          ...this.meta(),
        };
      }),
      quiz: this.chance(0.5) ? null : this.quiz(objectiveIds),
      ...this.meta(),
    };
    if (contentType === "scorm" || contentType === "cmi5" || contentType === "file") {
      lesson.asset = { path: `packages/${this.slug()}.zip`, sha256: "a".repeat(64), bytes: this.int(1, 100000), mediaType: "application/zip" };
      if (this.chance(0.5)) lesson.assetRef = { entityType: "topic", entityId: this.chance(0.5) ? this.int(1, 999) : this.id() };
    }
    if (contentType === "video") lesson.video = { provider: this.pick(["upload", "youtube", "vimeo"]), ...(this.chance(0.7) ? { url: "https://example.org/v/1" } : {}) };
    if (contentType === "embed") lesson.embed = { url: "https://example.org/embed", title: this.text(1, 40) };
    if (contentType === "liascript" || this.chance(0.1)) lesson.selfChecks = Array.from({ length: this.int(0, 2) }, () => this.question(objectiveIds));
    if (contentType === "h5p") {
      lesson.interaction = { id: this.id(), kind: "h5p", library: this.pick(["H5P.Blanks", "H5P.DragText", "H5P.Dialogcards"]), title: this.text(2, 60), data: { text: this.text(1, 40), nested: { list: [1, 2, { meta: "kept", deep: this.text(1, 10) }], flag: true }, meta: { keep: "this" } }, citations: this.citations(1, 2), objectiveIds: objectiveIds.slice(0, 1), ...this.meta() };
    } else if (contentType === "interactive") {
      lesson.interaction = { id: this.id(), kind: "interactive", packageId: this.int(1, 50), title: this.text(2, 60), ...(this.chance(0.5) ? { startStep: "a1" } : {}), caption: this.text(1, 100), citations: this.citations(0, 2), origin: "author", objectiveIds: [], ...this.meta() };
    } else if (this.chance(0.3)) lesson.interaction = null;
    if (this.chance(0.05)) lesson.exercises = [];
    return lesson;
  }
}

export const documentFor = (seed: number): Record<string, unknown> => new Gen(mulberry32(seed)).document();
