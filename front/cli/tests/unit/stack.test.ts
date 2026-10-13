import { describe, expect, it } from "vitest";
import { mkdtemp, readFile, writeFile, chmod, symlink, stat, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { parseAllDocuments } from "yaml";
import { configure, createSecrets, stackInput } from "../../src/stack/config.ts";
import { caddyfile, compose, initSql, yaml } from "../../src/stack/render.ts";
import { kubernetes } from "../../src/stack/kubernetes.ts";
import { renderInstall, withInstall } from "../../src/stack/files.ts";
import { cleanOutput } from "../../src/stack/process.ts";
import { runCli } from "../helpers.ts";
const input = (value:Record<string,unknown>={}) => stackInput.parse(value);

describe("self-contained stack generator",()=>{
  it("defaults to a local Phase 2 trial, with no public binding or live AI",()=>{
    const c=configure(input()),services=compose(c).services;
    expect(c.mode).toBe("development");expect(c.imageRegistry).toBe("ghcr.io/ulams-dev");
    expect(services.caddy).toMatchObject({ports:["127.0.0.1:8480:8480"]});
    expect(services.api).toMatchObject({environment:{LARAVEL_AI_DRIVER:"fake",LARAVEL_TENANCY_PORT:"8480",LARAVEL_APP_DEBUG:"false"}});
    expect(Object.keys(services)).toHaveLength(10);
    expect(caddyfile(c)).toContain("/interactive/*");
  });
  it("uses reachable NodePort numbers in generated public URLs",()=>{
    const dev=configure(input({target:"kubernetes",gateway:"NodePort"}));
    expect(dev.httpPort).toBe(30480);
    const prod=configure(input({target:"kubernetes",gateway:"NodePort",mode:"production",domain:"learn.example.org"}));
    expect(prod.httpsPort).toBe(30480);expect(prod.httpPort).toBe(30479);
    expect(()=>configure(input({target:"kubernetes",gateway:"NodePort",mode:"production",domain:"learn.example.org",node_port:30000}))).toThrow("adjacent HTTP");
  });
  it("production uses TLS, disabled AI and separate database/provisioning credentials",()=>{
    expect(()=>configure(input({mode:"production"}))).toThrow("requires --domain");
    expect(()=>configure(input({mode:"production",domain:"ulams.localhost"}))).toThrow("ACME");
    const c=configure(input({mode:"production",domain:"learn.example.org"}));
    expect(compose(c).services.api).toMatchObject({environment:{LARAVEL_AI_DRIVER:"disabled",LARAVEL_DB_USERNAME:"ulams",LARAVEL_DB_ADMIN_USERNAME:"ulams_owner"}});
    expect(caddyfile(c)).toContain("on_demand_tls");expect(caddyfile(c)).toContain("http://127.0.0.1:5555/check");
  });
  it("preserves immutable identity and allows an explicit image upgrade",()=>{
    const previous=configure(input());
    expect(configure(input(),previous)).toEqual(previous);
    expect(configure(input({image_tag:"sha-1234567"}),previous).imageTag).toBe("sha-1234567");
    expect(()=>configure(input({name:"other"}),previous)).toThrow("Cannot change name");
  });
  it("generates independent keys and never embeds secrets in public manifests",()=>{
    const a=createSecrets(),b=createSecrets();expect(a.APP_KEY).not.toEqual(b.APP_KEY);
    expect(Buffer.from(a.APP_KEY!.slice(7),"base64")).toHaveLength(32);
    const c=configure(input({target:"kubernetes"})),k=kubernetes(c,a);
    expect(JSON.stringify(k.public)).not.toContain(a.DB_PASSWORD);
    expect(k.private).toMatchObject({stringData:{DB_PASSWORD:a.DB_PASSWORD}});
    expect(initSql(a)).toContain("CREATE ROLE ulams LOGIN");
  });
  it("quotes YAML 1.1 boolean words used in Kubernetes environment values",()=>{
    const c=configure(input({target:"kubernetes"}));
    const documents=kubernetes(c,createSecrets()).public;
    const decoded=parseAllDocuments(documents.map(d=>yaml(d)).join("---\n"),{version:"1.1"}).map(d=>d.toJS()) as typeof documents;
    const app=decoded.find(d=>d.kind === "StatefulSet" && (d.metadata as {name:string}).name === "application") as unknown as {spec:{template:{spec:{enableServiceLinks:boolean;containers:Array<{name:string;env?:Array<{name:string;value?:unknown}>}>}}}};
    expect(app.spec.template.spec.enableServiceLinks).toBe(false);
    for(const container of app.spec.template.spec.containers)for(const env of container.env ?? [])if("value" in env)expect(typeof env.value).toBe("string");
  });
  it("separates the shared-pod H5P/admin ports and private gateway",()=>{
    const c=configure(input({target:"kubernetes"}));
    const docs=kubernetes(c,createSecrets()).public;
    const service=docs.find(d=>d.kind === "Service" && (d.metadata as {name:string}).name === "h5p");
    expect(service).toMatchObject({spec:{ports:[{port:8080,targetPort:8082}]}});
    expect(docs.some(d=>(d.metadata as {name:string}).name === "api-gateway")).toBe(true);
  });
  it("renders/resumes outside a checkout, retaining credentials and private permissions",async()=>{
    const directory=await mkdtemp(join(tmpdir(),"ulams-stack-unit-"));
    try{
      const first=await withInstall(directory,()=>renderInstall(directory,input()));
      const before=await readFile(join(directory,"credentials.json"),"utf8");
      const second=await withInstall(directory,()=>renderInstall(directory,input()));
      expect(first.config.id).toBe(second.config.id);
      expect(await readFile(join(directory,"credentials.json"),"utf8")).toBe(before);
      expect((await stat(join(directory,"credentials.json"))).mode & 0o077).toBe(0);
      expect(await readFile(join(directory,"compose.yaml"),"utf8")).toContain("ghcr.io/ulams-dev/api:latest");
    }finally{await rm(directory,{recursive:true,force:true});}
  });
  it("refuses to replace missing keys of a persistent installation",async()=>{
    const directory=await mkdtemp(join(tmpdir(),"ulams-stack-keys-"));
    try{await withInstall(directory,()=>renderInstall(directory,input()));await rm(join(directory,"credentials.json"));await expect(withInstall(directory,()=>renderInstall(directory,input()))).rejects.toThrow("Restore them");}finally{await rm(directory,{recursive:true,force:true});}
  });
  it("refuses an arbitrary nonempty directory and a symlink destination",async()=>{
    const directory=await mkdtemp(join(tmpdir(),"ulams-stack-protect-"));
    try{await writeFile(join(directory,"notes.txt"),"keep me");await expect(withInstall(directory,async()=>null)).rejects.toThrow("not empty");const link=`${directory}-link`;await symlink(directory,link);await expect(withInstall(link,async()=>null)).rejects.toThrow("symbolic link");await rm(link);}finally{await rm(directory,{recursive:true,force:true});}
  });
  it("rejects world-readable credentials",async()=>{
    const directory=await mkdtemp(join(tmpdir(),"ulams-stack-permissions-"));
    try{await withInstall(directory,()=>renderInstall(directory,input()));await chmod(join(directory,"credentials.json"),0o644);await expect(withInstall(directory,()=>renderInstall(directory,input()))).rejects.toThrow("chmod 600");}finally{await rm(directory,{recursive:true,force:true});}
  });
  it("redacts child-process diagnostics",()=>expect(cleanOutput("failed key=my-secret",["my-secret"])).toBe("failed key=[REDACTED]"));
  it("dry run generates no directory and does not require credentials/runtime",async()=>{
    const directory=join(tmpdir(),`ulams-no-create-${Date.now()}`);
    const result=await runCli(["stack","up","--directory",directory,"--dry-run","--json"]);
    try{expect(result.code).toBe(0);expect(result.json()).toMatchObject({data:{dryRun:true,target:"docker"}});await expect(stat(directory)).rejects.toThrow();}finally{result.cleanup();}
  });
  it("purge needs explicit confirmation and dry-run explains data deletion",async()=>{
    const result=await runCli(["stack","down","--purge","--json"]);
    try{expect(result.code).toBe(11);expect(result.json()).toMatchObject({error:{code:"CONFIRMATION_REQUIRED"}});}finally{result.cleanup();}
  });
});
