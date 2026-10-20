# 0.4.4 release candidate — 2026-10-20

Status: local package candidate on branch `release/0.4.4-20261020`. WordPress.org
upload and Git release tag remain separate operator steps.

## Source baseline

Application source is reviewed `master` tip
`ae15b907d662a8c1b6190a14f48e2790ef661427` plus this release identity bump only:

- Plugin header and `NPCINK_CLOUD_ADDON_VERSION` → `0.4.4`
- `readme.txt` Stable tag, Upgrade Notice, and Changelog
- POT / zh_CN PO / MO `Project-Id-Version`

Runtime behavior in this package already includes merged PRs #300–#306
(Site Knowledge on Overview; TOKEN usage mapping; generation tests P0;
disconnect keeps Base URL; generation-test UI polish; failure-state CTAs).
SEO/AEO/GEO specialist tasks and the three runtime pause keys remain paused.
No credential, endpoint allowlist, or WordPress write-boundary change.

## Local package receipt

On 2026-10-20 (Asia/Shanghai):

- `composer validate --no-check-publish`
- `composer test:all`
- `composer run check:wporg`
- `composer run package:release`
- `composer run package:verify`

Artifact:

- Path: `build/npcink-cloud-addon-0.4.4.zip` (also `build/npcink-cloud-addon.zip`)
- Files: 54
- SHA256: `a5648342507e5f009e372b84e12ebf0a53f1a410be61fb4956a2e28073ec0453`

## Stop conditions

Do not publish to WordPress.org or create a GitHub Release until the release PR
is squash-merged to `master` and the post-merge tip still matches this package
identity. Pause inventory and discoverability remain unchanged.
