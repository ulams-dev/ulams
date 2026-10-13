import { createServer } from "node:http";
import { describe,expect,it } from "vitest";
import { loopbackFetch } from "../../src/http/loopback.ts";
describe("localhost CLI transport",()=>{
  it("resolves subdomains, preserves Host/body and supports readable clones",async()=>{
    const server=createServer(async(request,response)=>{
      const chunks:Buffer[]=[];for await(const chunk of request)chunks.push(Buffer.from(chunk));
      response.setHeader("Content-Type","application/json");response.end(JSON.stringify({host:request.headers.host,body:Buffer.concat(chunks).toString()}));
    });
    await new Promise<void>(done=>server.listen(0,"127.0.0.1",done));
    const port=(server.address() as {port:number}).port;
    try{const response=await loopbackFetch(`http://academy.localhost:${port}/api/example`,{method:"POST",body:"policy"});expect(await response.clone().json()).toEqual({host:`academy.localhost:${port}`,body:"policy"});expect(await response.json()).toMatchObject({body:"policy"});}finally{await new Promise<void>(done=>server.close(()=>done()));}
  });
});
