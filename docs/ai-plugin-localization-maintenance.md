# WordPress AI Plugin Localization Maintenance

Status: active for `npcink-cloud-addon`.

## Purpose

The addon carries a narrow zh_CN compatibility shim for high-traffic
WordPress AI plugin admin and editor UI strings. The shim exists so local
operators can use the Cloud-backed WordPress AI connector naturally while the
upstream `ai` plugin does not yet provide complete Chinese translations.

This is not a full language pack. It must stay a bounded compatibility layer.

## Ownership Boundary

The addon may translate:

- Fixed UI labels rendered by the WordPress AI plugin under text domain `ai`.
- Fixed help text, notices, buttons, settings labels, and menu/page labels for
  the supported AI plugin surfaces.
- JavaScript `wp.i18n` strings for the same fixed surfaces through locale data.

The addon must not translate:

- Dynamic ability names, descriptions, schemas, JSON fields, or slugs registered
  by other plugins or themes.
- Provider/model/router/prompt/preset names.
- Generic WordPress core or component-library strings outside the `ai`
  text domain unless a future task explicitly approves a separate, extremely
  narrow compatibility exception.
- Any text by calling Cloud runtime or an external translation service at
  request time.

Dynamic ability metadata should be translated in the plugin that registers the
ability, such as `npcink-abilities-toolkit`, not in this Cloud addon.

## Current Implementation

The active shim lives in:

- `includes/class-ai-plugin-localization.php`
- `assets/ai-plugin-localization.js`
- `tests/behavior-ai-plugin-localization.php`

The implementation is intentionally static:

- It only runs in `wp-admin`.
- It only runs for Chinese locales (`zh_*`).
- It targets text domain `ai`, with the seventeen-key bundled component exception below.
- Its inline JS locale payload enqueues only on surfaces that render
  `ai`-domain JS — the AI plugin's own admin screens (hook suffix containing
  `_page_ai-`), its Connectors screen (`options-connectors`), the core
  Comments screen and dashboard Activity widget used by the reply-suggestion
  and comment-moderation features, the media library bulk alt-text surfaces,
  and the post editor screens — not on every `wp-admin` page. The payload is
  uncacheable inline HTML that must ship exactly once per request, so it must
  not ship to screens that never render `ai`-domain JS; when the AI plugin
  decorates a new surface, extend the `is_ai_payload_surface()` allowlist in
  the same change.
- It feeds PHP gettext, PHP ngettext (Chinese locales have no plural
  distinction, so the singular source key always maps), and
  `wp.i18n.setLocaleData()` through the same translation map.
- It must not call `npcink_cloud_addon_runtime_client()` or any Cloud runtime
  method.

Stand-down rule (per string, verified 2026-10-10): the PHP filters apply an
entry only when the incoming translation still equals the English source —
an official ai-domain zh_CN language pack therefore always wins and the shim
fills only the gaps. The JS asset prunes entries that
`wp.i18n.getLocaleData('ai')` already carries before registering its locale
data. If official coverage keeps growing, retire shim entries once the audit
shows them fully covered upstream.

Bundled component exception (verified 2026-10-18 against AI plugin 1.4.0):
its bundles embed `@wordpress/dataviews` components whose calls use the default
text domain. Source presence alone does not prove that the AI locale dictionary
can translate those calls. The earlier description of these calls as a build
that "dropped" the `ai` domain was unproven; the shipped bundle identifies them
as WordPress component code.

At the user's explicit request to fix these remaining gaps, nineteen source keys
(two plural pairs, seven contextual strings, and eight plain labels) have a bounded compatibility
exception: `bundled_ui_fallback()` supplies only those keys on
`settings_page_ai-wp-admin`, `tools_page_ai-request-logs`,
`tools_page_ai-mcp-access` and `tools_page_ai-abilities-explorer`. The latter two
screens also embed DataViews in AI 1.5.0. The `Actions` header and paging template were verified in the bundled table and
pagination sources on 2026-11-18. The paging template is accepted only with
`_x` context `paging`, retaining both placeholders and HTML wrappers.
The added `Filter` key is accepted only
with `_x` context `verb`; a plain call or another context must stand down.
The JS hooks check
the exact source, plural pair or context, accept only the default domain, and stand down
when an existing translation is present. Never register the full AI dictionary
in the default domain. Do not expand this exception to other keys or screens
without evidence and review. Retire it when the bundled component translations
work without the fallback.

