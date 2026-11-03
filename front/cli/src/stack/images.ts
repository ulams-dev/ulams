import { APP_IMAGES } from "./config.ts";

const ACCEPT = "application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.oci.image.manifest.v1+json, application/vnd.docker.distribution.manifest.v2+json";
async function registry(name: string, path: string, signal?: AbortSignal) {
  const scope = `repository:ulams-dev/${name}:pull`;
  const response = await fetch(`https://ghcr.io/token?service=ghcr.io&scope=${encodeURIComponent(scope)}`,{signal});
  if(!response.ok)throw new Error("The public image registry is unavailable.");
  const auth = await response.json() as { token: string };
  return fetch(`https://ghcr.io/v2/ulams-dev/${name}/${path}`,{headers:{Authorization:`Bearer ${auth.token}`,Accept:ACCEPT},signal});
}
async function revision(name: string, signal?: AbortSignal): Promise<string|null> {
  const latest = await registry(name,"manifests/latest",signal);
  if(!latest.ok)return null;
  let manifest = await latest.json() as { manifests?: Array<{digest:string;platform?:{os:string}}>; config?: {digest:string} };
  if(manifest.manifests){const entry = manifest.manifests.find(m=>m.platform?.os === "linux");if(!entry)return null;manifest=await (await registry(name,`manifests/${entry.digest}`,signal)).json() as typeof manifest;}
  if(!manifest.config)return null;
  const config = await (await registry(name,`blobs/${manifest.config.digest}`,signal)).json() as { config?: { Labels?: Record<string,string> } };
  return config.config?.Labels?.["org.opencontainers.image.revision"] ?? null;
}
/** Published jobs can finish independently: never mix the latest images of different commits. */
export async function resolveImageTag(wanted: string, signal?: AbortSignal): Promise<string> {
  const candidates = wanted === "latest" ? [...new Set((await Promise.all(APP_IMAGES.map(name=>revision(name,signal)))).filter((v):v is string=>Boolean(v)).map(v=>`sha-${v.slice(0,7)}`))] : [wanted];
  for(const tag of candidates){
    const available = await Promise.all(APP_IMAGES.map(async name=>(await registry(name,`manifests/${tag}`,signal)).ok));
    if(available.every(Boolean))return tag;
  }
  throw new Error("No matching release exists for all six application images. Retry after image publishing completes or pass --image-tag sha-<revision>.");
}
