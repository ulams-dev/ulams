import { describe, it } from "node:test";
import assert from "node:assert/strict";
import {
  avatarImage,
  imageConfigOf,
  imageServiceOf,
  legacyAvatarUrl,
  storagePathImage,
  type LegacyImageSettings,
} from "../src/lib/images/legacyImages.ts";
import { allowedCloudflareOptions } from "../sdk/src/images.ts";

const API = "https://api.example.test";
const STORAGE = "https://storage.example.test";
const base: LegacyImageSettings = { apiUrl: API, sources: STORAGE };
const owner = { path_avatar: "avatars/7/me.jpg", url_avatar: `${STORAGE}/avatars/7/me.jpg` };

describe("legacy app image service (VITE_ULAMS_IMAGE_SERVICE)", () => {
  it("defaults to php for unset, empty and unknown values", () => {
    for (const service of [undefined, null, "", "sharp", "PHP"]) {
      assert.equal(imageServiceOf({ ...base, service }), "php");
    }
  });

  it("asking for astro gives noop (a front-only mode)", () => {
    assert.equal(imageServiceOf({ ...base, service: "astro" }), "noop");
  });

  it("php mode builds exactly the URL the app always built, with or without a public URL", () => {
    const expected = `${API}/api/images/img?path=avatars/7/me.jpg`;
    assert.equal(legacyAvatarUrl(API, "avatars/7/me.jpg"), expected);
    assert.deepEqual(avatarImage(owner, 192, base), { src: expected });
    assert.deepEqual(avatarImage({ path_avatar: "avatars/7/me.jpg" }, 192, { ...base, service: "php" }), { src: expected });
    assert.deepEqual(avatarImage(owner, 192, { ...base, apiUrl: `${API}/` }), { src: expected });
  });

  it("cloudflare mode builds a same-host /cdn-cgi/image URL from the public URL with the avatar preset", () => {
    const image = avatarImage(owner, 192, { ...base, service: "cloudflare" });
    assert.equal(
      image?.src,
      `/cdn-cgi/image/width=192,quality=80,format=auto,fit=scale-down,onerror=redirect/${STORAGE}/avatars/7/me.jpg`,
    );
    assert.match(image?.srcSet ?? "", /width=96,.*\s96w, .*width=192,.*\s192w, .*width=600,.*\s600w$/);
  });

  it("cloudflare mode only emits option strings the WAF rule allows", () => {
    const allowed = new Set(allowedCloudflareOptions());
    for (const width of [10, 96, 150, 192, 400, 600, 5000]) {
      const image = avatarImage(owner, width, { ...base, service: "cloudflare" });
      const options = /\/cdn-cgi\/image\/([^/]+)\//.exec(image?.src ?? "")?.[1];
      assert.ok(options && allowed.has(options), `${image?.src}`);
    }
  });

  it("cloudflare mode keeps the PHP resizer for a file without a public URL", () => {
    const image = avatarImage({ path_avatar: "avatars/7/me.jpg" }, 192, { ...base, service: "cloudflare" });
    assert.deepEqual(image, { src: `${API}/api/images/img?path=avatars/7/me.jpg` });
  });

  it("cloudflare mode passes a source outside the allowed origins through untouched", () => {
    const foreign = { path_avatar: "a.jpg", url_avatar: "https://elsewhere.example/a.jpg" };
    assert.equal(avatarImage(foreign, 192, { ...base, service: "cloudflare" })?.src, "https://elsewhere.example/a.jpg");
  });

  it("cloudflare mode honours a transform origin", () => {
    const image = avatarImage(owner, 96, { ...base, service: "cloudflare", transformOrigin: "https://ulams.app/" });
    assert.ok(image?.src.startsWith("https://ulams.app/cdn-cgi/image/width=96,"));
  });

  it("noop mode returns the original file, no srcset, and no PHP call", () => {
    assert.deepEqual(avatarImage(owner, 192, { ...base, service: "noop" }), { src: owner.url_avatar, srcSet: undefined });
  });

  it("noop mode without a public URL falls back to the PHP resizer", () => {
    assert.equal(
      avatarImage({ path_avatar: "avatars/7/me.jpg" }, 192, { ...base, service: "noop" })?.src,
      `${API}/api/images/img?path=avatars/7/me.jpg`,
    );
  });

  it("no avatar gives undefined", () => {
    for (const mode of ["php", "cloudflare", "noop"]) {
      assert.equal(avatarImage({ path_avatar: null, url_avatar: null }, 192, { ...base, service: mode }), undefined);
      assert.equal(avatarImage(null, 192, { ...base, service: mode }), undefined);
    }
  });

  it("the API origin is always an allowed source", () => {
    assert.deepEqual(imageConfigOf({ apiUrl: `${API}/`, sources: `${STORAGE}/ , https://b.example` }).allowedSources, [
      API,
      STORAGE,
      "https://b.example",
    ]);
    assert.deepEqual(imageConfigOf({ apiUrl: "" }).allowedSources, []);
  });
});

describe("storagePathImage (the Image component)", () => {
  it("php mode leaves the component on its imgcache and PHP flow", () => {
    assert.equal(storagePathImage("course/1/a.png", 400, "content", base), undefined);
  });

  it("cloudflare mode builds the source from the first VITE_ULAMS_IMAGE_SOURCES origin", () => {
    const image = storagePathImage("/course/1/a.png", 400, "content", { ...base, service: "cloudflare" });
    assert.equal(
      image?.src,
      `/cdn-cgi/image/width=640,quality=78,format=auto,fit=scale-down,onerror=redirect/${STORAGE}/course/1/a.png`,
    );
    assert.ok(image?.srcSet?.includes("1600w"));
  });

  it("noop mode returns the storage file itself", () => {
    assert.deepEqual(storagePathImage("course/1/a.png", undefined, "content", { ...base, service: "noop" }), {
      src: `${STORAGE}/course/1/a.png`,
      srcSet: undefined,
    });
  });

  it("without sources the component keeps the PHP flow even in cloudflare mode", () => {
    assert.equal(storagePathImage("a.png", 400, "content", { apiUrl: API, service: "cloudflare" }), undefined);
  });
});
