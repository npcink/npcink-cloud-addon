# 2026-10-13 当前交接：0.4.1 候选与跨仓收口

状态：Addon 0.4.1 候选已经合并到 `master@9ae7e6a`；WordPress.org SVN
发布和跨仓质量矩阵仍未执行。

## 当前已确认事实

- 工作树干净，`master` 与 `origin/master` 一致。
- 版本头、`NPCINK_CLOUD_ADDON_VERSION`、`readme.txt` 和本地语言目录均为
  `0.4.1`。
- 候选包 SHA256 为
  `e0c885f6a50a55ba73d8931c43c4b5862e0b13055ac29ed49030ee29fb2cfdbf`。
- 0.4.1 尚无 Git tag，也没有提交 WordPress.org SVN；WordPress.org 当前
  稳定版本仍为 0.4.0。
- 候选代码门禁、CI、Release static gates、PHP 版本矩阵和 WordPress AI
  兼容性矩阵均已通过。OCR advisory 曾因额度不足返回 429；本次正在补审
  0.4.1 实际代码差异，不以空的文档审查代替代码审查。
- GlotPress 2026-10-12 的 stable/dev 插件和 readme 中文集合均已显示完整，
  但 0.4.1 发布后的语言包生成和目录缓存传播尚未验证。
- 本地 wp-admin 的中文概览、Site Knowledge 和连接管理页面已检查。本次
  `127.0.0.1:18010` 已恢复，后端 `e6e2a07f4870`、源码干净；连接成功反馈和
  无效 URL 失败反馈均在刷新后一次性消失。当前权益缓存 fresh，但 Site
  Knowledge 仍有 11 条放弃、782 条待处理，监控仍有 200 条待发，尚未声称恢复。
- 本地站点级语言文件日期为 2026-07-02，缺少两条手动备用说明；打包 PO 已有
  中文翻译。发布后应验证官方语言包更新，不能把此处英文归因于缺少源码翻译。

## 下一步顺序

1. 在发布前重新运行恢复后的 OCR advisory，并记录结果。
2. 获得单独的 WordPress.org 发布确认后，用临时 SVN 工作副本上传 trunk 和
   `tags/0.4.1`，核对 API、下载包、稳定版本、中文语言包和目录缓存。
3. 待所有参与仓库的工作树进入干净窗口并获得用户确认后，从
   `/Users/muze/gitee/npcink-workflow-toolbox` 运行
   `composer quality:matrix:run`。
4. Site Knowledge 真失败继续优先于 stale 标签；单独的 stale 浏览器案例尚未
   独立呈现，不得为了截图清除真实失败。Cloud 索引恢复可能涉及 Provider
   工作，应按预算另行确认；可选 vision、image 和评论验收继续延期。
5. 发布之外独立处理 Dependabot #260、#261、#262，并核对每个 PR 的完整
   检查结果；不把依赖升级混进已冻结的 0.4.1 包。

矩阵只读检查（2026-10-13T01:58:33Z）：Toolbox 已干净，但 Toolkit 仍有
未跟踪交接文档，Core 有一份已修改文档，Adapter 在
`codex/0-4-2-limit-validation` 有九个已修改文件。Addon、Eval Lab 和 Cloud
工作树干净。各仓库状态在变化，执行矩阵前必须重新读取分支、SHA 和状态；
本次没有运行跨仓 gates，也没有修改这些仓库。

## 暂不处理

- 不删除或提交 Toolkit、Adapter 的未跟踪交接文档。
- 不在 Addon 内实现图片续传/部分成功或 capability stale-while-revalidate；
  这些事项由 Cloud 或契约仓库负责。
- B 类 AJAX 保存、凭证吊销自动降级、图片参数提示和上游 AI 插件 shim
  贡献不进入 0.4.1 发布动作。

## 发布后回滚点

若 0.4.1 出现问题，使用后续补丁版本修复；不删除已发布 SVN 版本。候选阶段
仍可按 PR #267 粒度回滚，无需数据迁移。
