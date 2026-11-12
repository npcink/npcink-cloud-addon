# 停用站点提示与连接恢复验收 — 2026-11-12

## 修复与边界

详细检查将可达 Cloud 返回的 `auth.site_inactive` 投影为“需要在 Cloud 启用此站点”，
保留 signed-read 失败事实。Checks 的主按钮直接使用既有同页授权表单；技术详情链接
回该按钮。不能只链接 Connection：旧 verified 状态下那里可能没有授权入口。
连接验证成功后清除当前管理员旧的详细检查缓存，不将连接成功写成生成验收。

常显：停用原因、恢复主按钮。按需：排查信息。技术证据：signed-read/liveness
和失败原因。删除重复：不再把同一停用事实显示为通用服务故障或联系客服。
不新增 API、配置、权限、后台任务或 Cloud/WordPress 写入职责。

## 实测

2026-11-12 14:05–14:07（上海），Edge，PC 1912×961，device Local PHP 8.2.35，
生产 Cloud 既有账号。安装前完整备份；安装 ZIP SHA-256：
`c0ec06f7c76d25d21bfe4f4a5539d2feb0dbbed731ced92da7d25b16eeaff0bb`，
55 个包文件逐项核验。此次为受控测试站安装，不是 WordPress.org 发布。

- 为覆盖“已 verified、详细检查才发现 inactive”，只临时注入本地 readiness 样本；
  未停用生产站点，未改凭据或许可。该样本不构成生产真实停用的证据。
- Checks 正确显示停用提示及“打开 Npcink Cloud”。点击进入带授权回调的 Cloud 页面。
- 页面确认既有 device / 既有账号；点击“确认绑定并激活”，观察到禁用的“正在绑定…”
  状态，随后自动返回 WordPress Connection，出现“已连接到 Npcink Cloud”。
- Checks 不再出现注入的失败提示；运行真实详细连接检查后返回通过。
  独立只读检查：verified=true，bounded_status=ready，next_safe_action=continue，
  blocked_reason 为空；原临时样本已被真实检查覆盖。既有图片/视觉不可用提示保留。
- 无模型调用，无文章修改，无生产部署。真实草稿摘要、改写的既有证据不重复制造。

证据保存在持久目录 `~/.codex/task-evidence/editor-release-closeout-20261111/`：
安装清单 `device-addon-install-inactive-fix-20261112.json`、
`device-inactive-checks-fixed-20261112.png`、`device-cloud-confirm-before-20261112.png`、
`device-return-connected-20261112.png`、`device-readiness-after-browser-20261112.json`。
截图与备份均在临时工作区外，不包含凭据或授权 code/state。

## 复核与限制

完整 release:verify、PHPStan、Composer 校验通过；目录刷新保留翻译映射。
回归覆盖 exact inactive、transport 优先级、已 verified 的实际授权表单、same-tab
行为和验证成功清除旧 transient。OCR 初次发现“按钮通往没有恢复入口的页面”及文案
不符，已改为实际授权表单；最终差异另行复核。

PC 截图确认单一页面标题、首屏工作区、一条原因/一个主动作、状态含文本、无横向
溢出。真实回调及加载反馈通过；其他宽度、控制台/网络全量、键盘矩阵与人工视觉
签收未测，不据此宣称通过。Cloud 重命名弹窗的焦点证据属于独立已验收修复。
本轮补齐的是连接确认按钮交互，不是新增权限、Cloud 生产发布或付费模型验收。

## 经验

恢复提示必须验证“按钮→目标页面→可执行动作→回写结果”，不能只断言存在链接。
只读配置检查、真实连接、模型生成和人工采纳分别记账；异常样本注入必须说明范围
并确认退出，保留当前有效结果，不恢复过期失败缓存。回退只还原备份插件文件，
不卸载、不抹除站点绑定和历史。
