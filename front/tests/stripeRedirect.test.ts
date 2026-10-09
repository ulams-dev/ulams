import { describe, it } from "node:test";
import assert from "node:assert/strict";
import { stripeRedirectUrl } from "../src/lib/sdk/utils/stripeRedirect.ts";

describe("stripeRedirectUrl", () => {
  it("returns the 3-D Secure URL when the API asks for authentication", () => {
    assert.equal(
      stripeRedirectUrl({
        success: true,
        data: { redirect_url: "https://hooks.stripe.com/3d_secure/abc" },
      }),
      "https://hooks.stripe.com/3d_secure/abc"
    );
  });

  it("returns undefined for an immediate success", () => {
    assert.equal(stripeRedirectUrl({ success: true, data: {} }), undefined);
    assert.equal(stripeRedirectUrl({ success: true }), undefined);
    assert.equal(stripeRedirectUrl(undefined), undefined);
    assert.equal(stripeRedirectUrl({ data: { redirect_url: "" } }), undefined);
    assert.equal(stripeRedirectUrl({ data: { redirect_url: 5 } }), undefined);
  });
});