### AI 1.5.0 followthrough — 2026-11-18

Reviewed 267 additional fixed UI/error keys, preserving placeholders, destructive
scope and unknown/unverified wording. Two initially reported candidates are
registration metadata (`WebMCP` and the text-to-speech Ability description),
classified by exact source plus file; they are not translated. Settings labels
in the same files remain fixed UI candidates. Localization does not enable audio,
MCP, block labels, revision summaries or any paused/unsupported capability.

Installed-plugin strict audit now passes: 1,326 discovered ai-domain keys, 1,070
shim entries, zero fixed UI, Ability-error or uncovered default-domain gaps.
The remaining 300 missing entries are 72 metadata/developer copy, 211 schema/JSON
and 17 long-prompt entries; 27 stale entries remain for review. This bounded source
audit does not prove every English word on every plugin page has been translated.

A first zero-gap audit missed 113 actual `WP_Error` messages because wording
heuristics classified them as metadata/schema/long prompts. The audit now scans
PHP tokens for ai-domain strings inside the second `new WP_Error` argument and
classifies those as error notices before prose heuristics. Comments, error codes,
third-argument data and adjacent schema strings are excluded from that promotion.
Four known admin/revision bundles also contribute nine fixed notices previously
hidden by schema-word heuristics. These 122 keys are included in the 267 above.
Never use a zero count alone without reviewing excluded groups.

Local PC browser evidence at 1440 and 1280 covers settings, request logs,
Abilities Explorer and MCP Access, including the real locale payload, exact
default-domain/context stand-down behavior, view-options controls and cancelling
log deletion. No feature or content was saved, no Ability/model invoked; the
original post and feature-setting fingerprints are unchanged. Browser failures
from intentionally blocked Gravatar are recorded separately. Evidence lives in
the local `editor-localization-20261118/` artifact directory, with scripts and hashes.
Remaining component/literal labels such as `PAGE`, `OF`, `Plugin`, `ACTIONS` and
`Text`, and the existing wide/duplicated Explorer columns, are not fixed by this
batch. Do not expand the default-domain dictionary or claim a layout redesign
based on this strict audit. All fixed error states were reviewed in source/tests;
they were not each triggered in the browser.

## Maintenance Problem

The upstream WordPress AI plugin can change English strings when it updates.
When that happens, existing Chinese mappings may silently stop matching. The
right workflow is to detect drift quickly and generate a reviewable update
list, not to auto-translate in production.

## One-Command Audit

Run:

```sh
composer run ai:i18n:audit
```

The command scans the locally installed WordPress AI plugin and compares
its `ai` text-domain strings against
`Npcink_Cloud_AI_Plugin_Localization::translations()`.

Recommended scan roots, in order:

1. `wp-content/plugins/ai` from the local WordPress install when available.
2. A user-supplied path, for example:

```sh
AI_PLUGIN_PATH=/path/to/wp-content/plugins/ai composer run ai:i18n:audit
```

The command should read PHP and built JavaScript assets. It should detect at
least these forms:

- `__( 'Text', 'ai' )`
- `esc_html__( 'Text', 'ai' )`
- `esc_attr__( 'Text', 'ai' )`
- `_x( 'Text', ..., 'ai' )`
- `_n( 'Singular', 'Plural', ..., 'ai' )`
- `wp.i18n.__( 'Text', 'ai' )`
- Minified bundle forms that still contain `__("Text","ai")`

The command should output the scan root and installed AI plugin version, plus:

- Missing strings grouped for review:
  - `fixed_ui_candidates` for likely fixed admin/editor UI copy that may belong
    in this shim.
  - `ability_error_notices` for admin-facing failure notices found inside
    `includes/Abilities/` or `includes/Features/` files. These are fixed UI
    copy rather than ability metadata; a reviewer decides each one. Names,
    descriptions, schemas, and input fields from the same files still land in
    `dynamic_ability_metadata` or `schema_or_json_fields`.
  - `dynamic_ability_metadata` for likely ability labels, descriptions, or
    feature metadata that should usually stay out of this addon.
  - `schema_or_json_fields` for schema labels, JSON fields, REST arguments, and
    field descriptions that should usually stay out of this addon.
  - `long_prompt_copy` for generated-image prompt templates or other long
    prompt copy that should not be bulk-translated in this addon.
