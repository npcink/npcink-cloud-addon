# 遗留待办交接提示词(落盘 2026-08-12,原 2026-08-08)

## 背景

npcink-cloud-addon(插件仓库,当前 workspace,路径 /Users/muze/gitee/npcink-cloud-addon)与 npcink-ai-cloud(云端仓库,/Users/muze/gitee/npcink-ai-cloud)的跨仓库工作已闭环:站点容量语义重构 + 生产发布已上线 cloud.npc.ink。三篇文档已合并(addon master 已同步,PR #78/#80/#81;npcink master 含 #577-#586,之后另有 #82/#83 已合并)。

文档家族:
- 方法论文档:addon docs/development-experience-and-methodology-2026-08-08.md(怎么想)
- addon 复盘:addon docs/cloud-site-capacity-and-cross-repo-release-retrospective-2026-08-08.md
- Cloud 复盘:docs/cloud-site-capacity-production-release-retrospective-2026-08-08.md(时间数据 + 优化清单)
- 流程规则:npcink-ai-cloud docs/single-session-ai-workflow-standard-v1.md

两仓库开发流程:master 受保护,必须走 PR;用 npcink-ai-cloud 的 scripts/publish-pr.sh(或 addon 的 composer pr:publish)发布,自动请求 squash auto-merge;发布前全量测试,日常只跑改动相关测试(映射见 workflow standard)。

## 待办 1(最高优先,已过期):升级 frontend node 镜像并清理 CVE allowlist

- **状态(2026-08-12):3 个临时豁免已过 expires_on(2026-08-11),部署门禁已重新拦截;任何下次部署前必须先完成本待办。**
- 现状:frontend/Dockerfile 用 node:22-alpine@sha256:16e22a...(v22.23.1);allowlist(deploy/image-lock/cve-allowlist.json)曾含临时豁免 CVE-2026-58043/CVE-2026-56846/CVE-2026-56848。
- 步骤:
  1. 拉取 node:22-alpine 最新 digest(v22.23.2+,修复 HTTP/2 与 Permission Model CVE),更新 frontend/Dockerfile 的 FROM digest。
  2. 重建镜像并扫描(docker build + grype,或走 deploy-production.yml 门禁验证)。
  3. 确认 3 个 CVE 消失后从 allowlist 移除条目。
  4. 必须同步三层:tests/contract/test_container_image_supply_contract.py 的精确集合与 reason 模板(frontend_http2_reachability 等 dict)、scripts/check-first-install-cve-gate.py 的 governed 集合,然后跑 pytest tests/contract/。
- 验收:allowlist 无这 3 个 node CVE;契约测试与门禁脚本一致;master PR 合并后 production 重新部署(node 升级属发布,走 promote + deploy-production.yml,operator 确认)。

## 待办 2:deploy-production.yml 等待 production push CI

- 现状:"Require successful CI for this production commit" 步骤在 push CI 未完成时立即失败(本次第 2 次部署因此失败)。
- 目标:改为对 production head-SHA 的 push 事件 Cloud CI 做有界轮询(或等 status check)。
- 验收:master 小 PR;promote 后验证部署不再因 CI 时序失败。

## 待办 3(可选):CVE allowlist 变更 checklist 落地

把"allowlist → 契约测试精确集 → 门禁脚本 governed 集 → pytest tests/contract/"联动写成文档/脚本检查项,避免再花 ~50 分钟试错。

## 待办 4(独立操作,非代码):addon v0.1.4 WordPress.org 发布

npcink-cloud-addon build/npcink-cloud-addon.zip(v0.1.4,PCP 已过)。如要上架 WP.org,走 SVN 发布(参考 docs/wordpress-org-release-gate.md 与 wordpress-org-release-and-translation-log.md),独立 operator 动作。

## 环境须知

- npcink-ai-cloud 目录写权限在 addon 仓库 reasonix.toml 的 [sandbox] allow_write 已配置;新会话如报只读,检查配置是否生效,必要时重启会话。
- 服务器生产排查:SSH root@120.24.237.214,密钥 /Users/muze/gitee/数据/key/cloud-toolbox0801.pem;数据库在服务器 /opt/npcink-ai-cloud/shared/config/runtime-config.json(阿里云 RDS PostgreSQL);只读查询,勿改生产数据。
- 未跟踪遗留文件(0078、npcink-read-latest-evidence.py、production,位于 npcink-ai-cloud 根)非任务文件,勿提交。
