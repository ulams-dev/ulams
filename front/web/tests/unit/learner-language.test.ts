import { describe, expect, it } from "vitest";
import { courseStanding } from "@ulams/sdk";
import { contentLang, learnerPageLocale, tenantLanguage } from "../../src/lib/locale.ts";
import { courseHeaderDoc, purchaseDoc, syllabusDoc, topicDoc } from "../../src/lib/page-docs.ts";
import { standingNote } from "../../src/lib/standing.ts";
import { errorMessage } from "../../src/lib/social-login.ts";
import { failureText, formatDate, kindsFor, parseCreateForm, presetsFor, summariseScopes } from "../../src/lib/tokens.ts";
import { courseModel, siteModel } from "../../src/lib/view-model.ts";
import { COFFEE_PROGRAM, FIXTURES, raw } from "./fixtures.ts";

const settings = (language?: unknown) => ({ global: { language } });

describe("the language of a learner page (ADR 0106, tenant default)", () => {
  it("follows the course, then the tenant default, then English", () => {
    expect(learnerPageLocale("pl", settings("zh"))).toBe("pl");
    expect(learnerPageLocale("Chinese", settings("pl"))).toBe("zh");
    expect(learnerPageLocale(null, settings("pl"))).toBe("pl");
    expect(learnerPageLocale(undefined, settings("zh"))).toBe("zh");
    expect(learnerPageLocale(null, settings("de"))).toBe("en");
    expect(learnerPageLocale(null, settings(42))).toBe("en");
    expect(learnerPageLocale(null, null)).toBe("en");
    // a course in a language the interface does not have reads English chrome
    expect(learnerPageLocale("de", settings("pl"))).toBe("en");
  });

  it("reads the tenant default from the public settings", () => {
    expect(tenantLanguage(settings("pl"))).toBe("pl");
    expect(tenantLanguage(settings(undefined))).toBeNull();
    expect(tenantLanguage(undefined)).toBeNull();
  });

  it("marks course content that is not in the interface language", () => {
    expect(contentLang("de", "en")).toBe("de");
    expect(contentLang("de_AT", "pl")).toBe("de-AT");
    expect(contentLang("pl-PL", "en")).toBe("pl-PL");
    expect(contentLang("Polish", "en")).toBe("pl");
    expect(contentLang("pl", "pl")).toBeUndefined();
    expect(contentLang("English", "en")).toBeUndefined();
    expect(contentLang("zh-Hans", "zh")).toBeUndefined();
    expect(contentLang("Klingon speakers", "en")).toBeUndefined();
    expect(contentLang(null, "pl")).toBeUndefined();
    expect(contentLang("", "pl")).toBeUndefined();
  });
});

describe("course page documents in the language", () => {
  const model = courseModel(FIXTURES.coffee.course, "EUR");
  const site = siteModel(raw("coffee"), { slug: "coffee", adminUrl: "http://admin" });

  it("keeps the English wording as it was", () => {
    const header = courseHeaderDoc("coffee", model, undefined, "/learn/1");
    expect(header.props?.primaryCta).toEqual({ label: "Start learning", href: "/learn/1" });
    expect(purchaseDoc(model, site, "/learn/1").props?.title).toBe("Demo access");
    expect(syllabusDoc("nightsky", model).props?.title).toBe("Your mission map");
  });

  it("translates the header, the syllabus and the purchase card", () => {
    const pl = courseHeaderDoc("coffee", model, 20, "/learn/1", "", "pl");
    expect(pl.props?.primaryCta).toEqual({ label: "Kontynuuj naukę", href: "/learn/1" });
    expect(String(pl.props?.eyebrow)).toMatch(/lekcji$/);
    expect((pl.props?.facts as Array<{ label: string }>).map((f) => f.label)).toContain("Tematy");
    expect(syllabusDoc("nightsky", model, "pl").props?.title).toBe("Twoja mapa misji");
    expect(String(syllabusDoc("coffee", model, "zh").props?.totalLabel)).toMatch(/节课 · 18 个主题/);
    expect(purchaseDoc(model, site, "/learn/1", "pl").props?.cta).toEqual({ label: "Otwórz kurs", href: "/learn/1" });
    const items = purchaseDoc(model, site, "/learn/1", "zh").props?.items as string[];
    expect(items[0]).toBe("6 节课, 18 个主题");
    expect(items.join(" ")).not.toMatch(/lessons|Progress saved/);
  });
});

