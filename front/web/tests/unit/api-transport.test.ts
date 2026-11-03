import { describe, expect, it, vi } from "vitest";
import { tenantFetch } from "../../src/lib/api-transport.ts";

describe("private server API transport",()=>{
  it("preserves the tenant Host, public protocol and API path",async()=>{
    const fake=vi.fn(async()=>new Response('{}')) as unknown as typeof fetch;
    const transport=tenantFetch({apiUrl:'https://academy-api.example.org'},'http://api-gateway:8081',fake);
    await transport('https://academy-api.example.org/api/profile/me?x=1',{headers:{Authorization:'Bearer test-token'}});
    const request=(fake as unknown as ReturnType<typeof vi.fn>).mock.calls[0]![0] as Request;
    expect(request.url).toBe('http://api-gateway:8081/api/profile/me?x=1');
    expect(request.headers.get('X-Ulams-Api-Host')).toBe('academy-api.example.org');
    expect(request.headers.get('X-Forwarded-Proto')).toBe('https');
    expect(request.headers.get('Authorization')).toBe('Bearer test-token');
  });
  it("preserves POST bodies and does not redirect unrelated origins",async()=>{
    const fake=vi.fn(async()=>new Response('{}')) as unknown as typeof fetch;
    const transport=tenantFetch({apiUrl:'http://academy-api.localhost:8480'},'http://caddy:8081',fake);
    await transport('http://academy-api.localhost:8480/api/login',{method:'POST',body:'hello'});
    const request=(fake as unknown as ReturnType<typeof vi.fn>).mock.calls[0]![0] as Request;
    expect(await request.text()).toBe('hello');
    await transport('https://storage.example.org/file');
    const file=(fake as unknown as ReturnType<typeof vi.fn>).mock.calls[1]![0] as Request;
    expect(file.url).toBe('https://storage.example.org/file');expect(file.headers.has('X-Ulams-Api-Host')).toBe(false);
  });
  it("leaves existing installations unchanged and rejects unsafe gateway settings",()=>{
    const fake=vi.fn() as unknown as typeof fetch;
    expect(tenantFetch({apiUrl:'https://api.example.org'},'',fake)).toBe(fake);
    expect(()=>tenantFetch({apiUrl:'https://api.example.org'},'http://name:secret@internal')).toThrow();
    expect(()=>tenantFetch({apiUrl:'https://api.example.org'},'http://internal/path')).toThrow();
  });
});
