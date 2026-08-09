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
				'Local permissions' => '本地授权',
				'Overview' => '概览',
				'Advanced and troubleshooting' => '高级与排查',
				'Service summary' => '服务摘要',
				'View service details' => '查看服务详情',
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
				'Connection recovery' => '连接恢复',
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
				'Site Knowledge delivery' => '站点知识库投递',
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
				'Cloud index details' => 'Cloud 索引详情',
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
				'Monitoring' => '监控',
				'Upload metadata-only plugin monitoring events.' => '上传仅包含元数据的插件监控事件。',
				'More local permissions' => '更多本地授权',
				'Run the bounded connection checks or open Cloud for service detail.' => '运行有限的连接检查，或前往 Cloud 查看服务详情。',
				'Credentials' => '凭据',
				'Cloud connection' => 'Cloud 连接',
				'Last checked: %1$s · Signed read: %2$s' => '上次检查：%1$s · 签名读取：%2$s',
				'Run readiness test' => '运行就绪检查',
				'Readiness result' => '就绪检查结果',
				'Inspect by run ID' => '按运行 ID 检查',
				'Cloud error classification' => 'Cloud 错误分类',
				'Cloud error classification.' => 'Cloud 错误分类。',
				'AI credits' => 'AI 积分',
				'%1$s used / %2$s limit / %3$s remaining' => '已用 %1$s / 上限 %2$s / 剩余 %3$s',
				'Delivery is off; refresh controls and routine delivery rows are hidden.' => '投递已关闭；刷新控件和常规投递行已隐藏。',
				'Change in Overview' => '在概览中更改',
				'Manage index' => '管理索引',
				'Back to Site Knowledge' => '返回站点知识库',
				'Technical delivery details' => '技术投递详情',
				'Bridge health detail' => '桥接健康详情',
				'Last success' => '上次成功',
				'Last error code' => '上次错误代码',
				'Last error time' => '上次错误时间',
				'WP-Cron disabled' => 'WP-Cron 已禁用',
				'Manual flush command' => '手动刷新命令',
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
