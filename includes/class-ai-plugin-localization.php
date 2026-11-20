<?php
/**
 * Bounded compatibility localization for the WordPress AI plugin admin UI.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_AI_Plugin_Localization' ) ) {
	/**
	 * Provides a narrow zh_CN compatibility layer for high-traffic AI plugin UI strings.
	 */
	final class Npcink_Cloud_AI_Plugin_Localization {
		private const AI_TEXT_DOMAIN = 'ai';

		/**
		 * Whether the inline JS locale payload already shipped this request.
		 *
		 * @var bool
		 */
		private static bool $script_payload_enqueued = false;

		/**
		 * Registers admin-only localization hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_filter( 'gettext', array( __CLASS__, 'filter_gettext' ), 20, 3 );
			add_filter( 'ngettext', array( __CLASS__, 'filter_ngettext' ), 20, 5 );
			add_filter( 'rest_post_dispatch', array( __CLASS__, 'filter_rest_ui_error' ), 20, 3 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_script_locale_data' ), 1 );
		}

		/**
		 * Translates selected AI plugin PHP strings only when upstream left them untranslated.
		 *
		 * The shim stands down per string: an official ai-domain zh_CN language
		 * pack wins wherever it already provides a translation, and the map
		 * fills only the remaining gaps.
		 *
		 * @param string $translation Existing translation.
		 * @param string $text Original English string.
		 * @param string $domain Text domain.
		 * @return string
		 */
		public static function filter_gettext( string $translation, string $text, string $domain ): string {
			if ( self::AI_TEXT_DOMAIN !== $domain || ! self::should_localize() ) {
				return $translation;
			}

			$translations = self::translations();

			if ( $translation === $text && isset( $translations[ $text ] ) ) {
				return $translations[ $text ];
			}

			return $translation;
		}

		/**
		 * Translates selected AI plugin PHP plural strings only when upstream left them untranslated.
		 *
		 * Chinese locales have no plural distinction, so the untranslated
		 * resolved form equals the singular or plural source key.
		 *
		 * @param string $translation Existing translation.
		 * @param string $single      Singular original string.
		 * @param string $plural      Plural original string.
		 * @param int    $number      Number used for plural selection.
		 * @param string $domain      Text domain.
		 * @return string
		 */
		public static function filter_ngettext( $translation, $single, $plural, $number, $domain = 'default' ) {
			if ( self::AI_TEXT_DOMAIN !== $domain || ! self::should_localize() ) {
				return $translation;
			}

			$translations = self::translations();

			if ( ( $translation === $single || $translation === $plural ) && isset( $translations[ $single ] ) ) {
				return $translations[ $single ];
			}

			return $translation;
		}

		/**
		 * Localizes two fixed UI errors returned outside wp-admin by REST.
		 *
		 * @param WP_HTTP_Response $response Dispatched response.
		 * @param WP_REST_Server   $server REST server.
		 * @param WP_REST_Request  $request Current request.
		 * @return WP_HTTP_Response
		 */
		public static function filter_rest_ui_error( $response, $server, $request ) {
			if ( ! in_array( $request->get_route(), array( '/ai/v1/settings/import', '/wp-abilities/v1/abilities/ai/slug-generation/run' ), true )
				|| 'POST' !== $request->get_method() || $response->get_status() < 400 ) {
				return $response;
			}
			$data = $response->get_data();
			$capability = '/ai/v1/settings/import' === $request->get_route() ? 'manage_options' : 'edit_posts';
			if ( is_array( $data ) && isset( $data['code'], $data['message'] ) && is_string( $data['code'] ) && is_string( $data['message'] ) ) {
				$data['message'] = self::rest_ui_error_message(
					$data['message'], (string) $data['code'], $request->get_route(), $request->get_method(),
					get_user_locale(), current_user_can( $capability )
				);
				$response->set_data( $data );
			}
			return $response;
		}

		/**
		 * Keeps REST compatibility bounded to exact untranslated errors and routes.
		 *
		 * @param string $message Existing message.
		 * @param string $code Error code.
		 * @param string $route REST route.
		 * @param string $method Request method.
		 * @param string $locale Authenticated user's locale.
		 * @param bool   $authorized Existing user's required capability.
		 * @return string
		 */
		public static function rest_ui_error_message( string $message, string $code, string $route, string $method, string $locale, bool $authorized ): string {
			if ( ! $authorized || 'POST' !== $method || 0 !== strpos( strtolower( str_replace( '-', '_', $locale ) ), 'zh_' ) ) {
				return $message;
			}
			if ( '/ai/v1/settings/import' === $route && 'unsupported_schema_version' === $code
				&& preg_match( '/^Unsupported schema version: (-?\d+)\.$/D', $message, $matches ) ) {
				return sprintf( self::translations()['Unsupported schema version: %d.'], (int) $matches[1] );
			}
			if ( '/wp-abilities/v1/abilities/ai/slug-generation/run' === $route && 'no_results' === $code
				&& 'No slug suggestion was generated.' === $message ) {
				return self::translations()[ $message ];
			}
			return $message;
		}

		/**
		 * Adds JS locale data for AI plugin React/admin screens.
		 *
		 * @param string $hook Admin hook suffix from admin_enqueue_scripts.
		 *                     An empty suffix (direct call, or the no-argument
		 *                     enqueue_block_editor_assets hook) skips: every
		 *                     real admin screen already passes its suffix, and
		 *                     the post editor is covered by post.php /
		 *                     post-new.php.
		 * @return void
		 */
		public static function enqueue_script_locale_data( string $hook = '' ): void {
			if ( self::$script_payload_enqueued
				|| ! self::should_localize()
				|| ! function_exists( 'wp_enqueue_script' )
				|| ! function_exists( 'wp_localize_script' ) ) {
				return;
			}
			if ( ! self::is_ai_payload_surface( $hook ) ) {
				return;
			}

			$locale_data = array(
				'' => array(
					'domain'       => self::AI_TEXT_DOMAIN,
					'lang'         => 'zh_CN',
					// Chinese has one plural form; without this, JS _n() picks index 1 and falls back to English.
					'plural_forms' => 'nplurals=1; plural=0;',
				),
			);
			foreach ( self::translations() as $source => $translation ) {
				$locale_data[ $source ] = array( $translation );
			}

			$handle = 'npcink-cloud-addon-ai-plugin-localization';
			wp_enqueue_script(
				$handle,
				plugins_url( 'assets/ai-plugin-localization.js', NPCINK_CLOUD_ADDON_FILE ),
				array( 'wp-i18n', 'wp-hooks' ),
				NPCINK_CLOUD_ADDON_VERSION,
				false
			);
			wp_localize_script(
				$handle,
				'NpcinkCloudAiPluginLocalization',
				array(
					'localeData' => $locale_data,
					'bundledUiFallback' => in_array( $hook, array( 'settings_page_ai-wp-admin', 'tools_page_ai-request-logs', 'tools_page_ai-mcp-access', 'tools_page_ai-abilities-explorer' ), true )
						? self::bundled_ui_fallback() : array(),
				)
			);
			// Both the admin and block editor hooks fire on post editor
			// screens; WordPress appends a second copy of localized data on
			// repeat calls, so the payload must ship exactly once.
			self::$script_payload_enqueued = true;
		}

		/**
		 * Exact default-domain DataViews exceptions, scoped to four AI screens.
		 *
		 * @return array<string,string>
		 */
		public static function bundled_ui_fallback(): array {
			return array_intersect_key(
				self::translations(),
				array_flip( array(
					'%d Item selected',
					'%d Items selected',
					'%d field needs attention',
					'%d fields need attention',
					'Filter',
					'Actions',
					'<div>Page</div>%1$s<div>of %2$d</div>',
					'Sort by',
					'Order',
					'Density',
					'Balanced',
					'Compact',
					'Items per page',
					'Properties',
					'Add filter',
					'View options',
					'No results',
					'Comfortable',
					'Edit %s (has errors)',
				) )
			);
		}

		/**
		 * Returns whether an admin screen renders ai-domain JS.
		 *
		 * The payload is an uncacheable inline script, so it may only ship on
		 * surfaces that consume it: the AI plugin's own admin screens, its
		 * Connectors screen, the core Comments screen and dashboard Activity
		 * widget decorated by the reply-suggestion and comment-moderation
		 * features, the media library bulk alt-text surfaces, and the post
		 * editor screens.
		 *
		 * @param string $hook Admin hook suffix.
		 * @return bool
		 */
		private static function is_ai_payload_surface( string $hook ): bool {
			if ( false !== strpos( $hook, '_page_ai-' ) ) {
				return true;
			}

			return in_array(
				$hook,
				array(
					'edit-comments.php',
					'index.php',
					'upload.php',
					'post.php',
					'post-new.php',
					'options-connectors',
				),
				true
			);
		}

		/**
		 * Returns whether the compatibility translations should run.
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
		 * Returns the fixed AI plugin translation map, built once per request.
		 *
		 * @return array<string,string>
		 */
		public static function translations(): array {
			static $map = null;
			if ( null === $map ) {
				$map = self::translation_entries();
			}

			return $map;
		}

		/**
		 * Returns the uncached AI plugin translation entries.
		 *
		 * @return array<string,string>
		 */
		private static function translation_entries(): array {
			return array(
				'AI' => 'AI',
				// WordPress AI plugin 1.5.0: fixed settings labels and usage disclosures.
				'Turn on stable Features and early-stage Experiments. Experiments may change or be removed in any release, and some run automatically once enabled, so try them on a staging site first.' => '启用稳定功能和早期实验功能。实验功能可能在任何版本中变更或移除，部分功能启用后会自动运行，建议先在测试站点试用。',
				'Early-stage AI tools for the block editor, including content generation and enhancement tools. Experiments may change or be removed in any release.' => '面向区块编辑器的早期 AI 工具，包括内容生成和增强工具。实验功能可能在任何版本中变更或移除。',
				'Early-stage AI tools for the WordPress admin area, including exploration and testing tools. Experiments may change or be removed in any release.' => '面向 WordPress 后台的早期 AI 工具，包括探索和测试工具。实验功能可能在任何版本中变更或移除。',
				'Learn' => '学习',
				'Learn more' => '了解更多',
				'%s usage' => '%s的运行说明',
				'When %s runs and what it sends' => '%s何时运行、发送哪些内容',
				'When it runs' => '运行时机',
				'Sent to your AI provider' => '发送给 AI 提供方的内容',
				'Runs automatically' => '自动运行',
				'Your configured AI Connectors don\'t support text generation, which most AI features require.' => '当前配置的 AI 连接器不支持文本生成，大多数 AI 功能需要此能力。',
				'Records every AI request that filters through the WP AI Client' => '记录经由 WP AI Client 发起的每次 AI 请求',
				'Nothing' => '不发送内容',
				'Keeps prompt and response previews until you delete them' => '保留提示词和响应预览，直到你将其删除',
				'When you click Invoke Ability' => '点击“调用能力”时',
				'Depends on the ability being invoked' => '取决于所调用的能力',
				'When you click Generate Alt Text or run the bulk action, and automatically when you import an image made with Image Generation' => '点击“生成替代文本”或执行批量操作时；导入使用“图片生成”制作的图片时也会自动运行',
				'The image, plus post content when used in the editor' => '图片；在编辑器中使用时还会发送文章内容',
				'One request per image' => '每张图片发送一次请求',
				'Block Container Label Generation' => '容器区块标签生成',
				'Generate concise labels for content blocks. Requires an AI connector that includes support for text generation models.' => '为内容区块生成简短标签。需要支持文本生成模型的 AI 连接器。',
				'When you click Generate Block Labels in List View or Generate label in the Rename modal' => '在列表视图中点击“生成区块标签”，或在重命名弹窗中点击“生成标签”时',
				'The text of each container block and its inner blocks, the post title, and a short outline of the other container blocks and their labels' => '每个容器区块及其内部区块的文本、文章标题，以及其他容器区块和标签的简要概览',
				'One request per unlabeled container block, or one per click in the Rename modal' => '每个无标签的容器区块发送一次请求；在重命名弹窗中，每次点击发送一次请求',
				'Content Verification' => '内容凭证检测',
				'Detects C2PA Content Credentials in uploaded images, writes the raw manifest to a sidecar file, and stores a structured record in postmeta.' => '检测上传图片中的 C2PA 内容凭证，将原始清单写入附属文件，并将结构化记录保存到媒体元数据中。',
				'Automatically, on each new JPEG, PNG, or WebP upload' => '每次上传新的 JPEG、PNG 或 WebP 图片时自动运行',
				'Stores Content Credentials data with each image' => '为每张图片保存内容凭证数据',
				'Automatically, on each new comment, including guest comments by default. You can also run it from the "Analyze Sentiment, Toxicity, and Value" row action or bulk action in Comments' => '每条新评论提交后自动运行，默认包含访客评论。也可在“评论”中使用“分析情绪、毒性和价值”行操作或批量操作',
				'Comment text, author name, and a post excerpt' => '评论文本、作者姓名和文章摘要',
				'One request per new comment' => '每条新评论发送一次请求',
				'On each AI request from a plugin or theme' => '插件或主题每次发起 AI 请求时',
				'Blocks AI requests, including this plugin\'s, until you approve them' => '在你批准前阻止 AI 请求，包括本插件发起的请求',
				'When you click Suggest Tags or Suggest Categories' => '点击“建议标签”或“建议分类”时',
				'Post content, title, slug, author name, excerpt, content type, the terms already assigned, and up to 100 existing terms to choose from' => '文章内容、标题、别名、作者姓名、摘要、内容类型、已分配的分类项，以及最多 100 个供选择的现有分类项',
				'One request per click' => '每次点击发送一次请求',
				'When you choose Shorten, Expand, or Rephrase' => '选择“缩短”“扩展”或“改写”时',
				'The selected paragraph' => '选中的段落',
				'Translate the text of your blocks into a different language. Requires an AI connector that includes support for text generation models.' => '将区块中的文本翻译为另一种语言。需要支持文本生成模型的 AI 连接器。',
				'When you click Translate' => '点击“翻译”时',
				'Each paragraph and heading block, and optionally the title' => '每个段落和标题区块，以及选择包含的文章标题',
				'One request per block' => '每个区块发送一次请求',
				'When an authorized AI tool calls an ability' => '获授权的 AI 工具调用能力时',
				'Lets authorized tools read posts, users, and settings' => '允许获授权的工具读取文章、用户和设置',
				'When you click Generate Editorial Notes' => '点击“生成编辑建议”时',
				'Each block\'s text with surrounding content' => '每个区块的文本及其上下文内容',
				'Applies pending editorial Notes to your content and saves the post, without a preview. Use revisions to review or undo the changes. Requires an AI connector that includes support for text generation models.' => '将待处理的编辑建议直接应用到内容并保存文章，不提供预览。可通过修订版本查看或撤销更改。需要支持文本生成模型的 AI 连接器。',
				'When you click Apply Editorial Updates, which also saves the post' => '点击“应用编辑更新”时，同时会保存文章',
				'Each block with pending Notes, with surrounding content' => '有待处理编辑建议的每个区块及其上下文内容',
				'One request per block with Notes' => '每个有编辑建议的区块发送一次请求',
				'When you click Generate excerpt' => '点击“生成文章摘要”时',
				'Post content, title, slug, author name, excerpt, content type, and assigned categories and tags' => '文章内容、标题、别名、作者姓名、摘要、内容类型，以及已分配的分类和标签',
				'Encrypts stored AI provider API keys when enabled' => '启用后加密存储的 AI 提供方 API 密钥',
				'Restores plaintext keys when disabled' => '停用后恢复为明文密钥',
				'MCP Access' => 'MCP 访问',
				'Control which abilities are exposed to AI agents over the Model Context Protocol. Enabling this experiment installs and activates the MCP Adapter plugin if it is not already active; ability exposure choices apply only while the experiment is enabled.' => '控制通过模型上下文协议（MCP）向 AI 智能体开放哪些能力。启用此实验功能时，若 MCP Adapter 插件尚未启用，将自动安装并启用它；能力开放设置仅在此实验功能启用期间生效。',
				'When an authorized MCP client lists or runs an exposed ability' => '获授权的 MCP 客户端列出或运行已开放的能力时',
				'Nothing itself, but MCP clients can run exposed abilities that call your AI provider, such as Title Generation' => '本功能本身不发送内容，但 MCP 客户端可运行已开放的能力（如标题生成），由这些能力调用你的 AI 提供方',
				'Exposes this plugin\'s enabled abilities over MCP by default, and installs and activates the MCP Adapter plugin from WordPress.org' => '默认通过 MCP 开放本插件已启用的能力，并从 WordPress.org 安装和启用 MCP Adapter 插件',
				'On front-end requests' => '访问站点前台时',
				'Renders Markdown versions of public posts, pages, and comments' => '提供公开文章、页面和评论的 Markdown 版本',
				'When you click Generate Meta Description' => '点击“生成 SEO 描述”时',
				'Revision Change Summary' => '修订变更摘要',
				'Summarizes what changed between two revisions of a post in plain language. Requires an AI connector that includes support for text generation models.' => '用通俗语言总结文章两个修订版本之间的变化。需要支持文本生成模型的 AI 连接器。',
				'When you click Summarize changes on a revisions screen' => '在修订版本页面点击“总结更改”时',
				'The changed lines of the title and content between the two revisions' => '两个修订版本之间标题和内容中发生变化的文本行',
				'When you click Generate Slug or Generate Suggestions' => '点击“生成别名”或“生成建议”时',
				'Post content, title, current slug, author name, excerpt, content type, and assigned categories and tags' => '文章内容、标题、当前别名、作者姓名、摘要、内容类型，以及已分配的分类和标签',
				'When you click Suggest reply' => '点击“建议回复”时',
				'Comment text, author name, post title, and excerpt' => '评论文本、作者姓名、文章标题和摘要',
				'When you click Generate Summary or run the bulk action' => '点击“生成摘要区块”或执行批量操作时',
				'One request per post' => '每篇文章发送一次请求',
				'Generates an audio version of post content so visitors can listen instead of read. Requires an AI connector that includes support for text to speech models.' => '将文章内容生成为音频，方便访客收听。需要支持文本转语音模型的 AI 连接器。',
				'When you click Generate Audio or Regenerate Audio in the editor, then in the background until the audio is done' => '在编辑器中点击“生成音频”或“重新生成音频”后，在后台持续运行直至音频生成完成',
				'The post title and saved post content as plain text, plus the chosen voice' => '纯文本格式的文章标题、已保存的文章内容，以及所选音色',
				'About one request per 4,000 characters of title and content' => '标题和内容每约 4,000 个字符发送一次请求',
				'Adds the audio to the Media Library and shows a player above the post for visitors' => '将音频添加到媒体库，并在文章上方为访客显示播放器',
				'When you click Generate' => '点击“生成”时',
				'Post content, current title, slug, author name, excerpt, content type, and assigned categories and tags' => '文章内容、当前标题、别名、作者姓名、摘要、内容类型，以及已分配的分类和标签',
				'Automatically, when you pause typing at the end of a paragraph. You can also press Cmd/Ctrl + Space to ask for a suggestion anywhere in a paragraph' => '在段落末尾暂停输入时自动运行。也可在段落任意位置按 Cmd/Ctrl + 空格请求建议',
				'The current paragraph and the blocks around it' => '当前段落及其周围的区块',
				'One request per typing pause' => '每次暂停输入发送一次请求',
				'Lets an agent browser work in the block editor through WebMCP: set the title, insert and edit blocks, save and publish, with every change visible on the page as it happens.' => '允许智能体浏览器通过 WebMCP 在区块编辑器中设置标题、插入和编辑区块、保存及发布；每项更改都会实时显示在页面上。',
				'When an agent browser calls one of its tools in the block editor' => '智能体浏览器在区块编辑器中调用工具时',
				'Nothing to your AI provider. Depending on the tool called, the post title, status, and a short text preview of each block go to the AI model the agent browser uses' => '不向你的 AI 提供方发送内容。根据调用的工具，文章标题、状态和各区块的简短文本预览会发送给智能体浏览器使用的 AI 模型',
				'Lets the agent browser read the post and change its title and blocks, save, and publish it' => '允许智能体浏览器读取文章、修改标题和区块，以及保存和发布文章',
				'When you click Generate Image or Generate featured image' => '点击“生成图片”或“生成特色图片”时',
				'Your prompt; featured images also send post content, title, and post type; editing an image also sends the existing image' => '你的提示词；生成特色图片时还会发送文章内容、标题和文章类型；编辑图片时还会发送现有图片',
				'One to three requests per image' => '每张图片发送一至三次请求',
				'Configure AI features and experiments for your WordPress site.' => '配置此 WordPress 站点的 AI 功能和实验功能。',
				'AI Plugin initialization failed: %s' => 'AI 插件初始化失败：%s',
				'AI client is not available.' => 'AI 客户端不可用。',
				'AI plugin cannot run due to the following issues:' => 'AI 插件因以下问题无法运行：',
				'AI settings page render function not found. Run npm run build:routes to generate build assets.' => '未找到 AI 设置页面渲染函数。请运行 npm run build:routes 生成构建资源。',
				'Docs' => '文档',
				'Contribute' => '参与贡献',
				'Developer Tools' => '开发者工具',
				'Model selection' => '模型选择',
				'Select a specific provider and model per feature' => '为每个功能选择指定的提供方和模型',
				'Editor Experiments' => '编辑器实验功能',
				'AI-powered experiments for the block editor, including content generation and enhancement tools.' => '面向区块编辑器的 AI 实验功能，包括内容生成和增强工具。',
				'Type-ahead Text' => '输入预测文本',
				'Ghost text suggestions while writing paragraphs in the block editor. Requires an AI connector that includes support for text generation models.' => '在区块编辑器中撰写段落时显示灰色文本建议。需要支持文本生成模型的 AI 连接器。',
				'Admin Experiments' => '后台实验功能',
				'AI-powered experiments for the WordPress admin area, including exploration and testing tools.' => '面向 WordPress 后台的 AI 实验功能，包括探索和测试工具。',
				'Abilities Explorer' => '能力浏览器',
				'Discover, inspect, test, and document all abilities registered via the WordPress Abilities API.' => '发现、检查、测试并记录通过 WordPress Abilities API 注册的所有能力。',
				'Connector Approval' => '连接器审批',
				'Connector Approvals' => '连接器审批',
				'Require explicit administrator approval before plugins or themes can use AI connectors configured on this site. Enabling this feature will block all AI interactions, including those from the AI plugin, until an approved connector is available. Note this is an experimental, proof-of-concept feature and as such, issues may be encountered. Feedback welcome and desired to help shape the feature.' => '插件或主题使用本站配置的 AI 连接器前，需要管理员明确审批。启用此功能后，在有获批连接器可用前，所有 AI 交互（包括 AI 插件发起的交互）都会被阻止。此功能仍是实验性概念验证，可能会遇到问题，欢迎反馈以帮助完善。',
				'AI Request Logging' => 'AI 请求日志',
				'AI Request Logs' => 'AI 请求日志',
				'Logs AI requests for observability and debugging. View detailed logs under Tools.' => '记录 AI 请求，用于观测和调试。可在“工具”下查看详细日志。',
				'Key Encryption' => '密钥加密',
				'Encrypts AI provider API keys at rest using bundled libsodium encryption. Keys are transparently decrypted on read and re-encrypted on write. Disabling the experiment or deactivating the plugin restores plaintext keys.' => '使用内置 libsodium 加密静态存储的 AI 提供方 API 密钥。读取时会透明解密，写入时会重新加密。停用此实验功能或停用插件会恢复明文密钥。',
				'Suggest Reply' => '建议回复',
				'Adds a "Suggest Reply" action to the Comments screen and Activity widget, enabling moderators to quickly generate comment reply suggestions.' => '在“评论”界面和“活动”小工具中添加“建议回复”操作，让审核人员可以快速生成评论回复建议。',
				'Suggest reply' => '建议回复',
				'Suggest a reply to this comment' => '为这条评论建议回复',
				'Generating…' => '正在生成…',
				'Generating AI reply…' => '正在生成 AI 回复…',
				'Failed to generate a reply suggestion. Please try again.' => '无法生成回复建议。请重试。',
				'Change reply tone' => '更改回复语气',
				'Friendly' => '友好',
				'Professional' => '专业',
				'Casual' => '轻松',
				'Detailed logs of every AI request made by the WordPress AI plugin.' => '记录 WordPress AI 插件发起的每一次 AI 请求的详细日志。',
				'AI Capabilities' => 'AI 能力',
				'AI Status' => 'AI 状态',
				'API Key Settings' => 'API 密钥设置',
				'Approval matrix' => '审批矩阵',
				'Approve' => '批准',
				'Asset file for "%1$s" is invalid and cannot be registered.' => '“%1$s”的资源文件无效，无法注册。',
				'Asset file for "%1$s" is missing and cannot be registered.' => '缺少“%1$s”的资源文件，无法注册。',
				'Attempts' => '尝试次数',
				'Caller' => '调用方',
				'Connector' => '连接器',
				'Connectors' => '连接器',
				'Context' => '上下文',
				'Control which plugins and themes are allowed to use each AI connector on this site. Prompts from unapproved callers are prevented and listed below for review.' => '控制哪些插件和主题可以使用本站的每个 AI 连接器。未获批准调用方的提示会被阻止，并在下方列出以供审核。',
				'Copy Log ID' => '复制日志 ID',
				'Duration' => '耗时',
				'Edit %s (has errors)' => '编辑 %s（有错误）',
				'Failed' => '失败',
				'Failed to dismiss request.' => '忽略请求失败。',
				'Failed to load approval data.' => '加载审批数据失败。',
				'Failed to load providers.' => '加载提供方失败。',
				'Failed to save developer settings.' => '保存开发者设置失败。',
				'Failed to update approval.' => '更新审批失败。',
				'Filter by AI provider.' => '按 AI 提供方筛选。',
				'Filter by log type.' => '按日志类型筛选。',
				'Filter by status.' => '按状态筛选。',
				'Filter by user ID.' => '按用户 ID 筛选。',
				'General' => '常规',
				'Generated image output' => '生成的图片输出',
				'Generated image output (base64)' => '生成的图片输出（base64）',
				'Generated Images' => '生成的图片',
				'Image Generation' => '图片生成',
				'Image %d' => '图片 %d',
				'Input Preview' => '输入预览',
				'Input Tokens' => '输入 Token',
				'Log entry not found.' => '未找到日志条目。',
				'Log ID copied to clipboard.' => '日志 ID 已复制到剪贴板。',
				'Output Preview' => '输出预览',
				'Output Tokens' => '输出 Token',
				'Provider & Model' => '提供方和模型',
				'Provider / Model' => '提供方 / 模型',
				'Request Details' => '请求详情',
				'Request Kind' => '请求类型',
				'Refresh' => '刷新',
				'Review requests' => '查看请求',
				'Search in operations, request previews, response previews, and error messages.' => '在操作、请求预览、响应预览和错误信息中搜索。',
				'Status' => '状态',
				'Successfully purged %d log entries.' => '已成功清除 %d 条日志。',
				'Time' => '时间',
				'Time period' => '时间范围',
				'Timestamp' => '时间戳',
				'Tokens per Second' => '每秒 Token',
				'Token Usage' => 'Token 用量',
				'Total Tokens' => '总 Token',
				'Source' => '来源',
				'Source File' => '来源文件',
				'User' => '用户',
				'User ID' => '用户 ID',
				'View details' => '查看详情',
				'WordPress AI' => 'WordPress AI',
				'WordPress Core' => 'WordPress 核心',
				'%d plugin or theme is requesting access to an AI connector.' => '%d 个插件或主题正在请求访问 AI 连接器。',
				'%d plugins or themes are requesting access to AI connectors.' => '%d 个插件或主题正在请求访问 AI 连接器。',
				'%d ms' => '%d 毫秒',
				'%s/s' => '%s/秒',
				'< 100 tokens' => '少于 100 Token',
				'< 500 tokens' => '少于 500 Token',
				'< 1K tokens' => '少于 1K Token',
				'< 5K tokens' => '少于 5K Token',
				'> 1K tokens' => '超过 1K Token',
				'> 5K tokens' => '超过 5K Token',
				'> 10K tokens' => '超过 10K Token',
				'Allow %1$s to use %2$s' => '允许 %1$s 使用 %2$s',
				'The "%1$s" AI connector has not been approved for use by "%2$s".' => '“%1$s” AI 连接器尚未获准供“%2$s”使用。',
				'Cloud' => '云端',
				'Chat History' => '聊天历史',
				'Configure an AI provider' => '配置 AI 提供方',
				'Embedding Generation' => '嵌入生成',
				'Enable a feature or experiment' => '启用功能或实验',
				'Has Tokens (> 0)' => '有 Token（> 0）',
				'Last seen' => '上次出现',
				'Local' => '本地',
				'Model' => '模型',
				'Music Generation' => '音乐生成',
				'No AI connectors are currently registered. Configure a connector first.' => '当前没有已注册的 AI 连接器。请先配置连接器。',
				'No plugins or themes are currently waiting for AI access.' => '目前没有插件或主题等待 AI 访问授权。',
				'No pending approval request matches the provided key.' => '没有与所提供密钥匹配的待审批请求。',
				'No Tokens' => '无 Token',
				'Operation' => '操作',
				'Other' => '其他',
				'Pending requests' => '待处理请求',
				'PHP version %1$s or higher is required. You are running PHP version %2$s.' => '需要 PHP %1$s 或更高版本。当前运行的是 PHP %2$s。',
				'Plugin basename and connector ID are required.' => '插件 basename 和连接器 ID 为必填项。',
				'Speech Generation' => '语音生成',
				'Settings' => '设置',
				'Text Generation' => '文本生成',
				'Text to Speech' => '文本转语音',
				'Token Range' => 'Token 范围',
				'Type' => '类型',
				'Unable to load filter metadata.' => '无法加载筛选元数据。',
				'Unsupported capability: %s' => '不支持的能力：%s',
				'Video Generation' => '视频生成',
				'WordPress version %s or higher is required.' => '需要 WordPress %s 或更高版本。',
				'You do not have sufficient permissions to access this page.' => '你没有足够权限访问此页面。',
				'Your WordPress environment has AI functionality disabled. The AI plugin will not work until AI support is enabled.' => '当前 WordPress 环境已禁用 AI 功能。启用 AI 支持前，AI 插件无法工作。',
				'Last Minute' => '最近 1 分钟',
				'Last Hour' => '最近 1 小时',
				'Last 24 Hours' => '最近 24 小时',
				'Last 7 Days' => '最近 7 天',
				'Last 30 Days' => '最近 30 天',
				'All Time' => '全部时间',
				'Requests' => '请求',
				'Tokens' => 'Token',
				'Avg Time' => '平均耗时',
				'Success Rate' => '成功率',
				'Manage Logs' => '管理日志',
				'Danger Zone' => '危险操作区',
				'Permanently delete all logged requests. This action cannot be undone.' => '永久删除所有已记录的请求。此操作无法撤销。',
				'%d Item selected' => '已选择 %d 项',
				'%d Items selected' => '已选择 %d 项',
				'Purge All Logs' => '清空所有日志',
				'Are you sure?' => '确定吗？',
				'Yes, Purge All' => '是，全部清空',
				'All logs have been purged.' => '所有日志已清空。',
				'Unable to load summary data.' => '无法加载汇总数据。',
				'Something went wrong. Please try again.' => '出现问题，请重试。',
				'Dismiss' => '忽略',
				'Comment Moderation' => '评论审核',
				'Automatically moderate guest comments' => '自动审核访客评论',
				'%d comment queued for analysis.' => '%d 条评论已加入分析队列。',
				'%d comments queued for analysis.' => '%d 条评论已加入分析队列。',
				'All Sentiments' => '所有情绪',
				'All Toxicities' => '所有毒性级别',
				'Analyze this comment' => '分析此评论',
				'Analyzing…' => '正在分析…',
				'Comment Analysis' => '评论分析',
				'Filter by Sentiment' => '按情绪筛选',
				'Filter by Toxicity' => '按毒性筛选',
				'High' => '高',
				'High Toxicity (>=70%)' => '高毒性（>=70%）',
				'Low' => '低',
				'Low Toxicity (<40%)' => '低毒性（<40%）',
				'Medium' => '中',
				'Medium Toxicity (40%-69%)' => '中等毒性（40%-69%）',
				'Negative' => '负面',
				'Neutral' => '中性',
				'Positive' => '正面',
				'Queued' => '已排队',
				'Sentiment' => '情绪',
				'Settings → Connectors' => '设置 → 连接器',
				'Toxicity' => '毒性',
				'Other Features' => '其他功能',
				'Additional AI-powered features.' => '其他 AI 功能。',
				'Enable all' => '全部启用',
				'Disable all' => '全部停用',
				'Settings saved.' => '设置已保存。',
				'Failed to save settings.' => '设置保存失败。',
				'Save' => '保存',
				'Save %s settings' => '保存 %s 设置',
				'Saving settings…' => '正在保存设置…',
				'%d field needs attention' => '%d 个字段需要处理',
				'%d fields need attention' => '%d 个字段需要处理',
				'%s enabled.' => '%s 已启用。',
				'%s disabled.' => '%s 已停用。',
				'%s settings saved.' => '%s 设置已保存。',
				'Manage Connectors' => '管理连接器',
				'Reset to default' => '重置为默认值',
				'— Default —' => '— 默认 —',
				'The AI plugin requires a valid AI Connector to function properly. Please review the AI Connectors you have configured to ensure they are valid.' => 'AI 插件需要有效的 AI 连接器才能正常工作。请检查已配置的 AI 连接器，确保它们有效。',
				'The AI plugin requires a valid AI Connector to function properly. Verify you have one or more AI Connectors configured.' => 'AI 插件需要有效的 AI 连接器才能正常工作。请确认已配置一个或多个 AI 连接器。',
				'The plugin assets are not built. This is most likely because you downloaded the plugin from the GitHub repository without building the assets. Please run `nvm use && npm ci && npm run build` to build the assets.' => '插件资源尚未构建。这很可能是因为你从 GitHub 仓库下载了插件但未构建资源。请运行 `nvm use && npm ci && npm run build` 来构建资源。',
				'The previously selected provider is no longer available. This feature will not function as expected until a valid provider is selected or the selection is reset to default.' => '先前选择的提供方已不可用。此功能在选择有效提供方或重置为默认值之前可能无法正常工作。',
				'This feature does not require an AI provider or model.' => '此功能不需要 AI 提供方或模型。',
				'Content Classification' => '内容分类',
				'AI-powered suggestions for post tags and categories based on content analysis. Requires an AI connector that includes support for text generation models.' => '基于内容分析，为文章标签和分类目录提供 AI 建议。需要支持文本生成模型的 AI 连接器。',
				'Taxonomy strategy' => '分类策略',
				'Suggest new terms based on context' => '根据上下文建议新术语',
				'Only suggest existing terms' => '仅建议现有术语',
				'Maximum suggestions' => '最大建议数量',
				'Suggest %s' => '建议%s',
				'Content Classification will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可使用内容分类建议。',
				'Add "%s"' => '添加“%s”',
				'Dismiss "%s"' => '忽略“%s”',
				'Dismiss all' => '全部忽略',
				'Suggest again' => '重新建议',
				'Suggested %s' => '建议的%s',
				'new' => '新建',
				'Content Resizing' => '内容改写长度',
				'Shorten, expand, or rephrase selected block content. Requires an AI connector that includes support for text generation models.' => '缩短、扩展或改写选中的区块内容。需要支持文本生成模型的 AI 连接器。',
				'Resize Content' => '调整内容长度',
				'Shorten' => '缩短',
				'Expand' => '扩展',
				'Rephrase' => '改写',
				'Accept' => '接受',
				'Regenerate' => '重新生成',
				'Original' => '原文',
				'Original content' => '原始内容',
				'Suggested' => '建议',
				'Suggested content' => '建议内容',
				'Suggested replacement' => '建议替换内容',
				'No change' => '无变化',
				'+%d character' => '增加 %d 个字符',
				'+%d characters' => '增加 %d 个字符',
				'−%d character' => '减少 %d 个字符',
				'−%d characters' => '减少 %d 个字符',
				'%d character added' => '增加了 %d 个字符',
				'%d characters added' => '增加了 %d 个字符',
				'%d character removed' => '移除了 %d 个字符',
				'%d characters removed' => '移除了 %d 个字符',
				'Text is too short to shorten further.' => '文本太短，无法继续缩短。',
				'An error occurred while resizing content.' => '调整内容时发生错误。',
				'Excerpt Generation' => '文章摘要生成',
				'Generates excerpt suggestions from content. Requires an AI connector that includes support for text generation models.' => '根据内容生成文章摘要建议。需要支持文本生成模型的 AI 连接器。',
				'Generate excerpt' => '生成文章摘要',
				'Regenerate excerpt' => '重新生成文章摘要',
				'Failed to generate excerpt.' => '生成文章摘要失败。',
				'Excerpt generation will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成文章摘要。',
				'Alt Text Generation' => '替代文本生成',
				'Generates accessible alternative (alt) text for images using AI vision models, following common web accessibility guidance. Requires an AI connector that includes support for vision-based image analysis models.' => '使用 AI 视觉模型为图片生成符合常见无障碍规范的替代文本。需要支持视觉图片分析模型的 AI 连接器。',
				'Generate Alt Text' => '生成替代文本',
				'Alt Text' => '替代文本',
				'Alt text' => '替代文本',
				'Generated Alt Text' => '已生成替代文本',
				'Regenerate Alt Text' => '重新生成替代文本',
				'Generating alt text…' => '正在生成替代文本…',
				'Generating alt text: %1$d / %2$d…' => '正在生成替代文本：%1$d/%2$d…',
				'Alt text generated and applied.' => '替代文本已生成并应用。',
				'Alt text generated for %d image.' => '已为 %d 张图片生成替代文本。',
				'Alt text generated for all %d images.' => '已为全部 %d 张图片生成替代文本。',
				'Alt text generated for %1$d of %2$d images. Failed attachment IDs: %3$s.' => '已为 %1$d/%2$d 张图片生成替代文本。失败的附件 ID：%3$s。',
				'Image identified as decorative. Alt text cleared.' => '图片已识别为装饰性图片，替代文本已清空。',
				'No image available to generate alt text for.' => '没有可用于生成替代文本的图片。',
				'An error occurred while generating alt text.' => '生成替代文本时发生错误。',
				'An unexpected error occurred while generating alt text.' => '生成替代文本时发生意外错误。',
				'Failed to generate alt text.' => '生成替代文本失败。',
				'This image appears to be decorative. Applying will set an empty alt attribute, which tells screen readers to skip it.' => '这张图片似乎是装饰性图片。应用后会设置空的 alt 属性，提示屏幕阅读器跳过它。',
				'Dismiss this notice.' => '忽略此通知。',
				'Meta Description Generation' => 'SEO 描述生成',
				'Generates meta description suggestions and integrates those with various SEO plugins. Requires an AI connector that includes support for text generation models.' => '生成 SEO 描述建议，并与多种 SEO 插件集成。需要支持文本生成模型的 AI 连接器。',
				'Meta description' => 'SEO 描述',
				'%d characters' => '%d 个字符',
				'Aim for 140–160 characters for optimal display in search results.' => '建议控制在 140–160 个字符，以便在搜索结果中最佳展示。',
				'Copy to clipboard' => '复制到剪贴板',
				'Edit description' => '编辑描述',
				'Regenerate meta description' => '重新生成 SEO 描述',
				'No meta description suggestion was generated.' => '未生成 SEO 描述建议。',
				'Failed to generate meta description.' => '生成 SEO 描述失败。',
				'Meta description copied to clipboard.' => 'SEO 描述已复制到剪贴板。',
				'Meta Description generation will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成 SEO 描述。',
				'Editorial Notes' => '编辑建议',
				'Adds editorial suggestions to posts block-by-block, covering Accessibility, Readability, Grammar, and SEO. Requires an AI connector that includes support for text generation models.' => '按区块为文章添加编辑建议，覆盖无障碍、可读性和语法。需要支持文本生成模型的 AI 连接器。',
				'Generate Editorial Note' => '生成编辑建议',
				'%d suggestion added. Save to keep changes.' => '已添加 %d 条建议，保存后生效。',
				'%d suggestions added. Save to keep changes.' => '已添加 %d 条建议，保存后生效。',
				'%d suggestion added, view those Notes <a>here</a>.' => '已添加 %d 条建议，可在<a>此处</a>查看这些建议。',
				'%d suggestions added, view those Notes <a>here</a>.' => '已添加 %d 条建议，可在<a>此处</a>查看这些建议。',
				'No new suggestions found.' => '未找到新建议。',
				'Editorial Notes will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成编辑建议。',
				'Reviewing…' => '正在检查…',
				'Reviewing blocks… (%1$d of %2$d)' => '正在检查区块…（%1$d/%2$d）',
				'Editorial Updates' => '编辑更新',
				'Applies pending editorial Notes to your content automatically. Requires an AI connector that includes support for text generation models.' => '自动将待处理的编辑建议应用到内容中。需要支持文本生成模型的 AI 连接器。',
				'Apply Editorial Updates' => '应用编辑更新',
				'Automatically applies pending editorial Notes to update your content.' => '自动应用待处理的编辑建议来更新内容。',
				'Applies pending editorial Notes and saves the post. Save your current edits first.' => '应用待处理的编辑建议并保存文章。请先保存当前修改。',
				'Save your current edits and wait for saving to finish before applying Editorial Updates.' => '请先点击编辑器右上角的“保存”，等待保存完成后，再点击“应用编辑更新”。',
				'No usable block content was returned. The original block is unchanged.' => '未返回可用的区块内容，原区块保持不变。请在“编辑建议”侧栏检查该建议后重试。',
				'The post changed while AI was working. No updates were applied or saved. Save your edits, then try again.' => 'AI 生成期间文章发生了修改，本次结果未应用或保存。请先点击右上角“保存”，再重试。',
				'The post save could not be confirmed. Editor content is preserved and Notes remain pending. Check the Save status before trying again.' => '无法确认文章是否保存成功。生成内容保留在编辑器中，建议仍待处理。请检查右上角的保存状态，确认保存后再处理建议。',
				'Saved blocks: %1$d; failed blocks: %2$d. Failed Notes remain pending. Review them in the Notes sidebar before trying again. %3$s' => '已保存 %1$d 个区块，%2$d 个区块失败。失败建议仍待处理，请在“编辑建议”侧栏查看后重试。原因：%3$s',
				'The post was saved, but %d Notes could not be marked complete. Review the saved content and resolve those Notes in the Notes sidebar.' => '文章已保存，但有 %d 条建议未能标记完成。请核对已保存内容，并在“编辑建议”侧栏中手动完成这些建议。',
				'The complete Notes list could not be loaded. Try again before applying updates.' => '未能加载完整的编辑建议，本次未应用或保存任何结果。请刷新“编辑建议”侧栏后重试。',
				'No pending Notes found to refine.' => '未找到待应用的编辑建议。',
				'No blocks found matching the existing Notes.' => '未找到与现有建议匹配的区块。',
				'No content changes were needed based on the existing Notes.' => '根据现有建议无需更改内容。',
				'%d block refined with AI.' => '已用 AI 优化 %d 个区块。',
				'%d blocks refined with AI.' => '已用 AI 优化 %d 个区块。',
				'Refining block (%1$s of %2$s)…' => '正在优化区块（%1$s/%2$s）…',
				'Refinement failed for all blocks.' => '所有区块优化均失败。',
				'Open Notes' => '打开编辑建议',
				'Review in Revisions' => '在修订版本中查看',
				'Content Summarization' => '内容总结',
				'Summarizes long-form content into digestible overviews. Requires an AI connector that includes support for text generation models.' => '将长内容总结为易读的概览。需要支持文本生成模型的 AI 连接器。',
				'Generate Summary' => '生成摘要区块',
				'This will create a block that is a summary of the content of this post.' => '这将创建一个区块，作为本文内容的摘要。',
				'Content Summary' => '内容总结',
				'A generated summary of the post content.' => '根据文章内容生成的总结。',
				'Regenerate Summary' => '重新生成摘要区块',
				'This will update the generated summary block with a new summary of the content of this post.' => '这会用本文内容的新摘要更新已生成的摘要区块。',
				'Invalid response from API.' => 'API 返回无效响应。',
				'Failed to generate summary.' => '生成摘要区块失败。',
				'Summarization will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可使用内容总结。',
				'Title Generation' => '标题生成',
				'Generates title suggestions from content. Requires an AI connector that includes support for text generation models.' => '根据内容生成标题建议。需要支持文本生成模型的 AI 连接器。',
				'Title suggestion' => '标题建议',
				'Generated title' => '已生成标题',
				'Insert' => '插入',
				'Review, edit and insert the suggested title or regenerate a new one.' => '查看、编辑并插入建议标题，或重新生成一个。',
				'No title suggestion was generated.' => '未生成标题建议。',
				'Failed to generate title.' => '生成标题失败。',
				'This feature requires an AI Connector to function properly.' => '此功能需要 AI 连接器才能正常工作。',
				'This feature requires an AI Connector to function properly. Please set up a provider in Settings → Connectors.' => '此功能需要 AI 连接器才能正常工作。请在“设置 → 连接器”中设置提供方。',
				'This feature requires a valid AI Connector to function properly. Please set up a provider to use this feature in Settings → Connectors.' => '此功能需要有效的 AI 连接器才能正常工作。请在“设置 → 连接器”中设置提供方。',
				'This feature requires a valid AI Connector to function properly. Please set up a provider to use this feature in %s.' => '此功能需要有效的 AI 连接器才能正常工作。请在 %s 中设置提供方。',
				'Base64 Image Import' => 'Base64 图片导入',
				'Image Generation and Editing' => '图片生成与编辑',
				'Image Prompt Generation' => '图片提示词生成',
				'Generate and edit images using AI. Requires an AI connector that includes support for image generation models.' => '使用 AI 生成和编辑图片。需要支持图片生成模型的 AI 连接器。',
				'Generate Editorial Notes' => '生成编辑建议',
				'This analyzes the content of this post block-by-block and adds editorial Notes with suggestions on each block.' => '这会逐个区块分析本文内容，并为每个区块添加编辑建议。',
				'Meta Description' => 'SEO 描述',
				'Generate Meta Description' => '生成 SEO 描述',
				'Generate Image' => '生成图片',
				'Describe the image you want to generate.' => '描述你想生成的图片。',
				'Prompt' => '提示词',
				'Generate' => '生成',
				'RTL stylesheet "%1$s" is missing and will not be available.' => '缺少 RTL 样式表“%1$s”，因此不可用。',
				'Invalid response from image generation' => '图片生成返回无效响应',
				'An error occurred during image generation.' => '图片生成过程中发生错误。',
				'This feature requires an AI Connector that supports image generation. Review your Connectors to ensure you have a valid AI Connector configured.' => '此功能需要支持图片生成的 AI 连接器。请检查连接器，确保已配置有效的 AI 连接器。',
				'Save to Media Library' => '保存到媒体库',
				'Refine Image' => '优化图片',
				'Generate Another Image' => '再生成一张图片',
				'Start over' => '重新开始',
				'Describe the refinements you want to make to the image' => '描述你想对图片进行的优化',
				'Apply' => '应用',
				'Cancel' => '取消',
				'Saving to Media Library…' => '正在保存到媒体库…',
				'Failed to save image.' => '图片保存失败。',
				'Previous version' => '上一个版本',
				'Next version' => '下一个版本',
				'Use Image' => '使用图片',
				'Uploading image…' => '正在上传图片…',
				'Failed to upload image.' => '图片上传失败。',
				'Edit Prompt' => '编辑提示词',
				'%1$d / %2$d' => '%1$d / %2$d',
				'AI Generated Featured Image' => 'AI 生成的特色图片',
				'Brush size' => '画笔大小',
				'Cancel Refinement' => '取消优化',
				'Clear' => '清除',
				'Describe the refinements you want to make to the image.' => '描述你想对图片进行的优化。',
				'Describe what to replace with' => '描述要替换成什么',
				'Expand Background' => '扩展背景',
				'Failed to apply drawing to image.' => '应用绘制到图片失败。',
				'Failed to generate prompt: %s' => '生成提示词失败：%s',
				'Generate featured image' => '生成特色图片',
				'Generated by %1$s using %2$s on %3$s. Prompt: %4$s' => '由 %1$s 使用 %2$s 于 %3$s 生成。提示词：%4$s',
				'Generating image' => '正在生成图片',
				'Generating image prompt' => '正在生成图片提示词',
				'Image saved!' => '图片已保存！',
				'Image successfully added to the Media Library.' => '图片已成功添加到媒体库。',
				'Importing image…' => '正在导入图片…',
				'Invalid response from generate image.' => '生成图片返回无效响应。',
				'Invalid response from image generation.' => '图片生成返回无效响应。',
				'Invalid response from image import.' => '图片导入返回无效响应。',
				'Original image' => '原始图片',
				'Refine' => '优化',
				'Remove' => '移除',
				'Remove Background' => '移除背景',
				'Remove Item' => '移除项目',
				'Replace' => '替换',
				'Replace Item' => '替换项目',
				'Undo' => '撤销',
				'Uploading image to Media Library…' => '正在上传图片到媒体库…',
				'Version %d' => '版本 %d',
				'View in Media Library' => '在媒体库中查看',
				'View new image' => '查看新图片',
				'Total Abilities' => '能力总数',
				'Core' => '核心',
				'Plugins' => '插件',
				'Theme' => '主题',
				'All Providers' => '所有提供方',
				'All Categories' => '所有分类',
				'Filter by provider' => '按提供方筛选',
				'Filter by category' => '按分类筛选',
				'Filter' => '筛选',
				'Search Abilities' => '搜索能力',
				'Name' => '名称',
				'Slug' => '标识',
				'Provider' => '提供方',
				'Actions' => '操作',
				'Plugin' => '插件',
				'View' => '查看',
				'Test' => '测试',
				'Test Ability' => '测试能力',
				'Test Ability:' => '测试能力：',
				'View Details' => '查看详情',
				'&larr; Back to List' => '&larr; 返回列表',
				'No ability specified.' => '未指定能力。',
				'Ability not found.' => '未找到能力。',
				'Description' => '描述',
				'Details' => '详情',
				'Input Schema' => '输入 Schema',
				'Output Schema' => '输出 Schema',
				'Raw Data' => '原始数据',
				'Input Data' => '输入数据',
				'No Input Required' => '无需输入',
				'This ability does not accept any input parameters. Simply click "Invoke Ability" to execute it.' => '此能力不接受任何输入参数。直接点击“调用能力”即可执行。',
				'Edit the JSON input below to test the ability. The input will be validated against the input schema if available.' => '编辑下面的 JSON 输入来测试此能力。如果存在输入 schema，系统会按 schema 进行验证。',
				'How to test:' => '如何测试：',
				'Edit the JSON input below with your test data' => '用你的测试数据编辑下面的 JSON 输入',
				'Click "Validate Input" to check your JSON is correct' => '点击“验证输入”检查 JSON 是否正确',
				'Click "Invoke Ability" to execute the ability with your input' => '点击“调用能力”，使用当前输入执行此能力',
				'View the results below' => '在下方查看结果',
				'Ability test input (JSON)' => '能力测试输入（JSON）',
				'Invoke Ability' => '调用能力',
				'Validate Input' => '验证输入',
				'Clear Result' => '清除结果',
				'Result' => '结果',
				'Input Schema Reference' => '输入 Schema 参考',
				'Copy' => '复制',
				'Invoking ability...' => '正在调用能力...',
				'Success!' => '成功！',
				'Error' => '错误',
				'Invalid JSON input' => '无效的 JSON 输入',
				'Are you sure you want to invoke this ability?' => '确定要调用此能力吗？',
				'Copied!' => '已复制！',
				'Failed to copy' => '复制失败',
				'Valid' => '有效',
				'Validation Errors' => '验证错误',
				'JSON syntax is valid' => 'JSON 语法有效',
				'Failed to parse input schema' => '无法解析输入 schema',
				'Input is valid according to the schema' => '输入符合 schema 要求',
				'Input validation failed.' => '输入验证失败。',
				'Ability invoked successfully.' => '能力调用成功。',
				'Ability slug is required.' => '必须提供能力标识。',
				'Insufficient permissions.' => '权限不足。',
				'Unknown error occurred.' => '发生未知错误。',
				'Overview' => '概览',
				'Providers' => '提供方',
				'Testing' => '测试',
				'For more information:' => '更多信息：',
				'Abilities API Documentation' => 'Abilities API 文档',
				'You can test any ability directly from this screen:' => '你可以直接在此页面测试任意能力：',
				'Click "Test" next to an ability in the list.' => '点击列表中某个能力旁边的“测试”。',
				'Edit the pre-filled Input Data if the ability accepts JSON parameters.' => '如果该能力接受 JSON 参数，可以编辑预填的输入数据。',
				'Use "Validate Input" to check your JSON against the schema.' => '使用“验证输入”按 schema 检查 JSON。',
				'Click "Invoke Ability" to execute it and see the result.' => '点击“调用能力”执行并查看结果。',
				'<strong>Core</strong>: Built into WordPress itself.' => '<strong>核心</strong>：由 WordPress 自身提供。',
				'<strong>Plugin</strong>: Registered by an active plugin.' => '<strong>插件</strong>：由已启用插件注册。',
				'<strong>Theme</strong>: Registered by the active theme.' => '<strong>主题</strong>：由当前主题注册。',
				'Advanced settings' => '高级设置',
				'Another block is currently being reviewed.' => '当前正在检查另一个区块。',
				'Comfortable' => '宽松',
				'De-serializing instances of %s is not allowed.' => '不允许反序列化 %s 的实例。',
				'Developer settings saved successfully.' => '开发者设置已成功保存。',
				'Every ability is associated with a provider that indicates where it comes from:' => '每项能力都关联一个提供方，用于说明其来源：',
				'Failed post IDs: %s.' => '失败的文章 ID：%s。',
				'Failed to load connector approval status.' => '加载连接器审批状态失败。',
				'Feature description is required in load_metadata().' => 'load_metadata() 必须提供功能描述。',
				'Feature label is required in load_metadata().' => 'load_metadata() 必须提供功能标签。',
				'Generate title toolbar' => '生成标题工具栏',
				'Generates reply suggestions to a comment.' => '为评论生成回复建议。',
				'Generating summaries: %1$d / %2$d…' => '正在生成摘要：%1$d / %2$d…',
				'Invalid Feature id returned by ::get_id().' => '::get_id() 返回了无效的功能 ID。',
				'No change in character count' => '字符数没有变化',
				'No plugin or theme has been approved to use an AI connector yet. AI functionality will not work until at least one is approved.' => '尚未批准任何插件或主题使用 AI 连接器。至少批准一个调用方后，AI 功能才能工作。',
				'Replace the item circled/marked in red in the image with:' => '将图片中红圈或红色标记的物体替换为：',
				'Reply Suggestion' => '回复建议',
				'Show advanced feature configuration options' => '显示高级功能配置选项',
				'Sorry, you are not allowed to create AI Notes.' => '抱歉，你无权创建 AI 注释。',
				'Summary generated for %1$d of %2$d posts.' => '已为 %2$d 篇文章中的 %1$d 篇生成摘要。',
				'Summary generated for %d post.' => '已为 %d 篇文章生成摘要。',
				'Summary generated for %d posts.' => '已为 %d 篇文章生成摘要。',
				'This action will be removed in v1.0' => '此操作将在 v1.0 中移除',
				'This filter will be removed in v1.0' => '此过滤器将在 v1.0 中移除',
				'This will be removed in v1.0' => '此项将在 v1.0 中移除',
				'Title generation will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成标题。',
				'User #%d' => '用户 #%d',
				'Various AI features and experiments.' => '多种 AI 功能和实验功能。',
				'WordPress AI failed to upgrade to %1$s. Migration version %2$s failed with the following error: %3$s. Please deactivate and reactivate the plugin to try again.' => 'WordPress AI 升级到 %1$s 失败。迁移版本 %2$s 出错：%3$s。请停用并重新启用插件后重试。',
				// WordPress AI plugin 1.3.0: new experiments on the settings screen.
				'Slug Generation' => '别名生成',
				'Suggests SEO-friendly permalink slugs from post title or content. Requires an AI connector that includes support for text generation models.' => '根据文章标题或内容生成简短、易读的永久链接别名建议。需要支持文本生成模型的 AI 连接器。',
				'Content Translation' => '内容翻译',
				'Translate paragraph and heading blocks into a different language. Requires an AI connector that includes support for text generation models.' => '将段落和标题区块翻译为其他语言。需要支持文本生成模型的 AI 连接器。',
				'Custom Abilities' => '自定义能力',
				'Register the plugin\'s custom WordPress Abilities for use via the Abilities API and MCP.' => '注册插件的自定义 WordPress 能力，供 Abilities API 和 MCP 使用。',
				// Local WordPress AI 1.5 title-regeneration patch: fixed UI only.
				'Title guidance (optional)' => '标题修改要求（可选）',
				'For example: shorter, focus on the practical steps. Recent suggestions are avoided automatically.' => '例如：再简短一些，突出操作步骤。系统会避开最近生成的候选标题。',
				'This repeats a recent title. Add a different angle in the title guidance and regenerate.' => '本次标题与最近候选重复。请在“标题修改要求”中换个角度，再点击“重新生成”。',
				'The title, excerpt or content changed after this suggestion. Regenerate using your current draft before inserting.' => '生成建议后，标题、摘要或正文发生了变化。请先点击“重新生成”，再插入建议。',
				'The title changed while AI was working. Your edit was kept. Generate again to use the current title.' => '生成期间你修改了标题，已保留你的修改。请再次点击“生成标题”，按当前标题重新生成。',
				'The title, excerpt or content changed while AI was working. Your edit was kept. Generate again using the current draft.' => '生成期间标题、摘要或正文发生了变化，已保留你的修改。请按当前草稿重新生成标题。',
				'This repeats a recent title. Edit the name and regenerate.' => '本次建议与最近标题重复。请先修改上方名称，再点击“重新生成标题”。',
				// WordPress AI plugin 1.5.0: fixed editor progress and recovery UI.
				'%1$d block could not be reviewed: %2$s' => '有 %1$d 个区块未能完成检查：%2$s',
				'%1$d blocks could not be reviewed: %2$s' => '有 %1$d 个区块未能完成检查：%2$s',
				'%d block was not translated because the editor changed while translating.' => '翻译期间编辑器内容发生变化，已跳过 %d 个区块，保留你的修改。',
				'%d blocks were not translated because the editor changed while translating.' => '翻译期间编辑器内容发生变化，已跳过 %d 个区块，保留你的修改。',
				'Could not add term "%s". Please try again.' => '未能添加分类词“%s”，请在当前分类面板中重试。',
				'Generate title' => '生成标题',
				'Regenerate title' => '重新生成标题',
				'Regenerate Reply' => '重新生成回复',
				'Translating content… (%1$d/%2$d)' => '正在翻译内容…（%1$d/%2$d）',
				'The content could not be translated because the template has no Content block.' => '当前模板没有“内容”区块，无法在此翻译正文。请切换到包含“内容”区块的模板后重试。',
				'Change summary' => '变更摘要',
				'Changes can only be summarized between two saved revisions.' => '请选择两个已保存的修订版本，再生成变更摘要。',
				'Could not summarize the changes. Please try again.' => '未能生成变更摘要，请在修订版本页面重试。',
				'Summarize changes' => '生成变更摘要',
				'Summarizing changes…' => '正在生成变更摘要…',
				'Summarizing…' => '正在生成摘要…',
				'Summary of changes is ready.' => '变更摘要已生成。',
				'These changes are long, so the summary only covers the first part.' => '本次变更较长，摘要仅覆盖前半部分。',
				'Uses AI to describe what changed in the title and content between these revisions. Nothing is changed or saved.' => '使用 AI 概括这两个修订版本之间的标题和正文变化。此操作不会修改或保存文章。',
				// WordPress AI plugin 1.3.0: slug generation editor sidebar.
				'Generate Slug' => '生成别名',
				'Regenerate Slug' => '重新生成别名',
				'Suggest Slugs' => '建议别名',
				'Suggested Slugs' => '建议的别名',
				'Generate Suggestions' => '生成建议',
				'Generating suggestions…' => '正在生成建议…',
				'Slug suggestions' => '别名建议',
				'Slug suggestions will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成别名建议。',
				'Customize slug' => '自定义别名',
				'Selected slug' => '已选别名',
				'(no slug set)' => '（未设置别名）',
				'Applied' => '已应用',
				'Review, edit, and insert a suggested slug or regenerate new options.' => '查看、编辑并插入建议的别名，或重新生成新选项。',
				'This text cannot be used as a slug.' => '此文本不能用作别名。',
				'Will be applied as “%s”.' => '将应用为“%s”。',
				'Failed to generate slug.' => '生成别名失败。',
				// WordPress AI plugin 1.3.0: content translation editor sidebar.
				'Translate' => '翻译',
				'Translate to' => '翻译为',
				'Generate Translation' => '生成翻译',
				'Translating blocks… (%1$d/%2$d)' => '正在翻译区块…（%1$d/%2$d）',
				'Failed to translate %d block.' => '翻译 %d 个区块失败。',
				'Failed to translate %d blocks.' => '翻译 %d 个区块失败。',
				'Cannot translate an empty post title.' => '文章标题为空，无法翻译。',
				'Also translate the title' => '同时翻译标题',
				'Translates the title along with the post content.' => '翻译正文时一并翻译标题。',
				'Translates this post block by block and applies the translated content to each block.' => '逐个区块翻译本文，并将翻译后的内容应用到每个区块。',
				'No translatable content found in the post.' => '文章中没有可翻译的内容。',
				'No blocks were long enough to translate. Each block needs at least %d characters.' => '没有长度足够的区块可供翻译。每个区块至少需要 %d 个字符。',
				'Skipped %1$d block shorter than the %2$d character minimum.' => '已跳过 %1$d 个区块，其长度不足 %2$d 个字符的下限。',
				'Skipped %1$d blocks shorter than the %2$d character minimum.' => '已跳过 %1$d 个区块，其长度不足 %2$d 个字符的下限。',
				'Arabic' => '阿拉伯语',
				'Chinese (Simplified)' => '简体中文',
				'Chinese (Traditional)' => '繁体中文',
				'Dutch' => '荷兰语',
				'English (UK)' => '英语（英国）',
				'English (US)' => '英语（美国）',
				'French' => '法语',
				'German' => '德语',
				'Hindi' => '印地语',
				'Italian' => '意大利语',
				'Japanese' => '日语',
				'Korean' => '韩语',
				'Portuguese (Brazil)' => '葡萄牙语（巴西）',
				'Spanish' => '西班牙语',
				// WordPress AI plugin 1.3.0: site health and dashboard status.
				'AI Plugin' => 'AI 插件',
				'AI enabled' => 'AI 已启用',
				'Yes' => '是',
				'No' => '否',
				'Plugin version' => '插件版本',
				'Credentials configured' => '凭据已配置',
				'Configured providers' => '已配置的提供方',
				'None' => '无',
				'Features enabled' => '已启用的功能',
				'AI credentials configured' => 'AI 凭据已配置',
				'AI credentials are configured' => 'AI 凭据已配置',
				'At least one AI connector has credentials configured and is ready to use.' => '至少有一个 AI 连接器已配置凭据并可供使用。',
				'No AI credentials configured' => '未配置 AI 凭据',
				'No AI connector credentials have been configured. AI features will not be active until at least one connector is set up.' => '尚未配置任何 AI 连接器凭据。在至少设置一个连接器之前，AI 功能不会启用。',
				'Complete these steps to get started with the AI plugin:' => '完成以下步骤即可开始使用 AI 插件：',
				'Features' => '功能',
				'Enabled:' => '已启用：',
				'Disabled:' => '已停用：',
				'Manage Features' => '管理功能',
				'Experiments' => '实验功能',
				'Manage Experiments' => '管理实验功能',
				'%d experiment enabled' => '已启用 %d 个实验功能',
				'%d experiments enabled' => '已启用 %d 个实验功能',
				'%d experiment disabled' => '已停用 %d 个实验功能',
				'%d experiments disabled' => '已停用 %d 个实验功能',
				'%d experiment updated' => '已更新 %d 个实验功能',
				'%d experiments updated' => '已更新 %d 个实验功能',
				'Provider Capabilities' => '提供方能力',
				// WordPress AI plugin 1.3.0: settings import and export.
				'Export settings' => '导出设置',
				'Import settings' => '导入设置',
				'Import' => '导入',
				'Import AI Settings' => '导入 AI 设置',
				'Importing settings…' => '正在导入设置…',
				'Failed to export settings.' => '导出设置失败。',
				'Failed to import settings.' => '导入设置失败。',
				'Settings imported successfully.' => '设置导入成功。',
				'Settings imported successfully. %1$d setting(s) imported, %2$d rejected due to invalid values.' => '设置导入成功。已导入 %1$d 项设置，%2$d 项因取值无效被拒绝。',
				'This will overwrite your existing AI settings. Are you sure?' => '这将覆盖你现有的 AI 设置。确定继续吗？',
				// WordPress AI plugin 1.3.0: bulk action notices.
				'%d image was not processed because the bulk limit was reached. Select fewer images and run the action again.' => '已达到批量处理上限，未能处理 %d 张图片。请减少所选图片后重试。',
				'%d images were not processed because the bulk limit was reached. Select fewer images and run the action again.' => '已达到批量处理上限，未能处理 %d 张图片。请减少所选图片后重试。',
				'%d post was not processed because the bulk limit was reached. Select fewer posts and run the action again.' => '已达到批量处理上限，未能处理 %d 篇文章。请减少所选文章后重试。',
				'%d posts were not processed because the bulk limit was reached. Select fewer posts and run the action again.' => '已达到批量处理上限，未能处理 %d 篇文章。请减少所选文章后重试。',
				'%d post was skipped because its content is too short to summarize.' => '已跳过 %d 篇文章，其内容太短，无法生成摘要。',
				'%d posts were skipped because their content is too short to summarize.' => '已跳过 %d 篇文章，其内容太短，无法生成摘要。',
				// WordPress AI plugin 1.3.0: misc fixed UI copy.
				'Unable to update one or more blocks because their editable text attributes could not be determined.' => '无法更新一个或多个区块：无法确定其可编辑的文本属性。',
				'Embedding generation is not available in this environment.' => '当前环境不支持嵌入生成。',
				'%s connection details' => '%s 连接详情',
				'Unsupported log type: %1$s. Supported types are: %2$s.' => '不支持的日志类型：%1$s。支持的类型为：%2$s。',
				// WordPress AI plugin: admin-facing ability failure notices.
				'Reply suggestion could not be generated. Please ensure you have a connected provider that supports text generation.' => '无法生成回复建议。请确保已连接支持文本生成的提供方。',
				'Comment analysis failed. Please ensure you have a connected provider that supports text generation.' => '评论分析失败。请确保已连接支持文本生成的提供方。',
				'Term generation failed. Please ensure you have a connected provider that supports text generation.' => '术语生成失败。请确保已连接支持文本生成的提供方。',
				'Content resizing failed. Please ensure you have a connected provider that supports text generation.' => '内容长度调整失败。请确保已连接支持文本生成的提供方。',
				'Content translation failed. Please ensure you have a connected provider that supports text generation.' => '内容翻译失败。请确保已连接支持文本生成的提供方。',
				'Editorial notes generation failed. Please ensure you have a connected provider that supports text generation.' => '编辑建议生成失败。请确保已连接支持文本生成的提供方。',
				'Refinement generation failed. Please ensure you have a connected provider that supports text generation.' => '优化内容生成失败。请确保已连接支持文本生成的提供方。',
				'Excerpt generation failed. Please ensure you have a connected provider that supports text generation.' => '文章摘要生成失败。请确保已连接支持文本生成的提供方。',
				'Alt text generation failed. Please ensure you have a connected provider that supports both text generation and vision capabilities.' => '替代文本生成失败。请确保已连接同时支持文本生成和视觉能力的提供方。',
				'Image generation failed. Please ensure you have a connected provider that supports image generation.' => '图片生成失败。请确保已连接支持图片生成的提供方。',
				'Image refinement failed. Please ensure you have a connected provider that supports image refinement, not just image generation.' => '图片优化失败。请确保已连接支持图片优化（而不仅是图片生成）的提供方。',
				'Image prompt generation failed. Please ensure you have a connected provider that supports text generation.' => '图片提示词生成失败。请确保已连接支持文本生成的提供方。',
				'Meta description generation failed. Please ensure you have a connected provider that supports text generation.' => 'SEO 描述生成失败。请确保已连接支持文本生成的提供方。',
				'Slug generation failed. Please ensure you have a connected provider that supports text generation.' => '别名生成失败。请确保已连接支持文本生成的提供方。',
				'Summarization failed. Please ensure you have a connected provider that supports text generation.' => '内容总结失败。请确保已连接支持文本生成的提供方。',
				'Title generation failed. Please ensure you have a connected provider that supports text generation.' => '标题生成失败。请确保已连接支持文本生成的提供方。',
				'Type-ahead suggestion generation failed. Please ensure you have a connected provider that supports text generation.' => '输入预测建议生成失败。请确保已连接支持文本生成的提供方。',
				// WordPress AI plugin 1.4.0: comment moderation value scores.
				'Analyze Sentiment, Toxicity, and Value' => '分析情绪、毒性和价值',
				'Analyzes a comment for toxicity, sentiment, and value.' => '分析评论的毒性、情绪和价值。',
				'Automatically moderate comments based on toxicity detection and sentiment analysis and give each comment a value score. Requires an AI connector that includes support for text generation models.' => '基于毒性检测和情绪分析自动审核评论，并为每条评论给出价值分数。需要支持文本生成模型的 AI 连接器。',
				'Value' => '价值',
				'All Value Scores' => '所有价值分数',
				'High Value Score (>=70%)' => '高价值分数（>=70%）',
				'Medium Value Score (40%-69%)' => '中等价值分数（40%-69%）',
				'Low Value Score (<40%)' => '低价值分数（<40%）',
				'%d comment was not queued because the batch limit was reached.' => '已达到批量上限，%d 条评论未加入队列。',
				'%d comments were not queued because the batch limit was reached.' => '已达到批量上限，%d 条评论未加入队列。',
				'Sort by' => '排序依据',
				'Order' => '顺序',
				'Density' => '密度',
				'Balanced' => '适中',
				'Compact' => '紧凑',
				'Items per page' => '每页条目数',
				'Properties' => '属性',
				'Add filter' => '添加筛选',
				'View options' => '显示选项',
				'No results' => '没有结果',
				// Fixed admin/editor copy missed by length and schema-word audit heuristics.
				'Serves your content as Markdown for AI agents and other machine readers: adds a Markdown feed at /feed/markdown/ and Markdown versions of individual posts and pages via ?output_format=markdown, with optional Accept-header negotiation.' => '以 Markdown 格式向 AI 智能体和其他机器阅读程序提供内容：在 /feed/markdown/ 添加 Markdown 订阅源，并通过 ?output_format=markdown 提供单篇文章和页面的 Markdown 版本，还可通过 Accept 请求头协商返回格式。',
				'Abilities are a standardized way for WordPress core, plugins, and themes to expose discrete units of functionality. Each ability has a name, optional input/output schemas, and can be invoked programmatically.' => '能力是 WordPress 核心、插件和主题提供独立功能的标准方式。每项能力都有名称，可选配输入和输出结构定义，并可通过程序调用。',
				'The Abilities Explorer lets you browse every registered ability, inspect its schemas, and test it with custom input right from the admin.' => '能力浏览器可让你直接在后台浏览所有已注册的能力、查看其结构定义，并使用自定义输入进行测试。',
				'Content translation will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可翻译。',
				'The post title is too short to translate. A minimum of %d characters is required.' => '文章标题太短，无法翻译，至少需要 %d 个字符。',
				'Current Slug:' => '当前别名：',
				'No slug suggestion was generated.' => '未生成别名建议。',
				'Filter by Value Score' => '按价值分数筛选',
				'The selected file could not be read. Please choose a valid JSON file.' => '无法读取所选文件。请选择有效的 JSON 文件。',
				'The selected file is not a valid AI settings export.' => '所选文件不是有效的 AI 设置导出文件。',
				'Unsupported schema version: %d.' => '不支持的结构版本：%d。',
				'The summary could not be inserted because the template has no Content block.' => '无法插入内容总结：当前模板不包含内容区块。',
				// WordPress AI plugin 1.4.0: Markdown feeds experiment.
				'Markdown Feeds' => 'Markdown 订阅源',
				'Serve Markdown when a request prefers it via the Accept header (may conflict with page caches that ignore the Vary header)' => '当请求通过 Accept 头表明偏好 Markdown 时返回 Markdown（可能与忽略 Vary 头的页面缓存冲突）',
				'%s Markdown Feed' => '%s Markdown 订阅源',
				'Author: %s' => '作者：%s',
				'Published: %s' => '发布日期：%s',
				'Link: %s' => '链接：%s',
				'Site: %s' => '站点：%s',
				// WordPress AI plugin 1.4.0: content translation additions.
				'Translating…' => '正在翻译…',
				'Failed to translate the post title.' => '翻译文章标题失败。',
				'Retry failed translation' => '重试失败的翻译',
				'Retry failed translations' => '重试失败的翻译',
				'Title translation will be available when the title has at least %d characters.' => '标题至少达到 %d 个字符后可翻译。',
				'Bengali' => '孟加拉语',
				'Indonesian' => '印度尼西亚语',
				'Polish' => '波兰语',
				'Portuguese (Portugal)' => '葡萄牙语（葡萄牙）',
				'Russian' => '俄语',
				'Swedish' => '瑞典语',
				'Turkish' => '土耳其语',
				'Ukrainian' => '乌克兰语',
				'Vietnamese' => '越南语',
				// WordPress AI plugin 1.4.0: alt text generation settings.
				'Enable alt text generation' => '启用替代文本生成',
				'Mark as decorative' => '标记为装饰性图片',
				'This image appears to be decorative. Consider marking it as decorative so screen readers can skip it.' => '这张图片似乎是装饰性图片。建议将其标记为装饰性图片，以便屏幕阅读器跳过。',
				'This image is marked as decorative. Alt text generation is unavailable while this setting is enabled.' => '此图片已标记为装饰性图片。此设置启用期间无法生成替代文本。',
				// WordPress AI plugin 1.4.0: editorial notes template notice.
				'Unable to generate notes: the current template does not contain a post content block.' => '无法生成编辑建议：当前模板不包含文章内容区块。',
				// WordPress AI plugin 1.4.0: request log list accessibility and pagination.
				'%d result found, use up and down arrow keys to navigate.' => '找到 %d 个结果，可使用上下方向键导航。',
				'%d results found, use up and down arrow keys to navigate.' => '找到 %d 个结果，可使用上下方向键导航。',
				'<div>Page</div>%1$s<div>of %2$d</div>' => '<div>第</div>%1$s<div>页，共 %2$d 页</div>',
				'Loading suggestions' => '正在加载建议',
				// WordPress AI plugin 1.4.0: helpers deprecation and embedding notices.
				'%s (deprecated)' => '%s（已弃用）',
				'Deprecated: `%1$s` is deprecated since version %2$s. Use `%3$s` instead. %4$s' => '已弃用：`%1$s` 自版本 %2$s 起已弃用。请改用 `%3$s`。%4$s',
				'A provider must be specified when the embedding model is given as a model ID.' => '嵌入模型以模型 ID 形式给出时，必须指定提供方。',
				'An embedding model must be specified. Embeddings are only comparable to other embeddings from the same model, so no model is selected automatically.' => '必须指定嵌入模型。嵌入向量只能与同一模型生成的嵌入向量比较，因此系统不会自动选择模型。',
				// Fixed WP_Error messages and reviewed admin notices; never registration schemas.
				'A comment ID is required.' => '请提供评论 ID。',
				'A minimum of %d characters is required for translation.' => '翻译内容至少需要 %d 个字符。',
				'A minimum of %d characters is required to generate a label.' => '生成标签至少需要 %d 个字符。',
				'A minimum of %d characters is required to shorten the content.' => '缩短内容至少需要 %d 个字符。',
				'A post ID is required to generate a block container label.' => '生成容器区块标签需要提供文章 ID。',
				'At least one note is required to perform refinement.' => '至少需要一条编辑建议才能执行修改。',
				'Attachment with ID %d not found.' => '未找到 ID 为 %d 的附件。',
				'Block content is required to perform a review.' => '请提供要审阅的区块内容。',
				'Block content is required to perform refinement.' => '请提供要修改的区块内容。',
				'Choose two different revisions to compare.' => '请选择两个不同的修订版本进行比较。',
				'Combining audio chunks requires MP3 output, which the provider did not return.' => '合并音频片段需要 MP3 格式，但提供方未返回该格式。',
				'Comment ID is required.' => '请提供评论 ID。',
				'Comment with ID %d not found.' => '未找到 ID 为 %d 的评论。',
				'Content is required to generate %s suggestions.' => '生成%s建议需要提供内容。',
				'Content is required to generate a meta description.' => '生成元描述需要提供内容。',
				'Content is required to generate a prompt.' => '生成提示词需要提供内容。',
				'Content is required to generate a summary.' => '生成总结需要提供内容。',
				'Content is required to generate an excerpt suggestion.' => '生成摘要建议需要提供内容。',
				'Content is required to generate title suggestions.' => '生成标题建议需要提供内容。',
				'Content is required to resize.' => '调整内容长度需要提供内容。',
				'Could not parse AI response as valid suggestions.' => '无法将 AI 返回的内容解析为有效建议。',
				'Could not parse the AI response as a summary of changes.' => '无法将 AI 返回的内容解析为变更摘要。',
				'Could not parse the AI response as slug suggestions.' => '无法将 AI 返回的内容解析为别名建议。',
				'Could not retrieve image URL from attachment.' => '无法获取附件的图片地址。',
				'Either attachment_id or image_url must be provided.' => '请提供 attachment_id 或 image_url。',
				'Error getting terms: %1$s' => '获取分类词时出错：%1$s',
				'Failed to decode base64 image data.' => '无法解码 base64 图片数据。',
				'Failed to parse AI response.' => '无法解析 AI 返回的内容。',
				'Failed to retrieve attachment data.' => '无法获取附件数据。',
				'Failed to write image data to temporary file.' => '无法将图片数据写入临时文件。',
				'Invalid JSON input.' => '输入的 JSON 无效。',
				'No %s suggestions were generated.' => '未生成%s建议。',
				'No base64 data found in the provided input.' => '输入内容中未找到 base64 数据。',
				'No content provided for translation.' => '请提供要翻译的内容。',
				'No content provided to generate a label.' => '请提供用于生成标签的内容。',
				'No excerpt suggestion was generated.' => '未生成摘要建议。',
				'No image data was generated.' => '未生成图片数据。',
				'No image was generated.' => '未生成图片。',
				'No label was generated.' => '未生成标签。',
				'No prompt was generated.' => '未生成提示词。',
				'No resized content was generated.' => '未生成调整长度后的内容。',
				'No summary of the changes was generated.' => '未生成变更摘要。',
				'No summary was generated.' => '未生成总结。',
				'No translated content was generated.' => '未生成翻译内容。',
				'Post not found.' => '未找到文章。',
				'Post title or content is required to generate slug suggestions.' => '生成别名建议需要提供文章标题或内容。',
				'Post with ID %d not found.' => '未找到 ID 为 %d 的文章。',
				'Revision with ID %d is not a revision of this post.' => 'ID 为 %d 的修订不属于这篇文章。',
				'Revisions are turned off for this post.' => '这篇文章未启用修订功能。',
				'Sorry, you are not allowed to filter users by role.' => '你无权按角色筛选用户。',
				'Sorry, you are not allowed to filter users by username.' => '你无权按用户名筛选用户。',
				'Taxonomy "%s" does not exist.' => '分类法“%s”不存在。',
				'Taxonomy not found.' => '未找到分类法。',
				'Text or a post with content is required to generate speech.' => '生成语音需要提供文本或有正文的文章。',
				'The AI provider returned an empty suggestion.' => 'AI 提供方返回了空建议。',
				'The automatic installation was blocked by a filter.' => '自动安装被过滤器阻止。',
				'The data is not a valid base64 encoded string.' => '数据不是有效的 base64 编码字符串。',
				'The data is not a valid image.' => '数据不是有效的图片。',
				'The page number requested is larger than the number of pages available.' => '请求的页码超过了总页数。',
				'The plugin could not be installed.' => '无法安装插件。',
				'The plugin download link could not be determined.' => '无法确定插件下载链接。',
				'The plugin file could not be determined after installation.' => '安装后无法确定插件文件。',
				'The provided image URL could not be retrieved as a supported image.' => '无法从提供的地址获取受支持格式的图片。',
				'The provided image data is not a supported image type.' => '提供的图片数据不属于受支持的图片类型。',
				'The provider did not return inline audio data.' => '提供方未返回内联音频数据。',
				'The provider returned data that is not audio.' => '提供方返回的数据不是音频。',
				'The provider returned inconsistent audio formats across chunks.' => '提供方返回的音频片段格式不一致。',
				'The provider returned invalid audio data.' => '提供方返回了无效的音频数据。',
				'The reference image is not valid base64-encoded data.' => '参考图片不是有效的 base64 编码数据。',
				'The requested content was not found.' => '未找到请求的内容。',
				'The specified attachment is not an image.' => '指定的附件不是图片。',
				'The specified target language is not supported for translation.' => '翻译功能不支持指定的目标语言。',
				'The suggestion returned was blank after sanitization.' => '返回的建议在内容清理后为空。',
				'There is no earlier revision to compare with.' => '没有更早的修订版本可供比较。',
				'This comment is already being analyzed.' => '这条评论正在分析，请等待当前任务完成。',
				'This post is long enough that its audio is generated in several parts, which can only be joined as MP3, and the connected provider cannot produce MP3 audio. Connect a provider that supports MP3 output, or shorten the post.' => '这篇文章较长，音频需分段生成并以 MP3 格式合并，但已连接的提供方不支持 MP3 输出。请连接支持 MP3 输出的提供方，或缩短文章。',
				'This taxonomy cannot be used for this post.' => '此分类法不能用于这篇文章。',
				'Unable to encode the type-ahead prompt.' => '无法编码输入预测提示词。',
				'Unable to parse the type-ahead suggestion response.' => '无法解析输入预测返回的建议。',
				'You do not have permission to analyze comments.' => '你无权分析评论。',
				'You do not have permission to edit this attachment.' => '你无权编辑此附件。',
				'You do not have permission to edit this post.' => '你无权编辑这篇文章。',
				'You do not have permission to generate %s suggestions for this post.' => '你无权为这篇文章生成%s建议。',
				'You do not have permission to generate %s suggestions.' => '你无权生成%s建议。',
				'You do not have permission to generate alt text.' => '你无权生成替代文本。',
				'You do not have permission to generate excerpts for this post.' => '你无权为这篇文章生成摘要。',
				'You do not have permission to generate excerpts.' => '你无权生成摘要。',
				'You do not have permission to generate image prompts for this post.' => '你无权为这篇文章生成图片提示词。',
				'You do not have permission to generate image prompts.' => '你无权生成图片提示词。',
				'You do not have permission to generate images.' => '你无权生成图片。',
				'You do not have permission to generate meta descriptions for this post.' => '你无权为这篇文章生成元描述。',
				'You do not have permission to generate meta descriptions.' => '你无权生成元描述。',
				'You do not have permission to generate reply suggestions.' => '你无权生成回复建议。',
				'You do not have permission to generate slugs for this post.' => '你无权为这篇文章生成别名。',
				'You do not have permission to generate slugs.' => '你无权生成别名。',
				'You do not have permission to generate speech for this post.' => '你无权为这篇文章生成语音。',
				'You do not have permission to generate speech.' => '你无权生成语音。',
				'You do not have permission to generate suggestions for this taxonomy.' => '你无权为此分类法生成建议。',
				'You do not have permission to generate titles for this post.' => '你无权为这篇文章生成标题。',
				'You do not have permission to generate titles.' => '你无权生成标题。',
				'You do not have permission to import images.' => '你无权导入图片。',
				'You do not have permission to request type-ahead suggestions for this post.' => '你无权为这篇文章请求输入预测建议。',
				'You do not have permission to request type-ahead suggestions.' => '你无权请求输入预测建议。',
				'You do not have permission to resize content.' => '你无权调整内容长度。',
				'You do not have permission to run AI refinements on this post.' => '你无权使用 AI 修改这篇文章。',
				'You do not have permission to run AI refinements.' => '你无权执行 AI 修改。',
				'You do not have permission to run AI reviews on this post.' => '你无权使用 AI 审阅这篇文章。',
				'You do not have permission to run AI reviews.' => '你无权执行 AI 审阅。',
				'You do not have permission to summarize content.' => '你无权总结内容。',
				'You do not have permission to summarize this post.' => '你无权总结这篇文章。',
				'You do not have permission to translate content.' => '你无权翻译内容。',
				'You do not have permission to view the revisions of this post.' => '你无权查看这篇文章的修订版本。',
				'You do not have permission to view these revisions.' => '你无权查看这些修订版本。',
				'Generated by AI. Check the changes before relying on this summary.' => '此摘要由 AI 生成，使用前请核对实际变更。',
				'Invalid JSON input: %s' => '输入的 JSON 无效：%s',
				'The <a>MCP Adapter plugin</a> is installed but not active. Exposure choices are saved and take effect once it is active.' => '已安装 <a>MCP Adapter 插件</a>，但尚未启用。开放设置已保存，启用后才会生效。',
				'The <a>MCP Adapter plugin</a> is not active. Exposure choices are saved and take effect once it is active.' => '<a>MCP Adapter 插件</a>尚未启用。开放设置已保存，启用后才会生效。',
				'The Abilities Explorer is turned off. Reload the page.' => '能力浏览器已关闭，请重新加载页面。',
				'The MCP Adapter is active but no MCP server is registered on this site.' => 'MCP Adapter 已启用，但本站尚未注册 MCP 服务器。',
				'The automatic installation failed and will not retry on its own: %s' => '自动安装失败，系统不会自动重试：%s',
				'This data could not be encoded as JSON.' => '无法将此数据编码为 JSON。',
				'No text changes were found in the title or content between these revisions.' => '这些修订版本的标题和正文之间没有文本变更。',
				// WordPress AI 1.5.0: reviewed fixed UI drift; no dynamic metadata or prompts.
				'%d experiment not enabled' => '%d 项实验未启用',
				'%d experiments not enabled' => '%d 项实验未启用',
				'%d feature enabled' => '%d 项功能已启用',
				'%d feature not enabled' => '%d 项功能未启用',
				'%d features enabled' => '%d 项功能已启用',
				'%d features not enabled' => '%d 项功能未启用',
				'%s (Markdown)' => '%s（Markdown）',
				'%s (not offered by the current provider)' => '%s（当前提供方不支持）',
				'%s log entries have been deleted.' => '已删除 %s 条日志。',
				'%s log entry has been deleted.' => '已删除 %s 条日志。',
				'A summary of other revisions is still being generated. Please wait for it to finish.' => '其他修订的摘要仍在生成，请等待完成后再试。',
				'A wpai_embedding_sync_object_indexed callback failed for %1$s %2$d: %3$s' => '对象 %1$s %2$d 的 wpai_embedding_sync_object_indexed 回调失败：%3$s',
				'Abilities list' => '能力列表',
				'Ability' => '能力',
				'Success' => '成功',
				'Timeout' => '超时',
				'AI Client' => 'AI 客户端',
				'MCP Tool' => 'MCP 工具',
				'Text' => '文本',
				'Image' => '图片',
				'Audio' => '音频',
				'Embeddings' => '向量嵌入',
				'Metadata' => '元数据',
				'Ability statistics' => '能力统计',
				'Activate %s' => '启用 %s',
				'Activating…' => '正在启用…',
				'All container blocks already have labels, or do not contain enough content.' => '所有容器区块已有标签，或内容不足，暂时无法生成。',
				'All logs' => '全部日志',
				'All logs have been deleted.' => '已删除全部日志。',
				'An audio chunk contained no audio data.' => '一个音频片段未包含音频数据。',
				'An embedding consumer ID cannot be empty.' => '嵌入向量使用方 ID 不能为空。',
				'Audio for “%s”' => '“%s”的音频',
				'Audio generation failed.' => '音频生成失败。',
				'Audio generation failed. Please ensure you have a connected provider that supports text to speech.' => '音频生成失败。请在 AI 连接器中检查是否已连接支持语音合成的提供方。',
				'Audio generation is already in progress for this post.' => '这篇文章的音频正在生成，请等待当前任务完成。',
				'Audio generation is not available for this post type.' => '此文章类型不支持音频生成。',
				'Audio generation never started. Background processing (WP-Cron) does not appear to be running on this site.' => '音频生成尚未启动。本站的后台处理（WP-Cron）可能未运行，请检查计划任务。',
				'Audio generation state was corrupted. Please try again.' => '音频生成状态已损坏，请重试。',
				'Audio generation stopped responding. Please try again.' => '音频生成已停止响应，请重试。',
				'Automatic installation already ran for this experiment activation; it will run again if the experiment is turned off and on.' => '本次启用实验后已执行过自动安装；关闭并重新启用实验后才会再次执行。',
				'Automatic installation is in progress; reload this page in a moment.' => '正在自动安装，请稍后重新加载此页面。',
				'Automatic installation is unavailable because this site does not allow plugin installs.' => '本站不允许安装插件，无法执行自动安装。',
				'Block label generation requires at least %d characters.' => '生成区块标签至少需要 %d 个字符。',
				'Block label generation will be available when the post content has at least %d characters.' => '文章内容达到 %d 个字符后可生成区块标签。',
				'By: %s' => '作者：%s',
				'C2PA Content Credentials detected (not verified)' => '检测到 C2PA 内容凭证（尚未验证）',
				'C2PA Content Credentials were detected in this file. To verify them, download the original image and drag it into the Content Authenticity Initiative verify tool.' => '此文件中检测到 C2PA 内容凭证。要验证，请下载原图，并将其拖入 Content Authenticity Initiative 验证工具。',
				'Category' => '分类',
				'Check again' => '重新检查',
				'Choose the "Test" action from an ability\'s row actions in the list.' => '在能力列表中打开对应行的操作菜单，选择“测试”。',
				'Choose which abilities are exposed to AI agents through the MCP server. Abilities keep their default visibility unless changed here.' => '选择通过 MCP 服务器向 AI 代理开放的能力；未在此修改的能力保留默认可见性。',
				'Comment on %1$s by %2$s' => '%2$s 对《%1$s》的评论',
				'Comments' => '评论',
				'Comments for %s' => '《%s》的评论',
				'Copy %s' => '复制 %s',
				'Could not check on audio generation. It may still be running in the background.' => '无法查询音频生成状态，任务可能仍在后台运行。',
				'Could not create C2PA sidecar directory.' => '无法创建 C2PA 附属文件目录。',
				'Could not determine the file size.' => '无法确定文件大小。',
				'Could not determine the uploads directory.' => '无法确定上传目录。',
				'Credentials found' => '已发现内容凭证',
				'Default' => '默认',
				'Delete' => '删除',
				'Delete Audio' => '删除音频',
				'Delete all logs?' => '删除全部日志？',
				'Delete logs older than 1 year?' => '删除超过 1 年的日志？',
				'Delete logs older than 30 days?' => '删除超过 30 天的日志？',
				'Delete logs older than 90 days?' => '删除超过 90 天的日志？',
				'Delete only logs older than this many days. 0 deletes all logs.' => '仅删除超过此天数的日志；填 0 会删除全部日志。',
				'Delete the generated audio? This cannot be undone.' => '删除已生成的音频？此操作无法撤销。',
				'Display audio player on the front end' => '在网站前台显示音频播放器',
				'Expose %s over MCP' => '通过 MCP 开放 %s',
				'Exposed' => '已开放',
				'Exposure choices apply only while the MCP Access experiment is enabled. When it is disabled, abilities return to the defaults the MCP Adapter serves on its own.' => '开放设置仅在“MCP 访问”实验启用期间生效。禁用后，能力恢复为 MCP Adapter 自身提供的默认状态。',
				'Failed to activate the plugin.' => '插件启用失败。',
				'Failed to delete audio.' => '音频删除失败。',
				'Failed to generate block label.' => '区块标签生成失败。',
				'Failed to install the plugin.' => '插件安装失败。',
				'Failed to load MCP settings.' => 'MCP 设置加载失败。',
				'Failed to save MCP settings.' => 'MCP 设置保存失败。',
				'Failed to start audio generation.' => '无法启动音频生成。',
				'Failed to write C2PA sidecar file.' => '无法写入 C2PA 附属文件。',
				'Failed to write audio data to the temporary file.' => '无法将音频数据写入临时文件。',
				'Featured connector "%s" must be an array.' => '推荐连接器“%s”的配置必须为数组。',
				'Featured connector "%s" must have a name.' => '推荐连接器“%s”必须设置名称。',
				'Featured connector "%s" must have a plugin file ending in .php.' => '推荐连接器“%s”必须指定以 .php 结尾的插件文件。',
				'Featured connector ID "%s" is invalid. IDs may only contain lowercase letters, numbers, hyphens and underscores.' => '推荐连接器 ID“%s”无效；ID 只能包含小写字母、数字、连字符和下划线。',
				'Field "%1$s" must be at least %2$s' => '字段“%1$s”的值不能小于 %2$s',
				'Field "%1$s" must be at most %2$s' => '字段“%1$s”的值不能大于 %2$s',
				'Field "%1$s" must be one of: %2$s' => '字段“%1$s”的值必须为以下选项之一：%2$s',
				'Field "%1$s" should be of type "%2$s"' => '字段“%1$s”的类型应为“%2$s”',
				'File exceeds the maximum scan size of %d bytes.' => '文件超过最大扫描大小 %d 字节。',
				'Generate Audio' => '生成音频',
				'Generate Block Labels' => '生成区块标签',
				'Generate label' => '生成标签',
				'Generating audio…' => '正在生成音频…',
				'Generating audio… (%1$d of %2$d)' => '正在生成音频…（%1$d/%2$d）',
				'Generating labels… (%1$d/%2$d)' => '正在生成标签…（%1$d/%2$d）',
				'If you want to use a specific voice, enter the voice identifier here. Leave blank to use the provider\'s default voice.' => '如需指定音色，请填写音色标识；留空则使用提供方的默认音色。',
				'Input should be of type "%s"' => '输入类型应为“%s”',
				'Install & activate %s' => '安装并启用 %s',
				'Installing…' => '正在安装…',
				'Invoking ability…' => '正在调用能力…',
				'It is activated automatically when an administrator with plugin-activation permissions next visits the dashboard.' => '具有插件启用权限的管理员下次访问仪表盘时，会自动启用该插件。',
				'It is installed and activated automatically on the next admin page load.' => '下次加载后台页面时，会自动安装并启用该插件。',
				'It is installed and activated automatically when an administrator with plugin-install permissions next visits the dashboard.' => '具有插件安装权限的管理员下次访问仪表盘时，会自动安装并启用该插件。',
				'Label generation will be available when the block content has at least %d characters.' => '区块内容达到 %d 个字符后可生成标签。',
				'Listen to this post' => '收听本文',
				'Loading ability…' => '正在加载能力…',
				'Logs to delete' => '要删除的日志',
				'MCP exposure settings saved.' => 'MCP 开放设置已保存。',
				'MCP server endpoint: %s' => 'MCP 服务器地址：%s',
				'May change or be removed in any release.' => '可能在后续版本中更改或移除。',
				'No C2PA Content Credentials were detected in this file.' => '此文件中未检测到 C2PA 内容凭证。',
				'No connected AI provider supports text to speech.' => '已连接的 AI 提供方均不支持语音合成。',
				'No container blocks found to generate labels for.' => '未找到可生成标签的容器区块。',
				'No credentials' => '无内容凭证',
				'No input will be sent.' => '不会发送任何输入。',
				'No logs older than the selected age were found.' => '未找到超过所选期限的日志。',
				'Older than 1 year' => '超过 1 年',
				'Older than 30 days' => '超过 30 天',
				'Older than 90 days' => '超过 90 天',
				'Overridden' => '已覆盖默认值',
				'Permanently delete logged requests older than the selected age, or all of them. This action cannot be undone.' => '永久删除超过所选期限的请求日志，或删除全部日志。此操作无法撤销。',
				'Protected Comments: Please enter your password to view comments.' => '评论受密码保护，请输入密码后查看。',
				'Regenerate Audio' => '重新生成音频',
				'Regenerate label' => '重新生成标签',
				'Regenerating deletes the current audio and creates a new version.' => '重新生成会删除当前音频并创建新版本。',
				'Required field "%s" is missing' => '缺少必填字段“%s”',
				'Retry' => '重试',
				'Save changes' => '保存更改',
				'Save your changes first — audio is generated from the saved content.' => '请先保存更改；音频会根据已保存的内容生成。',
				'Short write for C2PA sidecar file.' => 'C2PA 附属文件未完整写入。',
				'Something went wrong.' => '操作失败。',
				'Successfully deleted %s log entries.' => '已成功删除 %s 条日志。',
				'Successfully deleted %s log entry.' => '已成功删除 %s 条日志。',
				'Text and image generation with Mistral AI models. Requires a Mistral AI account; usage is billed by Mistral AI.' => '使用 Mistral AI 模型生成文本和图片。需要 Mistral AI 账户，使用费用由 Mistral AI 收取。',
				'Text and image generation with models from many providers through one API key. Requires a Vercel account; usage is billed by Vercel.' => '通过一个 API 密钥使用多家提供方的模型生成文本和图片。需要 Vercel 账户，使用费用由 Vercel 收取。',
				'This post has no content to generate audio from.' => '此文章没有可用于生成音频的内容。',
				'This provider requires a voice. Choose one in the Text to Speech settings, under Voice. Provider error: %s' => '此提供方要求指定音色。请在“语音合成”设置的“音色”中选择。提供方错误：%s',
				'Unchecked' => '尚未检查',
				'Unverified — credentials were detected but have not been validated. Click to open the Content Authenticity Initiative verify tool.' => '尚未验证：已检测到内容凭证，但未验证其有效性。点击打开 Content Authenticity Initiative 验证工具。',
				'Use default (%s)' => '使用默认值（%s）',
				'Use the provider default' => '使用提供方默认值',
				'Voice' => '音色',
				'Yes, Delete' => '确认删除',
				'You do not have permission to delete audio for this post.' => '你无权删除此文章的音频。',
				'You do not have permission to delete the audio file for this post.' => '你无权删除此文章的音频文件。',
				'You do not have permission to generate audio for this post.' => '你无权为此文章生成音频。',
				'You do not have permission to use the Abilities Explorer.' => '你无权使用能力浏览器。',
				'You do not have permission to view audio generation status for this post.' => '你无权查看此文章的音频生成状态。',
				'You no longer have permission to use the Abilities Explorer. Reload the page to sign in again.' => '你已无权使用能力浏览器。请重新加载页面并重新登录。',
				'← Back to List' => '← 返回列表',
				'Label generation failed. Please ensure you have a connected provider that supports text generation.' => '标签生成失败。请在 AI 连接器中检查是否已连接支持文本生成的提供方。',
				'Summarizing the changes failed. Please ensure you have a connected provider that supports text generation.' => '修订变更摘要生成失败。请在 AI 连接器中检查是否已连接支持文本生成的提供方。',

			);
		}
	}
}
