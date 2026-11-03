### 3. Compatibility Checks

- The plugin's `ulamsCompat` field must be a valid semver range.
- The plugin's `ulamsCompat` field must be compatible with the current ULAMS version.
- The plugin's `extensionPoints` and `permissions` fields must be valid and recognized by ULAMS.

### 4. Version Bumps

- A major version bump is required if there are breaking changes in the plugin's API or behavior.
- A minor version bump is required if there are new features or non-breaking changes.
- A patch version bump is required for bug fixes and other non-breaking changes.

### 5. Open Questions

1. Should the `ulamsCompat` field support pre-release versions (e.g., `1.0.0-alpha`)? 
2. How should we handle plugins that require multiple versions of ULAMS?
3. What is the process for deprecating an extension point or permission?
4. Should the plugin manifest include additional metadata such as author, license, and description?
