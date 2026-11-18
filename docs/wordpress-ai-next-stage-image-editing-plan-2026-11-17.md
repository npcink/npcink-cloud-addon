# WordPress AI 下一阶段：补齐图片编辑（2026-11-17）

状态（2026-11-18 更新）：Flare 图片编辑能力探测及原生 WordPress AI → Addon →
M4 Cloud → Provider → artifact 下载/字节校验/ACK 的真实链路已成功，主模型已保存。
新增 5 次预算保守计数已用完，不再调用模型；人工图片质量验收仍待操作者回答。
源码尚未合并，实际站点已恢复原 Addon 挂载，M4 仍是 candidate。下文历史失败和
剩余额度记录按发生时间保留，以末尾最新验收为准。

## 已核实的缺口

| 层级 | 当前事实 | 证据位置 |
| --- | --- | --- |
| WordPress AI | `ai/image-generation` 已接受可选 `reference` 图片数据 URI，用于编辑 | 已安装 AI 插件 `includes/Abilities/Image/Generate_Image.php` |
| Addon | 图片模型已识别单个 WordPress AI `File`，先上传短 TTL artifact，再只传 artifact id；结果仍走下载、checksum 和 delivery ACK | `includes/class-cloud-wordpress-ai-image-model.php` |
| Cloud 托管目录 | 已增加 `wp-ai.image-editing` profile；候选仅接受显式 `endpoint_variant=image_edits` 的 Provider 实例 | Cloud `app/domain/wordpress_ai_connector/routing_profiles.py`、`app/domain/catalog/service.py` |
| Cloud 请求合同 | `wordpress_operation.v1` 的 `image_editing` 仅接受 `source_artifact_id` 与最多 4000 字符 prompt，拒绝 URL/base64/Provider 字段 | Cloud `app/domain/wordpress_ai_connector/contracts.py` |
| Provider | OpenAI-compatible mock/录制已覆盖 `POST /images/edits` multipart；结果标记 `image.edit.v1` 并复用图片 artifact 物化 | Cloud `app/adapters/providers/openai.py` |
| 可用性证据 | Flare 编辑实例已完成真实能力探测并保存为 M4 编辑主模型；Grok 仍保留验证失败状态 | Cloud `catalog_capability_evidence`、运行配置图片编辑入口 |

因此，目录和零模型执行链路已经补齐，但成功的真实 Provider 与质量证据仍缺失，不能
仅凭目录项或 mock 测试宣称“已支持”。
视觉理解提供 ALT/识图；图片生成创建图片；图片编辑以原图及修改要求生成结果，
三者需要分别检查输入、模型能力和结果质量。

## 实施顺序与归属

1. **完成已有安全修复的审查与本地站点验证。** 核实实际挂载版本，启用验收调用
   预算保护，保留当前视觉异常输出拦截及图片交付阶段日志。
2. **Cloud 定义图片编辑托管用途与输入合同。** 用途 ID 已注册为
   `wp-ai.image-editing`；首期限定单张参考图和文字编辑要求。
   明确它与既有生图请求的分流关系，避免未知任务落到默认文字或生图用途。
   独立记录编辑能力；不能由 `image_generation` 的验证结果推导可编辑。
3. **Provider 接入实际编辑通道。** 根据提供商的真实协议接入，先完成录制/模拟
   请求测试。没有编辑能力证据的模型不能被当作已验证编辑候选。未配置、未验证、
   无可用模型必须如实显示，不能初始化为“已配置”。
4. **Addon 接入有界参考图传输。** 优先复用现有短 TTL 源图上传与 artifact-id
   机制，校验 MIME、字节、尺寸、大小、归属和期限。WordPress 的 `reference`
   留在本地接入层解析，不允许任意 URL 获取。输出复用校验下载与 verified-transfer
   ACK，操作与结果合同严格对应；最终采纳和媒体保存继续属于 WordPress。
5. **完成真实配置与编辑验收。** 模型选择必须依据编辑能力；目录、配置
   快照、Addon 分流和运行结果需要一致。验收单图编辑时，确认所要求的区域或
   属性发生变化、应保留的主体仍存在，原图不被覆盖。生成、编辑、视觉分别记录
   run_id、Provider 尝试次数、交付结果和人工质量判断。

