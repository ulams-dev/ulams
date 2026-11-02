import { describe, it, expect } from "vitest";
import { fetchFrameOrigins } from "../../src/lib/frame-origins.ts";

describe("fetchFrameOrigins", () => {
  it("should fetch the correct URL", async () => {
    const apiUrl = "https://example.com";
    const expectedUrl = "https://example.com/api/lti/frame-origins";
    let calledUrl = "";

    const fakeFetcher = async (url: string) => {
      calledUrl = url;
      return new Response(JSON.stringify({ data: ["https://valid.com"] }), { status: 200 });
    };

    await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(calledUrl).toBe(expectedUrl);
  });

  it("should filter out non-string entries", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response(JSON.stringify({ data: ["https://valid.com", 123, "https://another.com"] }), { status: 200 });
    };

    const origins = await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(origins).toEqual(["https://valid.com", "https://another.com"]);
  });

  it("should return an empty array if data is missing", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response(JSON.stringify({}), { status: 200 });
    };

    const origins = await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(origins).toEqual([]);
  });

  it("should reject with a message containing the status code if response is not ok", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response("", { status: 404 });
    };

    await expect(fetchFrameOrigins(apiUrl, fakeFetcher)).rejects.toThrow("frame-origins answered 404");
  });

  it("should handle trailing slashes in apiUrl", async () => {
    const apiUrl = "https://example.com/";
    const expectedUrl = "https://example.com/api/lti/frame-origins";
    let calledUrl = "";

    const fakeFetcher = async (url: string) => {
      calledUrl = url;
      return new Response(JSON.stringify({ data: ["https://valid.com"] }), { status: 200 });
    };

    await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(calledUrl).toBe(expectedUrl);
  });

  it("should handle empty data array", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response(JSON.stringify({ data: [] }), { status: 200 });
    };

    const origins = await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(origins).toEqual([]);
  });

  it("should handle non-array data", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response(JSON.stringify({ data: "not an array" }), { status: 200 });
    };

    const origins = await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(origins).toEqual([]);
  });

  it("should handle null data", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response(JSON.stringify({ data: null }), { status: 200 });
    };

    const origins = await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(origins).toEqual([]);
  });

  it("should handle undefined data", async () => {
    const apiUrl = "https://example.com";
    const fakeFetcher = async () => {
      return new Response(JSON.stringify({}), { status: 200 });
    };

    const origins = await fetchFrameOrigins(apiUrl, fakeFetcher);
    expect(origins).toEqual([]);
  });
});