describe("topic cards in the language", () => {
  const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" };
  const locked = (locale: "en" | "pl" | "zh") =>
    topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic: { ...COFFEE_PROGRAM.lessons![0]!.topics![0]!, topicable: undefined }, access: false, nextHref: "/", locale });

  it("says what the locked topic card says, in each language", () => {
    const card = (locale: "en" | "pl" | "zh") => locked(locale).children![0]!.props as { title: string; cta: { label: string } };
    expect(card("en").title).toBe("Locked in this demo account");
    expect(card("pl").title).toBe("Zablokowane na tym koncie demo");
    expect(card("pl").cta.label).toBe("Wróć do kursu");
    expect(card("zh").cta.label).toBe("返回课程");
  });
});

describe("the what-is-next line in the language", () => {
  const course = {
    lessons: [
      {
        id: 1,
        title: "I",
        order: 1,
        topics: [
          { id: 1, title: "Origins", lesson_id: 1, order: 1, topicable_type: "X\\Video", duration: "5 min" },
          { id: 2, title: "Roasting", lesson_id: 1, order: 2, topicable_type: "X\\RichText", duration: "70 min" },
          { id: 3, title: "Tasting", lesson_id: 1, order: 3, topicable_type: "X\\RichText" },
        ],
      },
    ],
  };
  const done = (...ids: number[]) => ids.map((id) => ({ id, topic_id: id, status: 1, seconds: 0 }) as never);

  it("writes it in Polish and Chinese", () => {
    expect(standingNote(courseStanding(course, []), "pl")).toBe("3 tematy · łącznie około 1 godz. 15 min");
    expect(standingNote(courseStanding(course, done(1)), "pl")).toBe("Dalej: Roasting · zostało około 1 godz. 10 min");
    expect(standingNote(courseStanding(course, done(1, 2, 3)), "zh")).toBe("课程已完成。可以回顾任意一节课，或查看你的证书。");
    expect(standingNote(courseStanding(course, []), "zh")).toBe("3 个主题 · 总计约 1 小时 15 分钟");
  });
});

describe("the account page text in the language", () => {
  it("lists the token presets and kinds in the language, with the scopes the API expects", () => {
    expect(presetsFor("pl").map((p) => p.scope)).toEqual(["@read-only", "@author", "@ci", "@learner", "@admin"]);
    expect(presetsFor("pl")[0]!.label).toBe("Tylko odczyt");
    expect(presetsFor("zh")[4]!.label).toBe("我的账号能做的一切");
    expect(presetsFor()[0]!.label).toBe("Read only");
    expect(kindsFor("pl").map((k) => k.value)).toEqual(["cli", "agent", "ci", "integration"]);
    expect(kindsFor("zh")[1]!.label).toBe("AI 智能体");
  });

  it("validates the create form with messages in the language", () => {
    const form = (name: string, access = "ci") => {
      const f = new FormData();
      f.append("name", name);
      f.append("access", access);
      return f;
    };
    expect(parseCreateForm(form(""), "pl")).toEqual({ ok: false, error: "Nadaj tokenowi nazwę, aby później go rozpoznać." });
    expect(parseCreateForm(form("a<b"), "zh")).toEqual({ ok: false, error: "名称不能包含 < 或 >。" });
    expect(parseCreateForm(form("laptop", "nope"), "pl")).toEqual({ ok: false, error: "Wybierz, co token może robić." });
    expect(parseCreateForm(form("laptop"), "pl")).toMatchObject({ ok: true, input: { scopes: ["@ci"] } });
  });

  it("writes scopes, dates and failures in the language", () => {
    expect(summariseScopes(["courses:write", "users:read"], "pl")).toBe("Zmiany: courses · Odczyt: users");
    expect(summariseScopes(["*"], "zh")).toBe("你的账号能做的一切");
    expect(summariseScopes([], "pl")).toBe("Brak zakresów");
    expect(formatDate(null, "pl")).toBe("nigdy");
    expect(formatDate("nope", "zh")).toBe("未知");
    expect(formatDate("2026-10-01T10:00:00+00:00", "pl")).toContain("paź");
    expect(formatDate("2026-10-01T10:00:00+00:00")).toBe("Oct 1, 2026");
    expect(failureText("throttled", "pl")).toBe("Zbyt wiele prób. Odczekaj minutę i spróbuj ponownie.");
  });

  it("words the social sign-in failures in the language", () => {
    expect(errorMessage("provider_denied")).toBe("Sign-in was cancelled.");
    expect(errorMessage("provider_denied", "pl")).toBe("Logowanie zostało anulowane.");
    expect(errorMessage("something-else", "zh")).toBe("登录失败，请重试。");
    expect(errorMessage(null, "pl")).toBe("Nie udało się zalogować. Spróbuj ponownie.");
  });
});
