/**
 * Demo-card texts of the platform landing in Polish and Simplified Chinese. The academy names stay as
 * they are (they are product names; the tenants' own sites are in English, Poland, Measured also in
 * Polish); the short styles and descriptions, the fact labels and the button labels are translated.
 */
import type { Locale } from "./locales.ts";

export interface DemoStyle {
  label: string;
  text: string;
}
export interface DemoCopy {
  style: Record<string, DemoStyle>;
  facts: { style: string; lessons: string; topics: string; price: string; free: string };
  open: { learner: string; admin: string };
}

export const DEMO_COPY: Record<Exclude<Locale, "en">, DemoCopy> = {
  pl: {
    style: {
      coffee: { label: "Redakcyjny · we własnym tempie", text: "Powolny, magazynowy kurs od ziarna do filiżanki: film, podcast, interaktywne diagramy, quiz i projekt końcowy." },
      oncall: { label: "Kohorta · ciemne narzędzie pro", text: "Dowodzenie incydentem dla inżynierów platformy: 4-tygodniowa kohorta z symulatorem awarii, ćwiczeniem na żywo i recenzowanym postmortem." },
      nightsky: { label: "Grywalizacja · dzieci 10–14 lat", text: "Siedem krótkich misji do gwiazd z robotem Orbim, odznakami i dyplomem do wydruku." },
      gravity: { label: "Symulacja 3D", text: "Układ Słoneczny na prawdziwych danych, w którym się uczysz." },
      poland: { label: "Mapa i wykresy", text: "35 lat Polski w cytowanych danych publicznych, po angielsku i po polsku." },
      ulam: { label: "Matematyka i historia", text: "Ulam, Lwowska Szkoła Matematyczna i Księga Szkocka, z pięcioma interaktywnymi ćwiczeniami na żywo." },
    },
    facts: { style: "Styl", lessons: "Lekcje", topics: "Tematy", price: "Cena", free: "Bezpłatny" },
    open: { learner: "Otwórz jako uczeń", admin: "Otwórz jako admin" },
  },
  zh: {
    style: {
      coffee: { label: "杂志风格 · 自定进度", text: "从咖啡豆到杯中的慢节奏杂志式课程：视频、播客、交互图解、测验和结业项目。" },
      oncall: { label: "同期班 · 深色专业工具", text: "面向平台工程师的故障指挥课：为期 4 周的同期班，含故障模拟器、实时演练和经过评审的复盘报告。" },
      nightsky: { label: "游戏化 · 10–14 岁", text: "七个星空小任务，由机器人向导 Orbi 带路，附徽章和可打印的结业证书。" },
      gravity: { label: "3D 模拟", text: "基于真实数据的太阳系，在其中边玩边学。" },
      poland: { label: "地图与图表", text: "用有出处的公开数据看波兰 35 年，提供英文和波兰文。" },
      ulam: { label: "数学与历史", text: "乌拉姆、利沃夫学派与《苏格兰书》，含五个可在线操作的交互练习。" },
    },
    facts: { style: "风格", lessons: "课时", topics: "主题", price: "价格", free: "免费" },
    open: { learner: "以学员身份打开", admin: "以管理员身份打开" },
  },
};
