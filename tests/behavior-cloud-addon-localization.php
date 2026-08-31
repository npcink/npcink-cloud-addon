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
	&& '%1$d / %2$d 张图片' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%1$d / %2$d images',
		'%1$d / %2$d images',
		'npcink-cloud-addon'
	)
	&& '当前套餐还可识别 %s 张图片。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Your plan has %s image recognition slots remaining.',
		'Your plan has %s image recognition slots remaining.',
		'npcink-cloud-addon'
	)
	&& '当前批次正在识别，剩余图片会自动继续处理。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'The current batch is running. Remaining images will continue automatically.',
		'The current batch is running. Remaining images will continue automatically.',
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
	'%1$d / %2$d' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%1$d of %2$d',
		'%1$d of %2$d',
		'npcink-cloud-addon'
	)
	&& '识别站内图片，便于编辑器按含义搜索。不会修改已有媒体或 WordPress 内容。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Recognize local images so the editor can find them by meaning. Existing media and WordPress content are not changed.',
		'Recognize local images so the editor can find them by meaning. Existing media and WordPress content are not changed.',
		'npcink-cloud-addon'
	)
	&& '已识别：%1$d 张图片；视觉证据：%2$d 条。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Recognized: %1$d images; visual evidence: %2$d.',
		'Recognized: %1$d images; visual evidence: %2$d.',
		'npcink-cloud-addon'
	)
	&& '已处理 %2$d 张中的 %1$d 张，用时 %3$s 秒（平均每分钟 %4$d 张）。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Processed %1$d of %2$d images in %3$s seconds (average %4$d images/minute).',
		'Processed %1$d of %2$d images in %3$s seconds (average %4$d images/minute).',
		'npcink-cloud-addon'
	)
	&& '每分钟 %s 张' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%s images/minute',
		'%s images/minute',
		'npcink-cloud-addon'
	)
	&& '张图片/分钟' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'images/minute',
		'images/minute',
		'npcink-cloud-addon'
	)
	&& '完成后不再适用' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Not applicable after completion',
		'Not applicable after completion',
		'npcink-cloud-addon'
	)
	&& '套餐' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Plan',
		'Plan',
		'npcink-cloud-addon'
	)
	&& '%1$d 个并发任务；每批最多 %2$d 张图片' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'%1$d concurrent task(s); up to %2$d images per batch',
		'%1$d concurrent task(s); up to %2$d images per batch',
		'npcink-cloud-addon'
	)
	&& '检查新增图片' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Check for new images',
		'Check for new images',
		'npcink-cloud-addon'
	)
	&& '开始识别媒体图片' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Start media recognition',
		'Start media recognition',
		'npcink-cloud-addon'
	)
	&& '继续识别剩余图片' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Continue recognizing remaining images',
		'Continue recognizing remaining images',
		'npcink-cloud-addon'
	)
	&& '重试本批' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Retry this batch',
		'Retry this batch',
		'npcink-cloud-addon'
	)
	&& 'Cloud 响应超时，后台会安全重试当前批次，无需再次点击。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'The Cloud response timed out. Background recognition will safely retry this batch; no further click is needed.',
		'The Cloud response timed out. Background recognition will safely retry this batch; no further click is needed.',
		'npcink-cloud-addon'
	)
	&& 'Npcink Cloud 尚未通过验证。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Npcink Cloud is not verified.',
		'Npcink Cloud is not verified.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback covers the complete Site media recognition summary and actions.'
);

maca_assert(
	'已处理：%1$d 张图片；视觉证据：%2$d 条；未纳入识别：%3$d 张。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Processed: %1$d images; visual evidence: %2$d; not included: %3$d.',
		'Processed: %1$d images; visual evidence: %2$d; not included: %3$d.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback explains processed, evidence, and excluded image counts.'
);

maca_assert(
	'已识别：%1$d 张图片；视觉证据：%2$d 条；未纳入识别：%3$d 张。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Recognized: %1$d images; visual evidence: %2$d; not included: %3$d.',
		'Recognized: %1$d images; visual evidence: %2$d; not included: %3$d.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback distinguishes recognized and excluded image counts.'
);

maca_assert(
	'识别已完成，但有 %d 张图片因不符合当前图片要求未纳入识别。' === Npcink_Cloud_Addon_Localization::filter_gettext(
		'Recognition finished. %d images were not included because they did not meet the current image requirements.',
		'Recognition finished. %d images were not included because they did not meet the current image requirements.',
		'npcink-cloud-addon'
	),
	'Addon localization fallback explains why completed image recognition can exclude images.'
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
		&& '查看高级排查' === Npcink_Cloud_Addon_Localization::filter_gettext(
			'View advanced troubleshooting',
			'View advanced troubleshooting',
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
