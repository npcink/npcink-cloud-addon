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
	&& '全局启用 AI 功能' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Globally enable AI Features',
		'Globally enable AI Features',
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
	),
	'AI plugin localization translates editor action labels.'
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
	'分析情绪和毒性' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Analyze Sentiment and Toxicity',
		'Analyze Sentiment and Toxicity',
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
		'Taxonomy Strategy',
		'Taxonomy Strategy',
		'ai'
	)
	&& '最大建议数量' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Maximum Suggestions',
		'Maximum Suggestions',
		'ai'
	)
	&& '分类策略' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Taxonomy strategy',
		'Taxonomy strategy',
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
	&& '请添加更多内容以启用 AI 建议（约 150 个词）。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'Add more content to enable AI suggestions (approximately 150 words).',
		'Add more content to enable AI suggestions (approximately 150 words).',
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
	&& '+%d 个词' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'+%d words',
		'+%d words',
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
	),
	'AI plugin localization distinguishes post excerpts from summary blocks.'
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
	&& '已添加 %d 条建议。' === Npcink_Cloud_AI_Plugin_Localization::filter_gettext(
		'%d suggestions added.',
		'%d suggestions added.',
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
	&& 'NpcinkCloudAiPluginLocalization' === ( $localized_script['object_name'] ?? '' )
	&& '生成图片' === ( $locale_data['Generate Image'][0] ?? '' )
	&& '生成特色图片' === ( $locale_data['Generate featured image'][0] ?? '' )
	&& '画笔大小' === ( $locale_data['Brush size'][0] ?? '' )
	&& '能力浏览器' === ( $locale_data['Abilities Explorer'][0] ?? '' )
	&& '配置 AI 提供方' === ( $locale_data['Configure an AI provider'][0] ?? '' )
	&& '生成文章摘要' === ( $locale_data['Generate excerpt'][0] ?? '' )
	&& '生成摘要区块' === ( $locale_data['Generate Summary'][0] ?? '' )
	&& '分析情绪和毒性' === ( $locale_data['Analyze Sentiment and Toxicity'][0] ?? '' )
	&& 'SEO 描述' === ( $locale_data['Meta Description'][0] ?? '' )
	&& '建议%s' === ( $locale_data['Suggest %s'][0] ?? '' )
	&& '请添加更多内容以启用 AI 建议（约 150 个词）。' === ( $locale_data['Add more content to enable AI suggestions (approximately 150 words).'][0] ?? '' )
	&& '添加“%s”' === ( $locale_data['Add "%s"'][0] ?? '' )
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
	&& '无效的 JSON 输入' === ( $locale_data['Invalid JSON input'][0] ?? '' ),
	'AI plugin localization enqueues an asset-backed wp.i18n locale data shim for JS admin screens.'
);
