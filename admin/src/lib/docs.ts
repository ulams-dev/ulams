import { docsUrl } from '@ulams/tenant';

/** Link into the documentation; the base comes from the runtime settings (ADR 0056). */
export const adminDocsUrl = (path = ''): string =>
  docsUrl(typeof window !== 'undefined' ? window.REACT_APP_ULAMS_DOCS_URL : undefined, path);
