/** Workflow showcase data (src/data/workflows.json): the true status of every tab lives here. */
import data from "../data/workflows.json";
import plData from "../data/i18n/workflows.pl.json";
import zhData from "../data/i18n/workflows.zh.json";
import { applyWorkflowStatus, type LandingStatus, type WorkflowsData } from "./landing-status.ts";
import type { Locale } from "../i18n/locales.ts";

export const workflowsData = data as WorkflowsData;

/** Translated copies (same tabs, same order, same commands; only the prose differs): src/data/i18n/workflows.<locale>.json. */
export const workflowsTranslations: Partial<Record<Locale, WorkflowsData>> = { pl: plData as WorkflowsData, zh: zhData as WorkflowsData };

export const workflowsModel = (mode: LandingStatus, locale: Locale = "en") => applyWorkflowStatus(workflowsTranslations[locale] ?? workflowsData, mode);
