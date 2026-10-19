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

// Simulate an installed language pack that lacks newer fixed admin strings.
$admin_string_pattern = <<<'REGEX'
~(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:\\.|[^'\\])*)'\s*,\s*'npcink-cloud-addon'~s
REGEX;
$untranslated_admin_strings = array();
foreach ( array( 'settings-page', 'settings-actions', 'site-knowledge-admin-actions', 'addon-settings', 'site-knowledge-change-bridge', 'observability-collector' ) as $admin_module ) {
	preg_match_all( $admin_string_pattern, maca_read( MACA_TEST_ROOT . '/includes/class-cloud-' . $admin_module . '.php' ), $admin_matches );
	foreach ( $admin_matches[1] as $admin_source ) {
		$admin_source = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $admin_source );
		// Brand, accessibility glyph, number pair and currency identifier.
		if ( in_array( $admin_source, array( 'Npcink Cloud Addon', 'Cloud', 'i', '%1$s / %2$s', '%s USD' ), true ) ) {
			continue;
		}
		if ( $admin_source === Npcink_Cloud_Addon_Localization::filter_gettext( $admin_source, $admin_source, 'npcink-cloud-addon' ) ) {
			$untranslated_admin_strings[] = $admin_source;
		}
	}
}
maca_assert(
	array() === $untranslated_admin_strings,
	'Behavior: fixed connector admin and action strings remain Chinese with a lagging language pack: ' . implode( '; ', array_unique( $untranslated_admin_strings ) )
);

foreach ( array(
	'Site Knowledge refresh requested. Public content items sent: %d.' => '已请求站点知识库刷新。已发送公开内容项：%d。',
	'Knowledge base recovery has been scheduled. The warning will clear after all public content has been delivered to Cloud.' => '已安排知识库恢复更新。全部公开内容投递到 Cloud 后，警告将自动解除。',
	'A knowledge base update is already in progress. No new request was started.' => '知识库正在更新中，本次未重复发起请求。',
	'Public content is being re-sent to Cloud. The warning will clear when delivery finishes.' => '正在向 Cloud 重新投递公开内容。投递完成后，警告将自动解除。',
	'The cached Cloud read is out of date. Re-check the connection to confirm current service status.' => '缓存的 Cloud 读取已过期。请重新检查连接以确认当前服务状态。',
) as $source => $translation ) {
	maca_assert(
		$translation === Npcink_Cloud_Addon_Localization::filter_gettext( $source, $source, 'npcink-cloud-addon' )
		&& 'Existing translation' === Npcink_Cloud_Addon_Localization::filter_gettext( 'Existing translation', $source, 'npcink-cloud-addon' ),
		'Behavior: knowledge base recovery notices have a Chinese fallback when the installed language pack lags, preserving supplied translations.'
	);
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
	&& '功能' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Features',
		'Features',
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
	&& '可用图片数' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Available images',
		'Available images',
		'npcink-cloud-addon'
	)
	&& '无限制' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Unlimited',
		'Unlimited',
		'npcink-cloud-addon'
	)
	&& '剩余图片识别额度占比' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Remaining image recognition capacity percentage',
		'Remaining image recognition capacity percentage',
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
	&& '%1$s / %2$s · 剩余 %3$d%%' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%1$s / %2$s · %3$d%% remaining',
		'%1$s / %2$s · %3$d%% remaining',
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
		'检查' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'Checks',
			'Checks',
				'npcink-cloud-addon'
			)
			&& '连接与服务' === Npcink_Cloud_Addon_Localization::filter_gettext(
				'Connection and service',
				'Connection and service',
				'npcink-cloud-addon'
			)
			&& '已连接' === Npcink_Cloud_Addon_Localization::filter_gettext(
				'Connected',
				'Connected',
				'npcink-cloud-addon'
			)
			&& '打开 Cloud' === Npcink_Cloud_Addon_Localization::filter_gettext(
				'Open Cloud',
				'Open Cloud',
				'npcink-cloud-addon'
			)
			&& '连接管理' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'Connection management',
			'Connection management',
			'npcink-cloud-addon'
		)
		&& '更换 Cloud 账号' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'Change Cloud account',
			'Change Cloud account',
			'npcink-cloud-addon'
		)
		&& '断开此站点' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'Disconnect this site',
			'Disconnect this site',
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
	)
	&& '执行测试可能消耗额度。测试使用当前 WordPress AI 配置；请求日志只记录每次请求由哪个模型处理。模型由 Npcink Cloud 按任务自动选择，这里没有需要你选择的模型。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Invoking a test may consume credits. Tests use the current WordPress AI configuration; the request log records which model handled each request. Npcink Cloud chooses the model for each task, so there is no model selection to make here.',
		'Invoking a test may consume credits. Tests use the current WordPress AI configuration; the request log records which model handled each request. Npcink Cloud chooses the model for each task, so there is no model selection to make here.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers the connected summary, troubleshooting, readiness, monitoring-consent, and hosted model ownership surfaces.'
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
	&& '发送匿名诊断信息' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Send anonymous diagnostics',
		'Send anonymous diagnostics',
		'npcink-cloud-addon'
	)
	&& '可选发送功能步骤、执行结果、耗时和机器可读错误码等元数据，用于排查故障并改善可靠性。不会发送 Prompt、源内容或生成内容、WordPress 用户或文章原始 ID、邮箱、URL、DOM 数据、凭据或自由文本错误消息。默认关闭；管理员可随时关闭。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Optionally send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.',
		'Optionally send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.',
		'npcink-cloud-addon'
	)
	&& '隐私设置' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Privacy settings',
		'Privacy settings',
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
		'知识库投递' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'Knowledge base delivery',
			'Knowledge base delivery',
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
	&& '知识库详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Knowledge base details',
		'Knowledge base details',
		'npcink-cloud-addon'
	)
		&& '重新更新' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'Update again',
			'Update again',
			'npcink-cloud-addon'
		)
		&& '查看检查' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'View checks',
			'View checks',
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

