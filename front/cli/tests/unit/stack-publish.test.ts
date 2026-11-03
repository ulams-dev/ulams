import { describe,expect,it } from "vitest";
import { runCli } from "../helpers.ts";
const env={ULAMS_URL:"http://academy-api.example.org",ULAMS_TOKEN:"test-token"};
describe("explicit publication warning review",()=>{
  it("sends acknowledgement only when requested",async()=>{
    const result=await runCli(["builder","publish","s1","--acknowledge-warnings","--json"],{env,routes:{"POST /api/admin/course-builder/sessions/s1/publish":{body:{success:true,data:{courseId:1,published:true}}}}});
    try{expect(result.code).toBe(0);expect(result.requests[0]?.body).toEqual({acknowledgedWarnings:true});}finally{result.cleanup();}
  });
  it("does not bypass blocking publication errors",async()=>{
    const result=await runCli(["builder","publish","s1","--acknowledge-warnings","--json"],{env,routes:{"POST /api/admin/course-builder/sessions/s1/publish":{status:409,body:{success:false,message:"Course has blocking errors",data:{blocking:[{code:"missing_objectives"}]}}}}});
    try{expect(result.code).toBe(6);expect(result.requests).toHaveLength(1);}finally{result.cleanup();}
  });
});
