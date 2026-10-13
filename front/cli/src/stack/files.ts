import { mkdir, readdir, readFile, writeFile, rename, lstat, unlink } from "node:fs/promises";
import { resolve, join } from "node:path";
import { CliError } from "../errors.ts";
import { configure, createSecrets, type StackConfig, type StackInput, type Secrets, stackInput } from "./config.ts";
import { compose, caddyfile, initSql, yaml } from "./render.ts";
import { kubernetes } from "./kubernetes.ts";

export const installDirectory = (directory?: string) => resolve(directory ?? "ulams-stack");
async function exists(path: string) {return lstat(path).then(()=>true,()=>false);}
export async function readConfig(directory: string): Promise<StackConfig|undefined> {
  const path = join(directory,"stack.json");
  if(!await exists(path))return undefined;
  if((await lstat(path)).isSymbolicLink())throw new CliError("CONFLICT","Stack metadata must not be a symbolic link.");
  const config = JSON.parse(await readFile(path,"utf8")) as StackConfig;
  if(config.format !== 1 || !/^[a-f0-9]{32}$/.test(config.id))throw new CliError("CONFLICT","This directory is not a supported CLI-owned stack.");
  const checked = stackInput.safeParse({target:config.target,mode:config.mode,name:config.name,domain:config.domain,tenant:config.tenant,admin_email:config.adminEmail,http_port:config.httpPort,https_port:config.httpsPort,tls:config.tls,image_registry:config.imageRegistry,image_tag:config.imageTag,context:config.context,storage_class:config.storageClass,gateway:config.gateway,node_port:config.nodePort});
  const required = ["target","mode","name","domain","tenant","adminEmail","httpPort","httpsPort","tls","imageRegistry","imageTag","gateway","nodePort"] as const;
  if(!checked.success || required.some(key=>config[key]===undefined))throw new CliError("CONFLICT","Invalid stack metadata. Restore stack.json from this installation's backup.");
  try{configure(checked.data,config);}catch{throw new CliError("CONFLICT","Invalid stack metadata. Restore stack.json from backup.");}
  return config;
}
export async function readSecrets(directory: string): Promise<Secrets> {
  const path = join(directory,"credentials.json"), info = await lstat(path);
  if(info.isSymbolicLink() || (process.platform !== "win32" && (info.mode & 0o077)))throw new CliError("INSECURE_CREDENTIALS","Stack credentials must be a regular private file (chmod 600).");
  const value = JSON.parse(await readFile(path,"utf8")) as Secrets;
  for(const key of ["APP_KEY","DB_PASSWORD","POSTGRES_PASSWORD","VALKEY_PASSWORD","S3_KEY","S3_SECRET","JWT_PRIVATE_KEY_BASE64","JWT_PUBLIC_KEY_BASE64","PLATFORM_ADMIN_PASSWORD","TENANT_INITIAL_PASSWORD","H5P_INTERNAL_TOKEN","PDF_INTERNAL_TOKEN"])if(typeof value[key] !== "string" || !value[key])throw new CliError("CONFLICT",`Incomplete stack credentials: ${key}. Restore the credentials file; do not regenerate keys for an existing database.`);
  return value;
}
export async function atomicWrite(directory: string, name: string, value: string) {
  const target = join(directory,name);
  if(await exists(target) && (await lstat(target)).isSymbolicLink())throw new CliError("CONFLICT",`Refusing to overwrite symbolic link ${name}.`);
  const temporary = `${target}.tmp-${process.pid}`;
  await writeFile(temporary,value,{mode:0o600,flag:"wx"});
  await rename(temporary,target);
}
export async function withInstall<T>(directory: string, run: ()=>Promise<T>): Promise<T> {
  if(await exists(directory) && (await lstat(directory)).isSymbolicLink())throw new CliError("CONFLICT","Install directory must not be a symbolic link.");
  await mkdir(directory,{recursive:true,mode:0o700});
  const contents = await readdir(directory);
  if(!contents.includes("stack.json") && contents.some(name=>name !== ".stack.lock"))throw new CliError("CONFLICT","The directory is not empty and is not owned by this installer. Choose an empty directory.");
  const lock = join(directory,".stack.lock");
  if(await exists(lock)) {
    if((await lstat(lock)).isSymbolicLink())throw new CliError("CONFLICT","Stack lock must not be a symbolic link.");
    const owner = (await readFile(lock,"utf8")).trim();
    if(/^[1-9][0-9]*$/.test(owner)) {
      try { process.kill(Number(owner),0); }
      catch(error) { if((error as NodeJS.ErrnoException).code === "ESRCH")await unlink(lock); }
    }
  }
  try{await writeFile(lock,String(process.pid),{flag:"wx",mode:0o600});}catch{throw new CliError("CONFLICT","Another operation owns this stack lock. Wait for it to finish; after a crash, verify no stack command is running before removing .stack.lock.");}
  try{return await run();}finally{await unlink(lock).catch(()=>undefined);}
}
export async function renderInstall(directory: string, input: StackInput): Promise<{config:StackConfig;secrets:Secrets}> {
  const previous = await readConfig(directory);
  const config = configure(input,previous);
  // Write ownership metadata before other files, so an interrupted render can resume safely.
  await atomicWrite(directory,"stack.json",JSON.stringify(config,null,2)+"\n");
  const hasCredentials = await exists(join(directory,"credentials.json"));
  if(previous?.credentialsReady && !hasCredentials)throw new CliError("CONFLICT","Credentials are missing from an existing install. Restore them from backup; generating new APP_KEY/Passport keys would invalidate persistent data.");
  const secrets = hasCredentials ? await readSecrets(directory) : createSecrets();
  await atomicWrite(directory,"credentials.json",JSON.stringify(secrets,null,2)+"\n");
  config.credentialsReady = true;
  await atomicWrite(directory,"stack.json",JSON.stringify(config,null,2)+"\n");
  await atomicWrite(directory,".env",Object.entries(secrets).map(([k,v])=>`${k}=${v}`).join("\n")+"\n");
  await atomicWrite(directory,"init.sql",initSql(secrets));
  await atomicWrite(directory,"Caddyfile",caddyfile(config));
  await atomicWrite(directory,"compose.yaml",yaml(compose(config)));
  const kube = kubernetes(config,secrets);
  await atomicWrite(directory,"kubernetes.yaml",kube.public.map(doc=>yaml(doc)).join("---\n"));
  await atomicWrite(directory,"kubernetes-secret.yaml",yaml(kube.private));
  await atomicWrite(directory,".gitignore",".env\ncredentials.json\ninit.sql\nkubernetes-secret.yaml\n*.log\n*.pid\n.stack.lock\n");
  await atomicWrite(directory,"README.md",`# ULAMS ${config.mode} stack\n\nGenerated by the ULAMS CLI. Keep this directory and private credentials together.\n\nResume: ulams stack up --directory ${JSON.stringify(directory)}\nStatus: ulams stack status --directory ${JSON.stringify(directory)}\nStop (retain data): ulams stack down --directory ${JSON.stringify(directory)}\nDelete data deliberately: ulams stack down --directory ${JSON.stringify(directory)} --purge --yes\n\nCredentials: credentials.json (private). PLATFORM_ADMIN_PASSWORD and TENANT_INITIAL_PASSWORD are different.\nPlatform admin: ${config.adminEmail}. Academy admin: admin@${config.tenant}.${config.domain}.\nDevelopment uses fake AI; production uses disabled AI until configured. E-mail delivery is logged, not sent until SMTP is configured.\n\nCompose configuration uses .env. Kubernetes credentials are in kubernetes-secret.yaml; never commit or share this file.\nKubernetes requires a StorageClass and an accessible gateway. This singleton topology does not claim HA.\nProduction ACME requires DNS for the platform and tenant host names and reachable ports 80/443.\nInternal TLS requires trusting Caddy's private CA in clients.\n`);
  return {config,secrets};
}
