<?php
/**
 * Bounded fallback localization for this addon admin UI.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Addon_Localization' ) ) {
	/**
	 * Provides a narrow zh_CN fallback for fixed addon strings when site language packs lag.
	 */
	final class Npcink_Cloud_Addon_Localization {
		private const TEXT_DOMAIN = 'npcink-cloud-addon';

		/**
		 * Registers addon-owned localization hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_filter( 'gettext', array( __CLASS__, 'filter_gettext' ), 20, 3 );
			add_filter( 'ngettext', array( __CLASS__, 'filter_ngettext' ), 20, 5 );
		}

		/**
		 * Translates selected plural addon strings when the language pack lags.
		 *
		 * @param string $translation Existing plural translation.
		 * @param string $single Singular source string.
		 * @param string $plural Plural source string.
		 * @param int    $number Number used for plural selection.
		 * @param string $domain Text domain.
		 * @return string
		 */
		public static function filter_ngettext( string $translation, string $single, string $plural, int $number, string $domain ): string {
			if ( self::TEXT_DOMAIN !== $domain || ! self::should_localize() ) {
				return $translation;
			}
			$source = 1 === $number ? $single : $plural;
			if ( '' !== $translation && $translation !== $source ) {
				return $translation;
			}

			$translations = self::translations();

			return $translations[ $source ] ?? $translation;
		}

		/**
		 * Translates selected addon strings only when no upstream zh_CN translation exists.
		 *
		 * @param string $translation Existing translation.
		 * @param string $text Original English string.
		 * @param string $domain Text domain.
		 * @return string
		 */
		public static function filter_gettext( string $translation, string $text, string $domain ): string {
			if ( self::TEXT_DOMAIN !== $domain || ! self::should_localize() ) {
				return $translation;
			}

			if ( '' !== $translation && $translation !== $text ) {
				return $translation;
			}

			$translations = self::translations();

			return $translations[ $text ] ?? $translation;
		}

		/**
		 * Returns whether the fallback translations should run.
		 *
		 * @return bool
		 */
		public static function should_localize(): bool {
			if ( function_exists( 'is_admin' ) && ! is_admin() ) {
				return false;
			}

			$locale = '';
			if ( function_exists( 'determine_locale' ) ) {
				$locale = (string) determine_locale();
			} elseif ( function_exists( 'get_user_locale' ) ) {
				$locale = (string) get_user_locale();
			} elseif ( function_exists( 'get_locale' ) ) {
				$locale = (string) get_locale();
			}

			if ( '' === $locale ) {
				return false;
			}

			return 0 === strpos( str_replace( '-', '_', strtolower( $locale ) ), 'zh_' );
		}

		/**
		 * Returns fixed addon admin translation fallbacks.
		 *
		 * @return array<string,string>
		 */
		public static function translations(): array {
			return array(
				'Site media recognition' => '站内媒体识别',
				'Site media recognition status' => '站内媒体识别状态',
				'Site media recognition details' => '站内媒体识别详情',
				'View recognition details' => '查看识别详情',
				'Images' => '图片',
				'Plan image capacity' => '套餐图片容量',
				'%1$d of %2$d' => '%1$d / %2$d',
				'Recognize local images so the editor can find them by meaning. Existing media and WordPress content are not changed.' => '识别站内图片，便于编辑器按含义搜索。不会修改已有媒体或 WordPress 内容。',
				'Recognized: %1$d images; visual evidence: %2$d.' => '已识别：%1$d 张图片；视觉证据：%2$d 条。',
				'Processed %1$d of %2$d images in %3$s seconds (average %4$d images/minute).' => '已处理 %2$d 张中的 %1$d 张，用时 %3$s 秒（平均每分钟 %4$d 张）。',
				'%s images/minute' => '每分钟 %s 张',
				'images/minute' => '张图片/分钟',
				'Start media recognition' => '开始识别媒体图片',
				'Continue recognizing remaining images' => '继续识别剩余图片',
				'Retry this batch' => '重试本批',
				'Check for new images' => '检查新增图片',
				'Your plan image capacity is full. Existing recognized images can still be refreshed, but new images require available capacity.' => '当前套餐的图片容量已满。已识别图片仍可刷新，但识别新图片需要先释放或增加容量。',
				'Your plan image capacity is full. Increase the media image limit or remove no-longer-needed Cloud media evidence before continuing.' => '当前套餐的图片容量已满。请提高媒体图片上限，或清理 Cloud 中不再需要的媒体证据后继续。',
				'images' => '张图片',
				'%1$d / %2$d images' => '%1$d / %2$d 张图片',
				'Site media recognition completion' => '站内媒体识别完成度',
				'Progress' => '进度',
				'Visual evidence' => '视觉证据',
				'Succeeded / failed' => '成功 / 失败',
				'Current speed' => '当前速度',
				'Estimated batch completion' => '预计本批完成时间',
				'This batch is complete' => '本批已完成',
				'Waiting for background processing' => '等待后台执行',
				'Recognition will continue automatically during the next eligible processing window.' => '将在下一个可执行时段自动继续。',
				'An earlier batch is complete, but automatic continuation has not been started. Click Continue once to start the background plan; no further clicks are needed after that.' => '之前的批次已完成，但后台续批尚未启动。点击一次“继续识别”即可启动，之后无需再次点击。',
				'Background image recognition requires an attachment URL, media fingerprint, and image MIME type.' => '后台图片识别需要附件链接、媒体指纹和图片 MIME 类型。',
				'All images have been recognized.' => '全部图片已完成识别。',
				'More images remain. Background recognition will continue automatically; no further click is needed.' => '仍有图片未处理，后台会自动继续识别，无需再次点击。',
				'WordPress scheduled tasks are disabled on this site, so automatic continuation requires a server cron job that runs wp-cron.php.' => '此站点已禁用 WordPress 定时任务，自动续批需要配置服务器定时任务执行 wp-cron.php。',
				'%d images remain.' => '还剩 %d 张图片。',
				'Runtime limits' => '运行限制',
				'Not available yet' => '暂不可用',
				'Not started' => '尚未开始',
				'Recognizing images' => '正在识别图片',
				'Completed' => '已完成',
				'Partially completed' => '部分完成',
				'Recognition incomplete' => '识别未完成',
				'Processing' => '处理中',
				'Estimating' => '正在估算',
				'Activate this site in Cloud' => '在 Cloud 中激活此站点',
				'Check activation again' => '重新检查激活状态',
				'The site is connected, but Cloud service is not active yet. Activate this site in Npcink Cloud, then check activation again here.' => '站点已连接，但 Cloud 运行服务尚未激活。请先在 Npcink Cloud 中激活此站点，再返回这里重新检查。',
				'Cloud verification completed, but the activation state could not be stored securely.' => 'Cloud 检查已完成，但无法安全保存站点激活状态。',
				'Connected, activation required' => '已连接，等待激活',
				'This site is bound and its connection credential is stored, but Cloud runtime service is inactive. Activate it in Npcink Cloud, then verify the connection here.' => '此站点已绑定并保存连接凭据，但 Cloud 运行服务尚未激活。请先在 Npcink Cloud 中激活，再回到这里验证连接。',
				'Cloud connection completed. This site is bound but not active because no active-site slot was available. Activate it in Npcink Cloud, then verify the connection here.' => 'Cloud 连接已完成。由于没有可用的活动站点名额，此站点已绑定但尚未激活。请先在 Npcink Cloud 中激活，再回到这里验证连接。',
				'Enter a Cloud Base URL before starting self-hosted authorization.' => '请输入 Cloud Base URL 后再开始自托管授权。',
				'Connection context' => '连接上下文',
				'Advanced connection' => '高级连接',
				'Self-hosted Cloud endpoint' => '自托管 Cloud 端点',
				'Authorize with this endpoint' => '使用此端点授权',
				'For compatible Npcink Cloud deployments only. Cloud still owns site activation and key issuance.' => '仅用于兼容的 Npcink Cloud 部署。Cloud 仍负责站点激活和密钥签发。',
				'This does not manage Cloud sites, keys, billing, models, router, workflows, or runtime policy.' => '这里不管理 Cloud 站点、密钥、账单、模型、路由器、工作流或运行时策略。',
				'Features' => '功能',
				'Privacy settings' => '隐私设置',
				'Overview' => '概览',
				'Advanced and troubleshooting' => '高级与排查',
				'Plan and entitlement' => '套餐与权益',
				'Loading plan and entitlement…' => '正在获取套餐与权益…',
				'Plan and entitlement are temporarily unavailable.' => '暂时无法获取套餐与权益。',
				'Plan and entitlement are already being refreshed.' => '正在刷新套餐与权益。',
				'Verify the Cloud connection before reading plan and entitlement.' => '请先验证 Cloud 连接，再读取套餐与权益。',
				'You do not have permission to refresh Cloud entitlement.' => '您没有权限刷新 Cloud 权益。',
				'Update failed' => '更新失败',
				'Retry' => '重试',
				'Available' => '可用',
				'Free plan' => '免费版',
				'Pro plan' => '专业版',
				'Available AI credits' => '可用 AI 积分',
				'Remaining AI credits percentage' => '剩余 AI 积分占比',
				'Runtime allowance' => '运行额度',
				'%d%% remaining' => '剩余 %d%%',
				'%1$s / %2$s · %3$d%% remaining' => '%1$s / %2$s · 剩余 %3$d%%',
				'Used %1$s AI credits; remaining %2$s AI credits; limit %3$s AI credits.' => '已用 %1$s AI 积分；剩余 %2$s AI 积分；上限 %3$s AI 积分。',
				'AI credits shown here belong to the connected Cloud account. Disconnecting, removing, or changing this WordPress site does not transfer those AI credits.' => '此处显示的 AI 积分属于当前连接的 Cloud 账户。断开、移除或更换此 WordPress 站点不会转移这些 AI 积分。',
				'Free service and AI credits belong to the Cloud account selected during authorization, not this site. The same account may reconnect at any time; changing to another account is subject to the removal and cooldown requirements shown by Cloud.' => '免费服务和 AI 积分属于授权时选择的 Cloud 账户，不属于此站点。同一账户可随时重新连接；更换到其他账户时，须遵守 Cloud 显示的站点移除和冷却要求。',
				'%s remaining' => '剩余 %s',
				'%1$d of %2$d runs remaining' => '剩余 %1$d / %2$d 次',
				'Entitlement details' => '权益详情',
				'Renews' => '权益续期',
				'AI credit period' => 'AI 积分周期',
				'View AI credit details in Cloud' => '在 Cloud 中查看 AI 积分详情',
				'%1$s to %2$s' => '%1$s 至 %2$s',
				'Run limit' => '运行次数上限',
				'Token limit' => 'Token 上限',
				'Site limit' => '站点数上限',
				'Active run limit' => '并发运行上限',
				'Batch item limit' => '批量项目上限',
				'Cost limit' => '费用上限',
				'Execution tiers' => '执行层级',
				'No additional entitlement parameters were returned by Cloud.' => 'Cloud 未返回更多权益参数。',
				'Cloud reported the hosted runtime entitlement.' => 'Cloud 已返回托管运行时权益。',
				'Monitoring needs attention' => '监控需要处理',
				'Site Knowledge needs attention' => '站点知识库需要处理',
				'Service details' => '服务详情',
				'Connection management' => '连接管理',
				'Connection and service' => '连接与服务',
				'Connected' => '已连接',
				'Open Cloud' => '打开 Cloud',
				'Check connection' => '检查连接',
				'Change Cloud account' => '更换 Cloud 账号',
				'Change the connected Cloud account in Cloud.' => '如需更换当前连接的 Cloud 账号，请前往 Cloud 操作。',
				'Disconnect this site' => '断开此站点',
				'Cloud features will stop on this WordPress site and its local connection data will be cleared. The site and its data will remain in Cloud.' => '断开后，此 WordPress 站点将停止使用 Cloud 功能，并清除本地连接信息。Cloud 中的站点和数据会保留。',
				'Disconnect this WordPress site? Cloud features will stop here and local connection data will be cleared. The site and its data will remain in Cloud.' => '确定断开此 WordPress 站点吗？本站将停止使用 Cloud 功能，并清除本地连接信息。Cloud 中的站点和数据会保留。',
				'The latest Cloud summary is not available yet. Check the connection to try again.' => '暂时无法获取最新的 Cloud 摘要。请检查连接后重试。',
				'Status' => '状态',
				'Site Knowledge' => '站点知识库',
				'Troubleshooting' => '排查',
				'Connection Management' => '连接管理',
				'Submitted' => '已提交',
				'Queued' => '排队中',
				'Pending' => '等待中',
				'Running' => '运行中',
				'Processing' => '处理中',
				'Completed' => '已完成',
				'Succeeded' => '已成功',
				'Failed' => '失败',
				'Error' => '错误',
				'Canceled' => '已取消',
				'Ready' => '就绪',
				'Not ready' => '未就绪',
				'WordPress AI connector' => 'WordPress AI 连接器',
				'Allow WordPress AI to use Npcink Cloud.' => '允许 WordPress AI 使用 Npcink Cloud。',
				'Allow WordPress AI to use Npcink Cloud. Enabled by default after connection; turn it off in Overview when needed.' => '允许 WordPress AI 使用 Npcink Cloud。连接成功后默认启用，需要时可在“概览”中关闭。',
				'Site Knowledge delivery' => '站点知识库投递',
				'Enable Site Knowledge' => '启用站点知识库',
				'Keep public posts and pages updated automatically so AI can reference them.' => '自动更新公开文章和页面，供 AI 在生成内容时参考。',
				'AI knowledge base' => 'AI 知识库',
				'AI uses your public posts and pages. This does not change WordPress content or affect search engine settings.' => 'AI 会使用您网站的公开文章和页面。这不会修改 WordPress 内容，也不会影响搜索引擎设置。',
				'AI can reference your public posts and pages. WordPress content and search engine settings are not changed.' => 'AI 可以参考您网站的公开文章和页面，不会修改 WordPress 内容或搜索引擎设置。',
				'Automatic updates on' => '自动更新已开启',
				'Automatic updates off' => '自动更新已关闭',
				'Change settings' => '修改设置',
				'More actions' => '更多操作',
				'Knowledge base maintenance' => '知识库维护',
				'Knowledge base status' => '知识库状态',
				'Knowledge base update needs attention' => '知识库更新暂时出现问题',
				'The system will keep trying automatically.' => '系统会继续自动尝试。',
				'Automatic updates are delayed. Check the site scheduler in advanced troubleshooting.' => '自动更新已延迟，请在高级排查中检查站点计划任务。',
				'Some content is outside the knowledge base limit' => '部分内容超出知识库容量限制',
				'%d public items were not included. Review the plan details in Cloud.' => '%d 项公开内容未被收录，请在 Cloud 中查看套餐详情。',
				'Updating the knowledge base' => '正在更新知识库',
				'Updates waiting: %d' => '待处理更新：%d',
				'All public content is up to date' => '所有公开内容均已更新',
				'Automatic updates are ready' => '自动更新已就绪',
				'Updates will appear here after public content changes.' => '公开内容发生变化后，更新情况会显示在这里。',
				'Last updated: %s' => '最近更新：%s',
				'Manual update' => '手动更新',
				'Update again' => '重新更新',
				'View advanced troubleshooting' => '查看高级排查',
				'Advanced knowledge base settings' => '高级知识库设置',
				'View Cloud details' => '查看 Cloud 详情',
				'Knowledge base details' => '知识库详情',
				'Pending content (%d)' => '待处理内容（%d）',
				'Showing the first 50 pending items.' => '仅显示前 50 项待处理内容。',
				'Knowledge base content summary' => '知识库内容摘要',
				'items updated' => '项已更新',
				'items waiting' => '项待处理',
				'Details will appear after the first knowledge base update is completed.' => '首次完成知识库更新后，这里会显示详情。',
				'View content list' => '查看内容列表',
				'Waiting' => '待处理',
				'Completed' => '已完成',
				'Technical details' => '技术详情',
				'Send public content changes to Cloud Site Knowledge.' => '将公开内容变更发送到 Cloud 站点知识库。',
				'Available knowledge documents' => '可用文章数',
				'Loading Site Knowledge usage…' => '正在获取知识库用量…',
				'Site Knowledge usage is temporarily unavailable.' => '暂时无法获取知识库用量。',
				'You do not have permission to refresh Site Knowledge usage.' => '您没有权限刷新知识库用量。',
				'Enable Site Knowledge delivery before reading Cloud index usage.' => '请先开启站点知识库投递，再读取 Cloud 索引用量。',
				'Remaining knowledge document percentage' => '知识库文章剩余占比',
				'%1$s / %2$s · %3$d%% used' => '%1$s / %2$s · 已用 %3$d%%',
				'Indexed %1$s documents; remaining %2$s documents; limit %3$s documents.' => '已索引 %1$s 篇；剩余 %2$s 篇；上限 %3$s 篇。',
				'%d public changes awaiting delivery' => '%d 条公开变更待投递',
				'%1$s, %2$d public changes buffered' => '%1$s，%2$d 条公开变更已缓冲',
				'idle' => '空闲',
				'not configured' => '未配置',
				'unverified' => '未验证',
				'disabled' => '已关闭',
				'error' => '错误',
				'pending' => '等待中',
				'queued' => '待投递',
				'ok' => '正常',
				'Indexed chunks' => '已索引分块',
				'Per-sync limit' => '单次同步上限',
				'%1$s documents / %2$s chunks' => '%1$s 篇文章 / %2$s 个分块',
				'Truncated documents' => '被截断的文章',
				'Skipped documents' => '已跳过的文章',
				'%1$s skipped / %2$s due to quota' => '跳过 %1$s 篇 / 其中 %2$s 篇因配额限制',
				'Last Cloud sync' => '上次 Cloud 同步',
				'Reference site content during generation' => '生成时参考站点内容',
				'Use indexed public articles as generation context.' => '使用已索引的公开文章作为生成上下文。',
				'Allow Npcink Cloud to reference indexed public articles when generating titles and summaries so suggestions better match this site\'s writing style. WordPress content is not changed.' => '允许 Npcink Cloud 在生成标题和摘要时参考已索引的公开文章，使建议更贴近本站写作风格。不会更改 WordPress 内容。',
				'AI generation reference' => 'AI 生成参考',
				'enabled for supported editor tasks' => '已为支持的编辑器任务启用',
				'The AI task contract does not match the requested task.' => 'AI 任务契约与请求的任务不匹配。',
				'Usage and error diagnostics' => '使用与故障诊断',
				'Send anonymous diagnostics' => '发送匿名诊断信息',
				'Send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.' => '发送仅包含元数据的功能步骤、结果、耗时和机器可读错误码，用于排查故障和改进可靠性。不会发送 Prompt、源内容或生成内容、原始 WordPress 用户或文章 ID、邮箱、URL、DOM 数据、凭据或自由文本错误消息。默认关闭；管理员可以随时关闭。',
				'Optionally send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.' => '可选发送功能步骤、执行结果、耗时和机器可读错误码等元数据，用于排查故障并改善可靠性。不会发送 Prompt、源内容或生成内容、WordPress 用户或文章原始 ID、邮箱、URL、DOM 数据、凭据或自由文本错误消息。默认关闭；管理员可随时关闭。',
				'Cloud connection verified' => 'Cloud 连接已验证',
				'Would you like to help improve reliability by sharing anonymous usage and error diagnostics from this WordPress site?' => '是否愿意分享此 WordPress 站点的匿名使用和故障诊断数据，以帮助改进可靠性？',
				'Only feature steps, outcomes, timing, versions, and machine-readable error codes are sent. Prompts, source or generated content, user or post identifiers, URLs, credentials, and request headers are never sent.' => '只会发送功能步骤、结果、耗时、版本和机器可读错误码。不会发送 Prompt、源内容或生成内容、用户或文章标识、URL、凭据或请求头。',
				'Allow anonymous diagnostics' => '允许匿名诊断',
				'Not now' => '暂不允许',
				'More local permissions' => '更多本地授权',
				'Run the bounded connection checks or open Cloud for service detail.' => '运行有限的连接检查，或前往 Cloud 查看服务详情。',
				'Credentials' => '凭据',
				'Cloud connection' => 'Cloud 连接',
				'Recently reachable' => '最近可访问',
				'A cached signed Cloud read is available.' => '已有缓存的 Cloud 签名读取结果。',
				'Not recently checked' => '最近未检查',
				'Run a connection check when you need current service confirmation.' => '需要确认当前服务状态时，请运行连接检查。',
				'Last checked: %1$s · Signed read: %2$s' => '上次检查：%1$s · 签名读取：%2$s',
				'Checks' => '检查',
				'Check' => '检查项',
				'Detail' => '详情',
				'Advanced and troubleshooting sections' => '高级与排查分区',
				'Open Cloud status detail' => '打开 Cloud 状态详情',
				'saved' => '已保存',
				'verified' => '已验证',
				'reported' => '已返回',
				'Hosted Runtime' => '托管运行时',
				'ready' => '就绪',
				'continue' => '继续',
				'Ready' => '就绪',
				'Failed' => '失败',
				'unavailable' => '不可用',
				'Not configured' => '未配置',
				'Partial' => '部分配置',
				'not run' => '未运行',
				'Cloud Addon' => '云端扩展',
				'Cloud' => 'Cloud',
				'Operator' => '管理员',
				'Check Cloud status' => '检查 Cloud 状态',
				'Open settings' => '打开设置',
				'Run readiness test' => '运行就绪检查',
				'Readiness result' => '就绪检查结果',
				'Manual readiness test completed. Connector is ready.' => '手动就绪测试已完成。连接器已就绪。',
				'Owner: %1$s. Next safe action: %2$s. Blocked reason: %3$s' => '所有者：%1$s。下一步安全操作：%2$s。阻塞原因：%3$s',
				'Owner: %1$s. Next safe action: %2$s.' => '所有者：%1$s。下一步安全操作：%2$s。',
				'Cloud error classification' => 'Cloud 错误分类',
				'Cloud error classification.' => 'Cloud 错误分类。',
				'AI credits' => 'AI 积分',
				'%1$s used / %2$s limit / %3$s remaining' => '已用 %1$s / 上限 %2$s / 剩余 %3$s',
				'Delivery is off; refresh controls and routine delivery rows are hidden.' => '投递已关闭；刷新控件和常规投递行已隐藏。',
				'Change in Overview' => '在概览中更改',
				'Manage index' => '管理索引',
				'Back to Site Knowledge' => '返回站点知识库',
				'Technical delivery details' => '技术投递详情',
					'Knowledge base delivery' => '知识库投递',
				'Last success' => '上次成功',
				'Last error code' => '上次错误代码',
				'Last error time' => '上次错误时间',
				'WP-Cron disabled' => 'WP-Cron 已禁用',
				'Manual flush command' => '手动刷新命令',
				'%d change notification is no longer in the local delivery buffer. Request a public content refresh to reconcile it.' => '%d 条变更记录已不在本地投递缓冲区。请刷新公开内容以重新核对。',
				'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.' => '%d 条变更记录已不在本地投递缓冲区。请刷新公开内容以重新核对。',
				'Article index coverage' => '文章索引覆盖情况',
				'Check index status' => '核对索引状态',
				'Article coverage will appear after the Cloud index status is refreshed.' => '刷新 Cloud 索引状态后，将显示文章覆盖情况。',
				'Article index coverage summary' => '文章索引覆盖摘要',
				'indexed' => '已索引',
				'not indexed' => '未索引',
				'compared' => '已核对',
				'Showing the 1,000 most recently modified public posts and pages. Older content is not included in this comparison.' => '当前核对最近修改的 1,000 篇公开文章和页面，更早的内容不在本次结果中。',
				'Filter articles by index status' => '按索引状态筛选文章',
				'All' => '全部',
				'Not indexed' => '未索引',
				'Indexed' => '已索引',
				'No articles match this filter.' => '没有符合当前筛选条件的文章。',
				'Article' => '文章',
				'Last modified' => '最后修改时间',
				'Index status' => '索引状态',
				'Actions' => '操作',
				'(no title)' => '（无标题）',
				'Previous page' => '上一页',
				'Next page' => '下一页',
				'Page %1$d of %2$d' => '第 %1$d / %2$d 页',
				'Only published posts and pages can be refreshed in Site Knowledge.' => '只有已发布的文章和页面可以刷新到站点知识库。',
				'Choose a valid published article to refresh.' => '请选择有效的已发布文章进行刷新。',
				'Article refresh requested. Check its index status again after Cloud finishes processing.' => '已请求刷新文章。Cloud 处理完成后，请再次核对索引状态。',
				'WordPress AI alt text generation requires a local WordPress attachment.' => 'WordPress AI 替代文本生成需要本地 WordPress 附件。',
				'WordPress AI alt text generation requires one bounded attachment prompt.' => 'WordPress AI 替代文本生成需要一个有边界的附件提示词。',
				'You are not allowed to use this attachment for Cloud alt text generation.' => '您无权使用此附件进行 Cloud 替代文本生成。',
				'WordPress AI alt text generation requires a local media attachment.' => 'WordPress AI 替代文本生成需要本地媒体附件。',
				'The local attachment file is unavailable for Cloud alt text generation.' => '本地附件文件无法用于 Cloud 替代文本生成。',
				'The local attachment exceeds the Cloud alt text source size limit.' => '本地附件超出 Cloud 替代文本源大小限制。',
				'The local attachment could not be read for Cloud alt text generation.' => '无法读取本地附件以进行 Cloud 替代文本生成。',
				'The local attachment changed before it could be read for Cloud alt text generation.' => '本地附件在读取前已发生变化，无法进行 Cloud 替代文本生成。',
				'The local attachment image type is not supported for Cloud alt text generation.' => '本地附件图像类型不受 Cloud 替代文本生成支持。',
				'WordPress AI alt text generation requires verified Npcink Cloud settings.' => 'WordPress AI 替代文本生成需要经过验证的 Npcink Cloud 设置。',
				'Npcink Cloud did not return a valid source artifact for alt text generation.' => 'Npcink Cloud 未返回有效的替代文本生成源工件。',
			);
		}
	}
}
