import { describe, it } from "node:test";
import assert from "node:assert/strict";
import {
  SOCIAL_VERIFIER_KEY,
  challengeFor,
  newVerifier,
  readSocialCallback,
  startSocialLoginUrl,
  takeVerifier,
  type SocialStorage,
} from "../src/lib/sdk/utils/socialLogin.ts";

const memory = (): SocialStorage & { data: Map<string, string> } => {
  const data = new Map<string, string>();
  return {
    data,
    getItem: (k) => data.get(k) ?? null,
    setItem: (k, v) => void data.set(k, v),
    removeItem: (k) => void data.delete(k),
  };
};

describe("social login helpers", () => {
  it("builds the S256 challenge of RFC 7636 appendix B", async () => {
    assert.equal(
      await challengeFor("dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"),
      "E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM"
    );
  });

  it("makes a fresh 43 character verifier every time", () => {
    const a = newVerifier();
    assert.match(a, /^[A-Za-z0-9_-]{43}$/);
    assert.notEqual(a, newVerifier());
  });

  it("starts a login with the challenge only and keeps the verifier in storage", async () => {
    const storage = memory();
    const url = new URL(
      await startSocialLoginUrl("https://api.example.test", "google", "https://app.example.test/authentication", storage)
    );

    assert.equal(url.origin + url.pathname, "https://api.example.test/api/auth/social/google");
    assert.equal(url.searchParams.get("return_url"), "https://app.example.test/authentication");
    const verifier = storage.data.get(SOCIAL_VERIFIER_KEY)!;
    assert.equal(url.searchParams.get("challenge"), await challengeFor(verifier));
    assert.ok(!url.toString().includes(verifier));
  });

  it("hands the verifier out once", () => {
    const storage = memory();
    storage.setItem(SOCIAL_VERIFIER_KEY, "v");
    assert.equal(takeVerifier(storage), "v");
    assert.equal(takeVerifier(storage), null);
  });

  it("reads the code or the error of the callback", () => {
    assert.deepEqual(readSocialCallback("?code=abc&complete=true"), { code: "abc", error: null });
    assert.deepEqual(readSocialCallback("?error=link_required"), { code: null, error: "link_required" });
    assert.deepEqual(readSocialCallback(""), { code: null, error: null });
    // the old redirect carried the token: it is not read any more
    assert.deepEqual(readSocialCallback("?token=jwt&complete=true"), { code: null, error: null });
  });
});
