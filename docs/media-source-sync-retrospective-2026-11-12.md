# 媒体源同步验收与复盘 — 2026-11-12

本文件是日期化验收记录，不是生产状态探针。持续验证要求见
[证据规范](verification-evidence-standard.md#媒体源同步与恢复验收)，实现合同见
[Site Knowledge 投递](site-knowledge-full-index-delivery.md)。

## 目标与设计取舍

用户只在 WordPress 正常管理媒体库，Cloud 索引自动跟随，不要求运营人员手工
清理云端。WordPress 提供内容存在性与公开状态；Addon 负责有界签名投递；Cloud
执行索引、嵌入、删除并拥有索引生命周期。不能把遗漏一条 manifest、源站暂时
打不开或扫描失败当成删除依据。

沿用既有缓冲、投递 hook 和每小时核对，不新增控制台、队列或调度服务。正常行为
保持安静，失败才显示已有错误与恢复信息。外部定时触发只是低访问量站点的可选
运维保障；客户无需部署另一套 Cloud 服务。本机 LaunchAgent 只用于开发。

## 缺陷、修复和证据

| 问题 | 已合并修复 | 验证范围 |
| --- | --- | --- |
| 删除附件无通知；只扫现存内容发现不了消失的附件 | [#347](https://github.com/npcink/npcink-cloud-addon/pull/347)，配合 Cloud [#1400](https://github.com/npcink/npcink-ai-cloud/pull/1400) | M4 清理 69 条失效 media，保留两条有效 media、非媒体数据和 26 个本地附件 |
| 历史任务退出未核实，原清理停滞 | [#348](https://github.com/npcink/npcink-cloud-addon/pull/348) | 原 intent 完成；保留三条历史未核实退出及 legacy_unknown，后续按小时修复迟到残留，不伪造退出证据 |
| 删除 ALT／替换附件文件遗漏变化 | [#351](https://github.com/npcink/npcink-cloud-addon/pull/351) | 本机修改钩子与真实 MySQL 验证；不覆盖无 WordPress 事件的任意文件写入 |
| 无变化的核对页不刷新核对时间 | [#352](https://github.com/npcink/npcink-cloud-addon/pull/352) | 核对状态回归通过，核对时间与实际索引更新分开 |
| Cloud 无法读取本机源地址 | [#353](https://github.com/npcink/npcink-cloud-addon/pull/353) | 复用有界临时源上传；本地新增、更新、替换文件、断线、删除补偿通过 |
| 关闭投递清除 flush，已排队撤回无法继续 | [#354](https://github.com/npcink/npcink-cloud-addon/pull/354) | 测试站真实服务器 Cron 验证关闭后继续撤回，暂停的恢复入库不阻塞清理 |
| 普通通知失败三次即丢弃，已删除评论无法被扫描找回 | [#355](https://github.com/npcink/npcink-cloud-addon/pull/355) | 三次失败保留、每小时冷却、提前回调不发送、恢复补投与评论撤回身份保留 |

## 分层验收与清理

- 源码／CI：上述 PR 均合并，最终检查通过。#355 审查提交
  `1510743ec64c7447eafd4781acc07940bbaabf38` 与 squash 合入
  `7894d6ff9279202c6f35f1cff61d8cef37646861` 的树一致。
- 本地：旧代码的丢通知回归先失败，修复后完整测试、PHPStan、release:verify、
  严格发布包 Plugin Check 通过。真实 Local MySQL 评论删除测试保留修订、投递身份，
  关闭／重开遵守冷却；原选项、Cron 和内容恢复。错误和 HTTP 接受均为注入。
- 在线第一批：测试 WordPress 连接既有生产 Cloud，新增、修改、漏通知补偿、关闭
  投递后删除、有效项不误删通过。三张图片最终全部删除，Cloud 精确文档／块为空，
  撤回逐项 completed。首次核对提前排期且扫描限于附件，不称为全站自然小时验收。
- 在线恢复批：一张新临时图片，三次错误在本地传输入口注入；持久化截止时间正好
  3600 秒，提前的真实 Cron 回调未发送；仅把该测试任务到期时间提前后，真实服务器
  Cron 沿用相同去重键补投。运行 `run_bc05934421c647408d1502361bce513d` 成功，
  结果确认预期文档全部入库，Cloud 图片指纹与 WordPress 一致，缓冲清空。
- 恢复批收尾：删除图片后，五轮真实 Cron 完成原撤回任务；精确读取为零文档／块。
  15 项场景断言全部通过。图片文件／行、临时服务器 Cron、MU 保护代码、标记及
  注入选项均移除；原文章／页面／附件校验一致，绑定保留，投递许可恢复原值 false。
- 安装：本机插件主目录及线上测试 WordPress 加载了已验证代码。#355 ZIP SHA256
  `e2c9172aac9903aab2a4255209c0d82a503a1fadbabe6dae860eb7c9584af656`；
  桥接文件 SHA256 `72b9d071a31289fbe11b7a472f8f9ce04774a089cd7650151eebb6b34b1fe775`。
  不是正式客户站点发版，也没有由此授予后续生产操作权限。
- M4／生产：M4 历史清理有独立证据；最新恢复批没有同步 M4 或部署生产 Cloud，
  只通过已有生产 API 验证。机器验收不代替人工产品验收。

## 排障中纠正的判断

早期一次低流量测试确实触发 Cron，但投递因超时失败；HTTP 200 和容器存活都不能
证明任务成功。之后生产主机出现高 I/O 等待，用户自行重启后恢复。已授权的两次
MGLRU 对照最终恢复原值；十分钟窗口前基线已经正常，窗口内未复现，因此没有
证据把 MGLRU 判为根因或永久关闭它。该生产故障调查仍由独立运维工作继续，不能
随媒体链路通过一起关闭，也不得因此重复主机实验。

测试脚本的三个具体陷阱：FPM open_basedir 无法读 root 私有标记，运行时身份文件
应置于站点内可读的 PHP-return 文件；Linux crontab 的 `%` 需由外部脚本承载；
wp-cron.php 可能先结束 HTTP 再执行回调，必须读回调和持久状态。

## 保留限制与触发条件

- 附件数据库记录仍在但文件丢失／URL 暂时不可达，不自动撤索引；任意直接覆盖文件
  字节且没有 WordPress 事件的检测仍未交付。出现真实需求再限定方案。
- 现有 500-ID／200-comment、字节与持久化容量限制不变；不能找回旧版本已经丢弃的
  通知。HTTP 接受后的 Cloud 异步失败恢复不是 #355 的交付范围。
- 新上传和扫描需许可与有效连接；关闭投递暂停它们，但既有必要撤回继续。普通恢复
  依赖 WP-Cron 实际执行。本次临时服务器 Cron 已撤，不能称为永久低流量保障。
- 第三方识图／文本模型质量、正式客户站点发布、长时间自然运行及历史未索引的
  24 个本地附件入库没有被本次验收覆盖。不因留有待办重新扩展功能。

## 证据位置

原始机器回执保留在开发机 `.codex/task-evidence/media-auto-loop-20261112/`，
恢复批在其 `delivery-recovery/`：`scenario-assertions.json`、
`online-evidence-bundle.json`、`recovery-local-mysql.json`、CI、审查及包回执。
此前故障／MGLRU 记录在 `media-auto-loop-20261111/`。历史 M4 清理见 Toolbox
[验收台账](https://github.com/npcink/npcink-workflow-toolbox/blob/master/docs/maintenance-acceptance-2026-11-10.md)。
原始证据不是插件发行文件；凭据、主机地址和原始配置不纳入 Git。
