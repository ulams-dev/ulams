import { describe, expect, it } from "vitest";
import { spawn } from "node:child_process";
import { mkdtemp, readFile, writeFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

const enabled=process.env.ULAMS_STACK_E2E === "1";
const target=process.env.ULAMS_STACK_E2E_TARGET ?? "docker";
const mode=process.env.ULAMS_STACK_E2E_MODE ?? "development";
const cli=process.env.ULAMS_STACK_E2E_CLI ?? resolve(import.meta.dirname,"../../dist/ulams.mjs");
function invoke(args:string[],env:NodeJS.ProcessEnv,stdin?:string):Promise<Record<string,unknown>> {
  return new Promise((done,reject)=>{
    console.info(`E2E: ${args.slice(0,2).join(" ")}`);
    const child=spawn(process.execPath,[cli,...args,"--json"],{env,stdio:["pipe","pipe","pipe"]});
    let output="",errors="";
    child.stdout.on("data",c=>output+=String(c));child.stderr.on("data",c=>errors+=String(c));
    child.on("error",reject);child.on("close",code=>{
      let response:Record<string,unknown>;
      try{response=JSON.parse(output.trim()) as Record<string,unknown>;}catch{return reject(new Error(`No JSON from ${args.slice(0,2).join(" ")}: ${errors}`));}
      if(code!==0)return reject(new Error(`${args.slice(0,2).join(" ")}: ${JSON.stringify(response.error)} ${errors}`));
      done(response.data as Record<string,unknown>);
    });child.stdin.end(stdin);
  });
}

describe.skipIf(!enabled)(`real ${target} ${mode} stack`,()=>{
  it("launches a usable academy, authors content and retains data/keys across down/up",async()=>{
    const directory=await mkdtemp(join(tmpdir(),"ulams-stack-live-"));
    const name=`ulams-e2e-${Date.now().toString(36)}`;
    const httpPort=process.env.ULAMS_STACK_E2E_HTTP_PORT ?? "19180";
    const httpsPort=process.env.ULAMS_STACK_E2E_HTTPS_PORT ?? "19143";
    const env:NodeJS.ProcessEnv={...process.env,ULAMS_CONFIG_DIR:join(directory,"profile")};
    // Profiles are created after installation so the new install directory is initially empty.
    const flags=["--directory",directory,"--name",name,"--target",target,"--mode",mode,"--domain","ulams.localhost","--http-port",httpPort,"--https-port",httpsPort,"--image-registry",process.env.ULAMS_STACK_E2E_IMAGE_REGISTRY ?? "ghcr.io/ulams-dev","--image-tag",process.env.ULAMS_STACK_E2E_IMAGE_TAG ?? "latest",...(mode==="production"?["--tls","internal"]:[]),...(target==="kubernetes"?["--gateway","ClusterIP"]:[])];
    let installed=false;
    try{
      const up=await invoke(["stack","up",...flags],env);installed=true;
      expect(up.ready).toBe(true);
      const credentials=await readFile(join(directory,"credentials.json"),"utf8");
      const secrets=JSON.parse(credentials) as Record<string,string>;
      expect(JSON.stringify(up)).not.toContain(secrets.TENANT_INITIAL_PASSWORD);
      if(up.caFile)env.NODE_EXTRA_CA_CERTS=String(up.caFile);
      const urls=up.urls as Record<string,string>;
      await invoke(["login","--url",urls.academyApi!,"--email","admin@academy.ulams.localhost","--password-stdin"],env,secrets.TENANT_INITIAL_PASSWORD+"\n");
      let courseId:unknown;
      if(mode==="development"){
        const source=join(directory,"policy.md");await writeFile(source,"# Privacy policy\n\nHR retains employee records for twelve months. Sales obtains consent before contacting a prospect. Management reviews access quarterly.\n",{mode:0o600});
        const draft=await invoke(["builder","start","--from",source,"--defaults"],env);
        expect(draft.status).toBe("outline_review");expect(draft.published).toBe(false);
        const applied=await invoke(["builder","start","--session",String(draft.sessionId),"--approve-outline","--apply"],env);
        courseId=applied.courseId;expect(courseId).toBeTruthy();
        const check=await invoke(["builder","publish-check",String(draft.sessionId)],env);
        expect(check.blocking).toEqual([]);
        const published=await invoke(["builder","publish",String(draft.sessionId),"--acknowledge-warnings"],env);
        expect(published.published).toBe(true);
      }else{
        const course=await invoke(["courses","create","--title","Production persistence check"],env);
        courseId=course.id;expect(courseId).toBeTruthy();
      }
      const before=await invoke(["courses","get",String(courseId)],env);
      await invoke(["stack","down","--directory",directory],env);
      const again=await invoke(["stack","up","--directory",directory],env);
      expect(again.ready).toBe(true);
      expect(await readFile(join(directory,"credentials.json"),"utf8")).toBe(credentials);
      // The previous bearer token and course remain usable after containers/pods were stopped.
      expect(await invoke(["courses","get",String(courseId)],env)).toMatchObject({id:before.id,title:before.title});
    }finally{
      if(installed)await invoke(["stack","down","--directory",directory,"--purge","--yes"],env);
      await rm(directory,{recursive:true,force:true});
    }
  },1200000);
});
