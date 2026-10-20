# Reversible Cloud Feature Pauses v1

Status: active inventory + restore roadmap, 2026-10-20. No plugin distribution
or production deployment is implied.

Source of truth: `Npcink_Cloud_Discoverability_Pause`
(`includes/class-cloud-discoverability-pause.php`). One constant
`ACTIVE=true` gates every pause below. WordPress options, consent, credentials,
and historical records are **not** deleted. Reconnecting Cloud or refreshing
caches **cannot** re-enable a paused feature.

Specialist SEO detail remains in [seo-specialist-pause-v1.md](seo-specialist-pause-v1.md).
Coordinated Cloud/Toolbox evidence lives in Cloud’s matching
`docs/reversible-feature-pauses-v1.md` (and Toolbox pause implementation notes).

## Inventory (Addon)

| Pause 名 / 键 | 类型 | 隐藏或拦截的入口 | 恢复前置（缺一不可） | 建议优先级 |
| --- | --- | --- | --- | --- |
| **SEO/AEO/GEO specialist**（`is_task_paused` + `editorial_notes` review 过滤 + host filters） | 任务 / host UI | **已于 2026-10-20 P1 恢复**：`$paused_specialist_tasks` 为空；Meta Description host filter / UI SEO 文案改写不再注册；`is_task_paused` 与 specialist review 过滤默认放行。守卫方法保留，可再单键暂停 | 已完成 Addon：清空 `$paused_specialist_tasks`（未动 `ACTIVE` / P2–P3 runtime markers）。Cloud/Toolbox 同边界恢复另仓对齐 | **P1 已恢复（Addon）** |
| **`advanced_media`** | runtime feature markers | Ability/合约/profile 含 media derivative / governance audit / batch plan 等 marker；`Media_Derivative_Transport` 在上传前拒绝；`Media_Plan_Projection` 拒新优化计划；`Runtime_Client` 预签名 + 部分路径再拒 | ① 从 `$paused_runtime_features` 去掉本键（或全局 ACTIVE）② Cloud + Toolbox 媒体批处理入口可用 ③ 回归：派发/计划/治理审计与「暂停时零 HTTP」对偶用例 ④ 确认本地 Core/Toolbox continuation 不误伤 | **P3** 面最宽、上传与治理耦合最深，最后恢复 |
| **`audio_generation`** | runtime feature markers | `generate-audio` / `audio_generation*` / `audio.narration.default` 等 marker 在 `runtime_error` 预签名拒绝 | ① 移出 paused markers ② Cloud 音频执行可用 ③ 窄回归：音频请求 409→成功，普通文本不受影响 | **P2** 面较窄，依赖 Cloud 音频能力就绪 |
| **`cloud_inspection`** | runtime feature markers + 权益投影 | Nightly / site-ops 分析 marker；`retry_run` 等检查路径；权益将 Hosted Runtime 标为 `paused`；Overview **隐藏 Runtime allowance** | ① 移出 paused markers ② Cloud 巡检/批处理可用 ③ 权益缓存与 Overview Runtime 行回归 ④ 确认夜间批与 site-ops 入口 | **P2** 影响额度展示与夜间批，需 Cloud 批处理就绪 |
| **`wordpress_ai_generation_tests`** | admin surface | **已于 2026-10-20 P0 恢复**：Checks 再显示生成测试 / 缺插件引导；`is_admin_surface_paused` 守卫保留，默认可为空数组再单键暂停 | 已完成：从 `$paused_admin_surfaces` 去掉本键（未动 `ACTIVE`） | **P0 已恢复** |

全局开关：`Npcink_Cloud_Discoverability_Pause::ACTIVE`。设为 `false` 会一次解除上表全部项；**不推荐**作为「只开一项」的手段。单项恢复应改 `$paused_runtime_features` / `$paused_admin_surfaces` / `is_task_paused` 列表，并走审阅 PR。

失败契约：运行时返回 `WP_Error` code `cloud_feature_paused`，HTTP **409**，`retryable=false`。调用方不得改走其他付费生成路径重试。

## 仍可用（暂停期间）

普通标题/摘要/翻译/分类/slug（非 SEO 专项）、ALT、常规图像、Site Knowledge、搜索、已验证连接、历史 run/结果读、已有 artifact 下载/ACK、可选 monitoring。

## 恢复路线图（可转述）

1. **P0 — 生成测试入口（已完成 2026-10-20）**  
   已从 `$paused_admin_surfaces` 去掉 `wordpress_ai_generation_tests`（保持 `ACTIVE=true`，其它 pause 不变）→ Checks 露出测试入口或官方 AI 插件引导 → 联调可跑 WP AI smoke（不涉及 SEO 专项）。  
   *目的：先恢复联调入口，不动付费专项 transport。*

2. **P1 — SEO/AEO/GEO 专项（Addon 已完成 2026-10-20）**  
   已清空 `$paused_specialist_tasks`（保持 `ACTIVE=true`，P2–P3 runtime pauses 不变）→ Meta Description 与 SEO 固定文案再露出 → 行为测试断言 SEO 任务不再 409，且 advanced_media / audio / inspection 仍暂停。  
   *Cloud/Toolbox 同边界恢复需另仓对齐后再端到端联调。*  
   *目的：产品文案与编辑能力回正，验证宿主 filter 仍有效。*

3. **P2a — 音频**  
   去掉 `audio_generation` markers → Cloud 音频就绪证明 → 窄回归。  

4. **P2b — 夜间巡检 / site-ops**  
   去掉 `cloud_inspection` → 权益与 Overview Runtime 行恢复 → 批处理/重试回归。  
   *可与 P2a 并行，视 Cloud 排期。*

5. **P3 — 高级媒体衍生**  
   最后去掉 `advanced_media` → 上传前守卫、计划投影、治理审计、Toolbox continuation 全链路 → 强调「暂停时零 HTTP」对偶用例仍在。  

每步交付：源码 PR（Addon ± Cloud ± Toolbox）+ `composer test:contracts`（含 `tests/behavior-feature-pauses.php`）+ 对应宿主版本冒烟。禁止只靠清缓存或重连。

## Verification (Addon)

- Default policy：暂停 marker 零出站 HTTP；普通 runtime 仍可出站（见 `tests/behavior-feature-pauses.php`）。
- 实现类与 public 签名保留；测试可用反射覆盖，**无**公开设置开关。
- 恢复后：对偶增加「该 feature 不再 409」用例，并保留至少一条其它 pause 仍生效的断言（若未全局 `ACTIVE=false`）。

## Complexity note

Settings-page / runtime-client line ratchets are unchanged by this documentation-only update.
