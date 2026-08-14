# WordPress.org Release and Translation Log

Status: active operational handoff.

Last updated: 2026-08-14.

This document records the WordPress.org release, assets, legal pages, and zh_CN
translation work completed for `npcink-cloud-addon`.

## Plugin Directory State

- WordPress.org plugin URL: `https://wordpress.org/plugins/npcink-cloud-addon/`
- Plugin slug: `npcink-cloud-addon`
- WordPress.org SVN URL: `https://plugins.svn.wordpress.org/npcink-cloud-addon/`
- Historical local SVN working copy: `build/wporg-svn`
- Release package: `build/npcink-cloud-addon.zip`
- Stable tag: `0.1.7`

The plugin has passed WordPress.org review and was submitted to SVN. Later asset
updates were submitted separately.

Known SVN revisions:

- `r3582010`: initial WordPress.org SVN submission with trunk/tag/assets.
- `r3582534`: regenerated WordPress.org icon and banner assets.
- `r3590121`: release `0.1.1` with refreshed Cloud status UI, entitlement
  cache reuse, WordPress AI connector integration, and zh_CN metadata.
- `r3600099`: release `0.1.2` with bounded Site Knowledge Cloud transport,
  WordPress AI request-log compatibility, contract reuse documentation, and a
  clean Plugin Check release gate.
- `r3606249`: release `0.1.3` with the simplified admin overview, aligned
  quota summaries, hardened authorization redirect, refreshed screenshots,
  and updated `zh_CN` catalogs.
- `r3646532`: release `0.1.7` with the bounded runtime facade closeout,
  improved connection and Site Knowledge feedback, read-only retrieval
  acceptance projection, fixed WordPress AI localization compatibility, and
  the current 33-file release package.

## 2026-08-14 0.1.7 Release Closeout

Version `0.1.7` was published to WordPress.org SVN from the clean Addon
`origin/master` release candidate after the required local gates passed:

```text
SVN revision: r3646532
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.7/
Package SHA256: 44b36245bd34d013b960816b9c0241e07e288339c493def50fd95ead19fbcd19
```

Verification evidence:

- `composer run test:all` passed;
- `composer run smoke:playground` passed on WordPress 7.0.4 / PHP 8.2;
- `WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify` passed;
- the package contained exactly 33 release files;
- WordPress.org review guard, POT freshness, and strict Plugin Check passed.

The publication was performed through a temporary SVN working copy. The Git
working tree was not used as an SVN working copy and remains free of generated
release-state edits.

## Release Verification

Before packaging or handing off release changes, run:

```sh
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

For full WordPress.org release verification, use:

```sh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify
```

The local Plugin Check release command depends on the Local WordPress site and
the local WP-CLI/PHP paths configured in `composer.json`. On this machine,
Composer's default `/tmp/wp-cli.phar` path is not reliable, so pass
`WP_CLI_BIN=/opt/homebrew/bin/wp` explicitly for Plugin Check and release
verification commands.

## 2026-07-13 0.1.3 Release Closeout

Version `0.1.3` was published to WordPress.org SVN on 2026-07-13 after GitHub
pull request `#41` passed its required checks on exact head
`7670cdd74983752b5e90eec6e4c62de4993f2a53` and merged as
`c3ab9b0f2fb5297766afd16799a6880514a7a92f`:

```text
SVN revision: r3606249
Author: muze233
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.3/
Download: https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.3.zip
```

WordPress.org API reported after the release:

```text
version=0.1.3
last_updated=2026-07-13 2:44pm GMT
download_link=https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.3.zip
```

### Release Contents

- a simplified overview with compact, consistently aligned plan, point, and
  Site Knowledge quota summaries;
- Chinese quota terminology and fixed admin copy kept consistent with the
  shipped `zh_CN` catalog;
- an exact-host temporary allowlist around the validated Cloud authorization
  redirect so the flow can use `wp_safe_redirect()` without broadening the
  connector boundary;
- PHP 8.5-compatible reflection-based behavior tests while retaining PHP 8.0
  test support;
- refreshed WordPress.org screenshots for the overview, local permissions,
  Site Knowledge, and advanced troubleshooting surfaces;
- a release-version contract that keeps the plugin header, version constant,
  stable tag, POT, and `zh_CN` PO metadata synchronized.

The release does not add router, prompt, preset, proposal, approval,
workflow, scheduler, billing, runtime-truth, or WordPress-write ownership.

### Verification Evidence

The repository release gates, Plugin Check, PHP 8.0.30 and 8.5.3 behavior
suites, and a disposable WordPress 7.0.1 / PHP 8.3 package activation smoke
test passed before publication. Plugin Check returned no errors.

