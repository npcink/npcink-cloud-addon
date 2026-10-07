# AI 单人开发体检与收口

状态：Accepted（2026-10-07）

适用范围：`npcink-cloud-addon` 在"单人 + AI 开发"模式下的验证体系、
基线策略与工程卫生。本文记录 2026-10-07 项目体检的发现、处置、经验，
作为该模式下的方法论记录；拆分清偿阶段的记录见
`docs/mechanical-split-playbook-2026-10-06.md`。

## 1. 体检结论

结构债已清（#220–#230 收官），问题集中在验证体系的两个盲区、AI 自我
复核回路的固有风险和工程卫生杂项。五项处置全部当日落地：

| 处置 | 落点 | PR |
| --- | --- | --- |
| 测试运行器 fail-on-warning 门禁 | `tests/helpers.php` 安装错误处理器，任何测试进程出现未预期 `E_WARNING/E_NOTICE/E_DEPRECATED`（含 `E_USER_*`）即 `[fail]` 退出 1；`@` 抑制保持 PHP 语义；`error_reporting(E_ALL)` 钉死防止宿主 ini 解除武装 | #231 |
| git 身份统一 | 仓库级固定 `Npcink <36845206+npcink@users.noreply.github.com>`（历史 4 个身份不重写） | 本地配置 |
| 验证证据标准 | `docs/verification-evidence-standard.md`：变更类型→证据层矩阵、30 天/覆盖面变更时效、每次发布至少一条非 AI 证据锚点 | #232 |
| 基线机会主义规则 | complexity budget：每年 10 月复审 PHP 下限（wp.org 份额 <5% 才升）；PHPStan 新发现优先在可复现最高 level 修复 | #232 |
| 拆分程序正式关闭 | ratchet 留作天花板；后续拆分只由真实变更驱动 | #232 |

同时清偿 #227/#228 遗留的四个顾问审查 low 发现（#233）：交付头校验的
`strtotime` 统一为 `strict_timestamp`、非字符串字节源显式拒绝（不再误报
"文件为空"）、`auto_safe.v1` 单源化为 governance 公开常量、`.po` 行号
引用经 `i18n:refresh` 与拆分后源码布局对齐。

## 2. 经验

1. **门禁全绿不等于没有问题，且"人工看 Warning"必须升级为机器断言。**
   2026-09-23 空转复盘发现的缺陷类别（绿套件 + 一条不影响退出码的
   Warning），此前只修复了单个实例；#231 把"非失败输出即失败"升级为
   运行器级不变量后，当日下午即拦下一个真实失误：invalid-source 检查
   的第一版放在空判断之前，空描述符会触发未定义索引告警——正是新门禁
   要防的模式，发布前修正。规范见
   `docs/local-test-guide.md` 的 fail-on-diagnostics 一节。
2. **单人 + AI 的风险集中在 AI 无法自证的那几层。** 确定性层（契约、
   边界、lint、playground）自动化越全，残余风险越向手动层（Local
   MySQL/Cloud、浏览器、acceptance、生产观测）集中。对冲方式不是更多
   自动化，而是把"哪类变更欠哪层证据、证据何时过期"写成显式矩阵，
   并保留发布级非 AI 锚点，防止全链路模型自证。
3. **顾问审查（AI 审 AI）有真实收益，要保留但永不设为必需检查。**
   本轮 ocr 在 #231 抓到子进程 ini 继承会解除守卫武装（已修），
   #233 零发现。与 #224→#226（medium）一致证明其价值；同时其对纯
   文档变更按路径过滤跳过，advisory 定位准确。
4. **工程卫生的小事值得当场清：** git 身份碎片化（一人 4 身份）拆开
   了贡献归属；已合并 dependabot 分支残留远端。两者清理成本均为分钟级。
5. **清偿完成后停止"为拆而拆"。** 剩余记录项（settings-page 披露合并
   约 40–70 行、三组跨文件重复、phpstan 基线）全部维持机会主义处置；
   新投资由真实变更驱动。

## 3. 已知非阻断事项

- `make-pot` 对 site-knowledge admin actions 的一条带占位符字符串提示
  缺少 `translators:` 注释——仅顾问输出，从不导致门禁失败；下次触碰
  该字符串时顺手补注释并刷新 i18n 链即可。
- 手动证据层（Tier C：Local MySQL/Cloud 冒烟、浏览器 acceptance）按
  `docs/verification-evidence-standard.md` 的时效规则在下一次发布门前
  补做并记录日期；#233 的 PR 正文已记录该项欠账。
