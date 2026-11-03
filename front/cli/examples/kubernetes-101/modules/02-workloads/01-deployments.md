---
id: 01m5gj6q4bht7mhw8dwcs8eted
key: deployments
title: Scale a deployment
summary: Let Kubernetes keep three replicas alive.
minutes: 20
objectives:
  - id: 01m5gj6q4b3adbhmvpg70dtrgj
    text: Create a deployment
  - id: 01m5gj6q4bcnyesnaz3fm18bzm
    text: Scale it up and down
origin: author
---

:::paragraph{#01m5gj6q4ba53nnp6jx853hw70 cite="frg_deploymentsq" objectives="01m5gj6q4b3adbhmvpg70dtrgj"}
A deployment keeps the number of pods you ask for, replacing any that die.
:::

:::code{#01m5gj6q4bgyck2e71j9n1bgwd origin=author objectives="01m5gj6q4b3adbhmvpg70dtrgj,01m5gj6q4bcnyesnaz3fm18bzm"}
```bash
kubectl create deployment web --image=nginx --replicas=3
kubectl scale deployment web --replicas=5
```
:::