The package regenerated from merged `master` was:

```text
build/npcink-cloud-addon.zip
SHA256: 909222a45cc054b1091ff23bc7a241ed6e356abed957efdff6a3a0f394e25fcf
```

The downloaded WordPress.org package was:

```text
https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.3.zip
SHA256: c17fb4e62726ae2dfc6227d540581dbee1f9c0131c0bde7c65a36e9311e557fd
```

ZIP container hashes differ because packaging metadata differs; extracted file
contents were compared recursively and matched. The four `ps.w.org` screenshot
files at revision `r3606249` also matched the Git source assets byte-for-byte.

## 2026-07-08 0.1.2 Release Closeout

Version `0.1.2` was published to WordPress.org SVN on 2026-07-08:

```text
SVN revision: r3600099
Author: muze233
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.2/
Download: https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.2.zip
```

WordPress.org API reported after the release:

```text
version=0.1.2
last_updated=2026-07-08 9:46am GMT
download_link=https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.2.zip
```

### Release Contents

The release deliberately kept the addon as a thin Cloud connector. The release
included:

- bounded Site Knowledge Cloud transport and runtime bridge refinements;
- WordPress AI connector request-log compatibility;
- Cloud runtime contract reuse documentation and release metadata;
- Plugin Check cleanup for an external WordPress AI feature-flag filter.

The release did not add router, prompt, preset, proposal, approval, workflow,
scheduler, billing, or WordPress write ownership.

### Plugin Check Warning Handling

Plugin Check originally reported one warning:

```text
WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
Found: wpai_feature_ai-request-logging_enabled
```

The hook name was not renamed because it is owned by the external WordPress AI
plugin feature-flag surface. Renaming it to an `npcink_cloud_addon_*` hook would
remove compatibility rather than improve safety. The accepted fix was a single
targeted PHPCS ignore on the `apply_filters()` call, with a comment explaining
that WordPress AI owns the filter name.

This is the preferred pattern for future external integration hooks:

- keep external hook names intact when the integration contract requires them;
- suppress only the exact sniff at the exact call site;
- explain the owning integration in the ignore comment;
- verify Plugin Check returns a clean result afterward.

### Verification Evidence

Before SVN commit, the following gates passed:

```sh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

Plugin Check returned:

```text
Success: Checks complete. No errors found.
```

The release package was regenerated with:

```sh
composer run package:release
```

The generated package was:

```text
build/npcink-cloud-addon.zip
SHA256: 2878cf32a66b366014629cb373ddf3ac333ebf4c99d2cceb918de5decd60d955
```

The package was inspected to confirm:

- `npcink-cloud-addon.php` version is `0.1.2`;
- `NPCINK_CLOUD_ADDON_VERSION` is `0.1.2`;
- `readme.txt` stable tag is `0.1.2`;
- no `tests/`, `scripts/`, `docs/`, `sj/`, `.git/`, `composer.json`, or other
  non-release files were included.

After SVN commit, the downloaded WordPress.org `0.1.2` zip was compared against
the local `build/npcink-cloud-addon.zip`; the contents matched.

### SVN Release Procedure Used

The release was made from a temporary SVN working copy rather than the Git
working tree:

```sh
svn checkout https://plugins.svn.wordpress.org/npcink-cloud-addon/ /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon
rsync -a --delete build/npcink-cloud-addon/ /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/trunk/
svn add --force /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/trunk
svn copy /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/trunk /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/tags/0.1.2
svn commit --non-interactive -m "Release 0.1.2" /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon
```

Before commit, both `trunk` and `tags/0.1.2` were checked for version metadata
and for accidental inclusion of non-release directories.

## WordPress.org Assets

Source images:

- `sj/source/icon-source.png`
- `sj/source/banner-source.png`

Generated WordPress.org assets:

- `sj/exports/wordpress-org/icon-128x128.png`
- `sj/exports/wordpress-org/icon-256x256.png`
- `sj/exports/wordpress-org/banner-772x250.png`
- `sj/exports/wordpress-org/banner-1544x500.png`

The same four files were copied into:

- `build/wporg-assets/`
- `build/wporg-svn/assets/`

The latest regenerated assets were committed to WordPress.org SVN as:

```text
r3582534 | Update WordPress.org icon and banner assets
```

Local Git commit:

```text
191ddb8 Update WordPress.org plugin assets
```

Screenshots were prepared earlier and are present in the WordPress.org assets
set:

- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`

## Legal and External Service Pages

The plugin discloses Npcink Cloud as an external service in `readme.txt`.

