# Reversible Cloud Feature Pauses v1

Status: active inventory + cross-repo对照 + restore roadmap, 2026-10-20.
No plugin distribution or production deployment is implied by this document.

**Addon baseline (0.4.4):** reviewed `master` tip
`6100e23ca367c0bbce46174420c47473e4171a2b` (release identity + merged
#300–#306 behavior). SEO/AEO/GEO specialist tasks and the three runtime pause
keys remain paused on this tip.

## Addon source of truth

Class: `Npcink_Cloud_Discoverability_Pause`
(`includes/class-cloud-discoverability-pause.php`).

| Mechanism | What it gates |
| --- | --- |
| `ACTIVE` (const, default `true`) | Master switch for every pause below. `false` clears **all** pauses at once — do not use for single-feature restore. |
| `is_task_paused( $task )` | Inline list of **10** SEO/AEO/GEO task ids (not a named `$paused_specialist_tasks` property — that name does not exist in Addon). |
| `$paused_runtime_features` | Map keys `cloud_inspection`, `audio_generation`, `advanced_media` → ability/contract/profile markers. |
| `$paused_admin_surfaces` | Admin entry hide list. `wordpress_ai_generation_tests` was cleared 2026-10-20 (P0); array may be empty while `ACTIVE` stays true. |
| Host filters (when `ACTIVE`) | `wpai_feature_meta-description_enabled`, `wpai_default_feature_classes`, fixed `gettext_ai` SEO copy. |

**Naming rule:** never document Addon as having `$paused_specialist_tasks`.
The task list lives only inside `is_task_paused()`.

WordPress options, consent, credentials, and historical records are **not**
deleted. Reconnecting Cloud or refreshing caches **cannot** re-enable a paused
feature.

Specialist SEO narrative: [seo-specialist-pause-v1.md](seo-specialist-pause-v1.md).
Sibling inventories: Cloud and Toolbox each keep their own
`docs/reversible-feature-pauses-v1.md` (parallel enforcement, not a shared
constant package).

## Cross-repo对照 (parallel lists)

Three repositories enforce availability **independently**. There is no shared
cross-repo constant. Publish and unpause in order **Cloud → Addon → Toolbox**
so UI never advertises a path that Cloud still rejects.

Verified smoke baselines (Asia/Shanghai 2026-10-20, read-only, no unpause):

| Repo | HEAD used for matrix v1 |
| --- | --- |
| Cloud (`npcink-ai-cloud`) | `fe12ce2c` |
| Addon (`npcink-cloud-addon`) | `ae15b90` (matrix run); package/docs baseline now `6100e23` / **0.4.4** (pause policy unchanged) |
| Toolbox (`npcink-workflow-toolbox`) | `2dfc3fe` |

### Table A — SEO/AEO/GEO specialist

| Capability | Cloud | Addon | Toolbox | State |
| --- | --- | --- | --- | --- |
| Specialist task set | `PAUSED_DISCOVERABILITY_TASKS`: same **10** ids as Addon (`meta_description`, `seo_title`, `seo_headline`, `seo_description`, `seo_metadata`, `aeo_summary`, `answer_summary`, `geo_summary`, `structured_data_hints`, `content_discoverability`). Reject: `wordpress_operation.feature_paused` **400** | `is_task_paused()` same 10 ids; filter `editorial_notes` seo/aeo/geo; hide Meta Description host feature; reject `cloud_feature_paused` **409** | **No per-task array.** `Settings::DISCOVERABILITY_PAUSED = true`; hide Site Profile SEO/AEO/GEO tabs; do not register `build-content-discoverability-brief`; specialist plan/brief → `npcink_toolbox_feature_paused` **409** | All three paused; Cloud↔Addon task ids match; Toolbox is a whole-surface boolean |
| `editorial_notes` specialist | Pure specialist → pause; mixed → filter then continue | Legacy runtime may reject whole payload; connector path filters specialist types | No specialist brief exposed; generic writing still available | Specialist side paused |

### Table B — runtime feature keys (same names)

| Key | Cloud `PAUSED_RUNTIME_FEATURES` | Addon `$paused_runtime_features` | Toolbox `$paused_cloud_features` | State |
| --- | --- | --- | --- | --- |
| `cloud_inspection` | Nightly / site-ops markers; `runtime.feature_paused` **409** | Same key + markers; Hosted Runtime entitlement forced paused/hidden | `true`; hide Cloud analysis entry; Provider **409**; local Site Check kept | All three paused |
| `audio_generation` | `generate-audio`; intents `article_narration` / `article_audio_summary` | Same key; pre-sign reject | `true`; both intents paused; editor entries removed; existing players kept | All three paused |
| `advanced_media` | `plan-media-derivative-batch` / `audit-media-governance` etc. | Same key; transport / plan reject | `true`; withdraw batch optimize; omit `build-media-derivative-handoff`; backup restore kept | All three paused |

### Table C — asymmetric / Addon-only notes

| Item | Notes |
| --- | --- |
| Operations Advisor | Cloud-only (`OPERATIONS_ADVISOR_PAUSED`); Addon/Toolbox have no matching key |
| `wordpress_ai_generation_tests` | Addon `$paused_admin_surfaces` cleared (P0 restored 2026-10-20); unrelated to SEO pause |
| Toolbox specialist granularity | `contains_specialist_action` field set is narrower than Cloud’s 10 task ids; whole-surface `DISCOVERABILITY_PAUSED` closes the gap |
| Error codes (do not cross-search) | Cloud task: `wordpress_operation.feature_paused` **400**; Cloud runtime: `runtime.feature_paused` **409**; Addon: `cloud_feature_paused` **409**; Toolbox: `npcink_toolbox_feature_paused` **409** |

### Risks when aligning

1. Do not invent `$paused_specialist_tasks` for Addon.
2. Do not force Toolbox to grow a 10-id task array — map to “entry hidden + whole-surface reject.”
3. Keep the three error-code families distinct in logs and tests.
4. Addon `ACTIVE=false` unpauses SEO **and** all three runtime keys at once; restore one face by editing the list/map only.
5. Tables above are source-policy truth; live sites must match the deployed SHA.

## Pause smoke matrix v1 (row map)

Convention: every row must satisfy **entry not visible / not registered** and
**execute path returns the expected code**, with **zero outbound Cloud HTTP**
on Addon/Toolbox reject paths. “Visible but rejected” = fail.

| Rows | Owner | Addon / sibling coverage |
| --- | --- | --- |
| D1–D4 | Toolbox | SEO UI/projection/register/API |
| **D5–D6** | **Addon** | Meta Description + copy; 10 tasks / editorial → `cloud_feature_paused` 409 |
| D7–D8 | Cloud | Same 10 tasks + editorial → 400 `wordpress_operation.feature_paused` |
| C1–C3 | Toolbox | `cloud_inspection` UI/projection/API |
| **C4–C5** | **Addon** | Overview Hosted Runtime paused/hidden; nightly/site-ops markers 409 |
| C6 | Cloud | analyze-nightly → 409 `runtime.feature_paused` |
| A1–A3 | Toolbox | audio UI/API |
| **A4** | **Addon** | `execute_toolbox_audio_generation_runtime` → 409 `audio_generation` |
| A5 | Cloud | generate-audio → 409 |
| M1–M3 | Toolbox | advanced_media UI/register/API |
| **M4** | **Addon** | Transport / Plan_Projection / markers → 409 `advanced_media` |
| M5 | Cloud | plan-media-derivative-batch etc. → 409 |

Addon suite anchor: `tests/behavior-feature-pauses.php` (also via
`composer test:contracts`). Matrix v1 result on Addon `ae15b90`:
**D5 D6 C4 C5 A4 M4 all PASS** (read-only). Cloud and Toolbox reported matching
PASS for their rows the same day.

Still available while paused: generic title/excerpt/translate/classify/slug
(non-SEO specialist), ALT, ordinary images, Site Knowledge, search, verified
connection, historical run/result reads, existing artifact download/ACK,
optional monitoring; Toolbox local Site Check; existing audio playback;
media backup restore.

## Inventory (Addon detail)

| Pause 名 / 键 | 类型 | 隐藏或拦截的入口 | 恢复前置（缺一不可） | 建议优先级 |
| --- | --- | --- | --- | --- |
| **SEO/AEO/GEO specialist**（`is_task_paused` + `editorial_notes` review 过滤 + host filters） | 任务 / host UI | 任务：上表 10 ids；`editorial_notes` 中 seo/aeo/geo；WordPress AI **Meta Description**；固定 UI 文案去掉 SEO 表述 | ① 从 `is_task_paused` 列表移出相关任务（勿只靠 `ACTIVE=false`）② Cloud `PAUSED_DISCOVERABILITY_TASKS` 同边界恢复 ③ Toolbox `DISCOVERABILITY_PAUSED` 对齐 ④ 当前宿主 WordPress AI 版本通过 connector 注册与 runtime 行为测试 | **P1**（产品另说前保持暂停） |
| **`advanced_media`** | runtime feature markers | Ability/合约/profile media derivative / governance / batch plan markers；`Media_Derivative_Transport`；`Media_Plan_Projection`；`Runtime_Client` 预签名 | ① 从 `$paused_runtime_features` 去掉本键 ② Cloud + Toolbox 媒体批处理入口可用 ③ 回归含「暂停时零 HTTP」对偶 | **P3** |
| **`audio_generation`** | runtime feature markers | `generate-audio` / `audio_generation*` / `audio.narration.default` 等 | ① 移出 paused markers ② Cloud 音频可用 ③ 窄回归 | **P2** |
| **`cloud_inspection`** | runtime feature markers + 权益投影 | Nightly / site-ops markers；权益 Hosted Runtime=`paused`；Overview 隐藏 Runtime allowance | ① 移出 paused markers ② Cloud 巡检可用 ③ 权益与 Overview 回归 | **P2** |
| **`wordpress_ai_generation_tests`** | admin surface | **已于 2026-10-20 P0 恢复** | 已完成 | **P0 已恢复** |

失败契约（Addon）：`WP_Error` code `cloud_feature_paused`，HTTP **409**，
`retryable=false`。调用方不得改走其他付费生成路径重试。

## 恢复路线图（可转述）

1. **P0 — 生成测试入口（已完成 2026-10-20）**  
   已从 `$paused_admin_surfaces` 去掉 `wordpress_ai_generation_tests`（保持
   `ACTIVE=true`）→ Checks 露出测试入口。

2. **P1 — SEO/AEO/GEO 专项**  
   仅在产品明确要求时做。按本对照表 + [seo-specialist-pause-v1.md](seo-specialist-pause-v1.md)
   三仓同开同关 → 恢复任务列表与 Meta Description host filter → 负向：SEO 别名零出站；正向：通用 headline/editorial。  
   *默认继续暂停；禁止只改 Addon 一边。*

3. **P2a — 音频** / **P2b — 夜间巡检** — 去掉对应 runtime 键；Cloud→Addon→Toolbox。

4. **P3 — 高级媒体衍生** — 最后去掉 `advanced_media`；全链路含暂停时零 HTTP 对偶。

每步交付：源码 PR（Addon ± Cloud ± Toolbox）+ `composer test:contracts`（含
`tests/behavior-feature-pauses.php`）+ 对应宿主版本冒烟。禁止只靠清缓存或重连。

## Verification (Addon)

- Default policy：暂停 marker 零出站 HTTP；普通 runtime 仍可出站（见
  `tests/behavior-feature-pauses.php`）。
- 实现类与 public 签名保留；测试可用反射覆盖，**无**公开设置开关。
- 恢复后：对偶增加「该 feature 不再 409」用例，并保留至少一条其它 pause 仍生效的断言（若未全局 `ACTIVE=false`）。
- Cross-repo：before unpause, re-run matrix rows for that face on all three SHAs.

## Complexity note

Settings-page / runtime-client line ratchets are unchanged by this
documentation-only update.
