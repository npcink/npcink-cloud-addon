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
	'高级连接' === Npcink_Cloud_Addon_Localization::filter_gettext(
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
	'Addon localization fallback covers verified local permissions admin copy.'
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
	),
	'Addon localization fallback covers compact Site Knowledge usage and delivery status copy.'
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
