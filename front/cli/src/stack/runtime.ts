import { spawn } from "node:child_process";
import { open, readFile } from "node:fs/promises";
import { join } from "node:path";
import { CliError } from "../errors.ts";
import type { Ctx } from "../registry/types.ts";
import { urls, type StackConfig, type StackInput, type Secrets } from "./config.ts";
import { atomicWrite, installDirectory, readConfig, readSecrets, renderInstall, withInstall } from "./files.ts";
import { resolveImageTag } from "./images.ts";
import { runProcess } from "./process.ts";

export const composeArgs = (directory:string,c:StackConfig) => ["compose","--project-name",c.name,"--env-file",join(directory,".env"),"-f",join(directory,"compose.yaml")];
export const kubectlArgs = (c:StackConfig) => [...(c.context ? ["--context",c.context] : []),"--namespace",c.name];
const STS = ["postgres","valkey","object-store","application"];
const credentialsHint = (directory: string,c:StackConfig) => ({ file:join(directory,"credentials.json"), platformAdmin: c.adminEmail, academyAdmin:`admin@${c.tenant}.${c.domain}`, academyTutor:`tutor@${c.tenant}.${c.domain}`, academyStudent:`student1@${c.tenant}.${c.domain}` });
function options(ctx:Ctx,directory:string,s:Secrets = {}) {return {cwd:directory,signal:ctx.signal,env:{...process.env,...ctx.env},secrets:Object.values(s)};}
async function ownedDocker(ctx:Ctx,directory:string,c:StackConfig) {
  const opts = options(ctx,directory);
  for(const kind of ["ps","volume"]){
    const args = kind === "ps" ? ["ps","-a","--filter",`label=com.docker.compose.project=${c.name}`,"--format","{{.Label \"io.ulams.stack\"}}"] : ["volume","ls","--filter",`label=com.docker.compose.project=${c.name}`,"--format","{{.Label \"io.ulams.stack\"}}"];
    const output = await runProcess("docker",args,opts);
    if(output.trim() && output.trim().split("\n").some(id=>id.trim() !== c.id))throw new CliError("CONFLICT",`Docker project ${c.name} contains resources owned by another installation. Use a different --name and directory.`);
  }
}
async function ownedKubernetes(ctx:Ctx,directory:string,c:StackConfig,allowMissing:boolean) {
  const value = await runProcess("kubectl",[...(c.context?["--context",c.context]:[]),"get","namespace",c.name,"--ignore-not-found","-o","json"],options(ctx,directory));
  if(!value.trim()){if(allowMissing)return;throw new CliError("NOT_FOUND","The managed Kubernetes namespace does not exist.");}
  const namespace = JSON.parse(value) as { metadata: {labels?: Record<string,string>} };
  if(namespace.metadata.labels?.["io.ulams.stack"] !== c.id)throw new CliError("CONFLICT",`Namespace ${c.name} is not owned by this installation. Refusing to modify it.`);
}
export async function stackPlan(input:StackInput) {
  const directory = installDirectory(input.directory), prior = await readConfig(directory);
  const { configure } = await import("./config.ts");
  const config = configure(input,prior);
  return { directory,target:config.target,mode:config.mode,project:config.name,academy:{slug:config.tenant,name:input.academy_name ?? "Your academy",theme:input.theme ?? "coffee",demoStudents:input.users ?? 1},urls:urls(config), note:"Generate private credentials once, render configuration, launch services, provision the academy and wait for readiness. No existing data is removed." };
}
export async function stackRender(ctx:Ctx,input:StackInput) {
  const directory=installDirectory(input.directory);
  return withInstall(directory,async()=>{
    const {config}=await renderInstall(directory,input);
    return {directory,target:config.target,mode:config.mode,urls:urls(config),files:["compose.yaml","Caddyfile",".env","kubernetes.yaml","kubernetes-secret.yaml","credentials.json"],credentials:credentialsHint(directory,config)};
  });
}
const bootstrapCode = "require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); Ulams\\Tenancy\\Services\\S3BucketProvisioner::fromConfig(config('ulams_tenancy.s3'))->ensure('ulams');";
export async function stackUp(ctx:Ctx,input:StackInput) {
  const directory=installDirectory(input.directory);
  return withInstall(directory,async()=>{
    const initial = await renderInstall(directory,input);
    let c = initial.config;
    const s = initial.secrets;
    const opts = options(ctx,directory,s);
    ctx.io.stderr(`Checking ${c.target} runtime for ${c.name} (${c.mode})…`);
    if(c.target === "docker") {
      await runProcess("docker",["version","--format","{{.Server.Version}}"],opts);
      await runProcess("docker",["compose","version","--short"],opts);
      await ownedDocker(ctx,directory,c);
    } else {
      if(!c.context){c.context=(await runProcess("kubectl",["config","current-context"],opts)).trim();if(!c.context)throw new CliError("USAGE","Select or supply a Kubernetes context first.");}
      await runProcess("kubectl",[...kubectlArgs(c),"cluster-info"],opts);
      await ownedKubernetes(ctx,directory,c,true);
      const storage = await runProcess("kubectl",[...(c.context?["--context",c.context]:[]),"get","storageclasses","-o","json"],opts);
      const classes = (JSON.parse(storage) as {items:Array<{metadata:{name:string;annotations?:Record<string,string>}}>}).items;
      if(c.storageClass ? !classes.some(sc=>sc.metadata.name === c.storageClass) : !classes.some(sc=>sc.metadata.annotations?.["storageclass.kubernetes.io/is-default-class"] === "true"))throw new CliError("USAGE","Kubernetes needs a default StorageClass or --storage-class naming an existing class.");
    }
    if(c.imageRegistry === "ghcr.io/ulams-dev" && (c.imageTag === "latest" || input.image_tag)) {
      ctx.io.stderr("Resolving one matching application-image release…");
      c.imageTag=await resolveImageTag(c.imageTag,AbortSignal.any([ctx.signal,AbortSignal.timeout(120000)]));
    } else if(c.imageTag === "latest")throw new CliError("USAGE","Custom image registries require an explicit --image-tag.");
    await atomicWrite(directory,"stack.json",JSON.stringify(c,null,2)+"\n");
    ({config:c}=await renderInstall(directory,{...input,image_tag:c.imageTag,context:c.context}));
    ctx.io.stderr(`Starting persistent services from ${c.imageRegistry}:${c.imageTag}…`);
    if(c.target === "docker"){
      await runProcess("docker",[...composeArgs(directory,c),"config","--quiet"],opts);
      await runProcess("docker",[...composeArgs(directory,c),"up","--detach","--pull","missing","--wait","--wait-timeout",String(input.ready_timeout)],opts);
    } else {
      // Namespace first: Secret creation must happen before scheduling the application.
      await runProcess("kubectl",[...kubectlArgs(c),"apply","-f","-"],{...opts,stdin:JSON.stringify({apiVersion:"v1",kind:"Namespace",metadata:{name:c.name,labels:{"io.ulams.stack":c.id}}})});
      await runProcess("kubectl",[...kubectlArgs(c),"apply","-f",join(directory,"kubernetes-secret.yaml"),"-f",join(directory,"kubernetes.yaml")],opts);
      for(const name of STS) {
        // OnDelete lets us recover a failed old revision without StatefulSet rollback deadlocks.
        for(let attempt=0;attempt<30;attempt++) {
          const set = JSON.parse(await runProcess("kubectl",[...kubectlArgs(c),"get","statefulset",name,"-o","json"],opts)) as {metadata:{generation:number};status?:{observedGeneration?:number;updateRevision?:string}};
          if((set.status?.observedGeneration ?? 0) >= set.metadata.generation && set.status?.updateRevision) {
            const list = JSON.parse(await runProcess("kubectl",[...kubectlArgs(c),"get","pods","-l",`io.ulams.stack=${c.id},io.ulams.role=${name}`,"-o","json"],opts)) as {items:Array<{metadata:{name:string;labels:Record<string,string>}}>};
            for(const pod of list.items)if(pod.metadata.labels["controller-revision-hash"] !== set.status.updateRevision)await runProcess("kubectl",[...kubectlArgs(c),"delete","pod",pod.metadata.name,"--wait=true"],opts);
            break;
          }
          await new Promise(done=>setTimeout(done,500));
        }
        await runProcess("kubectl",[...kubectlArgs(c),"wait",`pod/${name}-0`,"--for=condition=Ready",`--timeout=${input.ready_timeout}s`],opts);
      }
    }
    const exec = (args:string[]) => c.target === "docker" ? runProcess("docker",[...composeArgs(directory,c),"exec","-T","api",...args],opts) : runProcess("kubectl",[...kubectlArgs(c),"exec","statefulset/application","-c","api","--",...args],opts);
    ctx.io.stderr("Preparing the initial academy through ULAMS tenant provisioning…");
    await exec(["php","-r",bootstrapCode]);
    await exec(["php","artisan","ulams:tenant:create",c.tenant,`--name=${input.academy_name ?? "Your academy"}`,`--theme=${input.theme ?? "coffee"}`,`--users=${input.users ?? 1}`,"--demo=off","--no-interaction"]);
    await exec(["php","artisan","ulams:h5p:export-config","--no-interaction"]);
    await exec(["php","artisan","health:check"]);
    let caFile: string | undefined;
    if(c.mode === "production" && c.tls === "internal") {
      const pem = c.target === "docker" ? await runProcess("docker",[...composeArgs(directory,c),"exec","-T","caddy","cat","/data/caddy/pki/authorities/local/root.crt"],opts) : await runProcess("kubectl",[...kubectlArgs(c),"exec","statefulset/application","-c","caddy","--","cat","/data/caddy/pki/authorities/local/root.crt"],opts);
      await atomicWrite(directory,"ca.crt",pem);caFile=join(directory,"ca.crt");
    }
    if(c.target === "kubernetes" && c.gateway === "LoadBalancer") {
      const deadline=Date.now()+input.ready_timeout*1000;
      for(;;) {
        const service=JSON.parse(await runProcess("kubectl",[...kubectlArgs(c),"get","service","caddy","-o","json"],opts)) as {status?:{loadBalancer?:{ingress?:unknown[]}}};
        if(service.status?.loadBalancer?.ingress?.length)break;
        if(Date.now()>=deadline)throw new CliError("TIMEOUT","The stack is running but the gateway has no LoadBalancer address. Install/configure a load-balancer provider or choose --gateway ClusterIP/NodePort.",{hint:"The namespace and data were retained. Repeat stack up after configuring the gateway."});
        await new Promise(done=>setTimeout(done,2000));
      }
    }
    let localForward:Record<string,unknown>|undefined;
    if(c.target === "kubernetes" && c.gateway === "ClusterIP")localForward=await portForward(ctx,directory,c);
    ctx.io.stderr("ULAMS is ready. Credentials are saved privately; no password is printed.");
    return {ready:true,directory,target:c.target,mode:c.mode,imageTag:c.imageTag,urls:urls(c),credentials:credentialsHint(directory,c),...(caFile?{caFile}:{}),...(localForward?{localForward}:{}),...(c.target === "kubernetes" ? {namespace:c.name,context:c.context,gateway:c.gateway}: {})};
  });
}
async function portForward(ctx:Ctx,directory:string,c:StackConfig) {
  const port = c.mode === "development" ? c.httpPort : c.httpsPort;
  const marker = async (): Promise<boolean> => {
    const production=c.mode === "production";
    const request = production ? (await import("node:https")).request : (await import("node:http")).request;
    const ca = production && c.tls === "internal" ? await readFile(join(directory,"ca.crt")) : undefined;
    return new Promise(resolve=>{
      const req=request({hostname:"127.0.0.1",port,path:"/",method:"GET",headers:{Host:c.domain},...(production?{servername:c.domain,ca}:{}),agent:false},response=>{response.resume();resolve(response.headers["x-ulams-stack"]===c.id);});
      req.setTimeout(1000,()=>req.destroy());req.on("error",()=>resolve(false));req.end();
    });
  };
  const existing=await marker();
  if(existing)return {port,retained:true};
  const log=await open(join(directory,"port-forward.log"),"w",0o600);
  const child=spawn("kubectl",[...kubectlArgs(c),"port-forward","--address=127.0.0.1","service/caddy",`${port}:${port}`],{cwd:directory,env:{...process.env,...ctx.env},detached:true,stdio:["ignore",log.fd,log.fd]});
  child.on("error",()=>undefined);child.unref();await log.close();
  await atomicWrite(directory,"port-forward.pid",String(child.pid ?? ""));
  for(let attempt=0;attempt<40;attempt++){
    if(ctx.signal.aborted)throw new CliError("TIMEOUT","Cancelled while waiting for local Kubernetes forwarding.");
    if(await marker())return {port,pid:child.pid,log:join(directory,"port-forward.log")};
    if(child.exitCode !== null)break;
    await new Promise(done=>setTimeout(done,250));
  }
  throw new CliError("SERVER_ERROR","Kubernetes port forwarding could not start. Check the local port and port-forward.log. The deployed stack was retained.");
}
export async function stackStatus(ctx:Ctx,directoryInput?:string) {
  const directory=installDirectory(directoryInput),c=await readConfig(directory);
  if(!c)throw new CliError("NOT_FOUND","No managed stack in this directory.");
  const s=await readSecrets(directory),opts=options(ctx,directory,s);
  if(c.target === "docker")await ownedDocker(ctx,directory,c);else await ownedKubernetes(ctx,directory,c,false);
  const output = c.target === "docker" ? await runProcess("docker",[...composeArgs(directory,c),"ps","--all","--format","json"],opts) : await runProcess("kubectl",[...kubectlArgs(c),"get","statefulsets,pods,services,persistentvolumeclaims","-l",`io.ulams.stack=${c.id}`,"-o","json"],opts);
  return {directory,target:c.target,urls:urls(c),resources:c.target === "docker" ? output.trim().split("\n").filter(Boolean).flatMap(line=>{const value=JSON.parse(line) as unknown;return Array.isArray(value)?value:[value];}) : JSON.parse(output)};
}
export async function stackDown(ctx:Ctx,directoryInput?:string,purge=false) {
  const directory=installDirectory(directoryInput);
  return withInstall(directory,async()=>{
    const c=await readConfig(directory);if(!c)throw new CliError("NOT_FOUND","No managed stack in this directory.");
    const s=await readSecrets(directory),opts=options(ctx,directory,s);
    if(c.target === "docker"){
      await ownedDocker(ctx,directory,c);
      await runProcess("docker",[...composeArgs(directory,c),"down",...(purge?["--volumes"]:[]),"--remove-orphans"],opts);
    } else {
      await ownedKubernetes(ctx,directory,c,false);
      if(purge)await runProcess("kubectl",[...kubectlArgs(c),"delete","namespace",c.name,"--wait=true"],opts);
      else {
        await runProcess("kubectl",[...kubectlArgs(c),"scale",...STS.map(name=>`statefulset/${name}`),"--replicas=0"],opts);
        await runProcess("kubectl",[...kubectlArgs(c),"wait","--for=delete","pods","-l",`io.ulams.stack=${c.id}`,"--timeout=900s"],opts);
      }
    }
    return {directory,target:c.target,stopped:true,dataRetained:!purge,credentialsRetained:true};
  });
}
