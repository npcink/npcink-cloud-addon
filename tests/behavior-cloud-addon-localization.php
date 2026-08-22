<?php
/**
 * Behavior tests for bounded addon fallback localization.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-localization.php';

$GLOBALS['maca_is_admin'] = true;
$GLOBALS['maca_locale'] = 'zh_CN';

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return (bool) ( $GLOBALS['maca_is_admin'] ?? false );
	}
}

if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale(): string {
		return (string) ( $GLOBALS['maca_locale'] ?? 'en_US' );
	}
}

maca_assert(
	'已连接，等待激活' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Connected, activation required',
		'Connected, activation required',
		'npcink-cloud-addon'
	)
	&& '高级连接' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced connection',
		'Advanced connection',
		'npcink-cloud-addon'
	)
	&& '使用此端点授权' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Authorize with this endpoint',
		'Authorize with this endpoint',
		'npcink-cloud-addon'
	)
	&& '本地授权' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Local permissions',
		'Local permissions',
		'npcink-cloud-addon'
	)
	&& '站点知识库' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Site Knowledge',
		'Site Knowledge',
		'npcink-cloud-addon'
	)
	&& '高级与排查' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced and troubleshooting',
		'Advanced and troubleshooting',
		'npcink-cloud-addon'
	)
	&& '技术投递详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Technical delivery details',
		'Technical delivery details',
		'npcink-cloud-addon'
	)
	&& '正在获取套餐与权益…' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Loading plan and entitlement…',
		'Loading plan and entitlement…',
		'npcink-cloud-addon'
	)
	&& '暂时无法获取套餐与权益。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Plan and entitlement are temporarily unavailable.',
		'Plan and entitlement are temporarily unavailable.',
		'npcink-cloud-addon'
	)
	&& '重试' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Retry',
		'Retry',
		'npcink-cloud-addon'
	)
	&& '可用 AI 积分' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Available AI credits',
		'Available AI credits',
		'npcink-cloud-addon'
	)
	&& '此处显示的 AI 积分属于当前连接的 Cloud 账户。断开、移除或更换此 WordPress 站点不会转移这些 AI 积分。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'AI credits shown here belong to the connected Cloud account. Disconnecting, removing, or changing this WordPress site does not transfer those AI credits.',
		'AI credits shown here belong to the connected Cloud account. Disconnecting, removing, or changing this WordPress site does not transfer those AI credits.',
		'npcink-cloud-addon'
	)
	&& '运行额度' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Runtime allowance',
		'Runtime allowance',
		'npcink-cloud-addon'
	)
	&& '免费版' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Free plan',
		'Free plan',
		'npcink-cloud-addon'
	)
	&& '已用 %1$s AI 积分；剩余 %2$s AI 积分；上限 %3$s AI 积分。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Used %1$s AI credits; remaining %2$s AI credits; limit %3$s AI credits.',
		'Used %1$s AI credits; remaining %2$s AI credits; limit %3$s AI credits.',
		'npcink-cloud-addon'
	)
	&& '剩余 %d%%' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%d%% remaining',
		'%d%% remaining',
		'npcink-cloud-addon'
	)
	&& '权益详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Entitlement details',
		'Entitlement details',
		'npcink-cloud-addon'
	),
	'Addon localization fallback translates fixed npcink-cloud-addon strings in zh_CN admin.'
);

maca_assert(
	'上次验证成功' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Last verification succeeded',
		'Last verification succeeded',
		'npcink-cloud-addon'
	)
	&& '当前服务' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Current service',
		'Current service',
		'npcink-cloud-addon'
	)
	&& '检查' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Checks',
		'Checks',
		'npcink-cloud-addon'
	)
	&& '运行记录' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Runtime runs',
		'Runtime runs',
		'npcink-cloud-addon'
	)
	&& '手动就绪测试已完成。连接器已就绪。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Manual readiness test completed. Connector is ready.',
		'Manual readiness test completed. Connector is ready.',
		'npcink-cloud-addon'
	)
	&& '允许匿名诊断' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Allow anonymous diagnostics',
		'Allow anonymous diagnostics',
		'npcink-cloud-addon'
	)
	&& '暂不允许' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Not now',
		'Not now',
		'npcink-cloud-addon'
	)
	&& '托管运行时' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Hosted Runtime',
		'Hosted Runtime',
		'npcink-cloud-addon'
	)
	&& '就绪' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'ready',
		'ready',
		'npcink-cloud-addon'
	)
	&& '继续' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'continue',
		'continue',
		'npcink-cloud-addon'
	)
	&& '云端扩展' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Cloud Addon',
		'Cloud Addon',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers the connected summary, troubleshooting, readiness, and monitoring-consent surfaces.'
);

maca_assert(
	'允许 WordPress AI 使用 Npcink Cloud。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Allow WordPress AI to use Npcink Cloud.',
		'Allow WordPress AI to use Npcink Cloud.',
		'npcink-cloud-addon'
	)
	&& 'Cloud 连接' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Cloud connection',
		'Cloud connection',
		'npcink-cloud-addon'
	)
	&& '生成时参考站点内容' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Reference site content during generation',
		'Reference site content during generation',
		'npcink-cloud-addon'
	)
	&& '使用与故障诊断' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Usage and error diagnostics',
		'Usage and error diagnostics',
		'npcink-cloud-addon'
	)
	&& '可选发送功能步骤、执行结果、耗时和机器可读错误码等元数据，用于排查故障并改善可靠性。不会发送 Prompt、源内容或生成内容、WordPress 用户或文章原始 ID、邮箱、URL、DOM 数据、凭据或自由文本错误消息。默认关闭；管理员可随时关闭。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Optionally send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.',
		'Optionally send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.',
		'npcink-cloud-addon'
	)
	&& '更多本地授权' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'More local permissions',
		'More local permissions',
		'npcink-cloud-addon'
	)
	&& '排队中' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Queued',
		'Queued',
		'npcink-cloud-addon'
	)
	&& '运行中' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Running',
		'Running',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers verified local permissions and explicit monitoring-scope copy.'
);

maca_assert(
	'桥接健康详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Bridge health detail',
		'Bridge health detail',
		'npcink-cloud-addon'
	)
	&& '手动刷新命令' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Manual flush command',
		'Manual flush command',
		'npcink-cloud-addon'
	)
	&& 'AI 积分' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'AI credits',
		'AI credits',
		'npcink-cloud-addon'
	)
	&& '可用文章数' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Available knowledge documents',
		'Available knowledge documents',
		'npcink-cloud-addon'
	)
	&& '知识库文章剩余占比' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Remaining knowledge document percentage',
		'Remaining knowledge document percentage',
		'npcink-cloud-addon'
	)
	&& '已索引 %1$s 篇；剩余 %2$s 篇；上限 %3$s 篇。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Indexed %1$s documents; remaining %2$s documents; limit %3$s documents.',
		'Indexed %1$s documents; remaining %2$s documents; limit %3$s documents.',
		'npcink-cloud-addon'
	)
	&& 'Cloud 索引详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Cloud index details',
		'Cloud index details',
		'npcink-cloud-addon'
	)
	&& '暂时无法获取知识库用量。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Site Knowledge usage is temporarily unavailable.',
		'Site Knowledge usage is temporarily unavailable.',
		'npcink-cloud-addon'
	)
	&& '%d 条公开变更待投递' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%d public changes awaiting delivery',
		'%d public changes awaiting delivery',
		'npcink-cloud-addon'
	)
	&& '待投递' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'queued',
		'queued',
		'npcink-cloud-addon'
	)
	&& '空闲' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'idle',
		'idle',
		'npcink-cloud-addon'
	)
	&& '文章索引覆盖情况' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Article index coverage',
		'Article index coverage',
		'npcink-cloud-addon'
	)
	&& '刷新这篇文章' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Refresh this article',
		'Refresh this article',
		'npcink-cloud-addon'
	)
	&& '核对索引状态' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Check index status',
		'Check index status',
		'npcink-cloud-addon'
	)
	&& '%d 条变更记录已不在本地投递缓冲区。请刷新公开内容以重新核对。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.',
		'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers compact Site Knowledge usage and delivery status copy.'
);

maca_assert(
	'%d 条变更记录已不在本地投递缓冲区。请刷新公开内容以重新核对。' === Npcink_Cloud_Addon_Localization::filter_ngettext(
		'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.',
		'%d change notification is no longer in the local delivery buffer. Request a public content refresh to reconcile it.',
		'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.',
		2,
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers dynamic Site Knowledge delivery-buffer plural copy.'
);

maca_assert(
	'已有翻译' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'已有翻译',
		'Advanced connection',
		'npcink-cloud-addon'
	),
	'Addon localization fallback preserves existing language-pack translations.'
);

$GLOBALS['maca_locale'] = 'en_US';
maca_assert(
	'Advanced connection' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced connection',
		'Advanced connection',
		'npcink-cloud-addon'
	),
	'Addon localization fallback does not translate outside zh locales.'
);

$GLOBALS['maca_locale'] = 'zh_CN';
maca_assert(
	'Advanced connection' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Advanced connection',
		'Advanced connection',
		'other-domain'
	),
	'Addon localization fallback is limited to the npcink-cloud-addon text domain.'
);

maca_assert(
	'WordPress AI 替代文本生成需要本地 WordPress 附件。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'WordPress AI alt text generation requires a local WordPress attachment.',
		'npcink-cloud-addon'
	)
	&& 'WordPress AI 替代文本生成需要一个有边界的附件提示词。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'WordPress AI alt text generation requires one bounded attachment prompt.',
		'npcink-cloud-addon'
	)
	&& '您无权使用此附件进行 Cloud 替代文本生成。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'You are not allowed to use this attachment for Cloud alt text generation.',
		'npcink-cloud-addon'
	)
	&& 'WordPress AI 替代文本生成需要本地媒体附件。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'WordPress AI alt text generation requires a local media attachment.',
		'npcink-cloud-addon'
	)
	&& '本地附件文件无法用于 Cloud 替代文本生成。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'The local attachment file is unavailable for Cloud alt text generation.',
		'npcink-cloud-addon'
	)
	&& '本地附件超出 Cloud 替代文本源大小限制。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'The local attachment exceeds the Cloud alt text source size limit.',
		'npcink-cloud-addon'
	)
	&& '无法读取本地附件以进行 Cloud 替代文本生成。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'The local attachment could not be read for Cloud alt text generation.',
		'npcink-cloud-addon'
	)
	&& '本地附件在读取前已发生变化，无法进行 Cloud 替代文本生成。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'The local attachment changed before it could be read for Cloud alt text generation.',
		'npcink-cloud-addon'
	)
	&& '本地附件图像类型不受 Cloud 替代文本生成支持。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'The local attachment image type is not supported for Cloud alt text generation.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback translates WordPress AI alt text source error messages in zh_CN admin.'
);

maca_assert(
	'WordPress AI 替代文本生成需要经过验证的 Npcink Cloud 设置。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'WordPress AI alt text generation requires verified Npcink Cloud settings.',
		'npcink-cloud-addon'
	)
	&& 'Npcink Cloud 未返回有效的替代文本生成源工件。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'Npcink Cloud did not return a valid source artifact for alt text generation.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers verified-settings and artifact validation alt text error messages in zh_CN admin.'
);