- Existing shim strings that no longer appear in the scanned plugin as
  `stale_review`.
- Shim strings kept alive only by `_n`/`_x`/`__` calls without an explicit
  `ai` domain literal in scanned JS files (bundled output and build-scripts
  sources; vendor trees excluded), reported as `domainless_rescue`. These
  discoveries are source-presence evidence, not proof of working localization.
  Strict UI mode fails for these keys unless the bounded exception covers them.
- Shim strings kept alive only as bare quoted literals inside `build/`
  output, reported as `literal_only_rescue`.
- Near matches for likely changed English copy.
- A count by surface when it can infer one, such as settings, editor, image
  generation, request logs, or abilities explorer.

Plain audit remains informational. Before localization handoff and after an
AI plugin update, run the opt-in UI gate:

```sh
composer run ai:i18n:check
```

It fails for fixed UI candidates, admin-facing failure notices, unsupported
keys found only through domain-less calls with an unsupported source file,
call form, plural pair, or context. Literal-only rescues stay informational: a
bare data literal is not proof of a translatable UI call. It does
not fail for dynamic ability metadata or schema documentation. This is a
heuristic review gate, not proof of complete translation coverage: inspect
all review groups for misclassified UI copy and verify the changed UI in a
browser. Keys used in both `ai` and default domains need runtime review; their
mere presence in a shared bundle is not proof of an untranslated UI.

Known fixed UI source files take precedence over the length and schema-word
heuristics: the reviewed settings import error, translation/slug/summary/revision
editor bundles, Abilities Explorer and MCP Access bundles, Explorer help,
Markdown Feeds settings, and comment moderation controls. Long help text
and UI notices mentioning JSON or schemas must remain review candidates; ability
registration metadata and image prompt templates remain excluded.

## Optional Draft Command

After the audit exists, a second command may be added:

```sh
composer run ai:i18n:draft
```

This command may generate a review artifact such as:

```text
build/ai-plugin-localization-missing.php
```

The draft must not directly edit
`includes/class-ai-plugin-localization.php`. A human or AI reviewer should copy
only approved fixed UI translations into the shim.

## Translation Review Rules

When adding strings to the shim:

1. Prefer source text exactly as used by the AI plugin. Add title-case variants
   when CSS transforms text to uppercase.
2. Keep translations short enough for WordPress admin controls.
3. Preserve placeholders such as `%s`, `%d`, and ordered placeholders exactly.
4. Preserve HTML entities and allowed markup intent.
5. Do not translate IDs, slugs, JSON keys, provider ids, model ids, or ability
   names.
6. If a string belongs to dynamic ability metadata, move the translation work
   to the registering plugin.
7. Add or update behavior tests for each newly covered surface.
8. Keep `tests/static-contracts.php` asserting that the shim does not call
   Cloud runtime.

## Required Verification

After updating translations or audit tooling, run:

