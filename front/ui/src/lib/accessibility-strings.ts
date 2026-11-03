/**
 * Interface text of the per-tenant accessibility statement (`/accessibility`) in English, Polish and
 * Simplified Chinese. The statement is a template: the academy's name and contact come from its public
 * settings, and the operator (the service provider under the European Accessibility Act) owns the facts.
 * `{name}` is replaced with the academy name.
 */
import type { TrainingLocale } from "./training-strings.ts";
import { trainingLocale } from "./training-strings.ts";

export type AccessibilityLocale = TrainingLocale;

export interface AccessibilityStrings {
  title: string;
  lead: string;
  operatorHeading: string;
  operatorText: string;
  targetHeading: string;
  targetText: string;
  testedHeading: string;
  tested: string[];
  featuresHeading: string;
  features: string[];
  limitsHeading: string;
  limits: string[];
  notesHeading: string;
  feedbackHeading: string;
  feedbackText: string;
  feedbackMail: string;
  feedbackNoMail: string;
  enforcementHeading: string;
  enforcementDefault: string;
  /** `{date}` is replaced. */
  updated: string;
  breadcrumbHome: string;
  footerLink: string;
}

const en: AccessibilityStrings = {
  title: "Accessibility statement",
  lead: "{name} wants everyone to be able to learn here, with a keyboard, a screen reader, a phone or a zoomed screen.",
  operatorHeading: "Who is responsible",
  operatorText:
    "This statement is published by {name}, the service provider of this academy under the European Accessibility Act. The academy runs on ulams, which is the same software for every academy; {name} owns the facts on this page, including the contact details and the content it publishes.",
  targetHeading: "What we aim for",
  targetText:
    "The learner pages of this academy are built to meet WCAG 2.2 level AA, which covers the accessibility requirements of EN 301 549 version 3.2.1 for web content (clause 9). That is a target we test against, not a certificate: where something falls short, it is listed below.",
  testedHeading: "How it is tested",
  tested: [
    "Every learner screen is scanned automatically with axe-core against WCAG 2.2 A and AA, in light and dark themes, on a desktop and on a 360 px phone.",
    "Keyboard journeys check that every control can be reached, operated and left, that focus is visible and never hidden behind a sticky bar, and that targets are at least 24 by 24 pixels.",
    "Pages are checked with the system setting for reduced motion switched on: nothing keeps moving.",
    "Text, spacing and reflow are checked at 200 % zoom and at 320 CSS pixels wide, without sideways scrolling.",
    "Screen reader semantics (labels, status in words, one polite announcement area per screen) are covered by tests; manual review with a screen reader is done by people, not claimed by the tests.",
  ],
  featuresHeading: "What helps you",
  features: [
    "A skip link is the first stop on every page, and every control works with the keyboard alone.",
    "The Lesson view lets you change text spacing, show a reading in parts, switch to step by step navigation and open hints at the start of an exercise.",
    "Animation stops when your system asks for reduced motion.",
    "Status is always written out (for example “Overdue by 3 days”), never shown by colour alone.",
    "The interface is available in English, Polish and Chinese; a course is shown in its own language.",
    "AI-generated help is labelled, explains why you see it and cites its sources.",
  ],
  limitsHeading: "Known limitations",
  limits: [
    "Interactive content from other providers (H5P, SCORM and cmi5 packages, LTI tools, embedded videos and documents) is shown in a frame and is not controlled by this academy’s software; its accessibility depends on its author.",
    "Content written by the model (extra help, tutor answers, generated course material) is checked by validators and by the builder’s accessibility critic, which catch many problems but do not guarantee a result; authors review what they publish.",
    "Files that authors upload (PDFs, images, videos) are accessible only if the author prepared them that way, for example with alternative text and captions.",
  ],
  notesHeading: "Notes from the academy",
  feedbackHeading: "Tell us about a barrier",
  feedbackText: "If something here is hard or impossible for you to use, tell us which page you were on, what you wanted to do and what stopped you. Say how you would like us to reply.",
  feedbackMail: "Write to",
  feedbackNoMail: "The academy has not published a contact address for accessibility yet. Use the contact form or the address on the home page, and mention accessibility.",
  enforcementHeading: "If you are not satisfied with our reply",
  enforcementDefault: "You can complain to the national body that enforces accessibility rules in your country (in the European Union, the market surveillance authority named under the European Accessibility Act).",
  updated: "Last updated {date}.",
  breadcrumbHome: "Home",
  footerLink: "Accessibility",
};

