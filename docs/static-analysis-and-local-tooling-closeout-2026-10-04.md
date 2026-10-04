# 静态分析门禁与本地工具可移植性收口

状态：Accepted（2026-10-04，PR #209 squash 合并）

适用范围：`npcink-cloud-addon` 的开发工具链（composer 脚本、CI workflow、
静态契约测试）、PHPStan 接入、本地 Local by Flywheel 环境解析、文档索引
与复杂度预算维护。

本文记录 2026-10-04 系统性体检的落地结论，以及后续必须遵守的工程规则。
界面与边界标准仍以 `docs/cloud-addon-boundary.md`、
`docs/cloud-addon-complexity-budget.md`、`docs/admin-surface-standard.md`
为准。

## 1. 体检结论

仓库整体健康：915 项契约测试、POT 新鲜度、WP.org 审核守卫、JS 检查全绿，
工作区干净。结构性缺口只有两类：

1. 静态检查仅有 `php -l`，2.3 万行安全敏感 PHP 缺少类型级分析。
2. composer.json 与多个 scripts 内嵌了本机专属默认值（Local 站点路径、
   特定 run id 的 MySQL socket、特定版本号目录的 lightning PHP），且这些
   默认值在主开发机上已经失效（run 目录更替、PHP 版本目录更新），
   属于"提交即腐烂"的反模式。

安全核心（凭证认证加密、SSRF 防护链）质量高，保持不动；仅补记 DNS
rebinding 为已接受残余风险。

## 2. 落地内容

- PHPStan level 5（wp-phpstan + WP 7.1 stubs）接入：
  `composer run stan` / `stan:baseline`；`composer.json` 以
  `config.platform.php=8.0.99` 锁定解析基线，保证 CI 的 PHP 8.0 矩阵腿
  可安装 dev 依赖；遗留 60 条进 `phpstan-baseline.neon` 作收敛起点。
  首轮即抓到两个真实缺陷（zh_CN 目录 5 个重复键、过期 `@param`），
  按"修复优先于 baseline"处置。
- 本地环境统一解析：`scripts/wp-cli-local.sh`（shell）、
  `scripts/local-env.php`（PHP）、`.mjs` 内解析助手共用一条链：
  环境变量 > gitignored 的 `scripts/.local-env` > 唯一候选自动发现 >
  引导式报错。composer.json 五条内嵌默认值的脚本全部改走该链。
- 复杂度棘轮：四个最宽类（runtime-client 4314 行、settings-page 3280、
  wp-ai-connector 2767、media-derivative-transport 2542）的行数上限
  固化进 `tests/static-contracts.php`；settings-page 拆分方向记录在
  复杂度预算文档。
- `docs/README.md` 分类索引（标准 / 契约 / 指南 / ADR / 历史），
  约定新增日期型文档同批入索引。
- DNS rebinding 残余风险记录进边界文档与 `host_resolves_publicly()`
  注释；`.gitignore` 显式忽略 `.pytest_cache/`、`.tmp/`；可选覆盖率
  快照 `composer run test:coverage`；AGENTS.md 本地验证段去重并补
  PHPStan 说明。

## 3. 工程规则

1. Advisory 门禁接入沿用 OCR 三层模式：本地命令 + CI workflow
   （`continue-on-error: true`，永不进 required checks）+ 评审发现的
   真缺陷修复而不是进 baseline。`phpstan.yml` 与 `ocr-review.yml`
   同一纪律。
2. `continue-on-error` 的 workflow 首次上线必须人工核对一次运行日志：
   本次 `shivammathur/setup-php` 拼写错误若未被发现，advisory 门禁会
   静默空转并永远显示绿色。
3. 任何"全树扫描"类契约（check:boundary 这类）必须在 rg 与 grep 两个
   分支显式排除 `vendor/`、`node_modules/`、`dist/`：本地 rg 尊重
   .gitignore 而 CI 的 grep 回退分支不尊重，dev 依赖落地 vendor 后该
   差异必然触发误报。此规则已固化在 `check:boundary` 的两个分支里。
4. 仓库不提交任何机器特定绝对路径（含 `$HOME` 拼接的特定 run id /
   特定版本目录）。需要本地差异时走 `.local-env`：格式为 KEY=VALUE，
   含空格的值必须加引号；三个语言（shell/PHP/Node）的解析器行为对齐：
   引号剥离、字面解析（不 eval，命令替换保持字面量）、CRLF 容忍、
   空但已设的环境变量视为未设。新增消费方必须复用同一解析链。
5. shipped PHP 文件的纯注释改动也会移动 POT 行号引用，提交前跑
   `composer run i18n:refresh` 并确认 `i18n:check` 绿。
6. 行数棘轮是上限不是目标；上调必须同步在
   `docs/cloud-addon-complexity-budget.md` 写明理由，优先支付而不是
   上调。
7. 日期型文档（closeout/retrospective/session notes）新增时必须在
   `docs/README.md` 索引登记；标准与 ADR 的效力高于历史文档。

## 4. 遗留事项

- settings-page 的 12 个请求 handler 拆分（按复杂度预算文档记录的
  方向，独立 PR，需按 admin surface 标准备浏览器证据）。
- `phpstan-baseline.neon` 60 条遗留逐步削减；削减到合适规模后再评估
  把 `composer run stan` 并入 `release:verify`。
- `composer run test:coverage` 在无 pcov/xdebug 的机器上只输出安装
  引导；本机（Homebrew phpdbg 无 xdebug 兼容覆盖 API）即如此，属预期。
