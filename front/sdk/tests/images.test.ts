import { describe, expect, it, vi } from "vitest";
import {
  IMAGE_PRESETS,
  allowedCloudflareOptions,
  imageSrcSet,
  imageUrl,
  resolveImageService,
  type ImageConfig,
} from "../src/images.ts";

const STORAGE = "https://staging-storage.ulams.app";
const cf: ImageConfig = { service: "cloudflare", allowedSources: [STORAGE] };
const file = `${STORAGE}/coffee/hero.png`;

describe("cloudflare mode", () => {
  it("builds a relative /cdn-cgi/image URL with options in a fixed order", () => {
    expect(imageUrl(file, 640, "content", cf)).toBe(
      `/cdn-cgi/image/width=640,quality=78,format=auto,fit=scale-down,onerror=redirect/${file}`,
    );
  });

  it("keeps own-host files root-relative", () => {
    expect(imageUrl("/landing/shot.png", 960, "content", cf)).toBe(
      "/cdn-cgi/image/width=960,quality=78,format=auto,fit=scale-down,onerror=redirect/landing/shot.png",
    );
  });

  it("uses the transform origin when set", () => {
    const url = imageUrl("/a.png", 360, "content", { ...cf, transformOrigin: "https://ulams.app/" });
    expect(url.startsWith("https://ulams.app/cdn-cgi/image/width=360,")).toBe(true);
  });

  it("uses the preset quality", () => {
    expect(imageUrl("/a.png", 96, "avatar", cf)).toContain("width=96,quality=80,");
    expect(imageUrl("/a.png", 160, "logo", cf)).toContain("width=160,quality=85,");
    expect(imageUrl("/a.png", 240, "card", cf)).toContain("width=240,quality=78,");
  });

  it("takes the url of an API file object", () => {
    expect(imageUrl({ path: "avatars/1/a.png", url: file }, 192, "avatar", cf)).toContain(`/${file}`);
  });

  it("allows wildcard source origins", () => {
    const config = { ...cf, allowedSources: ["https://*.ulams.app"] };
    expect(imageUrl(file, 640, "content", config)).toContain("/cdn-cgi/image/");
    expect(imageUrl("https://evilulams.app/a.png", 640, "content", config)).toBe("https://evilulams.app/a.png");
  });
});

describe("widths", () => {
  it("snaps to the smallest preset width that covers the request", () => {
    expect(imageUrl("/a.png", 500, "content", cf)).toContain("width=640,");
    expect(imageUrl("/a.png", 5000, "content", cf)).toContain("width=1600,");
    expect(imageUrl("/a.png", 1, "content", cf)).toContain("width=360,");
  });

  it("caps at the intrinsic width and never upscales", () => {
    expect(imageUrl("/a.png", 1600, "content", cf, 700)).toContain("width=640,");
    expect(imageUrl("/a.png", 100, "card", cf, 50)).toContain("width=240,");
  });

  it("only ever emits widths from the preset", () => {
    for (const [name, preset] of Object.entries(IMAGE_PRESETS)) {
      for (let w = 1; w <= 2000; w += 37) {
        const match = /width=(\d+),/.exec(imageUrl("/a.png", w, name as keyof typeof IMAGE_PRESETS, cf));
        expect(preset.widths).toContain(Number(match?.[1]));
      }
    }
  });

  it("builds a srcset from the preset, capped at the intrinsic width", () => {
    const set = imageSrcSet(file, "content", cf, 1000) as string;
    expect(set.split(", ").map((entry) => entry.split(" ")[1])).toEqual(["360w", "640w", "960w"]);
  });

  it("gives no srcset for richText", () => {
    expect(imageSrcSet(file, "richText", cf)).toBeUndefined();
    expect(imageUrl(file, 1000, "richText", cf)).toContain("width=1280,");
  });

  it("gives no srcset when only one width remains", () => {
    expect(imageSrcSet(file, "content", cf, 300)).toBeUndefined();
  });
});

describe("whitelist for the WAF rule", () => {
  it("lists exactly the option strings the builder emits", () => {
    const allowed = new Set(allowedCloudflareOptions());
    const seen = new Set<string>();
    for (const name of Object.keys(IMAGE_PRESETS) as (keyof typeof IMAGE_PRESETS)[]) {
      for (let w = 1; w <= 2000; w += 13) {
        const options = imageUrl("/a.png", w, name, cf).split("/")[3] as string;
        expect(allowed.has(options)).toBe(true);
        seen.add(options);
      }
    }
    expect([...seen].sort()).toEqual(allowedCloudflareOptions());
  });

  it("is sorted and free of duplicates", () => {
    const list = allowedCloudflareOptions();
    expect(list).toEqual([...new Set(list)].sort());
    expect(list.every((o) => o.endsWith("fit=scale-down,onerror=redirect"))).toBe(true);
  });
});

