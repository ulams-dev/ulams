/**
 * The FAQ page content (src/data/faq.json, translated in src/data/i18n/faq.<locale>.json): the same
 * sections and the same number of questions in every language (a unit test keeps them parallel).
 */
import en from "../data/faq.json";
import pl from "../data/i18n/faq.pl.json";
import zh from "../data/i18n/faq.zh.json";
import type { Locale } from "../i18n/locales.ts";

export interface FaqContent {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  intro: string;
  contact: string;
  docs: string;
  sections: Array<{ id: string; title: string; items: Array<{ q: string; a: string }> }>;
}

const CONTENT: Record<Locale, FaqContent> = { en, pl, zh };

export const faqContent = (locale: Locale): FaqContent | undefined => CONTENT[locale];