Public legal pages:

- Terms of Service: `https://cloud.npc.ink/terms/en/terms.html`
- Privacy Policy: `https://cloud.npc.ink/terms/en/privacy.html`
- Data Retention: `https://cloud.npc.ink/terms/en/data-retention.html`

Local legal site source:

- `sj/site`

The legal content was prepared with separate English and Chinese folders. English
is used for review-facing WordPress.org links; Chinese is for internal/local
reference.

Provided legal metadata:

- Company Legal Name: `麻城市牧泽世华工艺品`
- Contact email: `npc@npc.ink`
- Effective date: `2026年6月1日`
- Service domain: `https://cloud.npc.ink`

## zh_CN Translation Work

There are two separate translation surfaces:

- Plugin runtime/admin strings from PHP gettext calls.
- WordPress.org plugin directory/readme strings from GlotPress readme projects.

The in-plugin language files are:

- `languages/npcink-cloud-addon.pot`
- `languages/npcink-cloud-addon-zh_CN.po`
- `languages/npcink-cloud-addon-zh_CN.mo`

The plugin uses:

- Text domain: `npcink-cloud-addon`
- Domain path: `/languages`

Do not add `load_plugin_textdomain()` unless the existing project contract
changes; current tests assert the text domain/domain path behavior and absence
of explicit `load_plugin_textdomain()`.

Local translation refresh commands:

```sh
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:pot
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:update-po
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:make-mo
```

Note: `/tmp/wp-cli.phar` was broken in this environment during the translation
work, so the Homebrew WP-CLI binary was used explicitly.

Local Git commit:

```text
5c886f5 Update Chinese translations
```

## WordPress.org Translation Imports

Generated import files are in:

- `build/translate-wordpress-org/npcink-cloud-addon-stable-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-dev-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-stable-readme-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-dev-readme-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-zh_CN-wordpress-org-imports.zip`

These are ignored by Git because `build/` is ignored. Regenerate them when
source strings change.

Import status confirmed on 2026-06-23:

- `Stable Readme`: 46 waiting translations.
- `Stable`: 217 waiting translations.
- `Development Readme`: 46 waiting translations.
- `Development`: 217 waiting translations.

Total zh_CN waiting/fuzzy count:

```text
526
```

This total matches the four uploaded files:

```text
46 + 217 + 46 + 217 = 526
```

Important interpretation:

- `Waiting` means uploaded successfully but not approved.
- WordPress.org project percentages still show `0%` until translations become
  `Current`.
- The Chinese plugin directory page will not show the translations until the
  `Stable Readme` strings are approved.

## GlotPress Import URLs

Use these URLs when uploading refreshed PO files:

- Stable Readme:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/import-translations/`
- Stable:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/import-translations/`
- Development Readme:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/import-translations/`
- Development:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/import-translations/`

Use format:

```text
Portable Object Message Catalog (.po/.pot)
```

If GlotPress offers an import status option and the account is not already a PTE,
use `Waiting`.

## PTE Follow-Up History

PTE means Project Translation Editor. PTE access was requested for:

- Plugin: `npcink-cloud-addon`
- Locale: `Chinese (China) / zh_CN`
- WordPress.org username: `muze233`

The 2026-07-02 closeout below records the approval and completion state. Keep
the request template in this section as historical context only.

## 2026-07-02 zh_CN PTE Closeout

On 2026-07-02, the WordPress.org Polyglots notification confirmed that
`muze233` was added as a Project Translation Editor for:

```text
Plugins -> Npcink Cloud Addon
Locale: Chinese (China) / zh_CN
Project: https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon
Granted by: shenyanzhi
```

Interpretation:

- PTE access allows approving and importing translations for this plugin's
  zh_CN translation sets.
- It does not change this plugin's product boundary or runtime behavior.
- WordPress.org addon translation work should stay scoped to the
  `npcink-cloud-addon` text domain and the plugin readme projects.
- The separate local WordPress AI plugin compatibility shim remains a bounded,
  admin-only `ai` text-domain shim for fixed UI strings only. Dynamic ability
  metadata belongs in the plugin that registers the ability.

### Local Preparation

Before the online submission, the local zh_CN language file was checked:

```sh
msgfmt --statistics -o /tmp/npcink-cloud-addon-translation-submit/check.mo \
  /tmp/npcink-cloud-addon-translation-submit/npcink-cloud-addon-zh_CN-glotpress-import.po
```

Result:

```text
435 translated messages.
```

The temporary GlotPress import source was:

