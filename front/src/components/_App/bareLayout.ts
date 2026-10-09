import { useEffect, useSyncExternalStore } from "react";

/**
 * Pages that bring their own header and footer (tenant landing pages) ask the
 * app shell to drop the padding reserved for the fixed navbar. Works with any
 * router because the page itself registers while it is mounted.
 */
let bareCount = 0;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((listener) => listener());

const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
};

const getSnapshot = () => bareCount > 0;

/** Call in a page that renders without the shared Layout. */
export function useBareLayout(): void {
  useEffect(() => {
    bareCount += 1;
    emit();
    return () => {
      bareCount -= 1;
      emit();
    };
  }, []);
}

/** True while a bare page is mounted. */
export function useIsBareLayout(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, getSnapshot);
}
