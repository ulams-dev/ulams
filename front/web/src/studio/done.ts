/**
 * The publish summary on the success page: loads the publish check, renders the PublishSummary
 * component and publishes through the BFF (the API enforces the blocking items and the warnings).
 */
import { renderSurface } from "@ulams/ui/builder/renderer.ts";
import { announce, message, studioClient } from "./common.ts";

const root = document.querySelector<HTMLElement>("[data-publish]");
if (root) {
  const id = root.dataset.session!;
  const cb = studioClient();

  const show = async (): Promise<void> => {
    try {
      const check = await cb.publishCheck(id);
      const f = check.facts;
      const surface = renderSurface(
        [
          {
            id: "root",
            component: "PublishSummary",
            title: f.title ?? "",
            ...(f.url ? { url: f.url } : {}),
            published: f.published,
            price: { label: f.price.label, suggested: f.price.mode === "paid" && f.price.amountMinor === null && f.price.suggestion !== null },
            ...(f.theme ? { theme: { preset: f.theme.preset, ...(f.theme.accent ? { accent: f.theme.accent } : {}), ...(f.theme.adjustedAccent ? { adjustedAccent: f.theme.adjustedAccent } : {}) } } : {}),
            counts: { modules: f.counts.modules ?? 0, lessons: f.counts.lessons ?? 0, minutes: f.counts.minutes ?? 0, questions: f.counts.questions ?? 0 },
            blocking: check.blocking,
            warnings: check.warnings,
            notes: f.applyNotes,
          },
        ],
        {
          surfaceId: "publish",
          dispatch: async (action) => {
            if (action.name !== "publish") return;
            try {
              await cb.publish(id, Boolean(action.context.acknowledgedWarnings));
              announce("The course is published. Learners can enrol now.");
              root.dataset.published = "true";
              await show();
              root.querySelector<HTMLElement>("h2")?.focus();
            } catch (error) {
              announce(message(error, "Publishing failed."));
              await show();
            }
          },
        }
      );
      root.replaceChildren(surface);
      const status = document.createElement("p");
      status.className = f.published ? "cb-decision cb-decision-applied" : "cb-sr";
      status.setAttribute("role", "status");
      status.textContent = f.published ? "The course is published. Learners can enrol now." : "";
      root.prepend(status);
    } catch (error) {
      root.textContent = message(error, "The publish check could not be loaded. Reload to try again.");
    }
  };
  void show();
}
