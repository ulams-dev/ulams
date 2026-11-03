/**
 * Course variants of the mock tenant API (CV-5): five families with the shapes of VariantController and
 * PlacementController (api/packages/course-variants), one per case the learner pages must handle. State (the
 * learner's choice, dismissed hints, the last placement attempt) is kept per tenant so the desktop and phone
 * projects never meet; `/__mock/reset` forgets it.
 *
 *   A  courses 31, 32, 33  enrolled, may change; the only family a test switches freely
 *   B  courses 41, 42      assigned by the organisation's rule "Sales team" and locked
 *   C  courses 51, 52      the learner is not enrolled (read-only picker)
 *   D  courses 61, 62, 63  a hint to switch to the easier version, with reasons as message keys
 *   E  courses 71, 72, 73  the placement check is on (six questions, two per level)
 *
 * Every family has its own courses, so the pages of the other specs (course 1) are unchanged.
 */

const LEVELS = ["beginner", "intermediate", "expert"];

const v = (courseId, title, label, level, role, isDefault, description) => ({ courseId, title, label, level, role, isDefault, description, duration: ["3 hours", "5 hours", "8 hours"][LEVELS.indexOf(level)] ?? null });

export const FAMILIES = {
  A: {
    groupId: 1,
    defaultCourseId: 31,
    variants: [
      v(31, "Coffee roasting: the basics", "Beginner", "beginner", null, true, "For people who have never roasted a batch."),
      v(32, "Coffee roasting: in practice", "Intermediate", "intermediate", null, false, "For home roasters who want a repeatable profile."),
      v(33, "Coffee roasting: for professionals", "Expert", "expert", "Roaster", false, "For roasters who run a production schedule."),
    ],
    enrolled: true,
  },
  B: {
    groupId: 2,
    defaultCourseId: 41,
    variants: [v(41, "Cupping: the basics", "Standard", "beginner", null, true, "The standard version."), v(42, "Cupping for the sales team", "Sales team", "intermediate", "Sales", false, "For people who sell coffee, not roast it.")],
    enrolled: true,
    assigned: { courseId: 42, source: "rule", reason: { key: "rule", params: { name: "Sales team" } }, byOrganisation: true },
    locked: true,
  },
  C: {
    groupId: 3,
    defaultCourseId: 51,
    variants: [v(51, "Brewing: the basics", "Beginner", "beginner", null, true, "For home brewers."), v(52, "Brewing: for cafes", "Expert", "expert", "Barista", false, "For baristas who serve many cups an hour.")],
    enrolled: false,
  },
  D: {
    groupId: 4,
    defaultCourseId: 61,
    variants: [
      v(61, "Latte art: the advanced course", "Expert", "expert", null, true, "For baristas who pour every day."),
      v(62, "Latte art: step by step", "Intermediate", "intermediate", null, false, "For baristas who are learning the pours."),
      v(63, "Latte art: first pours", "Beginner", "beginner", null, false, "For people who have never poured a heart."),
    ],
    enrolled: true,
    hint: { id: 5, direction: "easier", courseId: 61, suggestedCourseId: 62, reasons: [{ rule: "variant_too_hard", messageKey: "insights.reason.variant_too_hard", params: { modules: 2 }, message: "You are finding the first 2 modules hard; an easier version of this course may suit you better" }] },
  },
  E: {
    groupId: 5,
    defaultCourseId: 71,
    variants: [
      v(71, "Espresso machines: the basics", "Beginner", "beginner", null, true, "For people who are new to espresso machines."),
      v(72, "Espresso machines: maintenance", "Intermediate", "intermediate", null, false, "For people who look after one machine."),
      v(73, "Espresso machines: service engineer", "Expert", "expert", null, false, "For people who repair machines for a living."),
    ],
    enrolled: true,
    placement: true,
  },
};

