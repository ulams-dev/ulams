// Test page for the SCORM playback specs: mounts the legacy front's <ScormPreview> exactly as the
// apps do. Query: ?uuid=<fixture>~<launch query>&mode=default|community.
// Nothing here changes what the player does; it only records what the host sees.
import React from "react";
import { createRoot } from "react-dom/client";
import ScormPreview from "../../../src/lib/scorm-player/player";

declare global {
  interface Window {
    __host: {
      posts: unknown[];
      gets: string[];
      done: { trace: unknown[] } | null;
    };
  }
}

const params = new URLSearchParams(window.location.search);
const uuid = params.get("uuid") ?? "";
const community = params.get("mode") === "community";

window.__host = { posts: [], gets: [], done: null };

window.addEventListener("message", (event: MessageEvent) => {
  if (event.data && event.data.source === "ulams-scorm-fixture" && event.data.type === "done") {
    window.__host.done = { trace: event.data.trace };
  }
});

// The community props are the host's way to store and restore CMI values (see player.tsx).
// The stand-in store returns nothing for a value it has not been given.
const store: Record<string, unknown> = {};
const onScormPost = async (data: { cmi: Record<string, unknown> }) => {
  window.__host.posts.push(JSON.parse(JSON.stringify(data)));
  return {};
};
const onScormGet = async (key: string) => {
  window.__host.gets.push(key);
  return store[key];
};

createRoot(document.getElementById("root") as HTMLElement).render(
  <ScormPreview
    uuid={uuid}
    apiUrl={window.location.origin}
    serviceWorkerUrl="/service-worker-scorm.js"
    comunitate={community}
    onScormPost={community ? onScormPost : undefined}
    onScormGet={community ? onScormGet : undefined}
  />,
);
