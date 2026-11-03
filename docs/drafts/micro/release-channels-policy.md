# ULAMS Release Channels Policy

## 1. Channels

- **Edge**: The latest features and improvements, may include breaking changes.
- **Stable**: Regularly updated with new features, stable for general use.
- **LTS (Long Term Support)**: Critical bug fixes and security patches, no new features.

## 2. Version Numbering

Version numbers follow the semantic versioning scheme: `MAJOR.MINOR.PATCH`.

- **MAJOR**: Incremented for incompatible API changes.
- **MINOR**: Incremented for adding functionality in a backwards-compatible manner.
- **PATCH**: Incremented for backwards-compatible bug fixes.

## 3. Support Windows

| Channel | Cadence | Support Window | Audience |
|---------|---------|----------------|----------|
| Edge    | Every 2 weeks | 3 months | Early adopters |
| Stable  | Every 6 months | 1 year | General users |
| LTS     | Every 1 year | 3 years | Enterprise users |

## 4. Upgrade Rules Between Channels

- **Edge to Stable**: Direct upgrade is allowed if the MINOR version is the same.
- **Stable to LTS**: Upgrade is allowed if the MAJOR version is the same.
- **Edge to LTS**: Upgrade is allowed if the MAJOR version is the same.

## 5. Security Fixes

Security fixes are applied to all channels. Critical security issues are addressed immediately across all channels.