const pl: AccessibilityStrings = {
  title: "Deklaracja dostępności",
  lead: "{name} chce, aby każdy mógł się tu uczyć: z klawiatury, z czytnikiem ekranu, na telefonie lub na powiększonym ekranie.",
  operatorHeading: "Kto za to odpowiada",
  operatorText:
    "Deklarację publikuje {name}, dostawca usługi tej akademii w rozumieniu europejskiego aktu o dostępności. Akademia działa na oprogramowaniu ulams, takim samym dla każdej akademii; {name} odpowiada za fakty podane na tej stronie, w tym dane kontaktowe i publikowane treści.",
  targetHeading: "Do czego dążymy",
  targetText:
    "Strony dla uczących się w tej akademii są tworzone tak, aby spełniały WCAG 2.2 na poziomie AA, co obejmuje wymagania dostępności normy EN 301 549 w wersji 3.2.1 dla treści internetowych (punkt 9). To cel, względem którego testujemy, a nie certyfikat: braki wymieniamy poniżej.",
  testedHeading: "Jak to sprawdzamy",
  tested: [
    "Każdy ekran dla uczących się jest automatycznie skanowany narzędziem axe-core względem WCAG 2.2 A i AA, w jasnym i ciemnym motywie, na komputerze i na telefonie o szerokości 360 px.",
    "Ścieżki z klawiatury sprawdzają, czy do każdego elementu można dotrzeć, obsłużyć go i go opuścić, czy fokus jest widoczny i nigdy nie chowa się pod przyklejonym paskiem, a cele mają co najmniej 24 na 24 piksele.",
    "Strony są sprawdzane z włączonym systemowym ustawieniem ograniczenia ruchu: nic się nie rusza w nieskończoność.",
    "Tekst, odstępy i zawijanie sprawdzamy przy powiększeniu 200 % i szerokości 320 pikseli CSS, bez przewijania w poziomie.",
    "Semantykę dla czytników ekranu (etykiety, stan opisany słowami, jedno uprzejme pole komunikatów na ekran) obejmują testy; przegląd z czytnikiem ekranu wykonują ludzie i testy tego nie zastępują.",
  ],
  featuresHeading: "Co ci pomaga",
  features: [
    "Link pomijający nawigację jest pierwszym punktem na każdej stronie, a każdy element działa samą klawiaturą.",
    "Widok lekcji pozwala zmienić odstępy tekstu, pokazać lekturę we fragmentach, przejść do nawigacji krok po kroku i otworzyć podpowiedzi na początku ćwiczenia.",
    "Animacje zatrzymują się, gdy system prosi o ograniczenie ruchu.",
    "Stan jest zawsze opisany słowami (na przykład „Zaległe o 3 dni”), nigdy samym kolorem.",
    "Interfejs jest dostępny po angielsku, polsku i chińsku; kurs jest pokazywany w swoim języku.",
    "Pomoc wygenerowana przez AI jest oznaczona, wyjaśnia, dlaczego ją widzisz, i podaje źródła.",
  ],
  limitsHeading: "Znane ograniczenia",
  limits: [
    "Treści interaktywne innych dostawców (pakiety H5P, SCORM i cmi5, narzędzia LTI, osadzone filmy i dokumenty) są pokazywane w ramce i nie podlegają oprogramowaniu tej akademii; ich dostępność zależy od autora.",
    "Treści pisane przez model (dodatkowa pomoc, odpowiedzi tutora, wygenerowane materiały kursu) sprawdzają walidatory i krytyk dostępności kreatora, które wychwytują wiele problemów, ale niczego nie gwarantują; autorzy przeglądają to, co publikują.",
    "Pliki wgrywane przez autorów (PDF, obrazy, filmy) są dostępne tylko wtedy, gdy autor tak je przygotował, na przykład z tekstem alternatywnym i napisami.",
  ],
  notesHeading: "Uwagi od akademii",
  feedbackHeading: "Zgłoś barierę",
  feedbackText: "Jeśli coś tutaj jest dla ciebie trudne lub niemożliwe w użyciu, napisz, na której stronie byłeś(-aś), co chciałeś(-aś) zrobić i co ci przeszkodziło. Napisz też, jak chcesz otrzymać odpowiedź.",
  feedbackMail: "Napisz na",
  feedbackNoMail: "Akademia nie podała jeszcze adresu kontaktowego w sprawach dostępności. Skorzystaj z formularza kontaktowego lub adresu ze strony głównej i wspomnij o dostępności.",
  enforcementHeading: "Jeśli odpowiedź cię nie satysfakcjonuje",
  enforcementDefault: "Możesz złożyć skargę do krajowego organu egzekwującego przepisy o dostępności (w Unii Europejskiej do organu nadzoru rynku wskazanego na podstawie europejskiego aktu o dostępności).",
  updated: "Ostatnia aktualizacja: {date}.",
  breadcrumbHome: "Strona główna",
  footerLink: "Dostępność",
};

