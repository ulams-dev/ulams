---
id: 01m5gj6q4b6r2zh3sbnh7fer7m
key: install
title: Install kubectl
summary: Install and verify the command line tool.
minutes: 10
objectives:
  - id: 01m5gj6q4acs4e96zefy2148wg
    text: Install kubectl and check its version
origin: author
---

:::paragraph{#01m5gj6q4bv6ttvn84kcx9eb6d cite="frg_kubectlintro" objectives="01m5gj6q4acs4e96zefy2148wg"}
kubectl is the command-line tool for Kubernetes clusters. It talks to the API server of the cluster named in your kubeconfig.
:::

:::code{#01m5gj6q4b4ea4qc3vk2kwvq21 origin=author objectives="01m5gj6q4acs4e96zefy2148wg"}
```bash
kubectl version --client
```
:::

:::callout{#01m5gj6q4bt4xrp5m09268mvsa origin=author objectives="01m5gj6q4acs4e96zefy2148wg"}
**Tip.** The client and the cluster may differ by one minor version; larger gaps cause surprises.
:::
