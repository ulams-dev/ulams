import type { Envelope } from "../src/protocol.ts";

type Listener = (e: { data: unknown; source: unknown; origin: string }) => void;

/** A minimal fake `Window` pair for exercising both ends without a DOM. */
export class FakeWindow {
  listeners: Listener[] = [];
  posted: Array<{ message: Envelope; target: string }> = [];
  parent: unknown = this;
  addEventListener(_t: string, l: Listener) {
    this.listeners.push(l);
  }
  removeEventListener(_t: string, l: Listener) {
    this.listeners = this.listeners.filter((x) => x !== l);
  }
  postMessage(message: unknown, target: string) {
    this.posted.push({ message: message as Envelope, target });
  }
  dispatch(data: unknown, source: unknown, origin = "null") {
    for (const l of [...this.listeners]) l({ data, source, origin });
  }
}
