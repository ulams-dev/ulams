import { environments, caddyfile, initSql } from "./render.ts";
import { INFRA_IMAGES, type StackConfig, type Secrets } from "./config.ts";

type Doc = Record<string, unknown>;
export function kubernetes(c: StackConfig, secrets: Secrets): { public: Doc[]; private: Doc } {
  const namespace = c.name;
  const labels = { "io.ulams.stack": c.id };
  const meta = (name: string) => ({ name, namespace, labels });
  const envs = environments(c);
  const env = (name: string) => Object.entries(envs[name]!).map(([key,value]) => value.startsWith("@secret:")
    ? { name: key, valueFrom: { secretKeyRef: { name: "credentials", key: value.slice(8) } } }
    : { name: key, value });
  const pvc = (name: string, size: string): Doc => ({ apiVersion: "v1", kind: "PersistentVolumeClaim", metadata: meta(name), spec: { accessModes: ["ReadWriteOnce"], ...(c.storageClass ? { storageClassName: c.storageClass } : {}), resources: { requests: { storage: size } } } });
  const service = (name: string, port: number, role: string, target = port): Doc => ({ apiVersion: "v1", kind: "Service", metadata: meta(name), spec: { selector: { ...labels, "io.ulams.role": role }, ports: [{ name: "service", port, targetPort: target }] } });
  const stateful = (name: string, containers: Doc[], volumes: Doc[], extra: Doc = {}): Doc => ({ apiVersion: "apps/v1", kind: "StatefulSet", metadata: meta(name), spec: { serviceName: name, replicas: 1, updateStrategy: { type: "OnDelete", rollingUpdate: null }, selector: { matchLabels: { ...labels, "io.ulams.role": name } }, template: { metadata: { labels: { ...labels, "io.ulams.role": name } }, spec: { automountServiceAccountToken: false, enableServiceLinks: false, securityContext: { fsGroup: 82 }, containers, volumes, ...extra } } } });
  const mount = (name: string, mountPath: string, subPath?: string, readOnly = false) => ({ name, mountPath, ...(subPath ? { subPath } : {}), ...(readOnly ? { readOnly } : {}) });
  const resources = (memory: string, cpu = "100m") => ({ requests: { cpu, memory }, limits: { memory } });
  const tcp = (port: number) => ({ tcpSocket: { port }, timeoutSeconds: 10, periodSeconds: 5, failureThreshold: 120 });
  const http = (port: number, path: string) => ({ httpGet: { port, path, ...(port === 8082 ? { httpHeaders: [{ name: "Host", value: `api.${c.domain}` }] } : {}) }, timeoutSeconds: 10, periodSeconds: 5, failureThreshold: 120 });
  const persistent = (name: string): Doc => ({ name: "data", persistentVolumeClaim: { claimName: name } });
  const postgres = { name: "postgres", image: INFRA_IMAGES.postgres, env: env("postgres"), volumeMounts: [mount("data","/var/lib/postgresql/data"), mount("init","/docker-entrypoint-initdb.d/10-ulams.sql","init.sql",true)], resources: resources("768Mi"), startupProbe: tcp(5432), readinessProbe: { exec: { command: ["sh","-c","pg_isready -U ulams_owner -d postgres"] }, timeoutSeconds: 10, periodSeconds: 5 } };
  const valkey = { name: "valkey", image: INFRA_IMAGES.valkey, env: env("valkey"), command: ["sh","-c",'exec valkey-server --requirepass "$VALKEY_PASSWORD" --appendonly yes --maxmemory 128mb --maxmemory-policy noeviction'], volumeMounts: [mount("data","/data")], resources: resources("192Mi"), startupProbe: tcp(6379), readinessProbe: { exec: { command: ["sh","-c",'valkey-cli -a "$VALKEY_PASSWORD" --no-auth-warning ping | grep -q PONG'] }, timeoutSeconds: 10, periodSeconds: 5 } };
  const storage = { name: "object-store", image: INFRA_IMAGES.storage, args: ["mini","-dir=/data","-s3.iam.readOnly=false"], env: env("object-store"), volumeMounts: [mount("data","/data")], resources: resources("512Mi"), startupProbe: tcp(8333), readinessProbe: tcp(8333) };
  const appImage = (name: string) => `${c.imageRegistry}/${name}:${c.imageTag}`;
  const app: Doc[] = [
    { name: "api", image: appImage("api"), env: env("api"), resources: resources("1536Mi","250m"), volumeMounts: [mount("app","/var/www/html/storage","api-storage"), mount("app","/var/www/html/storage/h5p-service","h5p-config")], startupProbe: { exec: { command: ["sh","-c","test -f /var/www/html/inited && php artisan health:check"] }, timeoutSeconds: 15, periodSeconds: 10, failureThreshold: 90 }, readinessProbe: tcp(9000) },
    { name: "h5p", image: appImage("h5p"), env: env("h5p"), resources: resources("384Mi"), volumeMounts: [mount("app","/config","h5p-config",true), mount("app","/data/libraries","h5p-libraries")], startupProbe: http(8082,"/h5p/health"), readinessProbe: http(8082,"/h5p/health") },
    { name: "web", image: appImage("web"), env: env("web"), resources: resources("384Mi"), startupProbe: http(4321,"/healthz"), readinessProbe: http(4321,"/healthz") },
    { name: "admin", image: appImage("admin"), env: env("admin"), resources: resources("128Mi"), startupProbe: http(8080,"/"), readinessProbe: http(8080,"/") },
    { name: "pdf", image: appImage("pdf"), env: env("pdf"), resources: resources("512Mi"), startupProbe: tcp(3000), readinessProbe: tcp(3000) },
    { name: "mjml", image: appImage("mjml"), resources: resources("128Mi"), startupProbe: tcp(15500), readinessProbe: tcp(15500) },
    { name: "caddy", image: INFRA_IMAGES.caddy, env: env("caddy"), resources: resources("128Mi"), volumeMounts: [mount("proxy","/etc/caddy/Caddyfile","Caddyfile",true), mount("app","/data","caddy"), mount("app","/config","h5p-config",true)], startupProbe: { exec: { command: ["wget","-q","-O","/dev/null","http://127.0.0.1:2019/config/"] }, timeoutSeconds: 10, periodSeconds: 5, failureThreshold: 120 }, readinessProbe: { exec: { command: ["wget","-q","-O","/dev/null","http://127.0.0.1:2019/config/"] }, timeoutSeconds: 10, periodSeconds: 5 } },
  ].map(container => ({ ...container, imagePullPolicy: "IfNotPresent", securityContext: { allowPrivilegeEscalation: false } }));
  const gatewayPort = c.mode === "development" ? c.httpPort : c.httpsPort;
  const gateway: Doc = { apiVersion: "v1", kind: "Service", metadata: meta("caddy"), spec: { type: c.gateway, selector: { ...labels, "io.ulams.role": "application" }, ports: c.mode === "development" ? [{ name: "http", port: c.httpPort, targetPort: c.httpPort, ...(c.gateway === "NodePort" ? { nodePort: c.nodePort } : {}) }] : [{ name: "http", port: c.httpPort, targetPort: c.httpPort, ...(c.gateway === "NodePort" ? { nodePort: c.nodePort - 1 } : {}) }, { name: "https", port: c.httpsPort, targetPort: c.httpsPort, ...(c.gateway === "NodePort" ? { nodePort: c.nodePort } : {}) }] } };
  const docs: Doc[] = [
    { apiVersion: "v1", kind: "Namespace", metadata: { name: namespace, labels } },
    ...[pvc("postgres-data","10Gi"), pvc("valkey-data","1Gi"), pvc("object-data","20Gi"), pvc("app-data","10Gi")],
    { apiVersion: "v1", kind: "ConfigMap", metadata: meta("proxy"), data: { Caddyfile: caddyfile(c) } },
    service("postgres",5432,"postgres"), service("valkey",6379,"valkey"), service("object-store",8333,"object-store"),
    ...[["api",9000,9000],["h5p",8080,8082],["web",4321,4321],["admin",8080,8080],["pdf",3000,3000],["mjml",15500,15500]].map(([name,port,target]) => service(String(name),Number(port),"application",Number(target))),
    { apiVersion: "v1", kind: "Service", metadata: meta("api-gateway"), spec: { selector: { ...labels, "io.ulams.role": "application" }, ports: [{ name: "private", port: 8081, targetPort: 8081 }] } },
    gateway,
    stateful("postgres",[postgres],[persistent("postgres-data"), { name: "init", secret: { secretName: "credentials", items: [{ key: "init.sql", path: "init.sql" }] } }]),
    stateful("valkey",[valkey],[persistent("valkey-data")]), stateful("object-store",[storage],[persistent("object-data")]),
    stateful("application",app,[{ name: "app", persistentVolumeClaim: { claimName: "app-data" } }, { name: "proxy", configMap: { name: "proxy" } }], {
      initContainers: [{ name: "prepare", image: "alpine:3.24", command: ["sh","-c","mkdir -p /app/api-storage /app/h5p-config /app/h5p-libraries /app/caddy; chown -R 82:82 /app/api-storage /app/h5p-config /app/h5p-libraries; chmod 2775 /app/api-storage /app/h5p-config /app/h5p-libraries; for svc in postgres:5432 valkey:6379 object-store:8333; do host=${svc%:*}; port=${svc#*:}; until nc -z $host $port; do sleep 2; done; done"], volumeMounts: [mount("app","/app")], resources: resources("32Mi") }],
      hostAliases: [{ ip: "127.0.0.1", hostnames: [`storage.${c.domain}`] }],
    }),
    { apiVersion: "networking.k8s.io/v1", kind: "NetworkPolicy", metadata: meta("namespace-ingress"), spec: { podSelector: {}, policyTypes: ["Ingress"], ingress: [{ from: [{ podSelector: {} }] }, { ports: [{ protocol: "TCP", port: gatewayPort }, ...(c.mode === "production" ? [{ protocol: "TCP", port: c.httpPort }] : [])] }] } },
  ];
  // Private API traffic reaches the separate 8081 ClusterIP, not the public gateway Service.
  const webContainer = app.find(container=>container.name === "web")!;
  (webContainer.env as Array<{ name: string; value?: string }>).find(item=>item.name === "ULAMS_API_INTERNAL_ORIGIN")!.value = "http://api-gateway:8081";
  return { public: docs, private: { apiVersion: "v1", kind: "Secret", metadata: meta("credentials"), type: "Opaque", stringData: { ...secrets, "init.sql": initSql(secrets) } } };
}
