import { stringify } from "yaml";
import { caddySnippets } from "./caddy-snippets.ts";
import { APP_IMAGES, INFRA_IMAGES, urls, type StackConfig, type Secrets } from "./config.ts";

const secret = (name: string) => `@secret:${name}`;
export function environments(c: StackConfig): Record<string, Record<string, string>> {
  const u = urls(c), scheme = c.mode === "development" ? "http" : "https";
  const port = c.mode === "development" ? c.httpPort : c.httpsPort;
  const suffix = port === (scheme === "http" ? 80 : 443) ? "" : `:${port}`;
  const front = `{slug}.${c.domain}`, api = `{slug}-api.${c.domain}`, admin = `{slug}-admin.${c.domain}`, content = `{slug}-content.${c.domain}`;
  const apiEnv: Record<string,string> = {
    JWT_PRIVATE_KEY_BASE64: secret("JWT_PRIVATE_KEY_BASE64"), JWT_PUBLIC_KEY_BASE64: secret("JWT_PUBLIC_KEY_BASE64"),
    LARAVEL_COURSE_BUILDER_LEARNER_PATH: "/learn/{id}", LARAVEL_APP_NAME: "ULAMS", LARAVEL_APP_ENV: c.mode === "development" ? "local" : "production", LARAVEL_APP_DEBUG: "false", LARAVEL_APP_KEY: secret("APP_KEY"),
    LARAVEL_APP_URL: u.api, LARAVEL_FRONTEND_URL: u.platform, LARAVEL_ADMIN_URL: u.admin,
    LARAVEL_DB_CONNECTION: "pgsql", LARAVEL_DB_HOST: "postgres", LARAVEL_DB_PORT: "5432", LARAVEL_DB_DATABASE: "ulams", LARAVEL_DB_USERNAME: "ulams", LARAVEL_DB_PASSWORD: secret("DB_PASSWORD"),
    LARAVEL_DB_ADMIN_DATABASE: "postgres", LARAVEL_DB_ADMIN_USERNAME: "ulams_owner", LARAVEL_DB_ADMIN_PASSWORD: secret("POSTGRES_PASSWORD"),
    LARAVEL_REDIS_HOST: "valkey", LARAVEL_REDIS_PORT: "6379", LARAVEL_REDIS_PASSWORD: secret("VALKEY_PASSWORD"),
    LARAVEL_CACHE_DRIVER: "redis", LARAVEL_SESSION_DRIVER: "cookie", LARAVEL_QUEUE_CONNECTION: "redis", LARAVEL_QUEUE_DRIVER: "redis", LARAVEL_BROADCAST_DRIVER: "log", LARAVEL_MAIL_DRIVER: "log", LARAVEL_MJML_API_URL: "http://mjml:15500/v1",
    LARAVEL_FILESYSTEM_DRIVER: "s3", LARAVEL_AWS_ACCESS_KEY_ID: secret("S3_KEY"), LARAVEL_AWS_SECRET_ACCESS_KEY: secret("S3_SECRET"), LARAVEL_AWS_DEFAULT_REGION: "us-east-1", LARAVEL_AWS_BUCKET: "ulams", LARAVEL_AWS_ENDPOINT: "http://object-store:8333", LARAVEL_AWS_URL: `${u.storage}/ulams`, LARAVEL_AWS_USE_PATH_STYLE_ENDPOINT: "true",
    LARAVEL_H5P_SERVICE_URL: "http://h5p:8080", LARAVEL_H5P_INTERNAL_TOKEN: secret("H5P_INTERNAL_TOKEN"), LARAVEL_H5P_SERVICE_CONFIG_DIR: "/var/www/html/storage/h5p-service",
    LARAVEL_PDF_SERVICE_URL: "http://pdf:3000", LARAVEL_PDF_INTERNAL_TOKEN: secret("PDF_INTERNAL_TOKEN"), LARAVEL_CONTENT_ORIGIN: `${scheme}://platform-content.${c.domain}${suffix}`,
    LARAVEL_INITIAL_USER_EMAIL: c.adminEmail, LARAVEL_INITIAL_USER_PASSWORD: secret("PLATFORM_ADMIN_PASSWORD"),
    LARAVEL_TENANCY_PLATFORM_HOSTS: `api.${c.domain},api,caddy,localhost,127.0.0.1`, LARAVEL_TENANCY_SCHEME: scheme,
    LARAVEL_TENANCY_API_HOST: api, LARAVEL_TENANCY_FRONT_HOST: front, LARAVEL_TENANCY_ADMIN_HOST: admin, LARAVEL_TENANCY_CONTENT_HOST: content, LARAVEL_TENANCY_EMAIL_DOMAIN: `{slug}.${c.domain}`,
    LARAVEL_TENANCY_PORT: String(port), LARAVEL_TENANCY_STORAGE_PUBLIC_URL: u.storage, LARAVEL_TENANCY_S3_PUBLIC_READ_POLICY: "true", LARAVEL_TENANCY_S3_IDENTITY_DRIVER: "seaweedfs", LARAVEL_TENANCY_PLATFORM_API: "true", LARAVEL_TENANT_DEMO_PASSWORD: secret("TENANT_INITIAL_PASSWORD"),
    LARAVEL_AI_DRIVER: c.mode === "development" ? "fake" : "disabled", ULAMS_WORKERS_MODE: "lean", WORKERS_LEAN_INTERVAL: "3", WORKERS_LEAN_HEAVY_INTERVAL: "3", DISABLE_HORIZON: "true", ULAMS_OPTIMIZE: c.mode === "development" ? "false" : "true",
  };
  return {
    api: apiEnv,
    postgres: { PGDATA: "/var/lib/postgresql/data/pgdata", POSTGRES_DB: "postgres", POSTGRES_USER: "ulams_owner", POSTGRES_PASSWORD: secret("POSTGRES_PASSWORD") },
    valkey: { VALKEY_PASSWORD: secret("VALKEY_PASSWORD") },
    "object-store": { AWS_ACCESS_KEY_ID: secret("S3_KEY"), AWS_SECRET_ACCESS_KEY: secret("S3_SECRET"), S3_BUCKET: "ulams" },
    pdf: { PORT: "3000", LOG_LEVEL: "info", PDF_INTERNAL_TOKEN: secret("PDF_INTERNAL_TOKEN"), PDF_MAX_CONCURRENCY: "2", PDF_MAX_QUEUE: "16" },
    h5p: { PORT: c.target === "kubernetes" ? "8082" : "8080", LOG_LEVEL: "info", PUBLIC_URL: u.api, TENANCY_MODE: "env-files", ENV_DIR: "/config", KEYS_DIR: "/config/keys", PLATFORM_HOSTS: `api.${c.domain}`, CORS_ORIGINS: `${u.platform},${u.admin},${u.api}`, TENANT_FRONT_ORIGIN_PATTERNS: `${scheme}://${front},${scheme}://${admin}`, REDIS_HOST: "valkey", REDIS_PORT: "6379", REDIS_PASSWORD: secret("VALKEY_PASSWORD"), S3_ENDPOINT: "http://object-store:8333", S3_REGION: "us-east-1", S3_KEY: secret("S3_KEY"), S3_SECRET: secret("S3_SECRET"), S3_BUCKET: "ulams", S3_FORCE_PATH_STYLE: "true", JWT_PUBLIC_KEY_PATH: "/config/keys/oauth-public.key", LARAVEL_API_URL: c.target === "kubernetes" ? "http://api-gateway:8081" : "http://caddy:8081", LARAVEL_API_HOST: `api.${c.domain}`, H5P_INTERNAL_TOKEN: secret("H5P_INTERNAL_TOKEN") },
    web: { ULAMS_TENANT_HOSTS: `${front}=>${scheme}://${api}${suffix}`, ULAMS_ADMIN_URL: `${scheme}://${admin}${suffix}`, ULAMS_PLATFORM_HOSTS: c.domain, ULAMS_DEFAULT_TENANT: "", ULAMS_DEMO_TENANTS: "", ULAMS_WARM_TENANTS: "", ULAMS_COOKIE_SECURE: c.mode === "development" ? "false" : "true", ULAMS_CONTENT_ORIGIN: `${scheme}://${content}${suffix}`, ULAMS_STORAGE_ORIGINS: u.storage, CSP_ENFORCE: "true", ULAMS_API_INTERNAL_ORIGIN: "http://caddy:8081" },
    admin: { REACT_APP_TENANT_API_HOST_PATTERN: `${admin}=>${scheme}://${api}${suffix}`, REACT_APP_API_URL: u.api },
    caddy: gatewayEnvironment(c), mjml: {},
  };
}
export function gatewayEnvironment(c: StackConfig): Record<string,string> {
  const scheme = c.mode === "development" ? "http" : "https", port = c.mode === "development" ? c.httpPort : c.httpsPort;
  const d = c.domain.replaceAll(".", "[.]");
  return { ULAMS_DOMAIN: c.domain, ULAMS_FCGI_HTTPS: scheme === "https" ? "on" : "off", ULAMS_SCHEME: scheme, ULAMS_PORT_SUFFIX: port === (scheme === "http" ? 80 : 443) ? "" : `:${port}`,
    ULAMS_PLATFORM_CONTENT_HOST: `platform-content.${c.domain}`, ULAMS_RE_CONTENT_ORIGIN: `^(null|https?://[^/:]+-content[.]${d}(:[0-9]+)?)$`,
    ULAMS_RE_FRONT: `^([a-z][a-z0-9]*)[.]${d}$`, ULAMS_RE_API: `^([a-z][a-z0-9]*)-api[.]${d}$`, ULAMS_RE_ADMIN: `^([a-z][a-z0-9]*)-admin[.]${d}$`, ULAMS_RE_CONTENT: `^([a-z][a-z0-9]*)-content[.]${d}$`,
    ULAMS_API_HOST_OF_SLUG: `{re.slug.1}-api.${c.domain}`, ULAMS_FRONT_HOST_OF_SLUG: `{re.slug.1}.${c.domain}`, ULAMS_ADMIN_HOST_OF_SLUG: `{re.slug.1}-admin.${c.domain}`, ULAMS_CSP_HEADER: "Content-Security-Policy" };
}
export function caddyfile(c: StackConfig): string {
  const production = c.mode === "production";
  const host = c.domain, d = host.replaceAll(".", "[.]");
  const known = [host, `api.${host}`, `admin.${host}`, `storage.${host}`, `platform-content.${host}`, `${c.tenant}.${host}`, `${c.tenant}-api.${host}`, `${c.tenant}-admin.${host}`, `${c.tenant}-content.${host}`];
  const global = production ? `{\n http_port ${c.httpPort}\n https_port ${c.httpsPort}\n on_demand_tls {\n ask http://127.0.0.1:5555/check\n }\n}\n` : "{\n auto_https off\n}\n";
  const tls = production ? (c.tls === "internal" ? "tls internal {\n on_demand\n}\n" : "tls {\n on_demand\n}\n") : "";
  const site = `${production ? "https" : "http"}://:${production ? c.httpsPort : c.httpPort}`;
  return `${global}${caddySnippets}\n${site} {\n ${tls}
 header X-Ulams-Stack ${c.id}
 request_header -X-Ulams-Content-Origin
 request_header -X-Ulams-Api-Host
 @storage host storage.${host}
 handle @storage {
  request_header -Cookie
  header -Set-Cookie
  header X-Content-Type-Options nosniff
  @active path *.svg *.svgz *.html *.htm *.xhtml *.xml
  header @active Content-Security-Policy "script-src 'none'; sandbox"
  @writes not method GET HEAD OPTIONS
  respond @writes 405
  reverse_proxy object-store:8333
 }
 @platform_front host ${host}
 @platform_api host api.${host}
 @platform_admin host admin.${host}
 @platform_content host platform-content.${host}
 @tenant_front vars_regexp slug {http.request.host} {$ULAMS_RE_FRONT}
 @tenant_api vars_regexp slug {http.request.host} {$ULAMS_RE_API}
 @tenant_admin vars_regexp slug {http.request.host} {$ULAMS_RE_ADMIN}
 @tenant_content vars_regexp slug {http.request.host} {$ULAMS_RE_CONTENT}
 handle @platform_front {\n import web\n }
 handle @platform_api {\n import api\n }
 handle @platform_admin {\n import admin ${urls(c).api}\n }
 handle @platform_content {\n import content_origin api.${host} ${urls(c).api} "${urls(c).platform} ${urls(c).admin}"\n }
 handle @tenant_front {\n import web\n }
 handle @tenant_api {\n import api\n }
 handle @tenant_admin {\n import admin {$ULAMS_SCHEME}://{$ULAMS_API_HOST_OF_SLUG}{$ULAMS_PORT_SUFFIX}\n }
 handle @tenant_content {\n import content_origin {$ULAMS_API_HOST_OF_SLUG} {$ULAMS_SCHEME}://{$ULAMS_API_HOST_OF_SLUG}{$ULAMS_PORT_SUFFIX} "{$ULAMS_SCHEME}://{$ULAMS_FRONT_HOST_OF_SLUG}{$ULAMS_PORT_SUFFIX} {$ULAMS_SCHEME}://{$ULAMS_ADMIN_HOST_OF_SLUG}{$ULAMS_PORT_SUFFIX}"\n }
 handle {\n respond "Not found" 404\n }
}
http://:8081 {
 request_header -X-Ulams-Content-Origin
 @forwarded_h5p {
  header X-Ulams-Api-Host *
  path /h5p /h5p/*
 }
 handle @forwarded_h5p {
  reverse_proxy h5p:8080 {
   header_up Host {http.request.header.X-Ulams-Api-Host}
   header_up X-Forwarded-Host {http.request.header.X-Ulams-Api-Host}
  }
 }
 @forwarded header X-Ulams-Api-Host *
 handle @forwarded {
  reverse_proxy api:9000 {
   header_up Host {http.request.header.X-Ulams-Api-Host}
   transport fastcgi {
    env SCRIPT_FILENAME /var/www/html/index.php
    env HTTPS {$ULAMS_FCGI_HTTPS}
    env HTTP_HOST {http.request.header.X-Ulams-Api-Host}
    env SERVER_NAME {http.request.header.X-Ulams-Api-Host}
   }
  }
 }
 handle {
  reverse_proxy api:9000 {
   transport fastcgi {
    env SCRIPT_FILENAME /var/www/html/index.php
    env HTTPS {$ULAMS_FCGI_HTTPS}
   }
  }
 }
}
${production ? `http://:5555 {
 @fixed expression {query.domain} in [${known.map(h => JSON.stringify(h)).join(",")}]
 handle @fixed {\n respond 200\n }
 @tenant vars_regexp tenant {query.domain} ^([a-z][a-z0-9]*)(-api|-admin|-content)?[.]${d}$
 handle @tenant {
  @exists file {\n root /config\n try_files /.env.{re.tenant.1}-api.${host}\n }
  handle @exists {\n respond 200\n }
  handle {\n respond 404\n }
 }
 handle {\n respond 404\n }
}\n` : ""}`;
}
export function initSql(s: Secrets): string {
  if (!/^[a-f0-9]+$/.test(s.DB_PASSWORD ?? "")) throw new Error("Invalid generated database credential.");
  return `CREATE ROLE ulams LOGIN PASSWORD '${s.DB_PASSWORD}';\nCREATE DATABASE ulams OWNER ulams;\n`;
}
const shellHealth = (command: string) => ({ test: ["CMD-SHELL", command], interval: "5s", timeout: "5s", retries: 60, start_period: "20s" });
export function compose(c: StackConfig) {
  const env = environments(c);
  const labels = { "io.ulams.stack": c.id };
  const common = { restart: "unless-stopped", labels, logging: { driver: "json-file", options: { "max-size": "10m", "max-file": "3" } } };
  const refs = (values: Record<string,string>) => Object.fromEntries(Object.entries(values).map(([k,v]) => [k, v.startsWith("@secret:") ? `\${${v.slice(8)}:?generated credentials required}` : v]));
  const image = (name: typeof APP_IMAGES[number]) => `${c.imageRegistry}/${name}:${c.imageTag}`;
  const healthy = (name: string) => ({ [name]: { condition: "service_healthy" } });
  const services: Record<string,unknown> = {
    postgres: { ...common, image: INFRA_IMAGES.postgres, environment: refs(env.postgres!), volumes: ["postgres_data:/var/lib/postgresql/data", "./init.sql:/docker-entrypoint-initdb.d/10-ulams.sql:ro"], healthcheck: shellHealth('pg_isready -U "$$POSTGRES_USER" -d postgres') },
    valkey: { ...common, image: INFRA_IMAGES.valkey, environment: refs(env.valkey!), command: ["sh","-c",'exec valkey-server --requirepass "$$VALKEY_PASSWORD" --appendonly yes --maxmemory 128mb --maxmemory-policy noeviction'], volumes: ["valkey_data:/data"], healthcheck: shellHealth('valkey-cli -a "$$VALKEY_PASSWORD" --no-auth-warning ping | grep -q PONG') },
    "object-store": { ...common, image: INFRA_IMAGES.storage, command: ["mini", "-dir=/data", "-s3.iam.readOnly=false"], environment: refs(env["object-store"]!), volumes: ["object_data:/data"], healthcheck: shellHealth("wget -q -O /dev/null http://127.0.0.1:8333/healthz") },
    mjml: { ...common, image: image("mjml") },
    pdf: { ...common, image: image("pdf"), environment: refs(env.pdf!) },
    api: { ...common, image: image("api"), environment: refs(env.api!), volumes: ["api_storage:/var/www/html/storage", "h5p_config:/var/www/html/storage/h5p-service"], depends_on: { ...healthy("postgres"), ...healthy("valkey"), ...healthy("object-store"), pdf: { condition: "service_started" }, mjml: { condition: "service_started" } }, healthcheck: shellHealth("test -f /var/www/html/inited && php artisan health:check") },
    h5p: { ...common, image: image("h5p"), environment: refs(env.h5p!), group_add: ["82"], volumes: ["h5p_config:/config:ro", "h5p_libraries:/data/libraries"], depends_on: { api: { condition: "service_started" }, ...healthy("valkey") } },
    web: { ...common, image: image("web"), environment: refs(env.web!) },
    admin: { ...common, image: image("admin"), environment: refs(env.admin!), healthcheck: shellHealth("wget -q -O /dev/null http://127.0.0.1:8080/") },
    caddy: { ...common, image: INFRA_IMAGES.caddy, environment: refs(env.caddy!), volumes: ["./Caddyfile:/etc/caddy/Caddyfile:ro", "caddy_data:/data", "caddy_config:/config", "h5p_config:/config:ro"], networks: { default: { aliases: [`storage.${c.domain}`] } }, ports: c.mode === "development" ? [`127.0.0.1:${c.httpPort}:${c.httpPort}`] : [`${c.httpPort}:${c.httpPort}`, `${c.httpsPort}:${c.httpsPort}`, `${c.httpsPort}:${c.httpsPort}/udp`], healthcheck: shellHealth("wget -q -O /dev/null http://127.0.0.1:2019/config/") },
  };
  return { name: c.name, services, volumes: Object.fromEntries(["postgres_data","valkey_data","object_data","api_storage","h5p_config","h5p_libraries","caddy_data","caddy_config"].map(k=>[k,{ labels }])) };
}
export function yaml(value: unknown): string { return stringify(value, { lineWidth: 0, compat: "yaml-1.1" }); }
