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
- It targets text domain `ai`, with the sixteen-key bundled component exception below.
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

At the user's explicit request to fix these remaining gaps, sixteen source keys
(two plural pairs, five contextual strings, and seven plain labels) have a bounded compatibility
exception: `bundled_ui_fallback()` supplies only those keys on
`settings_page_ai-wp-admin` and `tools_page_ai-request-logs`. The JS hooks check
the exact source, plural pair or context, accept only the default domain, and stand down
when an existing translation is present. Never register the full AI dictionary
in the default domain. Do not expand this exception to other keys or screens
without evidence and review. Retire it when the bundled component translations
work without the fallback.

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
heuristics: the reviewed settings import error, translation/slug/summary editor bundles, Abilities Explorer
help, Markdown Feeds settings, and comment moderation controls. Long help text
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
