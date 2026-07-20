# GitHub Actions supply-chain record

All `uses:` references in this repository are pinned to a full 40-character commit SHA. The adjacent workflow comment records the human-readable release. Dependabot is configured to propose updates; every update must re-verify the tag against the action's official upstream repository and review release notes before merge.

Verified on 2026-07-20 with:

```bash
git ls-remote --tags https://github.com/OWNER/REPOSITORY.git \
  refs/tags/VERSION 'refs/tags/VERSION^{}'
```

For an annotated tag, the workflow pins the dereferenced `^{}` commit. For a lightweight tag, it pins the tag SHA itself.

| Action | Release | Pinned commit |
| --- | --- | --- |
| `actions/checkout` | `v6.0.3` | `df4cb1c069e1874edd31b4311f1884172cec0e10` |
| `actions/setup-node` | `v6.5.0` | `249970729cb0ef3589644e2896645e5dc5ba9c38` |
| `actions/upload-artifact` | `v5.0.0` | `330a01c490aca151604b8cf639adc76d48f6c5d4` |
| `actions/dependency-review-action` | `v4.9.0` | `2031cfc080254a8a887f58cffee85186f0e49e48` |
| `github/codeql-action` | `v4.37.1` | `7188fc363630916deb702c7fdcf4e481b751f97a` |
| `shivammathur/setup-php` | `2.37.2` | `f3e473d116dcccaddc5834248c87452386958240` |
| `anchore/sbom-action` | `v0.24.0` | `e22c389904149dbc22b58101806040fa8d37a610` |

The CI secret scanner is the open-source Gitleaks CLI, invoked through Go at the verified `v8.30.1` commit `83d9cd684c87d95d656c1458ef04895a7f1cbd8e`, rather than the separately licensed Gitleaks GitHub Action. The Go module retains its historical `github.com/zricethezav/gitleaks/v8` path after the repository move. SBOM generation pins Syft `v1.48.0` (`d25515fbd72bf661a8618659c5bacdfc4eac954f`) through the pinned Anchore action.
