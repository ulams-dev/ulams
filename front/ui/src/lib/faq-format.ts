/**
 * Splits a plain-text FAQ answer into a lead and bullet points for the open (non-collapsible) layout.
 * Sentences end at ". ", "! ", "? " before an uppercase letter or quote, or at "。" for Chinese, so
 * abbreviations such as "e.g. the" or "np. historii" stay inside their sentence.
 */
export interface FaqPoint {
  label?: string;
  text: string;
}

export interface FaqSpan {
  code: boolean;
  text: string;
}

export interface FaqAnswer {
  lead: string;
  points: FaqPoint[];
}

const SENTENCE = /(?<=[.!?])\s+(?=[A-ZĄĆĘŁŃÓŚŹŻ"„“])|(?<=[。！？])/u;
const LABEL = /^([^:：.。]{2,48})[:：]\s*(.+)$/su;
const CJK = /[\u3400-\u9fff]/u;
const COMMAND = /(?:ulams )?stack (?:up|down)(?: --purge --yes)?/g;

export function splitSentences(text: string): string[] {
  return text.split(SENTENCE).map((s) => s.trim()).filter(Boolean);
}

export function formatFaqAnswer(answer: string): FaqAnswer {
  const sentences = splitSentences(answer);
  if (sentences.length < 3) return { lead: answer.trim(), points: [] };
  if (LABEL.test(sentences[0]!) && sentences.filter((s) => LABEL.test(s)).length >= 3) {
    return {
      lead: "",
      points: sentences.map((sentence): FaqPoint => {
        const match = LABEL.exec(sentence);
        return match ? { label: match[1]!.trim(), text: match[2]!.trim() } : { text: sentence };
      }),
    };
  }
  let take = 1;
  if (sentences[0]!.length < (CJK.test(sentences[0]!) ? 6 : 24)) take = 2;
  if (sentences.length - take < 2) return { lead: answer.trim(), points: [] };
  const joiner = /[。！？]$/u.test(sentences[0]!) ? "" : " ";
  const lead = sentences.slice(0, take).join(joiner);
  return { lead, points: sentences.slice(take).map((text) => ({ text })) };
}

/** Splits text into plain and command spans so CLI commands can render as code. */
export function codeSpans(text: string): FaqSpan[] {
  const spans: FaqSpan[] = [];
  let last = 0;
  for (const match of text.matchAll(COMMAND)) {
    if (match.index > last) spans.push({ code: false, text: text.slice(last, match.index) });
    spans.push({ code: true, text: match[0] });
    last = match.index + match[0].length;
  }
  if (last < text.length) spans.push({ code: false, text: text.slice(last) });
  return spans;
}