/** The six placement questions (two per level) with their answer key; the key never leaves the mock. */
const PLACEMENT = [
  { id: 701, level: "beginner", type: "single", stem: "What does the portafilter hold?", options: [["a", "The ground coffee"], ["b", "The water tank"], ["c", "The milk"]], correct: ["a"] },
  { id: 702, level: "beginner", type: "truefalse", stem: "Espresso is brewed under pressure.", options: [["t", "True"], ["f", "False"]], correct: ["t"] },
  { id: 703, level: "intermediate", type: "single", stem: "Which part do you backflush?", options: [["a", "The group head"], ["b", "The grinder hopper"], ["c", "The cup"]], correct: ["a"] },
  { id: 704, level: "intermediate", type: "multiple", stem: "Which signs say the machine needs descaling?", options: [["a", "Slow water flow"], ["b", "A white crust on the steam wand"], ["c", "A new colour of the casing"]], correct: ["a", "b"] },
  { id: 705, level: "expert", type: "single", stem: "A pump that hums but gives no pressure most likely has what?", options: [["a", "A failed vibration pump valve"], ["b", "A cold cup"], ["c", "A full drip tray"]], correct: ["a"] },
  { id: 706, level: "expert", type: "multiple", stem: "Which measurements do you take when you commission a boiler?", options: [["a", "Water hardness"], ["b", "Brew pressure"], ["c", "The colour of the cups"]], correct: ["a", "b"] },
];

const familyOf = (courseId) => Object.values(FAMILIES).find((f) => f.variants.some((x) => x.courseId === courseId));
const same = (a, b) => a.length === b.length && [...a].sort().every((x, i) => x === [...b].sort()[i]);

