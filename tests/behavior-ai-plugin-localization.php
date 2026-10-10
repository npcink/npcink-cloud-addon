<?php
/**
 * Behavior tests for the bounded AI plugin localization shim.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
	define( 'NPCINK_CLOUD_ADDON_FILE', MACA_TEST_ROOT . '/npcink-cloud-addon.php' );
}
if ( ! defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ) {
	define( 'NPCINK_CLOUD_ADDON_VERSION', '0.1.0-test' );
}

require_once MACA_TEST_ROOT . '/includes/class-ai-plugin-localization.php';

$GLOBALS['maca_is_admin'] = true;
$GLOBALS['maca_locale'] = 'zh_CN';
$GLOBALS['maca_enqueued_scripts'] = array();
$GLOBALS['maca_localized_scripts'] = array();

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

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), string $version = '', bool $in_footer = false ): void {
		$GLOBALS['maca_enqueued_scripts'][] = array(
			'handle' => $handle,
			'src' => $src,
			'deps' => $deps,
			'version' => $version,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( string $path = '', string $plugin = '' ): string {
		return 'https://example.test/wp-content/plugins/npcink-cloud-addon/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $data ): void {
		$GLOBALS['maca_localized_scripts'][] = array(
			'handle' => $handle,
			'object_name' => $object_name,
			'data' => $data,
		);
	}
}

maca_assert(
	'生成图片' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'ai'
	),
	'AI plugin localization translates fixed ai-domain PHP strings in zh_CN admin.'
);

maca_assert(
	'高级设置' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Advanced settings', 'Advanced settings', 'ai' )
	&& '加载连接器审批状态失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Failed to load connector approval status.', 'Failed to load connector approval status.', 'ai' )
	&& '正在生成摘要：%1$d / %2$d…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Generating summaries: %1$d / %2$d…', 'Generating summaries: %1$d / %2$d…', 'ai' )
	&& '文章内容至少达到 %d 个字符后可生成标题。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Title generation will be available when the post content has at least %d characters.', 'Title generation will be available when the post content has at least %d characters.', 'ai' ),
	'AI plugin localization covers the audited fixed UI candidates without translating dynamic ability metadata.'
);

maca_assert(
	'能力浏览器' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Abilities Explorer',
		'Abilities Explorer',
		'ai'
	)
	&& '连接器审批' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Connector Approval',
		'Connector Approval',
		'ai'
	)
	&& '输入预测文本' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Type-ahead Text',
		'Type-ahead Text',
		'ai'
	)
	&& '在区块编辑器中撰写段落时显示灰色文本建议。需要支持文本生成模型的 AI 连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Ghost text suggestions while writing paragraphs in the block editor. Requires an AI connector that includes support for text generation models.',
		'Ghost text suggestions while writing paragraphs in the block editor. Requires an AI connector that includes support for text generation models.',
		'ai'
	)
	&& '密钥加密' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Key Encryption',
		'Key Encryption',
		'ai'
	)
	&& '使用内置 libsodium 加密静态存储的 AI 提供方 API 密钥。读取时会透明解密，写入时会重新加密。停用此实验功能或停用插件会恢复明文密钥。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Encrypts AI provider API keys at rest using bundled libsodium encryption. Keys are transparently decrypted on read and re-encrypted on write. Disabling the experiment or deactivating the plugin restores plaintext keys.',
		'Encrypts AI provider API keys at rest using bundled libsodium encryption. Keys are transparently decrypted on read and re-encrypted on write. Disabling the experiment or deactivating the plugin restores plaintext keys.',
		'ai'
	),
	'AI plugin localization translates admin experiment feature labels.'
);

maca_assert(
	'配置 AI 提供方' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Configure an AI provider',
		'Configure an AI provider',
		'ai'
	)
	&& '文本生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Text Generation',
		'Text Generation',
		'ai'
	)
	&& '嵌入生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Embedding Generation',
		'Embedding Generation',
		'ai'
	)
	&& '聊天历史' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Chat History',
		'Chat History',
		'ai'
	)
	&& '模型' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Model',
		'Model',
		'ai'
	)
	&& '%s 已启用。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%s enabled.',
		'%s enabled.',
		'ai'
	)
	&& '重置为默认值' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Reset to default',
		'Reset to default',
		'ai'
	)
	&& '— 默认 —' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'— Default —',
		'— Default —',
		'ai'
	)
	&& '%d 个字段需要处理' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d fields need attention',
		'%d fields need attention',
		'ai'
	)
	&& '先前选择的提供方已不可用。此功能在选择有效提供方或重置为默认值之前可能无法正常工作。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'The previously selected provider is no longer available. This feature will not function as expected until a valid provider is selected or the selection is reset to default.',
		'The previously selected provider is no longer available. This feature will not function as expected until a valid provider is selected or the selection is reset to default.',
		'ai'
	),
	'AI plugin localization translates dashboard status and capability labels.'
);

maca_assert(
	'生成摘要区块' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Summary',
		'Generate Summary',
		'ai'
	)
	&& '生成编辑建议' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Editorial Notes',
		'Generate Editorial Notes',
		'ai'
	)
	&& '生成 SEO 描述' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Meta Description',
		'Generate Meta Description',
		'ai'
	)
	&& '文章内容至少达到 %d 个字符后可生成编辑建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Editorial Notes will be available when the post content has at least %d characters.',
		'Editorial Notes will be available when the post content has at least %d characters.',
		'ai'
	)
	&& '文章内容至少达到 %d 个字符后可生成 SEO 描述。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Meta Description generation will be available when the post content has at least %d characters.',
		'Meta Description generation will be available when the post content has at least %d characters.',
		'ai'
	)
	&& '文章内容至少达到 %d 个字符后可使用内容分类建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Content Classification will be available when the post content has at least %d characters.',
		'Content Classification will be available when the post content has at least %d characters.',
		'ai'
	),
	'AI plugin localization translates editor action labels and eligibility guidance.'
);

maca_assert(
	'生成特色图片' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate featured image',
		'Generate featured image',
		'ai'
	)
	&& 'AI 生成的特色图片' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'AI Generated Featured Image',
		'AI Generated Featured Image',
		'ai'
	)
	&& '正在导入图片…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Importing image…',
		'Importing image…',
		'ai'
	)
	&& '正在上传图片到媒体库…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Uploading image to Media Library…',
		'Uploading image to Media Library…',
		'ai'
	)
	&& '图片已成功添加到媒体库。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Image successfully added to the Media Library.',
		'Image successfully added to the Media Library.',
		'ai'
	)
	&& '在媒体库中查看' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'View in Media Library',
		'View in Media Library',
		'ai'
	)
	&& '扩展背景' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Expand Background',
		'Expand Background',
		'ai'
	)
	&& '画笔大小' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Brush size',
		'Brush size',
		'ai'
	)
	&& '替换项目' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Replace Item',
		'Replace Item',
		'ai'
	)
	&& '撤销' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Undo',
		'Undo',
		'ai'
	)
	&& '版本 %d' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Version %d',
		'Version %d',
		'ai'
	),
	'AI plugin localization translates image generation editor and media controls.'
);

maca_assert(
	'分析情绪、毒性和价值' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Analyze Sentiment, Toxicity, and Value',
		'Analyze Sentiment, Toxicity, and Value',
		'ai'
	)
	&& '正在分析…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Analyzing…',
		'Analyzing…',
		'ai'
	)
	&& '情绪' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Sentiment',
		'Sentiment',
		'ai'
	)
	&& '毒性' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Toxicity',
		'Toxicity',
		'ai'
	)
	&& '高毒性（>=70%）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'High Toxicity (>=70%)',
		'High Toxicity (>=70%)',
		'ai'
	)
	&& '%d 条评论已加入分析队列。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d comments queued for analysis.',
		'%d comments queued for analysis.',
		'ai'
	)
	&& '设置 → 连接器' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Settings → Connectors',
		'Settings → Connectors',
		'ai'
	)
	&& '此功能需要有效的 AI 连接器才能正常工作。请在 %s 中设置提供方。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'This feature requires a valid AI Connector to function properly. Please set up a provider to use this feature in %s.',
		'This feature requires a valid AI Connector to function properly. Please set up a provider to use this feature in %s.',
		'ai'
	)
	&& '自动审核访客评论' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Automatically moderate guest comments',
		'Automatically moderate guest comments',
		'ai'
	),
	'AI plugin localization translates comment moderation labels and statuses.'
);

maca_assert(
	'分类策略' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Taxonomy strategy',
		'Taxonomy strategy',
		'ai'
	)
	&& '最大建议数量' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Maximum suggestions',
		'Maximum suggestions',
		'ai'
	)
	&& '仅建议现有术语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Only suggest existing terms',
		'Only suggest existing terms',
		'ai'
	)
	&& '建议%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Suggest %s',
		'Suggest %s',
		'ai'
	)
	&& '添加“%s”' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Add "%s"',
		'Add "%s"',
		'ai'
	)
	&& '重新建议' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Suggest again',
		'Suggest again',
		'ai'
	)
	&& '建议的%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Suggested %s',
		'Suggested %s',
		'ai'
	),
	'AI plugin localization translates content classification editor labels and help text.'
);

maca_assert(
	'调整内容长度' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Resize Content',
		'Resize Content',
		'ai'
	)
	&& '缩短' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Shorten',
		'Shorten',
		'ai'
	)
	&& '重新生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Regenerate',
		'Regenerate',
		'ai'
	)
	&& '增加 %d 个字符' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'+%d characters',
		'+%d characters',
		'ai'
	)
	&& '减少 %d 个字符' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'−%d characters',
		'−%d characters',
		'ai'
	)
	&& '增加了 %d 个字符' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d characters added',
		'%d characters added',
		'ai'
	)
	&& '移除了 %d 个字符' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d characters removed',
		'%d characters removed',
		'ai'
	),
	'AI plugin localization translates content resizing editor controls.'
);

maca_assert(
	'SEO 描述' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Meta description',
		'Meta description',
		'ai'
	)
	&& '复制到剪贴板' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Copy to clipboard',
		'Copy to clipboard',
		'ai'
	)
	&& 'SEO 描述已复制到剪贴板。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Meta description copied to clipboard.',
		'Meta description copied to clipboard.',
		'ai'
	),
	'AI plugin localization translates meta description editor controls.'
);

maca_assert(
	'标题建议' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Title suggestion',
		'Title suggestion',
		'ai'
	)
	&& '插入' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Insert',
		'Insert',
		'ai'
	)
	&& '未生成标题建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No title suggestion was generated.',
		'No title suggestion was generated.',
		'ai'
	),
	'AI plugin localization translates title generation editor controls.'
);

maca_assert(
	'文章摘要生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Excerpt Generation',
		'Excerpt Generation',
		'ai'
	)
	&& '生成文章摘要' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate excerpt',
		'Generate excerpt',
		'ai'
	)
	&& '重新生成文章摘要' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Regenerate excerpt',
		'Regenerate excerpt',
		'ai'
	)
	&& '生成文章摘要失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Failed to generate excerpt.',
		'Failed to generate excerpt.',
		'ai'
	)
	&& '文章内容至少达到 %d 个字符后可生成文章摘要。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Excerpt generation will be available when the post content has at least %d characters.',
		'Excerpt generation will be available when the post content has at least %d characters.',
		'ai'
	),
	'AI plugin localization distinguishes post excerpts from summary blocks and translates excerpt eligibility guidance.'
);

maca_assert(
	'内容总结' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Content Summary',
		'Content Summary',
		'ai'
	)
	&& '重新生成摘要区块' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Regenerate Summary',
		'Regenerate Summary',
		'ai'
	)
	&& '生成摘要区块失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Failed to generate summary.',
		'Failed to generate summary.',
		'ai'
	),
	'AI plugin localization translates summarization editor controls.'
);

maca_assert(
	'生成替代文本' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Alt Text',
		'Generate Alt Text',
		'ai'
	)
	&& '替代文本' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Alt text',
		'Alt text',
		'ai'
	)
	&& '替代文本' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Alt Text',
		'Alt Text',
		'ai'
	)
	&& '正在生成替代文本…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generating alt text…',
		'Generating alt text…',
		'ai'
	)
	&& '替代文本已生成并应用。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Alt text generated and applied.',
		'Alt text generated and applied.',
		'ai'
	)
	&& '忽略' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Dismiss',
		'Dismiss',
		'ai'
	),
	'AI plugin localization translates alt text editor controls and statuses.'
);

maca_assert(
	'生成编辑建议' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Editorial Note',
		'Generate Editorial Note',
		'ai'
	)
	&& '已添加 %d 条建议。请保存以保留更改。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d suggestions added. Save to keep changes.',
		'%d suggestions added. Save to keep changes.',
		'ai'
	)
	&& '已添加 %d 条建议，可在<a>此处</a>查看这些建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d suggestions added, view those Notes <a>here</a>.',
		'%d suggestions added, view those Notes <a>here</a>.',
		'ai'
	)
	&& '应用编辑更新' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Apply Editorial Updates',
		'Apply Editorial Updates',
		'ai'
	)
	&& '正在优化区块（%1$s/%2$s）…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Refining block (%1$s of %2$s)…',
		'Refining block (%1$s of %2$s)…',
		'ai'
	),
	'AI plugin localization translates editorial note and update editor controls.'
);

maca_assert(
	'别名生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Slug Generation',
		'Slug Generation',
		'ai'
	)
	&& '根据文章标题或内容生成利于 SEO 的永久链接别名建议。需要支持文本生成模型的 AI 连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Suggests SEO-friendly permalink slugs from post title or content. Requires an AI connector that includes support for text generation models.',
		'Suggests SEO-friendly permalink slugs from post title or content. Requires an AI connector that includes support for text generation models.',
		'ai'
	)
	&& '内容翻译' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Content Translation',
		'Content Translation',
		'ai'
	)
	&& '将段落和标题区块翻译为其他语言。需要支持文本生成模型的 AI 连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Translate paragraph and heading blocks into a different language. Requires an AI connector that includes support for text generation models.',
		'Translate paragraph and heading blocks into a different language. Requires an AI connector that includes support for text generation models.',
		'ai'
	)
	&& '自定义能力' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Custom Abilities',
		'Custom Abilities',
		'ai'
	)
	&& '注册插件的自定义 WordPress 能力，供 Abilities API 和 MCP 使用。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Register the plugin\'s custom WordPress Abilities for use via the Abilities API and MCP.',
		'Register the plugin\'s custom WordPress Abilities for use via the Abilities API and MCP.',
		'ai'
	)
	&& '已启用 %d 个实验功能' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d experiments enabled',
		'%d experiments enabled',
		'ai'
	)
	&& '已停用 %d 个实验功能' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d experiments disabled',
		'%d experiments disabled',
		'ai'
	),
	'AI plugin localization translates the 1.3.0 settings screen experiment labels.'
);

maca_assert(
	'生成别名' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Slug',
		'Generate Slug',
		'ai'
	)
	&& '正在生成建议…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generating suggestions…',
		'Generating suggestions…',
		'ai'
	)
	&& '文章内容至少达到 %d 个字符后可生成别名建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Slug suggestions will be available when the post content has at least %d characters.',
		'Slug suggestions will be available when the post content has at least %d characters.',
		'ai'
	)
	&& '（未设置别名）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'(no slug set)',
		'(no slug set)',
		'ai'
	)
	&& '将应用为“%s”。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Will be applied as “%s”.',
		'Will be applied as “%s”.',
		'ai'
	)
	&& '此文本不能用作别名。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'This text cannot be used as a slug.',
		'This text cannot be used as a slug.',
		'ai'
	)
	&& '生成别名失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Failed to generate slug.',
		'Failed to generate slug.',
		'ai'
	),
	'AI plugin localization translates the slug generation editor sidebar.'
);

maca_assert(
	'翻译' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Translate',
		'Translate',
		'ai'
	)
	&& '翻译为' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Translate to',
		'Translate to',
		'ai'
	)
	&& '正在翻译区块…（%1$d/%2$d）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Translating blocks… (%1$d/%2$d)',
		'Translating blocks… (%1$d/%2$d)',
		'ai'
	)
	&& '同时翻译标题' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Also translate the title',
		'Also translate the title',
		'ai'
	)
	&& '文章中没有可翻译的内容。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No translatable content found in the post.',
		'No translatable content found in the post.',
		'ai'
	)
	&& '简体中文' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Chinese (Simplified)',
		'Chinese (Simplified)',
		'ai'
	)
	&& '英语（美国）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'English (US)',
		'English (US)',
		'ai'
	)
	&& '日语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Japanese',
		'Japanese',
		'ai'
	),
	'AI plugin localization translates the content translation sidebar and language names.'
);

maca_assert(
	'AI 插件' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'AI Plugin',
		'AI Plugin',
		'ai'
	)
	&& 'AI 已启用' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'AI enabled',
		'AI enabled',
		'ai'
	)
	&& '是' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Yes',
		'Yes',
		'ai'
	)
	&& '否' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No',
		'No',
		'ai'
	)
	&& '插件版本' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Plugin version',
		'Plugin version',
		'ai'
	)
	&& '未配置 AI 凭据' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No AI credentials configured',
		'No AI credentials configured',
		'ai'
	)
	&& '尚未配置任何 AI 连接器凭据。在至少设置一个连接器之前，AI 功能不会启用。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No AI connector credentials have been configured. AI features will not be active until at least one connector is set up.',
		'No AI connector credentials have been configured. AI features will not be active until at least one connector is set up.',
		'ai'
	)
	&& '完成以下步骤即可开始使用 AI 插件：' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Complete these steps to get started with the AI plugin:',
		'Complete these steps to get started with the AI plugin:',
		'ai'
	)
	&& '提供方能力' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Provider Capabilities',
		'Provider Capabilities',
		'ai'
	),
	'AI plugin localization translates site health and dashboard status labels.'
);

maca_assert(
	'导出设置' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Export settings',
		'Export settings',
		'ai'
	)
	&& '导入设置' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Import settings',
		'Import settings',
		'ai'
	)
	&& '正在导入设置…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Importing settings…',
		'Importing settings…',
		'ai'
	)
	&& '导出设置失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Failed to export settings.',
		'Failed to export settings.',
		'ai'
	)
	&& '设置导入成功。已导入 %1$d 项设置，%2$d 项因取值无效被拒绝。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Settings imported successfully. %1$d setting(s) imported, %2$d rejected due to invalid values.',
		'Settings imported successfully. %1$d setting(s) imported, %2$d rejected due to invalid values.',
		'ai'
	)
	&& '这将覆盖你现有的 AI 设置。确定继续吗？' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'This will overwrite your existing AI settings. Are you sure?',
		'This will overwrite your existing AI settings. Are you sure?',
		'ai'
	),
	'AI plugin localization translates the settings import and export surface.'
);

maca_assert(
	'已达到批量处理上限，未能处理 %d 张图片。请减少所选图片后重试。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d images were not processed because the bulk limit was reached. Select fewer images and run the action again.',
		'%d images were not processed because the bulk limit was reached. Select fewer images and run the action again.',
		'ai'
	)
	&& '已跳过 %d 篇文章，其内容太短，无法生成摘要。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d posts were skipped because their content is too short to summarize.',
		'%d posts were skipped because their content is too short to summarize.',
		'ai'
	)
	&& '当前环境不支持嵌入生成。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Embedding generation is not available in this environment.',
		'Embedding generation is not available in this environment.',
		'ai'
	)
	&& '不支持的日志类型：%1$s。支持的类型为：%2$s。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Unsupported log type: %1$s. Supported types are: %2$s.',
		'Unsupported log type: %1$s. Supported types are: %2$s.',
		'ai'
	),
	'AI plugin localization translates 1.3.0 bulk notices and helper messages.'
);

maca_assert(
	'无法生成回复建议。请确保已连接支持文本生成的提供方。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Reply suggestion could not be generated. Please ensure you have a connected provider that supports text generation.',
		'Reply suggestion could not be generated. Please ensure you have a connected provider that supports text generation.',
		'ai'
	)
	&& '别名生成失败。请确保已连接支持文本生成的提供方。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Slug generation failed. Please ensure you have a connected provider that supports text generation.',
		'Slug generation failed. Please ensure you have a connected provider that supports text generation.',
		'ai'
	)
	&& '替代文本生成失败。请确保已连接同时支持文本生成和视觉能力的提供方。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Alt text generation failed. Please ensure you have a connected provider that supports both text generation and vision capabilities.',
		'Alt text generation failed. Please ensure you have a connected provider that supports both text generation and vision capabilities.',
		'ai'
	)
	&& '图片优化失败。请确保已连接支持图片优化（而不仅是图片生成）的提供方。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Image refinement failed. Please ensure you have a connected provider that supports image refinement, not just image generation.',
		'Image refinement failed. Please ensure you have a connected provider that supports image refinement, not just image generation.',
		'ai'
	),
	'AI plugin localization translates admin-facing ability failure notices.'
);

maca_assert(
	'分析情绪、毒性和价值' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Analyze Sentiment, Toxicity, and Value',
		'Analyze Sentiment, Toxicity, and Value',
		'ai'
	)
	&& '价值' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Value', 'Value', 'ai' )
	&& '所有价值分数' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'All Value Scores', 'All Value Scores', 'ai' )
	&& '高价值分数（>=70%）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'High Value Score (>=70%)', 'High Value Score (>=70%)', 'ai' )
	&& '中等价值分数（40%-69%）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Medium Value Score (40%-69%)', 'Medium Value Score (40%-69%)', 'ai' )
	&& '低价值分数（<40%）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Low Value Score (<40%)', 'Low Value Score (<40%)', 'ai' )
	&& '分析评论的毒性、情绪和价值。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Analyzes a comment for toxicity, sentiment, and value.',
		'Analyzes a comment for toxicity, sentiment, and value.',
		'ai'
	)
	&& '基于毒性检测和情绪分析自动审核评论，并为每条评论给出价值分数。需要支持文本生成模型的 AI 连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Automatically moderate comments based on toxicity detection and sentiment analysis and give each comment a value score. Requires an AI connector that includes support for text generation models.',
		'Automatically moderate comments based on toxicity detection and sentiment analysis and give each comment a value score. Requires an AI connector that includes support for text generation models.',
		'ai'
	)
	&& '已达到批量上限，%d 条评论未加入队列。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d comment was not queued because the batch limit was reached.',
		'%d comment was not queued because the batch limit was reached.',
		'%d comments were not queued because the batch limit was reached.',
		3,
		'ai'
	),
	'AI plugin localization translates the 1.4.0 comment moderation value score surface.'
);

maca_assert(
	'Markdown 订阅源' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Markdown Feeds', 'Markdown Feeds', 'ai' )
	&& '当请求通过 Accept 头表明偏好 Markdown 时返回 Markdown（可能与忽略 Vary 头的页面缓存冲突）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Serve Markdown when a request prefers it via the Accept header (may conflict with page caches that ignore the Vary header)',
		'Serve Markdown when a request prefers it via the Accept header (may conflict with page caches that ignore the Vary header)',
		'ai'
	)
	&& '%s Markdown 订阅源' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( '%s Markdown Feed', '%s Markdown Feed', 'ai' )
	&& '作者：%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Author: %s', 'Author: %s', 'ai' )
	&& '发布日期：%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Published: %s', 'Published: %s', 'ai' )
	&& '链接：%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Link: %s', 'Link: %s', 'ai' )
	&& '站点：%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Site: %s', 'Site: %s', 'ai' ),
	'AI plugin localization translates the 1.4.0 Markdown feeds experiment surface.'
);

maca_assert(
	'正在翻译…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Translating…', 'Translating…', 'ai' )
	&& '翻译文章标题失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Failed to translate the post title.', 'Failed to translate the post title.', 'ai' )
	&& '重试失败的翻译' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Retry failed translations', 'Retry failed translations', 'ai' )
	&& '标题至少达到 %d 个字符后可翻译。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Title translation will be available when the title has at least %d characters.',
		'Title translation will be available when the title has at least %d characters.',
		'ai'
	)
	&& '孟加拉语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Bengali', 'Bengali', 'ai' )
	&& '印度尼西亚语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Indonesian', 'Indonesian', 'ai' )
	&& '波兰语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Polish', 'Polish', 'ai' )
	&& '葡萄牙语（葡萄牙）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Portuguese (Portugal)', 'Portuguese (Portugal)', 'ai' )
	&& '俄语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Russian', 'Russian', 'ai' )
	&& '瑞典语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Swedish', 'Swedish', 'ai' )
	&& '土耳其语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Turkish', 'Turkish', 'ai' )
	&& '乌克兰语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Ukrainian', 'Ukrainian', 'ai' )
	&& '越南语' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Vietnamese', 'Vietnamese', 'ai' ),
	'AI plugin localization translates the 1.4.0 content translation additions and language names.'
);

maca_assert(
	'启用替代文本生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Enable alt text generation', 'Enable alt text generation', 'ai' )
	&& '标记为装饰性图片' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Mark as decorative', 'Mark as decorative', 'ai' )
	&& '这张图片似乎是装饰性图片。建议将其标记为装饰性图片，以便屏幕阅读器跳过。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'This image appears to be decorative. Consider marking it as decorative so screen readers can skip it.',
		'This image appears to be decorative. Consider marking it as decorative so screen readers can skip it.',
		'ai'
	)
	&& '此图片已标记为装饰性图片。此设置启用期间无法生成替代文本。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'This image is marked as decorative. Alt text generation is unavailable while this setting is enabled.',
		'This image is marked as decorative. Alt text generation is unavailable while this setting is enabled.',
		'ai'
	),
	'AI plugin localization translates the 1.4.0 alt text generation settings surface.'
);

maca_assert(
	'无法生成编辑建议：当前模板不包含文章内容区块。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Unable to generate notes: the current template does not contain a post content block.',
		'Unable to generate notes: the current template does not contain a post content block.',
		'ai'
	),
	'AI plugin localization translates the 1.4.0 editorial notes template notice.'
);

maca_assert(
	'找到 %d 个结果，可使用上下方向键导航。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d result found, use up and down arrow keys to navigate.',
		'%d result found, use up and down arrow keys to navigate.',
		'%d results found, use up and down arrow keys to navigate.',
		7,
		'ai'
	)
	&& '<div>第</div>%1$s<div>页，共 %2$d 页</div>' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'<div>Page</div>%1$s<div>of %2$d</div>',
		'<div>Page</div>%1$s<div>of %2$d</div>',
		'ai'
	)
	&& '正在加载建议' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Loading suggestions', 'Loading suggestions', 'ai' ),
	'AI plugin localization translates the 1.4.0 request log accessibility and pagination copy.'
);

maca_assert(
	'%s（已弃用）' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( '%s (deprecated)', '%s (deprecated)', 'ai' )
	&& '已弃用：`%1$s` 自版本 %2$s 起已弃用。请改用 `%3$s`。%4$s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Deprecated: `%1$s` is deprecated since version %2$s. Use `%3$s` instead. %4$s',
		'Deprecated: `%1$s` is deprecated since version %2$s. Use `%3$s` instead. %4$s',
		'ai'
	)
	&& '嵌入模型以模型 ID 形式给出时，必须指定提供方。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'A provider must be specified when the embedding model is given as a model ID.',
		'A provider must be specified when the embedding model is given as a model ID.',
		'ai'
	)
	&& '必须指定嵌入模型。嵌入向量只能与同一模型生成的嵌入向量比较，因此系统不会自动选择模型。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'An embedding model must be specified. Embeddings are only comparable to other embeddings from the same model, so no model is selected automatically.',
		'An embedding model must be specified. Embeddings are only comparable to other embeddings from the same model, so no model is selected automatically.',
		'ai'
	),
	'AI plugin localization translates the 1.4.0 helper deprecation and embedding notices.'
);

maca_assert(
	'%d 条评论已加入分析队列。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d comment queued for analysis.',
		'%d comment queued for analysis.',
		'%d comments queued for analysis.',
		1,
		'ai'
	)
	&& '%d 条评论已加入分析队列。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d comment queued for analysis.',
		'%d comment queued for analysis.',
		'%d comments queued for analysis.',
		5,
		'ai'
	)
	&& '%d 个插件或主题正在请求访问 AI 连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d plugin or theme is requesting access to an AI connector.',
		'%d plugin or theme is requesting access to an AI connector.',
		'%d plugins or themes are requesting access to AI connectors.',
		3,
		'ai'
	)
	&& 'Unknown plural source' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'Unknown plural source',
		'Unknown plural source',
		'Unknown plural sources',
		2,
		'ai'
	)
	&& 'Already resolved plural' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'Already resolved plural',
		'One default item',
		'Many default items',
		2,
		'default'
	),
	'AI plugin localization translates server-rendered plural strings through ngettext.'
);

maca_assert(
	'最近 24 小时' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Last 24 Hours',
		'Last 24 Hours',
		'ai'
	)
	&& '请求' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Requests',
		'Requests',
		'ai'
	)
	&& '清空所有日志' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Purge All Logs',
		'Purge All Logs',
		'ai'
	)
	&& '请求详情' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Request Details',
		'Request Details',
		'ai'
	)
	&& '输入预览' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Input Preview',
		'Input Preview',
		'ai'
	)
	&& '输出预览' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Output Preview',
		'Output Preview',
		'ai'
	)
	&& 'Token 用量' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Token Usage',
		'Token Usage',
		'ai'
	)
	&& '输入 Token' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Input Tokens',
		'Input Tokens',
		'ai'
	)
	&& '输出 Token' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Output Tokens',
		'Output Tokens',
		'ai'
	)
	&& '复制日志 ID' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Copy Log ID',
		'Copy Log ID',
		'ai'
	)
	&& '日志 ID 已复制到剪贴板。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Log ID copied to clipboard.',
		'Log ID copied to clipboard.',
		'ai'
	)
	&& 'Token 范围' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Token Range',
		'Token Range',
		'ai'
	)
	&& '少于 500 Token' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'< 500 tokens',
		'< 500 tokens',
		'ai'
	)
	&& '无 Token' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No Tokens',
		'No Tokens',
		'ai'
	)
	&& '无法加载筛选元数据。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Unable to load filter metadata.',
		'Unable to load filter metadata.',
		'ai'
	)
	&& '来源文件' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Source File',
		'Source File',
		'ai'
	)
	&& 'Base64 图片导入' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Base64 Image Import',
		'Base64 Image Import',
		'ai'
	)
	&& '图片提示词生成' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Image Prompt Generation',
		'Image Prompt Generation',
		'ai'
	)
	&& '生成的图片输出' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generated image output',
		'Generated image output',
		'ai'
	)
	&& '未找到日志条目。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Log entry not found.',
		'Log entry not found.',
		'ai'
	)
	&& '已选择 %d 项' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d Items selected',
		'%d Items selected',
		'ai'
	),
	'AI plugin localization translates request log page labels.'
);

maca_assert(
	'AI 状态' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'AI Status',
		'AI Status',
		'ai'
	)
	&& '审批矩阵' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Approval matrix',
		'Approval matrix',
		'ai'
	)
	&& '按 AI 提供方筛选。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Filter by AI provider.',
		'Filter by AI provider.',
		'ai'
	)
	&& '提供方 / 模型' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Provider / Model',
		'Provider / Model',
		'ai'
	)
	&& '待处理请求' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Pending requests',
		'Pending requests',
		'ai'
	)
	&& '允许 %1$s 使用 %2$s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Allow %1$s to use %2$s',
		'Allow %1$s to use %2$s',
		'ai'
	)
	&& '当前没有已注册的 AI 连接器。请先配置连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No AI connectors are currently registered. Configure a connector first.',
		'No AI connectors are currently registered. Configure a connector first.',
		'ai'
	)
	&& '%d 个插件或主题正在请求访问 AI 连接器。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d plugins or themes are requesting access to AI connectors.',
		'%d plugins or themes are requesting access to AI connectors.',
		'ai'
	)
	&& '“%1$s” AI 连接器尚未获准供“%2$s”使用。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'The "%1$s" AI connector has not been approved for use by "%2$s".',
		'The "%1$s" AI connector has not been approved for use by "%2$s".',
		'ai'
	)
	&& '加载审批数据失败。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Failed to load approval data.',
		'Failed to load approval data.',
		'ai'
	)
	&& '查看请求' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Review requests',
		'Review requests',
		'ai'
	)
	&& '没有与所提供密钥匹配的待审批请求。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'No pending approval request matches the provided key.',
		'No pending approval request matches the provided key.',
		'ai'
	)
	&& '插件 basename 和连接器 ID 为必填项。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Plugin basename and connector ID are required.',
		'Plugin basename and connector ID are required.',
		'ai'
	),
	'AI plugin localization translates connector approval and status labels.'
);

maca_assert(
	'插件或主题使用本站配置的 AI 连接器前，需要管理员明确审批。启用此功能后，在有获批连接器可用前，所有 AI 交互（包括 AI 插件发起的交互）都会被阻止。此功能仍是实验性概念验证，可能会遇到问题，欢迎反馈以帮助完善。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Require explicit administrator approval before plugins or themes can use AI connectors configured on this site. Enabling this feature will block all AI interactions, including those from the AI plugin, until an approved connector is available. Note this is an experimental, proof-of-concept feature and as such, issues may be encountered. Feedback welcome and desired to help shape the feature.',
		'Require explicit administrator approval before plugins or themes can use AI connectors configured on this site. Enabling this feature will block all AI interactions, including those from the AI plugin, until an approved connector is available. Note this is an experimental, proof-of-concept feature and as such, issues may be encountered. Feedback welcome and desired to help shape the feature.',
		'ai'
	)
	&& '建议回复' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Suggest Reply',
		'Suggest Reply',
		'ai'
	)
	&& '在“评论”界面和“活动”小工具中添加“建议回复”操作，让审核人员可以快速生成评论回复建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Adds a "Suggest Reply" action to the Comments screen and Activity widget, enabling moderators to quickly generate comment reply suggestions.',
		'Adds a "Suggest Reply" action to the Comments screen and Activity widget, enabling moderators to quickly generate comment reply suggestions.',
		'ai'
	)
	&& '正在生成 AI 回复…' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generating AI reply…',
		'Generating AI reply…',
		'ai'
	),
	'AI plugin localization translates the current connector approval and suggest reply UI.'
);

maca_assert(
	'AI 插件初始化失败：%s' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'AI Plugin initialization failed: %s',
		'AI Plugin initialization failed: %s',
		'ai'
	)
	&& 'AI 插件因以下问题无法运行：' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'AI plugin cannot run due to the following issues:',
		'AI plugin cannot run due to the following issues:',
		'ai'
	)
	&& '需要 PHP %1$s 或更高版本。当前运行的是 PHP %2$s。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'PHP version %1$s or higher is required. You are running PHP version %2$s.',
		'PHP version %1$s or higher is required. You are running PHP version %2$s.',
		'ai'
	)
	&& '需要 WordPress %s 或更高版本。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'WordPress version %s or higher is required.',
		'WordPress version %s or higher is required.',
		'ai'
	)
	&& '缺少“%1$s”的资源文件，无法注册。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Asset file for "%1$s" is missing and cannot be registered.',
		'Asset file for "%1$s" is missing and cannot be registered.',
		'ai'
	)
	&& '缺少 RTL 样式表“%1$s”，因此不可用。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'RTL stylesheet "%1$s" is missing and will not be available.',
		'RTL stylesheet "%1$s" is missing and will not be available.',
		'ai'
	)
	&& '插件资源尚未构建。这很可能是因为你从 GitHub 仓库下载了插件但未构建资源。请运行 `nvm use && npm ci && npm run build` 来构建资源。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'The plugin assets are not built. This is most likely because you downloaded the plugin from the GitHub repository without building the assets. Please run `nvm use && npm ci && npm run build` to build the assets.',
		'The plugin assets are not built. This is most likely because you downloaded the plugin from the GitHub repository without building the assets. Please run `nvm use && npm ci && npm run build` to build the assets.',
		'ai'
	)
	&& '你没有足够权限访问此页面。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'You do not have sufficient permissions to access this page.',
		'You do not have sufficient permissions to access this page.',
		'ai'
	),
	'AI plugin localization translates fixed admin requirement and permission messages.'
);

maca_assert(
	'能力总数' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Total Abilities',
		'Total Abilities',
		'ai'
	)
	&& '所有提供方' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'All Providers',
		'All Providers',
		'ai'
	)
	&& '查看' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'View',
		'View',
		'ai'
	),
	'AI plugin localization translates abilities explorer labels.'
);

maca_assert(
	'描述' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Description',
		'Description',
		'ai'
	)
	&& '详情' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Details',
		'Details',
		'ai'
	)
	&& '原始数据' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Raw Data',
		'Raw Data',
		'ai'
	)
	&& '输入 Schema' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Input Schema',
		'Input Schema',
		'ai'
	),
	'AI plugin localization translates abilities explorer detail labels.'
);

maca_assert(
	'测试能力：' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Test Ability:',
		'Test Ability:',
		'ai'
	)
	&& '输入数据' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Input Data',
		'Input Data',
		'ai'
	)
	&& '调用能力' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Invoke Ability',
		'Invoke Ability',
		'ai'
	)
	&& '验证输入' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Validate Input',
		'Validate Input',
		'ai'
	)
	&& '清除结果' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Clear Result',
		'Clear Result',
		'ai'
	),
	'AI plugin localization translates ability test runner labels.'
);

maca_assert(
	'官方翻译优先保留' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'官方翻译优先保留',
		'Generate Image',
		'ai'
	)
	&& '生成图片' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext( 'Generate Image', 'Generate Image', 'ai' )
	&& '官方复数翻译优先保留' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'官方复数翻译优先保留',
		'%d comment queued for analysis.',
		'%d comments queued for analysis.',
		5,
		'ai'
	)
	&& '%d 条评论已加入分析队列。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d comment queued for analysis.',
		'%d comment queued for analysis.',
		'%d comments queued for analysis.',
		1,
		'ai'
	)
	&& '%d 条评论已加入分析队列。' === Npcink_Cloud_AI_Plugin_Localization::filter_ngettext(
		'%d comments queued for analysis.',
		'%d comment queued for analysis.',
		'%d comments queued for analysis.',
		5,
		'ai'
	),
	'AI plugin localization stands down per string when an official ai-domain translation already exists.'
);

maca_assert(
	'Generate Image' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'default'
	),
	'AI plugin localization does not translate other text domains.'
);

$GLOBALS['maca_locale'] = 'en_US';
maca_assert(
	'Generate Image' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'ai'
	),
	'AI plugin localization is inactive outside Chinese locales.'
);

$GLOBALS['maca_locale'] = 'zh_CN';
$GLOBALS['maca_is_admin'] = false;
maca_assert(
	'Generate Image' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Generate Image',
		'Generate Image',
		'ai'
	),
	'AI plugin localization is inactive outside wp-admin.'
);

$GLOBALS['maca_is_admin'] = true;
Npcink_Cloud_AI_Plugin_Localization::enqueue_script_locale_data();
$enqueued_script = $GLOBALS['maca_enqueued_scripts'][0] ?? array();
$localized_script = $GLOBALS['maca_localized_scripts'][0] ?? array();
$locale_data = isset( $localized_script['data']['localeData'] ) && is_array( $localized_script['data']['localeData'] )
	? $localized_script['data']['localeData']
	: array();
maca_assert(
	'npcink-cloud-addon-ai-plugin-localization' === ( $enqueued_script['handle'] ?? '' )
	&& in_array( 'wp-i18n', $enqueued_script['deps'] ?? array(), true )
	&& false === ( $enqueued_script['in_footer'] ?? true )
	&& 'NpcinkCloudAiPluginLocalization' === ( $localized_script['object_name'] ?? '' )
	&& '生成图片' === ( $locale_data['Generate Image'][0] ?? '' )
	&& '生成特色图片' === ( $locale_data['Generate featured image'][0] ?? '' )
	&& '画笔大小' === ( $locale_data['Brush size'][0] ?? '' )
	&& '能力浏览器' === ( $locale_data['Abilities Explorer'][0] ?? '' )
	&& '配置 AI 提供方' === ( $locale_data['Configure an AI provider'][0] ?? '' )
	&& '生成文章摘要' === ( $locale_data['Generate excerpt'][0] ?? '' )
	&& '文章内容至少达到 %d 个字符后可生成文章摘要。' === ( $locale_data['Excerpt generation will be available when the post content has at least %d characters.'][0] ?? '' )
	&& '生成摘要区块' === ( $locale_data['Generate Summary'][0] ?? '' )
	&& '文章内容至少达到 %d 个字符后可生成编辑建议。' === ( $locale_data['Editorial Notes will be available when the post content has at least %d characters.'][0] ?? '' )
	&& '文章内容至少达到 %d 个字符后可生成 SEO 描述。' === ( $locale_data['Meta Description generation will be available when the post content has at least %d characters.'][0] ?? '' )
	&& '文章内容至少达到 %d 个字符后可使用内容分类建议。' === ( $locale_data['Content Classification will be available when the post content has at least %d characters.'][0] ?? '' )
	&& '分析情绪、毒性和价值' === ( $locale_data['Analyze Sentiment, Toxicity, and Value'][0] ?? '' )
	&& '价值' === ( $locale_data['Value'][0] ?? '' )
	&& '孟加拉语' === ( $locale_data['Bengali'][0] ?? '' )
	&& '启用替代文本生成' === ( $locale_data['Enable alt text generation'][0] ?? '' )
	&& '正在翻译…' === ( $locale_data['Translating…'][0] ?? '' )
	&& '%s Markdown 订阅源' === ( $locale_data['%s Markdown Feed'][0] ?? '' )
	&& '找到 %d 个结果，可使用上下方向键导航。' === ( $locale_data['%d results found, use up and down arrow keys to navigate.'][0] ?? '' )
	&& 'SEO 描述' === ( $locale_data['Meta Description'][0] ?? '' )
	&& '建议%s' === ( $locale_data['Suggest %s'][0] ?? '' )
	&& '添加“%s”' === ( $locale_data['Add "%s"'][0] ?? '' )
	&& '别名生成' === ( $locale_data['Slug Generation'][0] ?? '' )
	&& '自定义能力' === ( $locale_data['Custom Abilities'][0] ?? '' )
	&& '文章内容至少达到 %d 个字符后可生成别名建议。' === ( $locale_data['Slug suggestions will be available when the post content has at least %d characters.'][0] ?? '' )
	&& '简体中文' === ( $locale_data['Chinese (Simplified)'][0] ?? '' )
	&& '设置导入成功。' === ( $locale_data['Settings imported successfully.'][0] ?? '' )
	&& '调整内容长度' === ( $locale_data['Resize Content'][0] ?? '' )
	&& '替代文本' === ( $locale_data['Alt text'][0] ?? '' )
	&& '应用编辑更新' === ( $locale_data['Apply Editorial Updates'][0] ?? '' )
	&& '最近 24 小时' === ( $locale_data['Last 24 Hours'][0] ?? '' )
	&& '请求详情' === ( $locale_data['Request Details'][0] ?? '' )
	&& 'AI 状态' === ( $locale_data['AI Status'][0] ?? '' )
	&& 'Token 范围' === ( $locale_data['Token Range'][0] ?? '' )
	&& '输入预览' === ( $locale_data['Input Preview'][0] ?? '' )
	&& '日志 ID 已复制到剪贴板。' === ( $locale_data['Log ID copied to clipboard.'][0] ?? '' )
	&& '待处理请求' === ( $locale_data['Pending requests'][0] ?? '' )
	&& '所有提供方' === ( $locale_data['All Providers'][0] ?? '' )
	&& '调用能力' === ( $locale_data['Invoke Ability'][0] ?? '' )
	&& '无效的 JSON 输入' === ( $locale_data['Invalid JSON input'][0] ?? '' )
	&& '建议回复' === ( $locale_data['Suggest Reply'][0] ?? '' )
	&& '更改回复语气' === ( $locale_data['Change reply tone'][0] ?? '' )
	&& '正在生成 AI 回复…' === ( $locale_data['Generating AI reply…'][0] ?? '' )
	&& '无法生成回复建议。请确保已连接支持文本生成的提供方。' === ( $locale_data['Reply suggestion could not be generated. Please ensure you have a connected provider that supports text generation.'][0] ?? '' )
	&& '%d 条评论已加入分析队列。' === ( $locale_data['%d comment queued for analysis.'][0] ?? '' ),
	'AI plugin localization enqueues an asset-backed wp.i18n locale data shim for JS admin screens.'
);
