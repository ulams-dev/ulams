# SBOM and image signing

## Goal

The goal of this runbook is to outline the process for generating Software Bill of Materials (SBOM), scanning images for vulnerabilities, signing images with a cosign keyless setup, and verifying the signed images as a consumer. This process will ensure that the images published to GHCR are secure, transparent, and verifiable.

## Generate an SBOM (syft, SPDX JSON)

Use `syft` to generate an SBOM in SPDX JSON format for the image.