$GLOBALS['maca_is_admin'] = false;
maca_assert(
	'' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'Advanced connection',
		'npcink-cloud-addon'
	)
	&& false === Npcink_Cloud_Addon_Localization::should_localize(),
	'Addon localization fallback stays off for pure front-end requests even in zh_CN locales.'
);
unset( $GLOBALS['maca_is_admin'] );

if ( ! defined( 'REST_REQUEST' ) ) {
	define( 'REST_REQUEST', true );
}
maca_assert(
	Npcink_Cloud_Addon_Localization::should_localize()
	&& '高级连接' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'Advanced connection',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers editor-facing REST requests so Cloud errors stay localized without a language pack.'
);

maca_assert(
	'连接后，WordPress AI 连接器将默认启用。站点知识库投递可随时在“概览”中开启或关闭。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'After connection, the WordPress AI connector is enabled by default. Site Knowledge delivery can be turned on or off in Overview at any time.',
		'npcink-cloud-addon'
	)
	&& '允许 WordPress AI 使用 Npcink Cloud。站点保持连接期间默认启用。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'Allow WordPress AI to use Npcink Cloud. Enabled by default while this site stays connected.',
		'npcink-cloud-addon'
	)
	&& 'Npcink Cloud 仍在处理此请求。请稍候再试，以免重复执行产生额外消耗。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'Npcink Cloud is still processing this request. Wait a moment and try again later to avoid a duplicate run.',
		'npcink-cloud-addon'
	)
	&& '新的 Cloud 连接未能通过验证：%s。连接信息已保存，请使用本页的“检查连接”重新验证。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'The new Cloud connection could not be verified: %s. The connection details were saved, so use Check connection on this page to verify again.',
		'npcink-cloud-addon'
	)
	&& '查看上传详情' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'View upload detail',
		'npcink-cloud-addon'
	)
	&& '查看站点知识库状态' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'',
		'View Site Knowledge status',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers the 2026-10-11 admin copy, connection recovery, and in-progress run strings in zh_CN.'
);

$task_contract_messages = array(
	'A registered WordPress Ability is required for Cloud AI task execution.',
	'The requested WordPress Ability is not registered.',
	'The registered Ability does not publish a Cloud AI task contract.',
	'The Toolkit contract source is unavailable for this Ability.',
	'The Toolkit contract is missing for this Ability.',
	'The WordPress Ability schema does not match the Toolkit contract.',
	'The Toolkit contract version or Ability identity is invalid.',
	'The Toolkit contract permission or write posture is incompatible with the local Ability.',
	'The Toolkit risk level does not match the registered WordPress Ability.',
	'The Toolkit approval requirement does not match the registered WordPress Ability.',
	'AI task contracts require ai_task_contract.v1.',
	'AI task contracts require a registered ability, task, and supported task family.',
	'AI task contract verification must be mapping_current before Cloud execution.',
	'AI task contracts contain an unsupported context requirement or constraint.',
	'AI task contracts require a stable sha256 schema hash.',
	'The Ability output schema is too large for runtime projection.',
	'The AI task contract schema hash does not match its input and output schemas.',
	'AI task contract list fields must be arrays.',
	'AI task contract list fields must contain strings.',
	'AI task contract list fields contain an unsupported value.',
	'AI task contract context none cannot be combined with other values.',
	'Npcink Cloud model metadata not found.',
);
$translation_map = Npcink_Cloud_Addon_Localization::translations();
maca_assert(
	count( $task_contract_messages ) === count( array_intersect( $task_contract_messages, array_keys( $translation_map ) ) )
		&& '提交前请确认要在本地断开连接。' === Npcink_Cloud_Addon_Localization::filter_gettext( '', 'Confirm local disconnect before submitting.', 'npcink-cloud-addon' )
		&& '未找到 Npcink Cloud 模型元数据。' === Npcink_Cloud_Addon_Localization::filter_gettext( '', 'Npcink Cloud model metadata not found.', 'npcink-cloud-addon' ),
	'Addon localization fallback covers the disconnect, model metadata, and complete AI task contract error vocabulary in zh_CN.'
);