首期不包含多图合成、局部蒙版、批量处理或自动覆盖原媒体；这些范围不能从本次
单图接入推导为已支持。Addon 不拥有模型路由、提示词、编辑策略或 WordPress 写入。

## 验证与预算

先完成零模型调用的合同、Provider 请求录制、参考图校验、错误分流与结果交付测试。
跨仓收尾从 Workflow Toolbox 运行中央质量矩阵，发版时同步 Cloud 兼容矩阵。
目录界面需要实际 PC 浏览器验收；仅规格或翻译测试通过不能算目录已交付。

此前建议的四次真实验收调整为：图片生成一次、视觉两次、图片编辑一次。
这是后续预算分配，不是本次调查的调用记录；不得增加到旧批次已发生的调用中。
任何实际调用前先核实累计授权、候选模型、禁用回退/自动重试及上游尝试计数。
若失败则停止；额外修复复验不通过重启 CLI 获得新的额度。

### 前轮 M4 验证记录（1 次预算）

- 环境：M4 candidate，基线 `f17354f234d2` 加未提交图片编辑变更；非 accepted，
  未发布生产。
- 用户单独授权的 1 次真实验证已使用。失败证据时间为
  `2026-11-17T06:04:04.074195Z`，错误码 `provider.invalid_request`。
  初次页面另显示 `client.invalid_envelope`，不能把这个前端错误当成未调用上游。
- 定位到 multipart 图片请求仍继承 JSON Content-Type、失败响应顶层缺少错误码、
  编辑选择器混入生图模型三项缺陷，已修复并完成零付费回归。HTTP 头缺陷确实存在，
  但没有真实复验，尚不能认定它是供应商拒绝请求的唯一原因。
- M4 页面已只显示显式编辑接口候选；`grok-imagine-edit` 保留“验证失败”，不能
  保存为主模型。修复后的错误码显示由模拟浏览器测试验证，未再次点击真实验证。
- 后续如再授权 1 次真实复验，成功后才保存图片编辑主模型。能力探测不等于
  WordPress 端到端成功或人工编辑质量通过。

### 追加授权后的 M4 复验（最多 5 次）

用户随后明确追加最多 5 次付费调用，覆盖本节复验；不与前轮已使用的 1 次混算。
先发现并修复能力探测的底层兼容重试：确认一次探测最多一次上游请求，普通执行
的兼容逻辑不变。4 项定向模拟回归及完整 OpenAI Provider 130 项测试通过，修复
已同步 M4 candidate（基线 `f17354f234d2`，源码包 `a9724342eccb03c2…`）。

| 本轮次数 | 操作与证据 | 结果 |
| --- | --- | --- |
| 1/5 | 运行配置页面验证 `grok-imagine-edit`，证据时间 `2026-11-17T06:34:01.656334Z` | `provider.upstream_unavailable`，页面正确显示失败，未保存主模型 |
| 2/5 | 使用相同连接、模型、编辑探测输入和既有 Provider budget guard 作有界诊断；transport 强制最多一次上游请求 | multipart 请求头正确；供应商 HTTP 503，错误类型 `grok_media_no_eligible_account`；上游尝试计数为 1 |

只读 `/models` 返回 200 且包含此模型，但目录存在不能证明媒体账号可用；不能据此
把编辑能力标为成功。诊断只记录状态、错误类型和请求计数，不记录密钥、源图或
完整供应商响应。诊断关联 ID 为 `capability_probe_632f0b20af384bb495a98b7a5857327d`，
它是能力探测标识，不是已完成 WordPress runtime run 的证据。

剩余 3 次不继续原样重试。下一步由供应商恢复可用于 Grok 媒体能力的账号/通道，
然后使用剩余额度复验；通过后才保存主模型。没有新增模型能力成功、WordPress
端到端或人工质量证据；本轮未新增 Addon 源码、bootstrap、公开 API 或兼容基线
变更，因此不重复 Playground 门禁。

### 双用途模型目录补正

操作者随后确认 `gpt-image-2.5-flare` 也支持图片编辑。原目录将每个型号只登记为
一个接口，导致已登记生图的该型号被编辑选择器误排除。这是目录登记遗漏，不能
据此认定模型不支持编辑。

