export type DraftWarning = { code: string; message: string; count?: number };

export type EmailDraft = {
  title: string;
  html: string;
  errors: string[];
  missing_variables: string[];
  warnings: DraftWarning[];
  sent?: boolean;
  recipient?: string;
};

export type DraftInput = { event: string; title: string; content: string };

export type DraftApiResponse = { success?: boolean; message?: string; data: EmailDraft };

export type DraftRequest = (input: DraftInput) => Promise<DraftApiResponse>;

/** Inserts `token` at the selection of `text` and returns the new text and caret position. */
export const insertAtSelection = (
  text: string,
  selectionStart: number | null | undefined,
  selectionEnd: number | null | undefined,
  token: string,
): { value: string; caret: number } => {
  const start = Math.min(Math.max(selectionStart ?? text.length, 0), text.length);
  const end = Math.min(Math.max(selectionEnd ?? start, start), text.length);
  return {
    value: text.slice(0, start) + token + text.slice(end),
    caret: start + token.length,
  };
};

/** Best human readable message of a failed request (validation errors first). */
export const requestErrorMessage = (error: unknown, fallback: string): string => {
  const data = (error as { response?: { data?: { message?: string; errors?: object } } })?.response
    ?.data;
  const first = data?.errors ? Object.values(data.errors).flat()[0] : undefined;
  if (typeof first === 'string') return first;
  if (data?.message) return data.message;
  const message = (error as { message?: string })?.message;
  return message || fallback;
};
