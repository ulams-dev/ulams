// @ts-check
import { connect } from "./vendor/interactive-bridge.js";
import { startShell } from "./vendor/ulam-shell.js";
import { feedback, filterByPoser, isCorrect, posersOf, scoreGuesses } from "./logic.js";

const $ = (/** @type {string} */ s) => /** @type {any} */ (document.querySelector(s));

/** @type {{ placeholder?: boolean, note?: string, options: {value: string, label: string}[], problems: import("./logic.js").Card[] }} */
let data = { options: [], problems: [] };
/** @type {Record<string, string>} what the learner chose, per card id (only after "Check") */
const guesses = {};
/** @type {any} */
let shell = null;
let step = "intro";

const el = (/** @type {string} */ tag, /** @type {string} */ cls, /** @type {string} */ text = "") => {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
};

function cardElement(/** @type {import("./logic.js").Card} */ card, /** @type {boolean} */ guessable) {
  const root = el("article", "sb-card");
  root.setAttribute("aria-labelledby", `n-${card.id}`);
  const h = el("h3", "sb-number", `Problem ${card.number}`); h.id = `n-${card.id}`; root.append(h);
  root.append(el("p", "sb-meta", `${card.poser} · ${card.date}`));
  root.append(el("p", "sb-summary", card.summary));
  root.append(el("p", "sb-meta", `Prize: ${card.prize}`));
  if (!guessable) return root;
  const done = card.id in guesses;
  const set = el("fieldset", "sb-guess");
  set.append(el("legend", "", "Guess the outcome"));
  for (const o of data.options) {
    const label = el("label", "");
    const input = /** @type {HTMLInputElement} */ (el("input", ""));
    input.type = "radio"; input.name = `g-${card.id}`; input.value = o.value; input.disabled = done; input.checked = guesses[card.id] === o.value;
    label.append(input, ` ${o.label}`);
    set.append(label);
  }
  root.append(set);
  const result = el("p", "sb-result"); result.setAttribute("aria-live", "polite"); result.hidden = !done;
  if (done) { result.textContent = feedback(card, guesses[card.id], data.options); result.classList.toggle("wrong", !isCorrect(card, guesses[card.id])); }
  const actions = el("div", "sb-actions");
  const check = /** @type {HTMLButtonElement} */ (el("button", "ix-primary", "Check my guess"));
  check.type = "button"; check.disabled = true; check.hidden = done;
  set.addEventListener("change", () => { check.disabled = !set.querySelector("input:checked"); });
  check.addEventListener("click", () => {
    const chosen = /** @type {HTMLInputElement | null} */ (set.querySelector("input:checked"));
    if (!chosen) return;
    guesses[card.id] = chosen.value;
    const s = scoreGuesses(data.problems, guesses);
    shell.bridge.score(s.raw, s.max, s.passed);
    render();
    const again = document.getElementById(`n-${card.id}`);
    if (again) { again.setAttribute("tabindex", "-1"); again.focus(); }
  });
  actions.append(check);
  root.append(result, actions);
  return root;
}

function render() {
  const banner = $("#banner");
  banner.hidden = !data.placeholder; banner.textContent = data.note || "";
  const wrap = $("#cards");
  wrap.replaceChildren();
  const single = step !== "intro";
  wrap.classList.toggle("single", single);
  $("#filters .ix-field").hidden = single;
  const cards = single ? data.problems.filter((c) => c.id === step) : filterByPoser(data.problems, $("#poser").value);
  for (const c of cards) wrap.append(cardElement(c, single));
  const s = scoreGuesses(data.problems, guesses);
  const answered = Object.keys(guesses).length;
  $("#score").textContent = answered ? `${s.raw} of ${answered} guesses right so far (${data.problems.length} problems in all).` : `${data.problems.length} problems. Open each problem to guess its outcome.`;
}

const loading = fetch("data/problems.json").then((r) => { if (!r.ok) throw new Error(`data ${r.status}`); return r.json(); }).then((d) => {
  data = d;
  for (const p of posersOf(d.problems)) { const o = document.createElement("option"); o.value = p; o.textContent = p; $("#poser").append(o); }
});
$("#poser").addEventListener("change", render);

shell = await startShell({
  connect,
  load: loading,
  render(s) { step = s.id; render(); },
});
