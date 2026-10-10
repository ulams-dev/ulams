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
            ...(f.quality && Object.keys(f.quality.critics).length ? { quality: { critics: Object.entries(f.quality.critics).map(([critic, c]) => ({ critic, ...c })), iterations: f.quality.iterations } } : {}),
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

/**
 * "New site": for platform operators who chose a new site in the brief. Creates the site, moves this
 * session there and links to the studio of the new site; progress is polled from the session state.
 */
const siteRoot = document.querySelector<HTMLElement>("[data-new-site]");
if (siteRoot) {
  const id = siteRoot.dataset.session!;
  const cb = studioClient();
  const labels: Record<string, string> = {
    queued: "Waiting for a worker…",
    provisioning: "Creating the site (database, storage, accounts). This takes a few minutes.",
    transferring: "Moving your course to the new site…",
  };
  const render = (state: Awaited<ReturnType<typeof cb.sessions.get>>): boolean => {
    const site = state.brief?.site;
    if (!state.canCreateSite || (site?.mode !== "new" && !state.newSite)) {
      siteRoot.hidden = true;
      return false;
    }
    siteRoot.hidden = false;
    const slug = state.newSite?.slug ?? site?.slug ?? "";
    const section = document.createElement("section");
    section.className = "cb-card";
    section.setAttribute("aria-labelledby", "st-newsite-title");
    const heading = document.createElement("h2");
    heading.id = "st-newsite-title";
    heading.className = "cb-h3";
    heading.textContent = "New site";
    section.append(heading);
    const status = state.newSite?.status;
    const note = document.createElement("p");
    note.setAttribute("role", "status");
    if (status === "done") {
      note.textContent = `The site ${slug} is ready. Your course moved there; ${state.newSite?.invited ? "an invitation was sent to your e-mail address." : "sign in with your usual e-mail address (ask an admin for a password link)."}`;
      section.append(note);
      if (state.newSite?.studioUrl) {
        const link = document.createElement("a");
        link.className = "cb-btn cb-btn-primary";
        link.href = state.newSite.studioUrl;
        link.textContent = "Continue in the new site";
        section.append(link);
      }
    } else if (status === "queued" || status === "provisioning" || status === "transferring") {
      note.textContent = labels[status] ?? "Working…";
      section.append(note);
    } else {
      note.textContent = `Create the site ${slug} and move this course to it. You apply, choose the theme and publish there.`;
      section.append(note);
      if (status === "failed") {
        const error = document.createElement("p");
        error.className = "cb-error";
        error.setAttribute("role", "alert");
        error.textContent = state.newSite?.error ?? "Creating the site failed.";
        section.append(error);
      }
      const button = document.createElement("button");
      button.type = "button";
      button.className = "cb-btn cb-btn-primary";
      button.textContent = status === "failed" ? "Try again" : `Create ${slug} and move this course`;
      button.addEventListener("click", async () => {
        button.disabled = true;
        try {
          await cb.newSite(id, slug);
          await tick();
        } catch (error) {
          announce(message(error, "The site could not be created."));
          button.disabled = false;
        }
      });
      section.append(button);
    }
    siteRoot.replaceChildren(section);

    return status === "queued" || status === "provisioning" || status === "transferring";
  };
  const tick = async (): Promise<void> => {
    try {
      const busy = render(await cb.sessions.get(id));
      if (busy) window.setTimeout(() => void tick(), 3000);
    } catch {
      window.setTimeout(() => void tick(), 8000);
    }
  };
  void tick();
}
