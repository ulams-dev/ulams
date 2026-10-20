import { beforeAll, describe, expect, it } from "vitest";
import { setupDom } from "./astro-render.ts";

beforeAll(async () => {
  setupDom();
  await import("../src/elements/frame-slider.ts");
});

describe("ulams-frame-slider", () => {
  it("shows one frame at a time and wraps with the arrows and keys", () => {
    const { document } = setupDom().window;
    document.body.innerHTML = `
      <ulams-frame-slider>
        <div>
          <button type="button" data-prev>Previous</button>
          <p data-status>1 / 2</p>
          <button type="button" data-next>Next</button>
        </div>
        <figure data-frame><figcaption>First</figcaption></figure>
        <figure data-frame><figcaption>Second</figcaption></figure>
      </ulams-frame-slider>
    `;
    const el = document.querySelector("ulams-frame-slider")!;
    const slides = [...el.querySelectorAll<HTMLElement>("[data-frame]")];
    const status = el.querySelector("[data-status]")!;
    expect(el.getAttribute("data-ready")).toBe("1");
    expect(slides[0]!.hidden).toBe(false);
    expect(slides[1]!.hidden).toBe(true);
    expect(status.textContent).toBe("1 / 2");

    el.querySelector<HTMLButtonElement>("[data-next]")!.click();
    expect(slides[0]!.hidden).toBe(true);
    expect(slides[1]!.hidden).toBe(false);
    expect(status.textContent).toBe("2 / 2");

    el.querySelector<HTMLButtonElement>("[data-next]")!.click();
    expect(slides[0]!.hidden).toBe(false);
    expect(status.textContent).toBe("1 / 2");

    el.querySelector<HTMLButtonElement>("[data-prev]")!.click();
    expect(slides[1]!.hidden).toBe(false);
    expect(status.textContent).toBe("2 / 2");
  });
});
