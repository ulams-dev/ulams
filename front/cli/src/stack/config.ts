import { randomBytes, generateKeyPairSync } from "node:crypto";
import { z } from "zod";

export const stackInput = z.object({
  directory: z.string().min(1).optional().describe("Install directory; default ./ulams-stack. Reuse it to resume."),
  target: z.enum(["docker", "kubernetes"]).optional(),
  mode: z.enum(["development", "production"]).optional(),
  name: z.string().regex(/^[a-z][a-z0-9-]{1,39}$/).optional().describe("Compose project / Kubernetes namespace; default ulams."),
  domain: z.string().regex(/^(?=.{1,180}$)[a-z0-9]+(?:[.-][a-z0-9]+)*$/).optional(),
  tenant: z.string().regex(/^[a-z][a-z0-9]{1,29}$/).optional().describe("Initial academy slug; default academy."),
  admin_email: z.string().email().optional(),
  http_port: z.coerce.number().int().min(1).max(65535).optional().describe("Gateway HTTP port; defaults to 8480 locally and 80 in production."),
  https_port: z.coerce.number().int().min(1).max(65535).optional(),
  tls: z.enum(["acme", "internal"]).optional().describe("Production TLS issuer; internal is for private networks/tests."),
  image_registry: z.string().regex(/^[a-z0-9][a-z0-9./:_-]*$/).optional().describe("Application image prefix; default ghcr.io/ulams-dev."),
  image_tag: z.string().regex(/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/).optional().describe("Matching application image release; latest resolves and pins a complete SHA release."),
  context: z.string().min(1).optional().describe("kubectl context; never changes the current context."),
  storage_class: z.string().regex(/^[a-z0-9][a-z0-9.-]*$/).optional(),
  gateway: z.enum(["LoadBalancer", "NodePort", "ClusterIP"]).optional(),
  node_port: z.coerce.number().int().min(30000).max(32767).optional(),
  ready_timeout: z.coerce.number().int().min(30).max(3600).default(900),
});
export type StackInput = z.infer<typeof stackInput>;
export interface StackConfig {
  format: 1; credentialsReady?: boolean; id: string; target: "docker" | "kubernetes"; mode: "development" | "production";
  name: string; domain: string; tenant: string; adminEmail: string; httpPort: number; httpsPort: number;
  tls: "acme" | "internal"; imageRegistry: string; imageTag: string; context?: string; storageClass?: string;
  gateway: "LoadBalancer" | "NodePort" | "ClusterIP"; nodePort: number;
}
export type Secrets = Record<string, string>;
export const APP_IMAGES = ["api", "web", "admin", "h5p", "pdf", "mjml"] as const;
export const INFRA_IMAGES = {
  postgres: "postgres:17.11-alpine", valkey: "valkey/valkey:8.1.10-alpine",
  caddy: "caddy:2.11.7-alpine", storage: "chrislusf/seaweedfs:4.37",
};
export function configure(input: StackInput, previous?: StackConfig): StackConfig {
  const mode = input.mode ?? previous?.mode ?? "development";
  const domain = input.domain ?? previous?.domain ?? (mode === "development" ? "ulams.localhost" : "");
  if (!domain) throw new Error("Production requires --domain and DNS pointing to the gateway.");
  const target = input.target ?? previous?.target ?? "docker";
  const gateway = input.gateway ?? previous?.gateway ?? (mode === "development" ? "ClusterIP" : "LoadBalancer");
  const nodePort = input.node_port ?? previous?.nodePort ?? 30480;
  const directNode = target === "kubernetes" && gateway === "NodePort";
  if(directNode && mode === "production" && nodePort === 30000)throw new Error("Production NodePort needs --node-port greater than 30000 for its adjacent HTTP port.");
  const config: StackConfig = {
    format: 1, credentialsReady: previous?.credentialsReady ?? false, id: previous?.id ?? randomBytes(16).toString("hex"), mode, domain,
    target, name: input.name ?? previous?.name ?? "ulams",
    tenant: input.tenant ?? previous?.tenant ?? "academy", adminEmail: input.admin_email ?? previous?.adminEmail ?? `admin@${domain}`,
    httpPort: input.http_port ?? previous?.httpPort ?? (directNode ? (mode === "development" ? nodePort : nodePort-1) : (mode === "development" ? 8480 : 80)),
    httpsPort: input.https_port ?? previous?.httpsPort ?? (directNode && mode === "production" ? nodePort : 443), tls: input.tls ?? previous?.tls ?? "acme",
    imageRegistry: input.image_registry ?? previous?.imageRegistry ?? "ghcr.io/ulams-dev",
    imageTag: input.image_tag ?? previous?.imageTag ?? "latest", context: input.context ?? previous?.context,
    storageClass: input.storage_class ?? previous?.storageClass, gateway, nodePort,
  };
  if (previous) for (const key of ["target", "mode", "name", "domain", "tenant", "httpPort", "httpsPort", "context"] as const) {
    if (previous[key] !== undefined && config[key] !== previous[key]) throw new Error(`Cannot change ${key} of an existing install; use another directory and project/namespace.`);
  }
  if (config.mode === "production" && config.tls === "acme" && (domain === "localhost" || domain.endsWith(".localhost"))) throw new Error("ACME needs a public DNS domain. Private networks must explicitly select --tls internal.");
  if (["api", "admin", "www", "storage", "platform", "default", "test", "postgres", "content", "mail", "docs"].includes(config.tenant)) throw new Error("The initial tenant uses a reserved slug.");
  if (config.httpPort === config.httpsPort || config.httpPort === 8081 || config.httpPort === 5555 || config.httpPort === 2019) throw new Error("Choose distinct gateway ports, excluding internal ports 8081, 5555 and 2019.");
  return config;
}
export function createSecrets(): Secrets {
  const rsa = generateKeyPairSync("rsa", { modulusLength: 2048, publicKeyEncoding: { type: "spki", format: "pem" }, privateKeyEncoding: { type: "pkcs8", format: "pem" } });
  const secrets: Secrets = {};
  for (const key of ["POSTGRES_PASSWORD", "DB_PASSWORD", "VALKEY_PASSWORD", "S3_SECRET", "H5P_INTERNAL_TOKEN", "PDF_INTERNAL_TOKEN", "PLATFORM_ADMIN_PASSWORD", "TENANT_INITIAL_PASSWORD"]) secrets[key] = randomBytes(24).toString("hex");
  secrets.APP_KEY = `base64:${randomBytes(32).toString("base64")}`;
  secrets.JWT_PRIVATE_KEY_BASE64 = Buffer.from(rsa.privateKey).toString("base64");
  secrets.JWT_PUBLIC_KEY_BASE64 = Buffer.from(rsa.publicKey).toString("base64");
  secrets.S3_KEY = `ulams${randomBytes(8).toString("hex")}`;
  return secrets;
}
export function urls(c: StackConfig) {
  const scheme = c.mode === "development" ? "http" : "https";
  const port = c.mode === "development" ? c.httpPort : c.httpsPort;
  const origin = (host: string) => `${scheme}://${host}${port === (scheme === "http" ? 80 : 443) ? "" : `:${port}`}`;
  return { platform: origin(c.domain), api: origin(`api.${c.domain}`), admin: origin(`admin.${c.domain}`), academy: origin(`${c.tenant}.${c.domain}`), academyApi: origin(`${c.tenant}-api.${c.domain}`), academyAdmin: origin(`${c.tenant}-admin.${c.domain}`), studio: `${origin(`${c.tenant}.${c.domain}`)}/studio/`, storage: origin(`storage.${c.domain}`) };
}
