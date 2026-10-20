# SEO Specialist Connector Pause

Status: development candidate, 2026-10-18.

`Npcink_Cloud_Discoverability_Pause` owns a small transport availability guard,
not local Ability truth or a second registry. `ACTIVE=true` pauses the named
specialist tasks. Registered task projections and legacy runtime calls fail
before signing or outbound HTTP with `cloud_feature_paused`. The task
projection uses the existing HTTP 400 contract; transport guards use HTTP 409.
Callers must not retry through another paid generation path.

Host integration uses the official `wpai_default_feature_classes` and
`wpai_feature_meta-description_enabled` filters. The Meta Description editor
entry and settings feature are absent while the saved enable option is
preserved. General slug and editorial features remain; SEO-only editorial
requests are rejected and mixed requests exclude the specialist review type.
Cloud independently validates the same runtime boundary.

No options, credentials, old run results, adopted metadata, history or WordPress
content are removed. General title, excerpt, summary, translation,
classification, slug, ALT and media features continue through the original
connector. Existing localization and metadata implementations are retained.

The installed WordPress AI plugin's feature filter was checked against the
local source. Future host versions must still pass connector registration and
runtime behavior tests before restoration. Reverting this focused change
restores the prior integration without a data migration; test the current
host and Cloud versions rather than relying on old cached results.

Toolbox owns product composition and Cloud owns runtime availability. Toolkit,
Core, Adapter and Eval remain outside this task. The source and verification
receipt is in the [three-repository implementation record](../../npcink-workflow-toolbox/docs/seo-specialist-pause-implementation-2026-10-18.md).

Cross-repo task-id / runtime-key对照, error-code differences, publish order
(Cloud → Addon → Toolbox), and smoke matrix row map live in
[reversible-feature-pauses-v1.md](reversible-feature-pauses-v1.md). Addon has no
`$paused_specialist_tasks` property — the ten ids are inline in
`Npcink_Cloud_Discoverability_Pause::is_task_paused()`.

旧 `execute_runtime()` 对包含任何专项编辑类型的请求整体拒绝（包括混合及大小写变体）；调用方须重新提交通用类型。当前 connector 路径保留通用类型并移除专项类型。固定界面说明同时覆盖 PHP 和 zh_CN React locale payload；动态 Ability metadata 不改写。

已注册任务投影中的 `seo_headline` 同样属于暂停任务；通用自定义任务投影仍可传输。回归使用通用 article_headline 正向样例和 SEO 别名零调用负向样例，避免依靠旧的 SEO 成功样例证明暂停完整。
