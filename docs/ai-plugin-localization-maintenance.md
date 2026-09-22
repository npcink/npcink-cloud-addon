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
- It only targets text domain `ai`.
- It feeds PHP gettext, PHP ngettext (Chinese locales have no plural
  distinction, so the singular source key always maps), and
  `wp.i18n.setLocaleData()` through the same translation map.
- It must not call `npcink_cloud_addon_runtime_client()` or any Cloud runtime
  method.

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

The command should output:

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
- Near matches for likely changed English copy.
- A count by surface when it can infer one, such as settings, editor, image
  generation, request logs, or abilities explorer.

It should exit non-zero only when a CI gate explicitly opts in. For local
developer use, plain reporting is enough.

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
6. Run the required verification commands.
7. Tell the user which strings are covered and which are intentionally left to
   the source plugin.