```text
/tmp/npcink-cloud-addon-translation-submit/npcink-cloud-addon-zh_CN-glotpress-import.po
```

Do not commit that temporary file. It is only an upload artifact derived from the
local `languages/npcink-cloud-addon-zh_CN.po`.

### Online Submission

The Development default translation set was opened at:

```text
https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/
```

The import form was submitted with:

```text
Format: Auto Detect
Status: Current
File: npcink-cloud-addon-zh_CN-glotpress-import.po
```

GlotPress reported:

```text
264 translations were added
```

After import, two remaining untranslated fixed UI strings were manually added:

| Original | Translation |
| --- | --- |
| `Entitlement and package are read from Cloud into a short local summary cache. Re-verifying refreshes the latest Cloud summary.` | `权益和套餐会从 Cloud 读取到简短的本地摘要缓存中。重新验证会刷新最新的 Cloud 摘要。` |
| `Details` | `详情` |

Three waiting fixed UI translations were reviewed and approved:

| Original | Translation |
| --- | --- |
| `not returned by Cloud` | `Cloud 未返回` |
| `Advanced Information` | `高级信息` |
| `Advanced` | `高级` |

One GlotPress warning was resolved by changing the first translation above to:

```text
未从 Cloud 返回
```

The warning was only about the original string starting with lowercase `not`
while the previous translation started with uppercase `Cloud`; it was not a
placeholder or format-string error.

### Readme Completion

The plugin readme translation sets are separate from the PHP gettext/default
translation sets. Do not upload the addon PO file into readme projects.

Development Readme had one untranslated changelog string:

| Original | Translation |
| --- | --- |
| `Refresh Cloud connection status actions, entitlement summary caching, WordPress AI connector integration, zh_CN strings, and release packaging checks.` | `更新 Cloud 连接状态操作、权益摘要缓存、WordPress AI 连接器集成、zh_CN 字符串和发布打包检查。` |

After this was saved, Stable Readme synchronized to the same completed state.

### Final WordPress.org State

The final project overview row for zh_CN was:

```text
Chinese (China)    100%    100%    100%    100%    0
```

Meaning:

- Development: 100%
- Development Readme: 100%
- Stable: 100%
- Stable Readme: 100%
- Waiting/Fuzzy: 0
- Untranslated: 0
- Warnings: 0

The verified GlotPress pages were:

- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/`

### Directory Page Behavior

The global WordPress.org plugin page remains the English directory page:

```text
https://wordpress.org/plugins/npcink-cloud-addon/
```

The Chinese-localized directory page is:

```text
https://cn.wordpress.org/plugins/npcink-cloud-addon/
```

After GlotPress reaches 100%, the remaining work is to wait for WordPress.org to
generate and distribute language packs and for plugin-directory caches to
refresh. If the Chinese page still shows English after caches settle, check the
Stable Readme translation set first, because the localized plugin directory page
uses readme translations rather than the runtime `.po` file alone.

For local language-pack verification after generation, use the local WordPress
site:

```sh
wp language plugin update npcink-cloud-addon \
  --path="/Users/muze/Local Sites/npcink/app/public"
```

or use the WordPress admin updates screen to refresh translations.

### Follow-Up

- Keep WordPress.org translations at 100% when new release strings are added.
- Re-run local i18n refresh before the next release package.
- Keep the AI plugin compatibility shim audit separate from WordPress.org addon
  translation work.
- If the WordPress.org plugin directory does not localize after the language
  pack/cache window, verify Stable Readme first, then the generated language
  pack state.

Suggested request title:

```text
PTE Request for Npcink Cloud Addon
```

Suggested request body:

```text
Hello Polyglots team,

I am the plugin author of Npcink Cloud Addon:
https://wordpress.org/plugins/npcink-cloud-addon/

I have imported Chinese (China) translations for the following projects:

- Stable
- Stable Readme
- Development
- Development Readme

There are currently 526 zh_CN strings waiting for review.

I would like to request Project Translation Editor access for this plugin for
#zh_CN, so I can review and maintain the Chinese translations.

WordPress.org username: muze233

Thank you.
```

Request location:

```text
https://make.wordpress.org/polyglots/
```

## Browser Automation Note

Chrome extension automation was not available during the 2026-07-02 closeout,
but the logged-in Playwright browser session could access GlotPress as
`Howdy, Npcink` and submit file inputs. The Development default PO import,
manual string saves, waiting approvals, warning fix, and readme completion were
all completed through that browser session.

Do not assume future upload failures are file-format issues. Validate PO files
locally with `msgfmt --check --statistics` or `msgfmt --statistics` before
debugging GlotPress/browser behavior.
