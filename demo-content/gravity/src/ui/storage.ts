// localStorage can throw (an opaque-origin frame, a private window, blocked site data). The tour's
// language choice is a convenience, so a failed read or write is simply ignored.
export function storageGet(key: string): string | null {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

export function storageSet(key: string, value: string): void {
  try {
    window.localStorage.setItem(key, value);
  } catch {
    /* ignore: the choice just is not remembered */
  }
}