Cloud 已支持有来源/版本的 `endpoint_variants` 声明。M4 为该模型保留原
`image_generations` 实例，并新增独立 `image_edits` 实例；编辑选择器已现场显示
`gpt-image-2.5-flare`，并在后续真实探测后保存编辑主模型。不能用 Grok 的账号
阻塞推导该型号也不可用。对应 139 项模拟回归和双用途浏览器专项通过。

MCP 的外部 HTTP 只读与权限检查继续走宿主/Adapter；WebMCP 等待原生
`modelContext` 宿主验证。两者不需要为了目录完整而新增 Cloud 场景。

## 交付口径

- “目录项已添加”只代表入口存在。
- “配置已保存”不代表模型具备图片编辑能力。
- “执行成功”不代表原图保真或编辑质量达标。
- 只有 WordPress 输入、Cloud/Provider 执行、安全交付及人工质量均通过，
  才将图片编辑标为已支持；当前是“Flare 真实链路已成功，人工质量待验收，
  合并及干净 master M4 验收待完成”。

## 最新真实验收（2026-11-18）

在此前 2/5 次 Grok 验证之后，本轮保守占用剩余 3 个预算槽：

| 槽位 | 操作 | 结果 |
| --- | --- | --- |
| 3/5 | `gpt-image-2.5-flare` 独立编辑能力探测 | verified；后台代理固定 12 秒的假失败已修正为有界探测超时 |
| 4/5 | 原生 WordPress 编辑调用 | Cloud 在 Provider 前以 `routing.execution_kind_mismatch` 拒绝，实际 Provider 0 次，仍保守占 1 槽 |
| 5/5 | 修复后原生 WordPress 端到端 | succeeded；1 次 ProviderCallRecord，0 重试、无回退 |

成功 run：`run_8a66f3a54c8143aebf9825076b09a02d`；profile
`wp-ai.image-editing`；实例 `openai-global-gpt-image-2-5-flare-image-edits`。
artifact `art_ad2bcb6442454c0a9eeebf9d82cf5406`，operation `image.edit.v1`，
676075 bytes，1254×1254 PNG；SHA256
`2e7ab534a449db05cd3c83582c3c9f8eecccc69eb109e478bcc85b8a7405e070`。
Provider latency 12676 ms；verified delivery ACK 时间
`2026-11-18T03:47:37.387884Z`，下载字节及 checksum 一致。

源图为代码绘制的 512×512 测试图。要求仅将红圆改为绿色，保留黑轮廓、蓝方块、
白背景；结果具备这些对象与颜色，但尺寸变为 1254×1254，边缘有重绘。
技术成功不能证明逐像素保真；人工质量问答仍 pending。没有导入媒体、保存文章或
发布，源图 checksum 与 WordPress posts/postmeta/comments 快照均未变化。

原生 SDK 预检修正了 text-only inputModalities 与错误的 File getter：使用真实
`getBase64Data()`，拒绝远程 File，不抓 URL；编辑严格要求 result 和每个 artifact
均为 `image.edit.v1`，生图仍要求 `image.generate.v1`。mock 匹配真实 SDK 与
connector envelope，并覆盖 operation 错配前拒绝下载/ACK。
Cloud 从可信 task 派生 image execution kind，从可信 run profile 派生编辑
operation，不接受 Provider 自报 operation 作为判断依据。

证据保存在 Cloud `.runtime/image-editing-final/` 的 budget、Cloud/WP receipts、
源图/结果/对照图和日志中。SDK 成功后未保留 current_cloud_run_id，本轮 run_id
由 artifact 的只读数据库关联找回，receipt 已标明来源。旧 last_ai_error 仍保留
先前 routing mismatch，不代表此次失败；未为诊断另增产品机制。

本机验收临时挂载候选后已恢复 `/Users/muze/gitee/npcink-cloud-addon`，不表示站点
永久安装了编辑功能。M4 运行未提交候选，clean-master accepted、CI、合并、人工
接受及生产必须分别记账。生产未授权。

同日 audit 发现安装的 AI 1.5 中 17 条新增固定文案缺失，涉及请求日志类型标签、
标题要求和编辑更新提示；已补 ai-domain PHP/JS 映射与真实资产测试。未扩大
default-domain 例外，动态能力/字段/提示词仍不在 Addon 翻译范围。
