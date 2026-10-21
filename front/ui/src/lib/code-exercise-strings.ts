/**
 * Interface strings of the code exercise lesson (ADR 0170) in English, Polish and Simplified Chinese. The
 * element runs in the browser, so the language comes from the element's `lang` attribute or the page's
 * `<html lang>`; without a match, English. The task text, file names, test names and the messages of
 * failed tests come from the exercise and are never translated here.
 */
export interface CodeStrings {
  task: string;
  files: string;
  /** Accessible name of the editor of one file; `{path}` is replaced. */
  fileLabel: string;
  tests: string;
  testsHint: string;
  run: string;
  running: string;
  reset: string;
  resetConfirm: string;
  resetYes: string;
  resetKeep: string;
  resetDone: string;
  shortcut: string;
  results: string;
  noResults: string;
  loading: string;
  unavailable: string;
  /** `{passed}` and `{total}` are replaced. */
  summaryPartial: string;
  /** `{total}` is replaced. */
  summaryAll: string;
  passed: string;
  failed: string;
  error: string;
  buildError: string;
  timeout: string;
  stopped: string;
  noTests: string;
  output: string;
  outputCut: string;
  /** `{n}` is replaced. */
  saved: string;
  notSaved: string;
  practiceNote: string;
  /** `{n}` and `{best}` are replaced. */
  attempts: string;
  completed: string;
  draftRestored: string;
}

const en: CodeStrings = {
  task: "The task",
  files: "Your code",
  fileLabel: "Code of {path}",
  tests: "The tests",
  testsHint: "These tests run in your browser when you press Run tests. You can read them, but not change them here.",
  run: "Run tests",
  running: "Running the tests…",
  reset: "Start over",
  resetConfirm: "Start over and lose your changes to every file?",
  resetYes: "Yes, start over",
  resetKeep: "Keep my code",
  resetDone: "Your code is back to the starting point.",
  shortcut: "In an editor, press Ctrl+Enter (Command+Enter on a Mac) to run the tests. Press Tab to move on; the editor does not trap the keyboard.",
  results: "Results",
  noResults: "Run the tests to see the results here.",
  loading: "Starting the code runner…",
  unavailable: "The code runner could not start, so the tests cannot run here. You can still read the task and edit your code.",
  summaryPartial: "{passed} of {total} tests passed.",
  summaryAll: "All {total} tests passed.",
  passed: "Passed",
  failed: "Failed",
  error: "Error",
  buildError: "Your code could not be built:",
  timeout: "The tests took too long and were stopped. Look for a loop that never ends.",
  stopped: "The test run was stopped.",
  noTests: "No tests ran.",
  output: "Console output",
  outputCut: "The output was cut short.",
  saved: "Attempt {n} saved.",
  notSaved: "The result could not be saved. Run the tests again to retry.",
  practiceNote: "This is practice: the result is kept with your attempts and moves your progress, but it does not count towards a certificate.",
  attempts: "Attempts so far: {n}. Best score: {best}%.",
  completed: "You completed this exercise.",
  draftRestored: "Your earlier changes were restored.",
};

