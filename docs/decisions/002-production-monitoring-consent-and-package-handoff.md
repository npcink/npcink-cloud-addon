# ADR-002: 在连接验证完成后请求正式用户 monitoring 授权

## 状态

Accepted

## 日期

2026-08-19

## 背景

Addon 面向正式站点连接 Cloud。正式使用者不是测试用户，不能要求他们填写批次或
手动寻找监控设置。与此同时，匿名诊断数据需要明确的站点管理员同意，且必须继续
由 WordPress 本地拥有授权和 monitoring 状态。

## 决策

Cloud Portal 授权完成且连接验证成功后，Addon 在 Overview/Permissions 流程中显示
一次原生确认框：`Allow anonymous diagnostics` 或 `Not now`。

允许按钮复用既有 `admin_post_npcink_cloud_addon_update_local_permission`，拒绝按钮
只清除一次性提示，不启用 monitoring。确认框不触发 Cloud HTTP、不新建采集路径，
管理员仍可在现有权限设置中修改选择。

## 边界

- 正式用户零操作；
- `cohort` 不是 Addon 的普通生产身份字段；
- 不新增站点注册表、队列、数据库表或 Cloud API；
- 观测仍为已验证、明确授权、metadata-only 的 bounded delivery buffer；
- 不记录 prompt、内容、生成文本、用户身份、凭据或原始 payload。

## 验证与交付

- behavior-settings-page-contract 通过；
- Composer 全量 Addon 测试、i18n、release verify 通过；
- WordPress Playground 激活冒烟通过；
- 精确安装包位于 `build/npcink-cloud-addon.zip`，由 `release-manifest.txt` 控制内容；
- 本次只生成候选包，不创建 Tag、GitHub Release 或生产部署。

## 复用规则

以后若需要新增授权项，应优先在“连接验证完成”上下文中确认，只有在授权生命周期、
数据用途或撤销行为明显不同且有独立证据时，才增加新的设置入口或字段。
