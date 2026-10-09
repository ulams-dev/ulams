import { createLivingLearnerClient } from "@ulams/sdk";

/**
 * <ulams-notice notice-id="7">
 *   <button data-dismiss hidden>Mark as reviewed</button>
 *   <p role="status" data-status></p>
 * </ulams-notice>
 *
 * Progressive enhancement of a lesson update notice: the button stays hidden without scripts and
 * is shown here. It marks the learner's notice as reviewed through the BFF, then says so in the
 * status line (read out and focusable) and removes the button. Progress is never touched.
 */
const living = createLivingLearnerClient({ baseUrl: "/bff", timeoutMs: 20_000 });

class UlamsNotice extends HTMLElement {
  connectedCallback(): void {
    const noticeId = Number(this.getAttribute("notice-id"));
    const button = this.querySelector<HTMLButtonElement>("button[data-dismiss]");
    if (!noticeId || !button) return;
    button.hidden = false;
    button.addEventListener("click", () => void this.dismiss(noticeId, button));
  }

  private async dismiss(noticeId: number, button: HTMLButtonElement): Promise<void> {
    const status = this.querySelector<HTMLElement>("[data-status]");
    button.disabled = true;
    button.setAttribute("aria-busy", "true");
    try {
      await living.dismiss(noticeId);
      button.hidden = true;
      if (status) {
        status.textContent = "Marked as reviewed. This notice will not show again.";
        status.focus();
      }
    } catch {
      button.disabled = false;
      button.removeAttribute("aria-busy");
      if (status) status.textContent = "Could not save that. Try again.";
    }
  }
}

if (!customElements.get("ulams-notice")) customElements.define("ulams-notice", UlamsNotice);