const pl: CodeStrings = {
  task: "Zadanie",
  files: "Twój kod",
  fileLabel: "Kod pliku {path}",
  tests: "Testy",
  testsHint: "Te testy uruchamiają się w Twojej przeglądarce po naciśnięciu „Uruchom testy”. Możesz je czytać, ale nie możesz ich tu zmieniać.",
  run: "Uruchom testy",
  running: "Testy są uruchamiane…",
  reset: "Zacznij od nowa",
  resetConfirm: "Zacząć od nowa i stracić zmiany we wszystkich plikach?",
  resetYes: "Tak, zacznij od nowa",
  resetKeep: "Zachowaj mój kod",
  resetDone: "Kod wrócił do punktu wyjścia.",
  shortcut: "W edytorze naciśnij Ctrl+Enter (Command+Enter na Macu), aby uruchomić testy. Tab przenosi dalej; edytor nie blokuje klawiatury.",
  results: "Wyniki",
  noResults: "Uruchom testy, aby zobaczyć tu wyniki.",
  loading: "Uruchamianie środowiska do kodu…",
  unavailable: "Nie udało się uruchomić środowiska do kodu, więc testy nie mogą tu działać. Nadal możesz czytać zadanie i edytować kod.",
  summaryPartial: "Zaliczone testy: {passed} z {total}.",
  summaryAll: "Wszystkie testy zaliczone: {total}.",
  passed: "Zaliczony",
  failed: "Niezaliczony",
  error: "Błąd",
  buildError: "Nie udało się zbudować Twojego kodu:",
  timeout: "Testy trwały zbyt długo i zostały zatrzymane. Poszukaj pętli, która się nie kończy.",
  stopped: "Uruchomienie testów zostało zatrzymane.",
  noTests: "Żaden test się nie uruchomił.",
  output: "Wyjście konsoli",
  outputCut: "Wyjście zostało skrócone.",
  saved: "Zapisano próbę {n}.",
  notSaved: "Nie udało się zapisać wyniku. Uruchom testy ponownie, aby spróbować jeszcze raz.",
  practiceNote: "To ćwiczenie: wynik trafia do Twoich prób i wpływa na postęp, ale nie liczy się do certyfikatu.",
  attempts: "Dotychczasowe próby: {n}. Najlepszy wynik: {best}%.",
  completed: "Ćwiczenie ukończone.",
  draftRestored: "Przywrócono Twoje wcześniejsze zmiany.",
};

const zh: CodeStrings = {
  task: "任务",
  files: "你的代码",
  fileLabel: "{path} 的代码",
  tests: "测试",
  testsHint: "点击“运行测试”后，这些测试会在你的浏览器中运行。你可以阅读它们，但不能在这里修改。",
  run: "运行测试",
  running: "正在运行测试…",
  reset: "重新开始",
  resetConfirm: "要重新开始并丢弃所有文件的修改吗？",
  resetYes: "是，重新开始",
  resetKeep: "保留我的代码",
  resetDone: "代码已恢复到初始状态。",
  shortcut: "在编辑器中按 Ctrl+Enter（Mac 上为 Command+Enter）运行测试。按 Tab 键可移到下一项，编辑器不会锁住键盘。",
  results: "结果",
  noResults: "运行测试后，结果会显示在这里。",
  loading: "正在启动代码运行环境…",
  unavailable: "代码运行环境无法启动，因此无法在此运行测试。你仍然可以阅读任务并编辑代码。",
  summaryPartial: "通过了 {passed}/{total} 个测试。",
  summaryAll: "全部 {total} 个测试都通过了。",
  passed: "通过",
  failed: "未通过",
  error: "出错",
  buildError: "无法构建你的代码：",
  timeout: "测试用时过长，已被停止。请检查是否有永远不会结束的循环。",
  stopped: "测试运行已停止。",
  noTests: "没有运行任何测试。",
  output: "控制台输出",
  outputCut: "输出已被截断。",
  saved: "已保存第 {n} 次尝试。",
  notSaved: "无法保存结果。请再次运行测试以重试。",
  practiceNote: "这是练习：结果会记入你的尝试并影响进度，但不计入证书。",
  attempts: "目前尝试次数：{n}。最高得分：{best}%。",
  completed: "你已完成此练习。",
  draftRestored: "已恢复你之前的修改。",
};

const BY_LANGUAGE: Record<string, CodeStrings> = { en, pl, zh };

export function codeStrings(lang: string | null | undefined): CodeStrings {
  const code = (lang ?? "").toLowerCase().split("-")[0] ?? "";
  return BY_LANGUAGE[code] ?? en;
}

export const fill = (template: string, values: Record<string, string | number>): string => template.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? ""));
