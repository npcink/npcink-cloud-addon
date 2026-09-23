# Cloud Addon 测试套件"空转回归"复盘（custom-option 清理测试）

状态：Accepted（2026-09-23）

适用范围：`tests/run.php` 共享进程测试运行器的沙箱隔离约定，以及由本轮
体检、根因定位、修复过程沉淀的测试套件规范。本文记录问题如何被发现、
根因是什么、如何修复、哪些方法可直接复用。

## 1. 背景与发现过程

2026-09-22 例行体检时，所有门禁全绿：`test:all` 827 条断言通过、CI 全绿、
发布清单与磁盘一致、版本三元组一致。唯一异常信号是契约测试输出里的一条
PHP Warning：

```
Warning: Constant NPCINK_CLOUD_ADDON_OPTION_NAME already defined in
tests/behavior-cleanup-custom-option.php on line 10
```

顺藤摸瓜的过程：

1. `tests/run.php` 尾部注释明确写着"该测试必须最后跑，因为常量不可重
   定义，任何更早的运行都会让后续设置/清理测试跑到自定义选项名下"——
   设计意图是对的，且被写进了注释。
2. 但共享进程测试序列的第一个文件 `behavior-performance-guards.php:29`
   `require_once` 了插件主文件，主文件（带守卫地）把常量固定为默认值。
3. 于是最后加载的 custom-option 测试里无守卫的 `define()` 静默失败，
   常量保持默认值：测试从未在自定义选项名
   `npcink_cloud_addon_custom_settings` 下播种和清理，断言"两个选项键都
   不存在"平凡成立。

结论：该回归自 performance-guards 引入主文件加载起就一直在"空转"，
"统一清理遵循自定义选项名"这条保护形同虚设；且失效完全静默——套件依然
全绿，只有一条不影响退出码的 Warning。

| 交付 | 内容 | PR |
| --- | --- | --- |
| 修复 | custom-option 测试改为子进程沙箱 + 响亮失败守卫 | #169 |
| 规范 | `local-test-guide.md` 增补进程全局钉住测试的沙箱规则 | 本 PR |
| 卫生 | 删除 `build/` 改名前旧产物并重建校验；清理全部已合并分支 | 不入库 |

## 2. 核心结论

1. **门禁全绿不等于没有问题。** 这条失效不产生任何失败，只产生一条不
   影响退出码的 Warning。巡检时必须看"非失败输出"（Warning / Notice /
   Deprecated），它们是最高价值的线索：本例是 827 条绿断言 + 1 条
   Warning = 1 个真实缺陷。
2. **注释里的 invariant 必须升级为机器断言。** run.php 的注释正确记录了
   设计意图，但没有任何检查强制它，后来者照样踩坏。修复在测试文件顶部
   加守卫：常量已定义即向 STDERR 输出 `[fail]` 并 `exit(1)`，任何人把它
   挪回共享进程都会立刻红。
3. **钉住进程级全局状态（常量、函数替换）的测试必须放子进程沙箱。**
   共享进程运行器里只要有别的测试加载主文件或替换全局函数，这类测试就
   必然冲突；子进程是唯一可靠的隔离方式。修复后本仓库共有三个此类沙箱
   （alt-text handoff、site-knowledge admin actions、cleanup custom-option）。
4. **测试文件里禁止无守卫的 `define()`。** 要么先 `defined()` 检查并
   响亮失败，要么放子进程。

## 3. 可复用的工程经验

### 3.1 体检清单（本轮实际执行并全部通过/定位问题的项）

- `git status --short --branch`、`git diff --check`；
- `composer run test:all` 完整输出（重点看 Warning，不只看退出码）；
- `composer run check:wporg`、`check:js`、`i18n:check`、`ai:i18n:audit`；
- `release-manifest.txt` 与磁盘文件对账（本轮 33 项全在）；
- 代码中的 `/v1/...` 端点与 AGENTS.md 运行时契约清单对账；
- 版本一致性：插件头 / 版本常量 / readme `Stable tag` 三元组；
- `gh run list` / `gh pr checks` 的完整 rollup；
- 遗留分支与 `build/` 产物盘点。

### 3.2 修复模式

- **子进程沙箱**：`escapeshellarg(PHP_BINARY)` 拼命令 + `passthru` +
  非零退出码即失败，复用仓库既有模式，不发明新机制。
- **守卫写法**：与 `maca_assert` 失败语义一致（STDERR `[fail]` +
  `exit(1)`），信息里写明"应该怎么跑"而不只是"错了"。
- **修好后必须证明修复真实生效**，三步证据缺一不可：
  1. 目标测试独立进程运行通过（退出码 0）；
  2. 模拟旧的错误包含路径，守卫触发并失败（退出码 1）；
  3. 全量套件重跑，Warning 消失且断言数量不降。
- **分阶段小步交付**：修复（阶段 1）→ 防再次静默失效（阶段 2）→ 本地
  卫生（阶段 3）→ 门禁与 PR 交付（阶段 4），每阶段独立验证后再进入下一
  阶段，问题单随时可回滚。

### 3.3 交付与卫生

- `build/` 里改名前的旧产物（`magick-ai-cloud-addon*`）删除后用
  `package:release` + `package:verify` 重建当前版本并核对 sha256；
  `build/` 不入 git，属本地卫生。
- 分支清理的安全顺序：`git worktree list` 确认没有其他 worktree 占用 →
  `gh pr list --state merged` 对账分支与已合并 PR → 批量删本地与远端。
  `pr:publish` 刻意不删分支是给多 worktree 场景留的余地，不是永久状态。
- github.com 443 间歇超时（api.github.com 可能同时可达）：等待 60–90 秒
  重试即可，本轮删远端分支时再次验证；不要绕道改推送通道。

## 4. 边界与克制（本轮自检通过项）

- 修复是纯测试基础设施改动：无运行时、公共契约、管理面、文案变更。
- `smoke:playground` 不适用（未触及 bootstrap、激活、公共连接器 API、
  默认凭据状态或兼容基线），理由已写入 PR #169 验证记录。
- `build/` 清理不产生 git 变更；未动 `includes/`、`assets/`、
  `languages/`。

## 5. 后续工作

- 无已知欠账。新增"钉住进程全局"的测试时，按
  `docs/local-test-guide.md` 的沙箱规则执行；本文件作为该规则的经验
  依据。
