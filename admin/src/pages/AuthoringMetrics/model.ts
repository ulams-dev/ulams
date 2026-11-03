import type { CourseMetric } from '@/services/authoringMetrics';

/** Whole seconds as "6 min 57 s", "1 h 2 min" or "24 s"; the words come from the page's messages. */
export function duration(
  seconds: number,
  t: (id: string, values?: Record<string, string | number>) => string,
): string {
  const total = Math.max(0, Math.round(seconds));
  if (total < 60) return t('authoring_metrics_duration_seconds', { s: total });
  if (total < 3600) {
    return t('authoring_metrics_duration_minutes', { m: Math.floor(total / 60), s: total % 60 });
  }
  return t('authoring_metrics_duration_hours', {
    h: Math.floor(total / 3600),
    m: Math.floor((total % 3600) / 60),
  });
}

/** Micro USD as dollars: four places under a dollar, two above. */
export function usd(micro: number, locale?: string): string {
  const digits = micro < 1_000_000 ? 4 : 2;
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(micro / 1_000_000);
}

/** The AI elements by what became of them, as "kept / revised / edited" counts and the share kept as written. */
export function elementSplit(m: CourseMetric): {
  total: number;
  kept: number;
  revised: number;
  edited: number;
  unknown: number;
  keptShare: number | null;
} {
  const e = m.elements;

  return {
    total: e.ai_elements,
    kept: e.accepted_unchanged,
    revised: e.revised_on_request,
    edited: e.edited_by_author,
    unknown: e.unknown,
    keptShare: e.ai_elements > 0 ? e.accepted_unchanged / e.ai_elements : null,
  };
}

/** A download in the browser from a Blob. */
export function saveBlob(name: string, blob: Blob): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  document.body.append(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
