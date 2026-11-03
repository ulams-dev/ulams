# 0109. A Helm chart for production Kubernetes next to the stack launcher

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-8-self-hosting.md` (milestone P8-5)

## Context and problem statement

ADR 0102 gave `ulams stack up --target kubernetes` its own manifest generator
(`front/cli/src/stack/kubernetes.ts`): one namespace, singleton StatefulSets, startup and
readiness probes, no Helm needed. That is right for trials and single-node installs, but it has
no horizontal scaling, no PodDisruptionBudget, no liveness probes, no Ingress or cert-manager
integration and no migration Job, and Phase 8.1 asks for a Helm chart with sane defaults.
`operators/high-availability.mdx` already describes the roles (web, worker, scheduler, migration
job) that a scalable deployment needs.

## Considered options

1. **Chart in `deploy/helm/ulams`, launcher keeps its generator.** The chart is the production and
   scaling path; the launcher stays dependency-free for trials. A CI parity check keeps images,
   environment keys and probes in step.
2. **Launcher renders the chart.** One source, but every launcher user needs the `helm` binary,
   which ADR 0102 deliberately avoided.
3. **Kustomize base instead of Helm.** No templating language, but the roadmap names Helm, and
   most operators expect a chart with a `values.yaml`.

## Decision

Option 1.

- `deploy/helm/ulams` (Apache-2.0, `apiVersion: v2`), published as an OCI chart to
  `ghcr.io/ulams-dev/charts/ulams` and signed like the images (ADR 0108).
- Our own templates, no third-party subcharts. Bundled PostgreSQL, Valkey and SeaweedFS are
  single-replica StatefulSets for small clusters and are switched off in favour of external
  services (managed PostgreSQL or CloudNativePG, external S3) for production.
- The step-by-step Kubernetes YAML tutorial is generated from `helm template` output, so its
  examples are the chart's manifests and are validated in CI.
- The launcher's Kubernetes output stays as it is; the docs present it as the trial path and the
  chart as the production path.

## Consequences

- Good: scaling, PDBs, Ingress and zero-downtime upgrades without changing the launcher's
  no-dependency promise.
- Bad: two Kubernetes renderings to maintain; the parity test limits drift but does not remove it.
- Bad: multiple web replicas still depend on the existing rule that tenant env files are rebuilt
  from the `tenants` table on start, and H5P replicas need a ReadWriteMany volume or one replica.