describe("pass-through to the original", () => {
  const cases: [string, string][] = [
    ["data URI", "data:image/png;base64,AAAA"],
    ["blob URL", "blob:https://x.test/1234"],
    ["SVG", `${STORAGE}/logo.svg`],
    ["SVG with query", `${STORAGE}/logo.SVG?v=2`],
    ["GIF", `${STORAGE}/anim.gif`],
    ["relative GIF", "/anim.gif"],
    ["foreign origin", "https://cdn.example.com/a.png"],
    ["http origin that is not allowed", "http://staging-storage.ulams.app/a.png"],
    ["S3 signature", `${STORAGE}/a.png?X-Amz-Signature=abc&X-Amz-Expires=60`],
    ["Signature query", `${STORAGE}/a.png?Signature=abc`],
    ["token query", `${STORAGE}/a.png?token=abc`],
    ["Expires query", `${STORAGE}/a.png?Expires=1`],
    ["credentials", "https://user:pw@staging-storage.ulams.app/a.png"],
    ["already transformed", "/cdn-cgi/image/width=1/a.png"],
    ["astro endpoint", "/_image?href=/a.png&w=1"],
    ["unparseable", "http://"],
    ["empty", ""],
  ];

  for (const service of ["cloudflare", "astro"] as const) {
    it.each(cases)(`${service}: %s`, (_name, url) => {
      expect(imageUrl(url, 640, "content", { service, allowedSources: [STORAGE] })).toBe(url);
      expect(imageSrcSet(url, "content", { service, allowedSources: [STORAGE] })).toBeUndefined();
    });
  }
});

describe("noop mode", () => {
  const noop: ImageConfig = { service: "noop", allowedSources: [STORAGE] };

  it("returns the original and no srcset", () => {
    expect(imageUrl(file, 640, "content", noop)).toBe(file);
    expect(imageUrl({ path: "a/b.png", url: file }, 640, "content", noop)).toBe(file);
    expect(imageSrcSet(file, "content", noop)).toBeUndefined();
  });
});

describe("astro mode", () => {
  it("points at /_image with the preset width and quality", () => {
    const url = imageUrl(file, 640, "content", { service: "astro", allowedSources: [STORAGE] });
    expect(url).toBe(`/_image?href=${encodeURIComponent(file)}&w=640&f=webp&q=78`);
  });
});

describe("php mode", () => {
  const php: ImageConfig = { service: "php", allowedSources: [], apiUrl: "https://api.example.com/" };

  it("equals today's admin URL byte for byte", () => {
    // admin/src/services/ulams/files.ts: `${API}/api/images/img?path=${path}&w=${width}`
    expect(imageUrl({ path: "course/1/assets/a b.png" }, 240, "card", php)).toBe(
      "https://api.example.com/api/images/img?path=course/1/assets/a b.png&w=240",
    );
  });

  it("builds a srcset of the preset widths", () => {
    expect(imageSrcSet({ path: "a.png" }, "card", php)).toBe(
      "https://api.example.com/api/images/img?path=a.png&w=240 240w, https://api.example.com/api/images/img?path=a.png&w=480 480w",
    );
  });

  it("passes through when there is no storage path", () => {
    expect(imageUrl(file, 240, "card", php)).toBe(file);
    expect(imageUrl({ url: file }, 240, "card", php)).toBe(file);
  });
});

describe("unknown mode", () => {
  it("behaves as noop", () => {
    const config = { service: "imgproxy" as never, allowedSources: [STORAGE] };
    expect(imageUrl(file, 640, "content", config)).toBe(file);
  });
});

describe("resolveImageService", () => {
  it("reads known values case-insensitively", () => {
    expect(resolveImageService(" Cloudflare ", "astro")).toBe("cloudflare");
    expect(resolveImageService("php", "noop")).toBe("php");
  });

  it("falls back for empty and unknown values", () => {
    expect(resolveImageService(undefined, "astro")).toBe("astro");
    expect(resolveImageService("", "php")).toBe("php");
    expect(resolveImageService("nonsense", "astro")).toBe("astro");
  });

  it("uses noop, warning once, for a mode the client does not support", () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    expect(resolveImageService("php", "astro", ["astro", "noop"])).toBe("noop");
    expect(resolveImageService("php", "astro", ["astro", "noop"])).toBe("noop");
    expect(warn).toHaveBeenCalledTimes(1);
    warn.mockRestore();
  });
});

describe("presets", () => {
  it("defines the five presets with ascending widths", () => {
    expect(Object.keys(IMAGE_PRESETS).sort()).toEqual(["avatar", "card", "content", "logo", "richText"]);
    for (const preset of Object.values(IMAGE_PRESETS)) {
      expect([...preset.widths]).toEqual([...preset.widths].sort((a, b) => a - b));
    }
    expect(IMAGE_PRESETS.content.widths).toEqual([360, 640, 960, 1280, 1600]);
    expect(IMAGE_PRESETS.richText.srcset).toBe(false);
  });
});