```sh
composer run test:all
composer run check:wporg
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

For audit tooling, also run the audit command against the local AI plugin when
the plugin is installed.

## Future AI Checklist

When the user reports untranslated WordPress AI plugin text:

1. Confirm it is fixed UI text from text domain `ai`.
2. If it is dynamic ability metadata, do not add it to this addon.
3. Search the installed AI plugin for the exact source string.
4. Add the exact source string to `translations()`.
5. Add behavior coverage for PHP gettext and, when relevant, JS locale data.
6. Run `composer run ai:i18n:check`, the required verification commands, and
   `composer run check:js` (which executes the shipped localization asset).
   Confirm the mounted plugin path and verify the changed UI in a browser.
7. Tell the user which strings are covered and which are intentionally left to
   the source plugin.

## 2026-10-18 Omission Analysis

- The 220-character heuristic classified Markdown settings help as prompt copy.
  Schema/JSON vocabulary and sentence prefixes also hid fixed UI notices.
  Known UI sources now take precedence; regression tests cover these cases.
- Dictionary coverage tests showed that Chinese entries existed but did not
  execute the JS translation path. The bundled DataViews keys used a
  different domain and, for two keys, a context. Node tests now execute the
  shipped asset and verify plurals, contexts, language-pack precedence, and
  rejection of unrelated domains/screens.
- Rescue groups were informational and were read as coverage. The opt-in UI
  gate now treats unsupported domain-less calls as failures. CI's existing `check:js`
  step runs the new JS tests; the installed-plugin UI audit is a separate
  required local check because CI does not have that local plugin tree.
  `release:verify` now runs the strict UI gate, and `AGENTS.md` records the
  audit + JS execution + browser acceptance requirement.
- Local deterministic checks and UI acceptance do not publish a fix. Record
  commit/package/site separately; do not describe local success as an online
  deployment.

Verification on 2026-10-18: the local `magick-ai.local` mount resolves to this
repository. Browser acceptance confirmed the Markdown description and the
request-log display menu (sort, order, all density options, items per page,
properties), plus filter/view buttons and the empty state in Chinese. The two
plural pairs and error-field context were also exercised with the site's actual
WordPress `hooks.js` and `i18n.js` in an isolated JS runtime; no site data changed.
All sixteen component exception keys have shipped-asset execution tests.
Playground is not applicable: bootstrap, connector APIs, default credentials,
and the supported compatibility baseline did not change. No online deployment
or PR publication is claimed by this local verification record.

PR review follow-up: strict UI coverage now matches each discovered call's source
file, form, context, and plural pair against the two supported admin bundles.
Bare data literals remain manual-review evidence and cannot block the release
by themselves. The gate intentionally validates the selected installed AI
version; use `AI_PLUGIN_PATH` to select the supported stable plugin tree when a
development installation contains unrelated upstream drift. It does not grant
an automatic bypass for fixed UI gaps. The audited baseline here is AI 1.4.0.

## Release-candidate REST acceptance follow-up (2026-10-19)

Browser validation of the isolated candidate found that the settings import
version error bypassed the admin-only PHP gettext shim: REST has is_admin=false,
and the UI displays the server message directly. A dictionary entry alone did
not fix that path. The response fallback now handles only POST settings/import
unsupported_schema_version and the exact ai/slug-generation/run no_results error,
for an authenticated Chinese-locale user with the existing corresponding
capability. Only an exact untranslated message changes; routes, codes, status,
permissions, payloads and existing official translations remain unchanged.
All other REST errors and all front-end gettext calls retain their source behavior.

## Settings compatibility update (2026-11-16, AI 1.5.0)

The installed AI 1.5.0 changed the settings introduction and group descriptions,
added fixed feature labels, and introduced usage disclosures. The local Addon
mount resolves to this repository. Added 89 exact `ai`-domain entries for this
settings surface, including when a feature runs, what it sends, and request
counts. Preserve direct-save, automatic execution, plugin-install, plaintext-key
and external-AI data-sharing consequences in Chinese. WebMCP remains a proper
name. No feature was enabled and no upstream plugin file was edited.

Maintenance lesson: check the whole affected settings surface, including the
information buttons and accessible names, after an upstream update. Known
upstream feature usage prose can appear in the audit's schema or long-copy
groups; inspect its actual call site before deciding ownership. This does not
authorize translating third-party ability metadata or automatically translating
unknown strings at request time.

The existing visible labels/descriptions and on-demand usage disclosures retain
their positions; no content was moved or duplicated. Local PHP regressions, JS
asset execution (33 tests), WordPress.org review guard and PHPStan passed.
Browser evidence is under `ai15-settings-*.jpg` and
`ai15-settings-receipt.json` in this task's evidence directory. The screenshot
reported by the user and the updated real page are separate from automated
dictionary coverage; feature checkbox values remain unchanged.

The full installed-plugin strict audit still fails: UI coverage gaps decreased
from 255 to 180. Residual editor, request-log, ability-explorer and other surfaces,
including default-domain calls in the new MCP bundle, remain separate review
work. This settings fix is not full AI 1.5 localization acceptance. No CI,
merge or production deployment is claimed. Playground is not applicable:
bootstrap, public APIs, default connector state and compatibility floors did
not change.

## 2026-11-18 editor followthrough

Added nineteen exact ai-domain source keys for title/reply regeneration,
translation progress and stale-editor protection, Notes review failures,
classification adoption errors, and the existing revision-summary UI.
Revision-summary localization does not map or enable its currently unsupported
Ability. No default-domain exception, prompt, dynamic metadata, option,
feature state, or layout changed. Existing official translations still win.

This is a bounded editor slice of the 180-gap installed AI 1.5 baseline.
Full strict coverage and browser acceptance are recorded separately below.

Verification receipt: PHP suite/boundary checks, PHPStan and 38 JavaScript
tests pass. Strict installed AI 1.5 audit now reports **161 UI gaps**
(145 fixed candidates, two ability error notices, fourteen default-domain
keys), down from 180; it remains red. The mounted Addon resolves to this
checkout. A disposable real editor at PC 1440 and 1280 loaded all nineteen
locale entries; the native title review modal and translation-progress button
were rendered in Chinese. Screenshot/report: task evidence directory
`editor-followthrough-20261118/ui-*`. Other newly translated error states and
revision-summary controls were not individually triggered in this browser run.
Draft persistence and original post 279790 were unchanged; temporary draft,
session, and outbound-blocking MU helper were removed. No production or human
visual acceptance is claimed. Playground is not applicable to this copy-only
change because bootstrap/API/default connector state did not change.

## Local AI 1.5 title-regeneration patch (2026-11-18)

Five fixed ai-domain keys support the separately maintained upstream title patch:
optional feedback, recent-candidate repetition, draft changes and recovery.
The addon does not build those prompts, keep candidate history, save titles,
or own the upstream modal. Its connector now forwards the explicitly supplied
`current_title` as `existing_title`, including clearing the stored-title fallback
when the draft title is empty; older callers retain the saved-title fallback.
The text-model file remains within its existing 401-line ceiling.

Real WordPress REST checks cover draft and empty overrides, legacy input,
rejection of feedback over 500 characters/history over five candidates, and
anonymous permission denial. Suggestions did not persist the disposable post.
Native title review browser checks cover duplicate rejection without automatic
retry, retained feedback/candidate, explicit draft adoption, and stale insertion
blocked after author edits at PC 1440/1280. Errors stay inside the review modal.
Evidence: task directory `editor-followthrough-20261118/title-*`.


### List labels followthrough — 2026-11-18

The installed AI 1.5 local patch now owns known request-log enum labels and
known origin badge fallbacks. This addon adds nine fixed ai-domain translations
(Success, Timeout, AI Client, MCP Tool, Text, Image, Audio, Embeddings, Metadata)
and two exact component fallback keys; it does not translate dynamic IDs or
change native list persistence/layout. Unknown extension enum codes and explicit
custom origin labels remain readable without replacement.

Local strict audit after rebuilding the native patch: 1335 discovered AI strings,
1079 shim entries; zero fixed-UI/error/default-domain gaps; the same 300 reviewed
non-UI strings remain excluded. This is bounded installed-plugin evidence,
not complete English coverage or proof of every error branch. Four real admin
pages were checked at 1440×1050 and 1280×960, including list options, legacy view
recovery, keyboard row actions, log detail Escape/focus return, and cancelled
purge confirmation. Five Gravatar requests were intentionally blocked; no
unexpected request or console failures. Article 279790 and feature settings
fingerprints stayed unchanged. Evidence lives in the local
`editor-list-followthrough-20261118/final-3` report and sibling visual receipt.

Native source commit `7c9381c` is local only. Rebuilding/upgrading native AI
assets must reapply or replace its source patch; these addon translations alone
do not fix duplicate columns or raw enum rendering. CI's stable AI 1.4 lane and
local AI 1.5 evidence remain separate. Rollback only these addon dictionary/hook
changes or the separately recorded native asset hashes; do not erase saved views.


### Rename modal recovery — 2026-11-18

The native local AI patch `f6e8c64` now rejects stale Rename results after post,
title, excerpt or content changes and ignores late responses after closing the
original form. It owns in-modal errors and connector recovery actions. This addon
only adds two fixed ai-domain messages: changed-draft recovery and duplicate-name
recovery that names Rename's actual controls. No new default-domain key or prompt
ownership is introduced.

Installed strict audit: 1337 discovered AI keys / 1081 shim entries, zero bounded
UI/error/default-domain gaps; the same 300 non-UI entries remain excluded. PC
1440×1000 and 1280×960 browser evidence covers duplicate requests/candidates,
name/excerpt edits, close/reopen with delayed output, expected 503, missing-provider
link and Escape/focus return. No content was saved; temporary post/auth/helper
cleanup and original article fingerprint are recorded. The four earlier admin
pages were not rerun for these editor-only strings. Native 30 tests and Addon
PHP/JS/strict audit remain distinct from model quality, M4 and production evidence.
Native patch artifact and browser receipts are linked from the Cloud followthrough
record; upgrading AI must reapply/review the patch before claiming this behavior.