const zh: AccessibilityStrings = {
  title: "无障碍声明",
  lead: "{name} 希望每个人都能在这里学习，无论使用键盘、屏幕阅读器、手机还是放大后的屏幕。",
  operatorHeading: "责任方",
  operatorText: "本声明由 {name} 发布，{name} 是本学院依据《欧洲无障碍法案》的服务提供者。学院运行在 ulams 之上，所有学院使用同一套软件；本页所述事实，包括联系方式和发布的内容，由 {name} 负责。",
  targetHeading: "我们的目标",
  targetText: "本学院面向学员的页面按 WCAG 2.2 AA 级构建，涵盖 EN 301 549 3.2.1 版对网页内容的无障碍要求（第 9 条）。这是我们据以测试的目标，而不是认证：不足之处列在下方。",
  testedHeading: "如何测试",
  tested: [
    "每个学员界面都用 axe-core 按 WCAG 2.2 A 和 AA 自动扫描，涵盖浅色和深色主题，以及桌面和 360 px 手机宽度。",
    "键盘流程检查每个控件都能到达、操作和离开，焦点清晰可见且不会被固定栏遮住，点击目标至少为 24×24 像素。",
    "在开启系统“减少动态效果”设置的情况下检查页面：没有无休止的动画。",
    "在 200% 缩放和 320 CSS 像素宽度下检查文字、间距和重排，不出现横向滚动。",
    "屏幕阅读器语义（标签、用文字表达的状态、每屏一个礼貌播报区）由测试覆盖；用屏幕阅读器的人工检查由人完成，测试不能代替。",
  ],
  featuresHeading: "对你有帮助的功能",
  features: [
    "每个页面的第一个停靠点都是跳转链接，所有控件仅用键盘即可操作。",
    "“课程视图”可调整文字间距、分段显示阅读内容、切换为逐步导航，并在练习开始时展开提示。",
    "当系统要求减少动态效果时，动画会停止。",
    "状态始终用文字写明（例如“逾期 3 天”），绝不只靠颜色表示。",
    "界面提供英语、波兰语和中文；课程以其自身语言显示。",
    "AI 生成的帮助有标注，说明你看到它的原因，并注明来源。",
  ],
  limitsHeading: "已知限制",
  limits: [
    "其他提供方的交互内容（H5P、SCORM 和 cmi5 包、LTI 工具、嵌入的视频和文档）显示在框架中，不受本学院软件控制；其无障碍程度取决于作者。",
    "由模型撰写的内容（额外帮助、导师回答、生成的课程材料）由校验器和课程构建器的无障碍评审检查，能发现许多问题，但不作保证；作者会审阅其发布的内容。",
    "作者上传的文件（PDF、图片、视频）只有在作者做过相应准备时才具备无障碍性，例如添加替代文本和字幕。",
  ],
  notesHeading: "学院的补充说明",
  feedbackHeading: "告诉我们遇到的障碍",
  feedbackText: "如果这里有内容你难以使用或无法使用，请告诉我们你所在的页面、想做什么以及什么阻碍了你，并说明你希望如何收到回复。",
  feedbackMail: "请写信至",
  feedbackNoMail: "学院尚未公布无障碍事务的联系地址。请使用联系表单或首页上的地址，并注明与无障碍有关。",
  enforcementHeading: "如果你对我们的回复不满意",
  enforcementDefault: "你可以向你所在国家负责执行无障碍规定的机构投诉（在欧盟，为依据《欧洲无障碍法案》指定的市场监督机构）。",
  updated: "最后更新：{date}。",
  breadcrumbHome: "首页",
  footerLink: "无障碍",
};

const BY_LOCALE: Record<AccessibilityLocale, AccessibilityStrings> = { en, pl, zh };

export const accessibilityStrings = (lang: string | null | undefined): AccessibilityStrings => BY_LOCALE[trainingLocale(lang)];
