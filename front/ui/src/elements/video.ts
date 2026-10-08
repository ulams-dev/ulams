import { announceComplete } from "./bff.ts";

/**
 * <ulams-video src="…m3u8|mp4"> <video …> <button data-play> <button data-seek="42"> </ulams-video>
 * The video has no source until the learner presses play: no bytes are spent on page load.
 * HLS plays natively in Safari; elsewhere hls.js (light build) is loaded on that first play.
 */
class UlamsVideo extends HTMLElement {
  private started = false;

  connectedCallback(): void {
    const video = this.querySelector("video");
    if (!video) return;
    this.querySelectorAll<HTMLButtonElement>("[data-play]").forEach((b) => b.addEventListener("click", () => void this.start(video)));
    this.querySelectorAll<HTMLButtonElement>("[data-seek]").forEach((b) =>
      b.addEventListener("click", async () => {
        await this.start(video);
        video.currentTime = Number(b.dataset.seek ?? 0);
        void video.play();
      })
    );
    video.addEventListener("ended", () => announceComplete("video"));
    video.addEventListener("timeupdate", () => {
      const active = [...this.querySelectorAll<HTMLButtonElement>("[data-seek]")]
        .filter((b) => Number(b.dataset.seek) <= video.currentTime)
        .pop();
      this.querySelectorAll("[data-seek]").forEach((b) => b.toggleAttribute("aria-current", b === active));
    });
  }

  private async start(video: HTMLVideoElement): Promise<void> {
    if (this.started) return;
    this.started = true;
    this.setAttribute("state", "loading");
    const src = this.getAttribute("src") ?? "";
    try {
      if (/\.m3u8($|\?)/.test(src) && !video.canPlayType("application/vnd.apple.mpegurl")) {
        const { default: Hls } = await import("hls.js/dist/hls.light.min.mjs");
        if (Hls.isSupported()) {
          const hls = new Hls({ capLevelToPlayerSize: true });
          hls.loadSource(src);
          hls.attachMedia(video);
        } else {
          video.src = src;
        }
      } else {
        video.src = src;
      }
      video.controls = true;
      await video.play().catch(() => undefined);
      this.setAttribute("state", "playing");
    } catch {
      this.setAttribute("state", "error");
    }
  }
}

if (!customElements.get("ulams-video")) customElements.define("ulams-video", UlamsVideo);
