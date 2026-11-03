# Air-Gapped Deployment Checklist for ULAMS

## Images and Registry Mirror
- [ ] Set up a local Docker registry mirror.
- [ ] Mirror all ULAMS images to the local registry.
- [ ] Update the ULAMS configuration to use the local registry.
- [ ] Verify that all images are accessible from the local registry.

## Fonts and CDNs
- [ ] Download all required fonts and store them locally.
- [ ] Update the ULAMS configuration to use local font paths.
- [ ] Download all required CDN assets and store them locally.
- [ ] Update the ULAMS configuration to use local CDN paths.

## AI Provider (Ollama or vLLM on-prem)
- [ ] Install and configure the chosen AI provider (Ollama or vLLM) on-premises.
- [ ] Update the ULAMS configuration to point to the on-prem AI provider.
- [ ] Test the AI provider integration in an air-gapped environment.
- [ ] Ensure that all necessary AI models are available locally.

## Mail and SSO
- [ ] Set up a local mail server or use an existing one.
- [ ] Update the ULAMS configuration to use the local mail server.
- [ ] Set up a local SSO provider or use an existing one.
- [ ] Update the ULAMS configuration to use the local SSO provider.

## Time sync and Certificates
- [ ] Ensure that all nodes in the air-gapped environment have synchronized time.
- [ ] Obtain and install SSL certificates for all ULAMS services.
- [ ] Update the ULAMS configuration to use the installed SSL certificates.
- [ ] Ensure that all nodes can trust the installed SSL certificates.

## Update Flow without Internet
- [ ] Create a process for regularly updating ULAMS images and configurations.
- [ ] Test the update process in an air-gapped environment.
- [ ] Ensure that all updates are tested and approved before deployment.
- [ ] Document the update process and procedures.

## Data Residency
- [ ] Ensure that all data is stored locally within the air-gapped environment.
- [ ] Update the ULAMS configuration to use local storage paths.
- [ ] Test data backup and restore procedures in an air-gapped environment.
- [ ] Ensure that all data is isolated and secure within the air-gapped environment.