export function createVariantMock({ program, progressRows, courseFor }) {
  /** tenant -> `${groupId}` -> { current, hintDismissed, attempt } */
  const state = new Map();
  const of = (tenant, family) => {
    const key = `${tenant}:${family.groupId}`;
    if (!state.has(key)) state.set(key, { current: family.assigned ? { ...family.assigned } : null, hintDismissed: false, hintTaken: false, attempt: null });
    return state.get(key);
  };
  const picker = (family) => family.variants;
  const effective = (family, s) => s.current?.courseId ?? family.defaultCourseId;
  const stateOf = (tenant, family) => {
    const s = of(tenant, family);
    const showHint = family.hint && !s.hintDismissed && !s.hintTaken;
    return {
      groupId: family.groupId,
      defaultCourseId: family.defaultCourseId,
      variants: picker(family),
      current: s.current ? { ...s.current, decidedAt: "2026-10-20T10:00:00+00:00" } : null,
      effectiveCourseId: effective(family, s),
      enrolled: family.enrolled,
      canChange: family.enrolled && !family.locked,
      lockedReason: !family.enrolled ? "not_enrolled" : family.locked ? "locked" : null,
      placement: { enabled: Boolean(family.placement), available: Boolean(family.placement), lastAttempt: s.attempt },
      hint: showHint ? family.hint : null,
    };
  };
  const unauthenticated = (headers) => !String(headers?.authorization ?? "").startsWith("Bearer ");

  const routes = [
    ["GET", /^\/api\/courses\/(\d+)\/variants$/, ({ match }) => {
      const family = familyOf(Number(match[1]));
      return family ? { groupId: family.groupId, defaultCourseId: family.defaultCourseId, variants: picker(family) } : null;
    }],
    ["GET", /^\/api\/courses\/(\d+)\/variant$/, ({ match, tenant, headers }) => {
      const family = familyOf(Number(match[1]));
      if (unauthenticated(headers)) return { __status: 401, message: "Unauthenticated." };
      return family ? stateOf(tenant, family) : { __status: 404, message: "This course has no variants." };
    }],
    ["PUT", /^\/api\/courses\/(\d+)\/variant$/, ({ match, tenant, body, headers }) => {
      const family = familyOf(Number(match[1]));
      if (!family) return { __status: 404, message: "This course has no variants." };
      if (unauthenticated(headers)) return { __status: 401, message: "Unauthenticated." };
      if (!family.enrolled || family.locked) return { __status: 403, message: "You cannot change the version of this course." };
      const target = Number(body?.course_id);
      const source = body?.source ?? "self";
      if (!family.variants.some((x) => x.courseId === target)) return { __status: 422, message: "That is not a version of this course." };
      const s = of(tenant, family);
      if (source === "hint" && !(family.hint && family.hint.id === body?.hint_id && family.hint.suggestedCourseId === target && !s.hintDismissed)) return { __status: 422, message: "That hint does not suggest this version." };
      if (source === "placement" && !(s.attempt && s.attempt.id === body?.attempt_id && s.attempt.suggestedCourseId === target)) return { __status: 422, message: "That placement result does not suggest this version." };
      if (target === effective(family, s)) return { ...stateOf(tenant, family), changed: false, carried: 0 };
      const reason = source === "placement" ? s.attempt.reason : source === "hint" ? { key: "hint", params: { direction: family.hint.direction, reasons: family.hint.reasons } } : { key: "self" };
      s.current = { courseId: target, source, reason, byOrganisation: false };
      if (source === "hint") s.hintTaken = true;
      // completion carries over for the lessons both versions share: the mock says 4 for the expert version, 2 otherwise
      return { ...stateOf(tenant, family), changed: true, carried: target % 10 === 3 ? 4 : 2 };
    }],
    ["POST", /^\/api\/courses\/(\d+)\/variant\/hints\/(\d+)\/dismiss$/, ({ match, tenant }) => {
      const family = familyOf(Number(match[1]));
      if (!family?.hint || family.hint.id !== Number(match[2])) return { __status: 404, message: "Unknown hint." };
      of(tenant, family).hintDismissed = true;
      return stateOf(tenant, family);
    }],
    ["GET", /^\/api\/courses\/(\d+)\/placement$/, ({ match }) => {
      const family = familyOf(Number(match[1]));
      if (!family?.placement) return { __status: 404, message: "There is no placement check for this course." };
      return { questions: PLACEMENT.map((q) => ({ id: q.id, type: q.type, stem: q.stem, options: q.options.map(([id, text]) => ({ id, text })) })) };
    }],
    ["POST", /^\/api\/courses\/(\d+)\/placement\/attempts$/, ({ match, tenant, body }) => {
      const family = familyOf(Number(match[1]));
      if (!family?.placement) return { __status: 404, message: "There is no placement check for this course." };
      const scores = {};
      for (const q of PLACEMENT) {
        const given = (body?.answers?.[q.id] ?? body?.answers?.[String(q.id)] ?? []).map(String);
        scores[q.level] ??= { correct: 0, total: 0 };
        scores[q.level].total += 1;
        if (same(given, q.correct)) scores[q.level].correct += 1;
      }
      // the API's rule: climb while at least 70% are right; the lowest level is the floor
      let level = null;
      let basis = {};
      for (const l of LEVELS) {
        if (!scores[l]) continue;
        level ??= l;
        if (scores[l].correct / scores[l].total >= 0.7) {
          level = l;
          basis = { level: l, ...scores[l] };
        } else {
          if (!basis.level) basis = { level: l, ...scores[l] };
          break;
        }
      }
      const suggested = family.variants.find((x) => x.level === level)?.courseId ?? null;
      const attempt = {
        id: `01JMOCKATTEMPT${String(Date.now()).slice(-12)}`.slice(0, 26),
        scores,
        suggestedCourseId: suggested,
        reason: { key: "placement", params: { level, correct: basis.correct ?? 0, total: basis.total ?? 0, basisLevel: basis.level ?? null } },
        createdAt: new Date().toISOString(),
      };
      of(tenant, family).attempt = attempt;
      return attempt;
    }],
    // the courses of the families: the same program as course 1 under another id and title
    ...Object.values(FAMILIES).flatMap((family) =>
      family.variants.flatMap(({ courseId, title, description }) => {
        const course = (tenant) => courseFor(tenant, { ...program, id: courseId, title, summary: description, target_group: description });
        return [
          ["GET", new RegExp(`^/api/courses/${courseId}$`), ({ tenant }) => course(tenant)],
          ["GET", new RegExp(`^/api/courses/${courseId}/program$`), ({ tenant }) => (family.enrolled ? course(tenant) : { __status: 403, message: "You are not enrolled in this course." })],
          ["GET", new RegExp(`^/api/courses/progress/${courseId}$`), () => progressRows],
        ];
      })
    ),
  ];
  return {
    routes,
    reset: (tenant) => {
      for (const key of [...state.keys()]) if (key.startsWith(`${tenant}:`)) state.delete(key);
    },
  };
}
