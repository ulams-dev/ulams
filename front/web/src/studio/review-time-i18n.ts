/**
 * Strings of the review time indicator and its notice page in English, Polish and Simplified Chinese (glossary in
 * src/i18n/GLOSSARY.md). The notice names exactly what is stored; the same list is in the operator docs.
 */
export { INSIGHTS_LOCALES as REVIEW_LOCALES, pickInsightsLocale as pickReviewLocale, type InsightsLocale as ReviewLocale } from "./insights-i18n.ts";
import type { InsightsLocale as ReviewLocale } from "./insights-i18n.ts";

export interface ReviewStrings {
  htmlLang: string;
  indicator: string;
  indicatorLink: string;
  pageTitle: string;
  heading: string;
  languageLabel: string;
  intro: string;
  measuredTitle: string;
  measured: string[];
  storedTitle: string;
  stored: string[];
  notStoredTitle: string;
  notStored: string[];
  shownTitle: string;
  shown: string[];
  retentionTitle: string;
  retention: string;
  controlTitle: string;
  control: string;
  smallTeams: string;
  back: string;
}

const en: ReviewStrings = {
  htmlLang: "en",
  indicator: "Review time is recorded for this course (anonymous)",
  indicatorLink: "What is recorded",
  pageTitle: "Review time",
  heading: "How review time is recorded",
  languageLabel: "Language",
  intro: "Your administrator turned on review time. It adds up the minutes people actively spend on a course in the studio and in the course editor, so the academy can say how long a course took to produce. It is anonymous by design.",
  measuredTitle: "What is measured",
  measured: [
    "Whole minutes of active screen time on one course, in the studio or in the admin course editor.",
    "A minute counts when this tab is visible and in front, and you used the keyboard, the pointer or scrolled in the last two minutes.",
    "Time with the tab hidden, the window in the background or no input is not counted.",
  ],
  storedTitle: "What is stored",
  stored: ["The course, the start of the minute, the place (studio or admin) and a random code made when the page was opened.", "The code is kept only in the page's memory, so two visits cannot be linked to each other."],
  notStoredTitle: "What is not stored",
  notStored: ["Your name or account, your address, your browser or device, and which lesson, block or question you looked at."],
  shownTitle: "What is shown",
  shown: ["Author-minutes per course, per day and in total.", "Never per person. Nothing in the API or the exports can single out an author."],
  retentionTitle: "How long it is kept",
  retention: "The minutes are totalled per course and day every night and the single minutes are deleted after 7 days. The daily totals stay as long as the course.",
  controlTitle: "Who decides",
  control: "Your administrator can switch it off at once, and delete everything recorded so far, in Settings. Generation time, cost and counts are not personal and stay.",
  smallTeams: "A course with one author shows that author's time, because there is nobody else. In a small team a total can still point to a person.",
  back: "Back to the studio",
};

const pl: ReviewStrings = {
  htmlLang: "pl",
  indicator: "Czas przeglądu jest zapisywany dla tego kursu (anonimowo)",
  indicatorLink: "Co jest zapisywane",
  pageTitle: "Czas przeglądu",
  heading: "Jak zapisywany jest czas przeglądu",
  languageLabel: "Język",
  intro: "Administrator włączył zapisywanie czasu przeglądu. Sumuje ono minuty, które osoby aktywnie spędzają nad kursem w studiu i w edytorze kursu, aby akademia mogła powiedzieć, ile trwało przygotowanie kursu. Z założenia jest anonimowe.",
  measuredTitle: "Co jest mierzone",
  measured: [
    "Pełne minuty aktywnej pracy przy ekranie nad jednym kursem, w studiu lub w edytorze kursu w panelu.",
    "Minuta się liczy, gdy ta karta jest widoczna i na wierzchu, a w ciągu ostatnich dwóch minut użyto klawiatury, wskaźnika albo przewijano.",
    "Czas z ukrytą kartą, oknem w tle lub bez żadnej aktywności nie jest liczony.",
  ],
  storedTitle: "Co jest zapisywane",
  stored: ["Kurs, początek minuty, miejsce (studio lub panel) i losowy kod utworzony przy otwarciu strony.", "Kod jest trzymany tylko w pamięci strony, więc dwóch wizyt nie da się ze sobą powiązać."],
  notStoredTitle: "Czego nie zapisujemy",
  notStored: ["Imienia ani konta, adresu, przeglądarki ani urządzenia, ani tego, którą lekcję, blok lub pytanie oglądano."],
  shownTitle: "Co jest pokazywane",
  shown: ["Minuty autorskie na kurs, na dzień i łącznie.", "Nigdy na osobę. Nic w API ani w eksportach nie wskazuje konkretnego autora."],
  retentionTitle: "Jak długo jest przechowywane",
  retention: "Co noc minuty są sumowane na kurs i dzień, a pojedyncze minuty usuwane po 7 dniach. Sumy dzienne zostają tak długo jak kurs.",
  controlTitle: "Kto o tym decyduje",
  control: "Administrator może to natychmiast wyłączyć i usunąć wszystko, co dotąd zapisano, w Ustawieniach. Czas generowania, koszt i liczby nie są danymi osobowymi i zostają.",
  smallTeams: "Kurs z jednym autorem pokazuje czas tego autora, bo nie ma nikogo innego. W małym zespole suma nadal może wskazywać osobę.",
  back: "Wróć do studia",
};

const zh: ReviewStrings = {
  htmlLang: "zh-Hans",
  indicator: "正在为这门课程记录审阅时间（匿名）",
  indicatorLink: "记录了什么",
  pageTitle: "审阅时间",
  heading: "审阅时间如何记录",
  languageLabel: "语言",
  intro: "管理员已开启审阅时间记录。它累计人们在工作室和课程编辑器中积极处理某门课程的分钟数，使学院能够说明制作一门课程用了多长时间。它在设计上是匿名的。",
  measuredTitle: "测量什么",
  measured: ["在工作室或管理后台课程编辑器中，针对一门课程的完整活跃屏幕分钟数。", "当此标签页可见且位于前台，并且你在最近两分钟内使用了键盘、指针或滚动时，这一分钟才会计入。", "标签页隐藏、窗口在后台或没有任何操作的时间不计入。"],
  storedTitle: "存储什么",
  stored: ["课程、该分钟的起点、所在位置（工作室或后台）以及打开页面时生成的随机代码。", "该代码只保存在页面内存中，因此无法把两次访问关联起来。"],
  notStoredTitle: "不存储什么",
  notStored: ["你的姓名或账号、地址、浏览器或设备，以及你查看了哪个课时、内容块或题目。"],
  shownTitle: "显示什么",
  shown: ["每门课程、每天和总计的作者分钟数。", "绝不按个人显示。API 和导出文件中的任何内容都无法指向某位作者。"],
  retentionTitle: "保存多久",
  retention: "每晚按课程和日期汇总分钟数，单个分钟记录在 7 天后删除。每日合计与课程同时保留。",
  controlTitle: "由谁决定",
  control: "管理员可以在设置中立即关闭记录，并删除迄今记录的全部内容。生成时间、成本和数量不属于个人数据，会保留。",
  smallTeams: "只有一位作者的课程会显示这位作者的时间，因为没有其他人。在小团队中，合计数据仍可能指向某个人。",
  back: "返回工作室",
};

export const REVIEW_STRINGS: Record<ReviewLocale, ReviewStrings> = { en, pl, zh };
