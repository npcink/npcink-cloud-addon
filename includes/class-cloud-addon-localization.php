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
		private const BUNDLED_TEXT_DOMAIN = 'npcink-cloud-addon-bundled';
		private static bool $bundled_domain_loaded = false;

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
			if ( isset( $translations[ $source ] ) ) {
				return $translations[ $source ];
			}

			return self::bundled_translation( $translation, $source );
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
			if ( isset( $translations[ $text ] ) ) {
				return $translations[ $text ];
			}

			return self::bundled_translation( $translation, $text );
		}

		/** Loads the shipped complete zh_CN catalog as a last-resort domain. */
		private static function load_bundled_domain(): void {
			if ( self::$bundled_domain_loaded || ! function_exists( 'load_textdomain' ) || ! self::should_localize() ) {
				return;
			}
			self::$bundled_domain_loaded = true;
			$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : '';
			$locale = str_replace( '-', '_', $locale );
			$path = dirname( __DIR__ ) . '/languages/npcink-cloud-addon-' . $locale . '.mo';
			if ( is_readable( $path ) ) {
				load_textdomain( self::BUNDLED_TEXT_DOMAIN, $path );
			}
		}

		/** Returns a bundled translation only when WordPress supplied the English source. */
		private static function bundled_translation( string $translation, string $source ): string {
			self::load_bundled_domain();
			if ( ! function_exists( 'get_translations_for_domain' ) ) {
				return $translation;
			}
			$catalog = get_translations_for_domain( self::BUNDLED_TEXT_DOMAIN );
			if ( ! is_object( $catalog ) || ! method_exists( $catalog, 'translate' ) ) {
				return $translation;
			}
			$bundled = $catalog->translate( $source );

			return '' !== $bundled && $bundled !== $source ? $bundled : $translation;
		}

		/**
		 * Returns whether the fallback translations should run.
		 *
		 * @return bool
		 */
		public static function should_localize(): bool {
			if ( function_exists( 'is_admin' ) && ! is_admin() ) {
				// Editor-facing REST and AJAX calls run outside is_admin(),
				// but they render the same admin-adjacent error strings.
				$rest_request = defined( 'REST_REQUEST' ) && REST_REQUEST;
				$doing_ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
				if ( ! $rest_request && ! $doing_ajax ) {
					return false;
				}
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
		 * Returns fixed addon admin translation fallbacks, built once per request.
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
		 * Returns the uncached addon admin translation fallback entries.
		 *
		 * @return array<string,string>
		 */
		private static function translation_entries(): array {
			return array(
				'Saving…' => '正在保存…',
				'Cloud connector status and access settings for this WordPress site.' => '此 WordPress 站点的 Cloud 连接器状态和访问设置。',
				'Connect' => '连接',
				'Cloud addon sections' => 'Cloud addon 分区',
				'Cloud Base URL' => 'Cloud 基础 URL',
				'Not set' => '未设置',
				'Last verified' => '上次验证',
				'Never' => '从未',
				'Save' => '保存',
				'Open Cloud sites' => '打开 Cloud 站点',
				'Not checked' => '未检查',
				'Temporarily unavailable' => '暂时不可用',
				'Manual connection fallback' => '手动连接备用方式',
				'Use only for recovery or local debugging when Cloud authorization is unavailable.' => '仅在 Cloud 授权不可用时用于恢复或本地调试。',
				'Only when Cloud authorization is unavailable.' => '仅在 Cloud 授权不可用时使用。',
				'Leave blank to keep the stored key. Accepts a Cloud-issued mak1_ recovery key only.' => '留空则保留已存密钥；仅接受 Cloud 签发的 mak1_ 恢复密钥。',
				'Recovery Cloud API Key' => '恢复用 Cloud API 密钥',
				'Paste a Cloud-issued mak1_ recovery key' => '粘贴 Cloud 签发的 mak1_ 恢复密钥',
				'Leave blank to keep the stored connection key. Use Change Cloud account for normal account changes.' => '留空可继续使用已保存的连接密钥。如需正常更换账号，请使用“更换 Cloud 账号”。',
				'This fallback accepts only a Cloud-issued mak1_ wrapper and never displays the stored value.' => '此恢复入口仅接受 Cloud 签发的 mak1_ 封装密钥，并且不会显示已保存的值。',
				'Timeout' => '超时',
				'seconds' => '秒',
				'Save and Verify' => '保存并验证',
				'Not available yet' => '暂不可用',
				'Use Run readiness test to execute the liveness and signed-read checks.' => '使用“运行就绪测试”执行存活检查和签名读取检查。',
				'Cloud entitlement did not return hosted runtime detail. Re-verify or open Cloud status detail.' => 'Cloud 权益未返回托管运行时详情。请重新验证或打开 Cloud 状态详情。',
				'Connection' => '连接',
				'Current site' => '当前站点',
				'Add this site in Npcink Cloud' => '在 Npcink Cloud 添加当前站点',
				'Cloud will create or activate this site connection and return here with a one-time authorization code.' => 'Cloud 会创建或激活此站点连接，并通过一次性授权码返回这里。',
				'Review Cloud privacy and data retention information' => '查看 Cloud 隐私和数据保留信息',
				'Site Knowledge delivery starts after the connector verifies successfully.' => '连接器成功验证后才会开始站点知识库投递。',
				'Start indexing' => '开始索引',
				'Send a public post and page manifest so existing content enters the knowledge base.' => '发送公开文章和页面清单，使现有内容进入知识库。',
				'yes' => '是',
				'Collection' => '收集',
				'enabled' => '已启用',
				'Buffered events' => '缓冲事件',
				'Last upload status' => '上次上传状态',
				'never' => '从未',
				'failed' => '失败',
				'Last upload error' => '上次上传错误',
				'Connection status' => '连接状态',
				'Last failure' => '上次失败',
				'available' => '可用',
				'not refreshed' => '尚未刷新',
				'read failed' => '读取失败',
				'%1$s, %2$d buffered' => '%1$s，%2$d 条待发送',
				'You do not have permission to manage Npcink Cloud settings.' => '你没有管理 Npcink Cloud 设置的权限。',
				'Cloud settings saved and verified.' => 'Cloud 设置已保存并验证。',
				'The Cloud authorization request expired or is invalid. Start the connection again from WordPress.' => 'Cloud 授权请求已过期或无效。请从 WordPress 重新开始连接。',
				'Cloud credentials could not be stored securely. The existing connection was not changed. Check the WordPress security salts and reconnect.' => '无法安全存储 Cloud 凭据。现有连接未更改。请检查 WordPress 安全盐并重新连接。',
				'Cloud connection completed and verified.' => 'Cloud 连接已完成并通过验证。',
				'Cloud Base URL must use HTTPS unless it points to localhost or 127.0.0.1.' => 'Cloud 基础 URL 必须使用 HTTPS，除非指向 localhost 或 127.0.0.1。',
				'Cloud connection disconnected locally. Stored credentials and addon-owned buffers were cleared.' => 'Cloud 连接已在本地断开。已清除存储的凭据和 addon 自有缓冲。',
				'Cloud Addon settings are not verified.' => 'Cloud Addon 设置尚未验证。',
				'The requested local permission is not supported.' => '请求的本地授权不受支持。',
				'The local permission could not be saved securely. No permission or background delivery state was changed.' => '本地权限无法安全保存，权限和后台投递状态均未改变。',
				'%1$s %2$s.' => '%1$s%2$s。',
				'Cloud settings verified, but entitlement summary could not refresh: %s' => 'Cloud 设置已验证，但权益摘要无法刷新：%s',
				'Unknown entitlement refresh result.' => '未知的权益刷新结果。',
				'The replacement Cloud connection could not be verified, so the existing connection was kept: %s' => '新的 Cloud 连接验证失败，因此保留了现有连接：%s',
				'Cloud authorization exchange failed: %s' => 'Cloud 授权交换失败：%s',
				'Cloud authorization exchange did not return a valid connection key.' => 'Cloud 授权交换没有返回有效的连接密钥。',
				'Cloud authorization exchange returned an invalid activation state.' => 'Cloud 授权交换返回了无效的激活状态。',
				'The Cloud account has reached its active-site limit. Deactivate another active site in Npcink Cloud or upgrade the account plan, then start the connection again. (%s)' => 'Cloud 账户已达到启用站点上限。请在 Npcink Cloud 中停用另一个启用中的站点，或升级账户套餐，然后重新开始连接。（%s）',
				'The Cloud account has reached its connected-site limit. Remove an unused site in Npcink Cloud, then start the connection again. (%s)' => 'Cloud 账户已达到已连接站点上限。请在 Npcink Cloud 中移除不再使用的站点，然后重新开始连接。（%s）',
				'The Cloud authorization request expired or is invalid. Start the connection again from WordPress. (%s)' => 'Cloud 授权请求已过期或无效。请从 WordPress 重新开始连接。（%s）',
				'Cloud authorization exchange failed. Start the connection again or contact support with this error code: %s' => 'Cloud 授权交换失败。请重新开始连接，或联系支持并提供此错误代码：%s',
				'Live check failed: %s' => '存活检查失败：%s',
				'Signed verification failed: %s' => '签名验证失败：%s',
				'Cloud verification failed.' => 'Cloud 验证失败。',
				'The requested Site Knowledge index action is not supported.' => '请求的站点知识库索引操作不受支持。',
				'Type the confirmation word before running this Site Knowledge index action.' => '运行此站点知识库索引操作前，请输入确认词。',
				'Site Knowledge indexing delivery scheduled: %1$d public content items in %2$d batches.' => 'Site Knowledge 索引投递已安排：%1$d 项公开内容，共 %2$d 个批次。',
				'Site Knowledge rebuild delivery scheduled: %1$d public content items in %2$d batches.' => 'Site Knowledge 重建投递已安排：%1$d 项公开内容，共 %2$d 个批次。',
				'Site Knowledge index deletion requested. WordPress content was not changed.' => '站点知识库索引删除请求已发送。WordPress 内容未被更改。',
				'Site Knowledge index action requested.' => '站点知识库索引操作请求已发送。',
				'Cloud settings are incomplete. Reconnect this site in Npcink Cloud to issue a new connection key.' => 'Cloud 设置不完整。请在 Npcink Cloud 中重新连接此站点以签发新的连接密钥。',
				'Authorize this site in Npcink Cloud to create the connection.' => '在 Npcink Cloud 授权当前站点以创建连接。',
				'Verified' => '已验证',
				'Cloud settings are saved and verified.' => 'Cloud 设置已保存并通过验证。',
				'Pending verification' => '等待验证',
				'Cloud settings are saved but have not passed verification.' => 'Cloud 设置已保存，但尚未通过验证。',
				'Cloud verification completed, but the verified state could not be stored securely.' => 'Cloud 验证已完成，但无法安全存储已验证状态。',
				'Cloud credentials could not be stored or read securely. Reconnect this site after checking the WordPress security salts.' => '无法安全存储或读取 Cloud 凭据。请检查 WordPress 安全盐后重新连接此站点。',
				'Cloud API Key cannot be empty.' => 'Cloud API 密钥不能为空。',
				'Manual Cloud API Key recovery must use a Cloud-issued mak1_ wrapper. Reconnect this site in Npcink Cloud to issue a new key.' => '手动恢复 Cloud API 密钥必须使用 Cloud 签发的 mak1_ 封装密钥。请在 Npcink Cloud 中重新连接此站点以签发新密钥。',
				'Cloud API Key could not be decoded.' => '无法解码 Cloud API 密钥。',
				'Cloud API Key format is invalid. Use a key issued by Npcink Cloud.' => 'Cloud API 密钥格式无效。请使用 Npcink Cloud 签发的密钥。',
				'Cloud API Key wrapper is missing required signing data.' => 'Cloud API 密钥封装缺少必需的签名数据。',
				'Site Knowledge delivery is disabled locally.' => '站点知识库投递已在本地关闭。',
				'Site Knowledge delivery is disabled locally. Enable delivery before starting or rebuilding the index.' => '站点知识库投递已在本地关闭。开始索引或重建索引前，请先启用投递。',
				'Site Knowledge delete cleanup is waiting for a local retry.' => 'Site Knowledge 删除清理正在等待本地重试。',
				'Site Knowledge full-index delivery is blocked after bounded retries.' => 'Site Knowledge 全量索引投递已达到重试上限并暂停。',
				'Cloud Site Knowledge run identifier is invalid.' => 'Cloud 站点知识库运行标识符无效。',
				'Cloud Site Knowledge run did not finish within the bounded polling window.' => 'Cloud Site Knowledge 运行未能在有限轮询窗口内完成。',
				'Cloud Site Knowledge maintenance run failed.' => 'Cloud Site Knowledge 维护运行失败。',
				'Site Knowledge sync mode must be refresh, rebuild, or delete.' => '站点知识库同步模式必须是 refresh、rebuild 或 delete。',
				'Cloud did not accept the monitoring state projection.' => 'Cloud 未接受监控状态投影。',
				'Core' => '治理核心',
				'Abilities' => '原子能力',
				'Adapter' => '渠道适配器',
				'No pending public content changes were found. No content was sent to Cloud.' => '未发现待投递的公开内容变更。本次未向 Cloud 发送内容。',
				'Cloud observability response did not include an accepted event count.' => 'Cloud 监控响应未返回已接受事件数量。',
				'Cloud customer journey response did not include an accepted event count.' => 'Cloud 客户旅程响应未返回已接受事件数量。',
				'Text generation' => '文本生成',
				'Image generation' => '图片生成',
				'Image understanding' => '图片理解',
				'Healthy' => '正常',
				'Paused' => '已暂停',
				'Expired' => '已过期',
				'Not provided' => '未提供',
				'Not verified' => '未验证',
				'WordPress AI generation tests' => 'WordPress AI 生成测试',
				'May use credits. Cloud picks the model.' => '可能消耗额度，模型由云端选择。',
				'Test text' => '测文本',
				'Test image' => '测图片',
				'Test vision' => '测图像理解',
				'Advanced: Open Ability Explorer' => '高级：打开能力浏览器',
				'Generate' => '生成',
				'Generating…' => '生成中…',
				'Generated' => '已生成',
				'View raw response' => '查看原始响应',
				'Generated, but no plain text result was returned.' => '已生成，但没有可直接阅读的文本结果。',
				'Generation failed. Try again, or open Ability Explorer for details.' => '生成失败。请重试，或打开能力浏览器查看详情。',
				'Could not reach the site. Check your connection and try again.' => '无法连接本站，请检查网络后重试。',
				'You need administrator access to run this test.' => '需要管理员权限才能运行此测试。',
				'This generation ability is not available. Open Ability Explorer or check the WordPress AI plugin.' => '此生成能力不可用。请打开能力浏览器，或检查 WordPress AI 插件。',
				'Check the form fields and try again.' => '请检查表单字段后重试。',
				'This page expired. Refresh and try again.' => '页面已过期，请刷新后重试。',
				'Select from media library' => '从媒体库选择',
				'Select an image' => '选择图片',
				'Use this image' => '使用此图片',
				'Media library is unavailable on this screen.' => '当前屏幕无法打开媒体库。',
				'Content' => '内容',
				'Prompt' => '提示词',
				'Attachment ID' => '附件 ID',
				'Image URL' => '图片 URL',
				'Context' => '上下文',
				'Media library attachment ID. Optional when an image URL is set.' => '媒体库附件 ID。已填写图片 URL 时可留空。',
				'Public image URL or data URI.' => '公开图片 URL 或 data URI。',
				'Concise' => '简洁',
				'Keep the alt text concise and specific.' => '保持替代文本简洁具体。',
				'Main subject' => '主体',
				'Focus on the main subject and skip minor background details.' => '聚焦主体，略过次要背景细节。',
				'Screen reader' => '读屏',
				'Write the alt text for screen reader users.' => '面向读屏用户撰写替代文本。',
				'Not decorative' => '非装饰',
				'Treat this as informative, not decorative.' => '按信息图处理，而非装饰图。',
				'Simple scene' => '简洁场景',
				'A simple, clean illustration with a plain background.' => '简洁干净的插画，背景留白。',
				'Photo style' => '照片风格',
				'A realistic photograph with natural lighting.' => '自然光下的写实照片。',
				'No text' => '无文字',
				'Do not include any text or logos in the image.' => '画面中不要出现文字或标志。',
				'WordPress AI can draft a short title from this sample paragraph about cloud-connected generation.' => 'WordPress AI 可据此示例段落起草一个简短标题，内容与云端生成相关。',
				'A simple watercolor illustration of a notebook and a fountain pen on a wooden desk.' => '木桌上的笔记本与钢笔，简洁水彩插画。',
				'Close' => '关闭',
				'Request log' => '请求日志',
				'AI settings' => 'AI 设置',
				'Install the official WordPress AI plugin to use generation tests.' => '安装官方 WordPress AI 插件以使用生成测试。',
				'Unavailable' => '不可用',
				'Cloud configuration and site entitlement are confirmed. Generation has not been tested by this check.' => 'Cloud 配置与本站权益已确认，本次检查未执行生成测试。',
				'Activate this site in Cloud.' => '请在 Cloud 启用本站服务。',
				'This site has no Cloud subscription.' => '本站没有 Cloud 订阅。',
				'Subscription renewal or grace needs a runtime check.' => '订阅续期或宽限状态需要在运行时核对。',
				'Cloud has no active entitlement for this site.' => 'Cloud 未返回本站的有效权益。',
				'Cloud did not provide complete entitlement evidence.' => 'Cloud 未提供完整的权益证据。',
				'This site is not entitled to this capability.' => '本站权益未包含此能力。',
				'Cloud has no executable provider configured.' => 'Cloud 未配置可执行的模型服务。',
				'The required Cloud runtime profile is not configured.' => '所需的 Cloud 运行配置尚未完成。',
				'Cloud has no eligible model or current capability evidence. Check Cloud service detail.' => 'Cloud 没有符合条件的模型或有效的能力验证证据，请查看 Cloud 服务详情。',
				'The Cloud runtime profile does not match this capability.' => 'Cloud 运行配置与此能力不匹配。',
				'The previous capability check has expired. Run the connection checks to refresh it.' => '上次能力检查已过期，请运行连接检查以刷新状态。',
				'The latest Cloud check failed. Previous evidence is not current.' => '最近一次 Cloud 检查失败，先前证据不再代表当前状态。',
				'Capability evidence is unavailable. Run the connection checks; older Cloud versions may not report it.' => '暂无能力证据，请运行连接检查；旧版 Cloud 可能尚未提供此信息。',
				'Checked: %s' => '检查时间：%s',
				'Available images' => '可用图片数',
				'Unlimited' => '无限制',
				'Remaining image recognition capacity percentage' => '剩余图片识别额度占比',
				'%1$d of %2$d' => '%1$d / %2$d',
				'Background image recognition requires an attachment URL, media fingerprint, and image MIME type.' => '后台图片识别需要附件链接、媒体指纹和图片 MIME 类型。',
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
				'Entitlements' => '权益',
				'Knowledge base' => '知识库',
				'Privacy settings' => '隐私设置',
				'Anonymous diagnostics %s' => '匿名诊断%s',
				'on' => '已开启',
				'off' => '已关闭',
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
				'%1$s / %2$s · %3$d%% remaining' => '%1$s / %2$s · 剩余 %3$d%%',
				'Used %1$s AI credits; remaining %2$s AI credits; limit %3$s AI credits.' => '已用 %1$s AI 积分；剩余 %2$s AI 积分；上限 %3$s AI 积分。',
				'AI credits shown here belong to the connected Cloud account. Disconnecting, removing, or changing this WordPress site does not transfer those AI credits.' => '此处显示的 AI 积分属于当前连接的 Cloud 账户。断开、移除或更换此 WordPress 站点不会转移这些 AI 积分。',
				'Free service and AI credits belong to the Cloud account selected during authorization, not this site. The same account may reconnect at any time; changing to another account is subject to the removal and cooldown requirements shown by Cloud.' => '免费额度属于你连接时选择的账号，不属于此站点。换成其他账号时，请按 Npcink Cloud 页面上的提示操作。',
				'After connection, the WordPress AI connector is enabled by default. Site Knowledge delivery can be turned on or off in Overview at any time.' => '连接后，WordPress AI 连接器将默认启用。站点知识库投递可随时在“概览”中开启或关闭。',
				'The new Cloud connection could not be verified: %s. The connection details were saved, so use Check connection on this page to verify again.' => '新的 Cloud 连接未能通过验证：%s。连接信息已保存，请使用本页的“检查连接”重新验证。',
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
				'Nightly site inspection is temporarily paused.' => '夜间站点巡检已暂时暂停。',
				'Usage limits and quotas' => '用量限额与配额',
				'Monitoring needs attention' => '监控需要处理',
				'Site Knowledge needs attention' => '站点知识库需要处理',
				'Service details' => '服务详情',
				'Connection management' => '连接管理',
				'Connection and service' => '连接与服务',
				'Connected' => '已连接',
				'Open Cloud' => '打开 Cloud',
				'Check connection' => '检查连接',
				'Change Cloud account' => '更换 Cloud 账号',
				'Changing the account disconnects this site first, then opens Npcink Cloud so you can pick an account.' => '更换账号会先断开此站点，再打开 Npcink Cloud 让你选择账号。',
				'Connected Cloud identity' => '当前连接',
				'Plan' => '方案',
				'More information' => '更多信息',
				'Site connected' => '已连接',
				'Connected to Npcink Cloud.' => '已连接到 Npcink Cloud。',
				'This site is connected to Npcink Cloud.' => '此站点已连接到 Npcink Cloud。',
				'This site needs to be turned on in Npcink Cloud' => '需要在 Npcink Cloud 中启用此站点',
				'Open Npcink Cloud, turn this site on, then come back here.' => '打开 Npcink Cloud 启用此站点，然后回到这里。',
				'The connection has a problem' => '连接出现问题',
				'Click Check again. If it keeps happening, contact support with the details under More information.' => '点「重新检查」。如果一直这样，请联系客服并附上「更多信息」里的内容。',
				'Open Npcink Cloud' => '打开 Npcink Cloud',
				'Details for support' => '给客服的信息',
				'Switching accounts disconnects this site first.' => '换账号会先断开此站点。',
				'Back' => '返回',
				'Change account, disconnect, or connect manually' => '更换账号、断开或手动连接',
				'The previous account was disconnected from this site.' => '已断开此站点之前的账号。',
				'Disconnected. The site and its data remain in Npcink Cloud.' => '已断开。Npcink Cloud 中的站点和数据会保留。',
				'Connection not finished' => '连接还没完成',
				'Click Reconnect to finish connecting in Npcink Cloud.' => '点「重新连接」，在 Npcink Cloud 中完成连接。',
				'Reconnect' => '重新连接',
				'Check again' => '重新检查',
				'Connect it to Npcink Cloud to use AI features.' => '连接到 Npcink Cloud 后即可使用 AI 功能。',
				'The connection needs to be confirmed again.' => '连接需要重新确认。',
				'Click the button below and confirm in Npcink Cloud to restore it.' => '点下面的按钮，在 Npcink Cloud 里确认一下即可恢复。',
				'The service has a problem we cannot fix automatically.' => '服务暂时有问题，无法自动修复。',
				'Please contact support and include the information under More information.' => '请联系客服，并附上“更多信息”里的内容。',
				'Everything is working. AI features are ready to use.' => '一切正常，AI 功能可以使用。',
				'Last checked: %s' => '上次检查：%s',
				'Contact support' => '联系客服',
				'More information (for contacting support)' => '更多信息（联系客服时使用）',
				'Copy information' => '复制信息',
				'Copied' => '已复制',
				'Connect this site to Npcink Cloud to use AI features.' => '把此站点连接到 Npcink Cloud，即可使用 AI 功能。',
				'Not connected' => '未连接',
				'This site is not connected yet.' => '此站点还没有连接。',
				'Npcink Cloud opens in a new tab. Choose your account there, then come back here.' => '会在新标签页打开 Npcink Cloud，选好账号后回到这里即可。',
				'This site' => '此站点',
				'Once connected, AI features turn on automatically. You can change them in Overview at any time.' => '连接后 AI 功能会自动开启，可随时在「概览」中调整。',
				'Free credits belong to the account you choose when connecting, not to this site. To switch to another account later, follow the steps shown in Npcink Cloud.' => '免费额度属于你连接时选择的账号，不属于此站点。以后要换账号，请按 Npcink Cloud 中的提示操作。',
				'Advanced settings' => '高级设置',
				'Usually not needed' => '一般不需要',
				'Only use this if support asks you to, or if connecting through Npcink Cloud does not work.' => '仅在客服要求，或通过 Npcink Cloud 连接不成功时使用。',
				'Change account' => '更换账号',
				'Last checked' => '上次检查',
				'Service address' => '服务地址',
				'Free credits belong to the account you chose when connecting, not to this site.' => '免费额度属于你连接时选择的账号，不属于此站点。',
				'Change account? This site will be disconnected first, then Npcink Cloud opens so you can pick an account. Cancel keeps everything as it is.' => '要更换账号吗？此站点会先断开，然后打开 Npcink Cloud 让你选择账号。点「取消」则保持不变。',
				'Confirm before changing the account.' => '更换账号前请先确认。',
				'I understand this site will be disconnected first.' => '我知道此站点会先断开。',
				'This site was disconnected. Pick an account in Npcink Cloud to reconnect.' => '此站点已断开。请在 Npcink Cloud 中选择账号重新连接。',
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
				'Succeeded' => '已成功',
				'Failed' => '失败',
				'Error' => '错误',
				'Canceled' => '已取消',
				'Ready' => '就绪',
				'Not ready' => '未就绪',
				'WordPress AI connector' => 'WordPress AI 连接器',
				'Allow WordPress AI to use Npcink Cloud.' => '允许 WordPress AI 使用 Npcink Cloud。',
				'Allow WordPress AI to use Npcink Cloud. Enabled by default while this site stays connected.' => '允许 WordPress AI 使用 Npcink Cloud。站点保持连接期间默认启用。',
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
				'Knowledge base status' => '知识库状态',
				'Knowledge base update needs attention' => '知识库更新暂时出现问题',
				'The system will keep trying automatically.' => '系统会继续自动尝试。',
				'Automatic updates are delayed. Check the site scheduler in Checks.' => '自动更新已延迟，请在「检查」中查看站点计划任务。',
				'Some content is outside the knowledge base limit' => '部分内容超出知识库容量限制',
				'%d public items were not included. Review the plan details in Cloud.' => '%d 项公开内容未被收录，请在 Cloud 中查看套餐详情。',
				'Updating the knowledge base' => '正在更新知识库',
				'Updating' => '更新中',
				'Up to date' => '全部最新',
				'Needs attention' => '需要注意',
				'Updates waiting: %d' => '待处理更新：%d',
				'All public content is up to date' => '所有公开内容均已更新',
				'Automatic updates are ready' => '自动更新已就绪',
				'Updates will appear here after public content changes.' => '公开内容发生变化后，更新情况会显示在这里。',
				'Last updated: %s' => '最近更新：%s',
				'Manual update' => '手动更新',
				'Update again' => '重新更新',
				'Site Knowledge refresh requested. Public content items sent: %d.' => '已请求站点知识库刷新。已发送公开内容项：%d。',
				'Site Knowledge refresh request failed.' => '站点知识库刷新请求失败。',
				'A knowledge base update is already in progress. No new request was started.' => '知识库正在更新中，本次未重复发起请求。',
				'Knowledge base recovery has been scheduled. The warning will clear after all public content has been delivered to Cloud.' => '已安排知识库恢复更新。全部公开内容投递到 Cloud 后，警告将自动解除。',
				'Public content is being re-sent to Cloud. The warning will clear when delivery finishes.' => '正在向 Cloud 重新投递公开内容。投递完成后，警告将自动解除。',
				'A Site Knowledge full-index delivery is already in progress.' => '已有一个 Site Knowledge 全量索引投递正在进行。',
				'No public posts or pages were found for Site Knowledge indexing.' => '没有找到可用于站点知识库索引的公开文章或页面。',
				'View checks' => '查看检查',
				'Sync above' => '使用上方同步',
				'View delivery detail' => '查看投递详情',
				'Delivery failed (%s). Re-check the Cloud connection, then sync if needed.' => '投递失败（%s）。请先检查 Cloud 连接，必要时再同步。',
				'Delivery needs attention. Re-check the Cloud connection.' => '投递需要处理。请检查 Cloud 连接。',
				'Open Knowledge base on Overview' => '打开概览中的知识库',
				'Open Connection' => '打开连接',
				'%d content updates were dropped' => '有 %d 条内容更新被丢弃',
				'Delivery error: %s' => '投递错误：%s',
				'Advanced knowledge base settings' => '高级知识库设置',
				'Needs update' => '需要更新',
				'More limits' => '更多限额',
				'Check details' => '检查明细',
				'Ready to index' => '可以开始索引',
				'%d outside limit' => '%d 项超出限额',
				'Updating · %d waiting' => '更新中 · 等待 %d',
				'View details in Cloud' => '在 Cloud 查看明细',
				'Sync' => '同步',
				'Syncing' => '同步中',
				'Open in Cloud' => '在 Cloud 中打开',
				'Knowledge base details' => '知识库详情',
				'Knowledge base content summary' => '知识库内容摘要',
				'items updated' => '项已更新',
				'items waiting' => '项待处理',
				'Details will appear after the first knowledge base update is completed.' => '首次完成知识库更新后，这里会显示详情。',
				'View content list' => '查看内容列表',
				'Waiting' => '待处理',
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
				'Send metadata-only diagnostic events (feature steps, outcomes, timing, and error codes) to improve reliability. Never includes prompts, content, user or post IDs, emails, URLs, or credentials. Off by default.' => '发送仅含元数据的诊断事件（功能步骤、结果、耗时和错误码）以改进可靠性。绝不包含 Prompt、内容、用户或文章 ID、邮箱、URL 或凭据。默认关闭。',
				'Full detail: metadata-only events cover feature steps, outcomes, timing, and machine-readable error codes. Never sent: prompts, source or generated content, raw WordPress user or post IDs, emails, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.' => '完整说明：仅含元数据的事件涵盖功能步骤、结果、耗时和机器可读错误码。绝不发送 Prompt、源内容或生成内容、原始 WordPress 用户或文章 ID、邮箱、URL、DOM 数据、凭据或自由文本错误消息。默认关闭；管理员可以随时关闭。',
				'Optionally send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.' => '可选发送功能步骤、执行结果、耗时和机器可读错误码等元数据，用于排查故障并改善可靠性。不会发送 Prompt、源内容或生成内容、WordPress 用户或文章原始 ID、邮箱、URL、DOM 数据、凭据或自由文本错误消息。默认关闭；管理员可随时关闭。',
				'Cloud connection verified' => 'Cloud 连接已验证',
				'Would you like to help improve reliability by sharing anonymous usage and error diagnostics from this WordPress site?' => '是否愿意分享此 WordPress 站点的匿名使用和故障诊断数据，以帮助改进可靠性？',
				'Only feature steps, outcomes, timing, versions, and machine-readable error codes are sent. Prompts, source or generated content, user or post identifiers, URLs, credentials, and request headers are never sent.' => '只会发送功能步骤、结果、耗时、版本和机器可读错误码。不会发送 Prompt、源内容或生成内容、用户或文章标识、URL、凭据或请求头。',
				'Allow anonymous diagnostics' => '允许匿名诊断',
				'Not now' => '暂不允许',
				'More local permissions' => '更多本地授权',
				'Run checks, or' => '运行检查，或',
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
				'Hosted Runtime' => '托管运行时',
				'ready' => '就绪',
				'continue' => '继续',
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
				'Cloud error classification' => 'Cloud 错误分类',
				'AI credits' => 'AI 积分',
				'%1$s used / %2$s limit / %3$s remaining' => '已用 %1$s / 上限 %2$s / 剩余 %3$s',
				'Delivery is off; refresh controls and routine delivery rows are hidden.' => '投递已关闭；刷新控件和常规投递行已隐藏。',
				'Change in Overview' => '在概览中更改',
				'Manage index' => '管理索引',
				'Technical delivery details' => '技术投递详情',
					'Knowledge base delivery' => '知识库投递',
				'Last success' => '上次成功',
				'Last error code' => '上次错误代码',
				'Last error time' => '上次错误时间',
				'WP-Cron disabled' => 'WP-Cron 已禁用',
				'Manual flush command' => '手动刷新命令',
				// The change-notification plural pair below no longer has a live
				// render site; it stays as the plural (ngettext) fixture for the
				// localization behavior contract.
				'%d change notification is no longer in the local delivery buffer. Request a public content refresh to reconcile it.' => '%d 条变更记录已不在本地投递缓冲区。请刷新公开内容以重新核对。',
				'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.' => '%d 条变更记录已不在本地投递缓冲区。请刷新公开内容以重新核对。',
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
				'The plugin is active, but this site is not connected to Npcink Cloud yet. Connect the site to enable its AI features.' => '插件已启用，但本站点尚未连接 Npcink Cloud。连接站点后即可启用其 AI 功能。',
				'Connect this site' => '连接此站点',
				'Do not show again' => '不再显示',
				'Cloud Addon settings are not verified. Monitoring uploads resume after the connection is verified again.' => 'Cloud Addon 设置尚未通过验证。连接重新验证通过后，将自动恢复监控数据上传。',
				'Monitoring upload state' => '监控上传状态',
				'The Cloud connection works, but this site is not active in Cloud yet. Activate the site in Npcink Cloud, then check the activation again here.' => 'Cloud 连接可用，但本站点尚未在 Cloud 激活。请先在 Npcink Cloud 激活站点，然后回到这里再次检查激活状态。',
				'Some content updates could not be delivered to the knowledge base. Open Overview to update it again.' => '部分内容更新未能投递到站点知识库。请打开概览重新更新。',
				'A recent Site Knowledge delivery could not reach or authenticate with Cloud. Re-check the connection or open Cloud for service detail.' => '最近一次站点知识库投递无法连接或通过 Cloud 认证。请重新检查连接，或打开 Cloud 查看服务详情。',
				'The cached Cloud read is out of date. Re-check the connection to confirm current service status.' => '缓存的 Cloud 读取已过期。请重新检查连接以确认当前服务状态。',
				'The latest signed Cloud read did not complete. Re-check the connection or try again later.' => '最近一次 Cloud 签名读取未完成。请重新检查连接，或稍后再试。',
				'All checks passed. No action is needed.' => '全部检查已通过，无需任何操作。',
				'The readiness check did not pass. Next step: %1$s (handled by %2$s). Reason: %3$s' => '就绪检查未通过。下一步：%1$s（由%2$s处理）。原因：%3$s',
				'Next step: %1$s (handled by %2$s).' => '下一步：%1$s（由%2$s处理）。',
				'%d content updates could not be delivered and were dropped. Use Sync to include them again.' => '有 %d 条内容更新未能投递并已丢弃。请使用「同步」重新纳入。',
				'Dropped updates' => '被放弃的更新',
				'%1$d (last at %2$s)' => '%1$d（最近于 %2$s）',
				'The Cloud connection is not configured yet.' => '尚未配置 Cloud 连接。',
				'The Cloud connection is healthy.' => 'Cloud 连接正常。',
				'This site still needs to be activated in Cloud.' => '本站点还需要在 Cloud 激活。',
				'Cloud could not be reached or refused the connection.' => '无法连接 Cloud，或 Cloud 拒绝了本次连接。',
				'The Cloud credentials have not been verified yet.' => 'Cloud 凭据尚未通过验证。',
				'The last Cloud verification did not complete.' => '最近一次 Cloud 验证未完成。',
				'The knowledge base update could not be delivered and will be retried automatically. Detail: %s' => '知识库更新未能投递，系统会自动重试。详情：%s',
				'The knowledge base update could not be delivered and automatic retries have stopped. Update the knowledge base again. Detail: %s' => '知识库更新未能投递，且自动重试已停止。请再次更新知识库。详情：%s',
				'The knowledge base update could not be delivered. Check Overview for the next step. Detail: %s' => '知识库更新未能投递。请在概览查看下一步。详情：%s',
				'This request is not supported by the Npcink Cloud AI connection yet.' => 'Npcink Cloud AI 连接暂不支持该请求。',
				'The Npcink Cloud AI connection does not support conversation history for this request.' => 'Npcink Cloud AI 连接不支持在此请求中使用对话历史。',
				'The Npcink Cloud AI connection does not support tool calls or web search.' => 'Npcink Cloud AI 连接不支持工具调用或网页搜索。',
				'The Npcink Cloud AI connection needs text input for this request.' => '此请求需要为 Npcink Cloud AI 连接提供文本输入。',
				'Image-to-image refinement is not supported by the Npcink Cloud AI connection yet.' => 'Npcink Cloud AI 连接暂不支持图生图细化。',
				'This AI feature is not currently available through the Npcink Cloud connection.' => '该 AI 功能当前无法通过 Npcink Cloud 连接使用。',
				'Npcink Cloud did not return usable output for this request. Please try again.' => 'Npcink Cloud 未返回可用的结果，请重试。',
				'Npcink Cloud is still processing this request. Wait a moment and try again later to avoid a duplicate run.' => 'Npcink Cloud 仍在处理此请求。请稍候再试，以免重复执行产生额外消耗。',
				'View upload detail' => '查看上传详情',
				'View Site Knowledge status' => '查看站点知识库状态',
				'Npcink Cloud returned an unexpected image result. Please try again.' => 'Npcink Cloud 返回了异常的图片结果，请重试。',
				'The generated image previews are too large to show. Try generating fewer images at once.' => '生成的图片预览过大，无法显示。请尝试一次生成更少的图片。',
				'The generated image could not be verified for safe delivery. Please try again.' => '生成的图片未能通过安全投递校验，请重试。',
				'Npcink Cloud could not confirm delivery of the generated image. Please try again.' => 'Npcink Cloud 未能确认图片已投递，请重试。',
				'The generated image is no longer available. Please generate it again.' => '生成的图片已失效，请重新生成。',
				'Npcink Cloud could not complete this AI request. Please try again in a moment.' => 'Npcink Cloud 暂时无法完成该 AI 请求，请稍后重试。',
				'Npcink Cloud could not be reached from this site. Please try again in a moment.' => '本站点暂时无法连接 Npcink Cloud，请稍后重试。',
				'The Npcink Cloud connection is no longer authorized. Please reconnect the site from the Cloud Addon settings page.' => 'Npcink Cloud 连接的授权已失效。请在 Cloud Addon 设置页面重新连接站点。',
				'This request reached a Npcink Cloud limit. Please wait a moment and try again, or review the plan usage in Cloud.' => '该请求已达到 Npcink Cloud 的限额。请稍后重试，或在 Cloud 中查看套餐用量。',
				'Confirm local disconnect before submitting.' => '提交前请确认要在本地断开连接。',
				'I understand that this clears only the WordPress-side connection and addon buffers.' => '我确认这只会清除 WordPress 端连接和插件缓冲区。',
				'Confirm the local disconnect before submitting this action.' => '提交此操作前请确认要在本地断开连接。',
				'Npcink Cloud model metadata not found.' => '未找到 Npcink Cloud 模型元数据。',
				'A registered WordPress Ability is required for Cloud AI task execution.' => 'Cloud AI 任务执行需要已注册的 WordPress 能力。',
				'The requested WordPress Ability is not registered.' => '请求的 WordPress 能力尚未注册。',
				'The registered Ability does not publish a Cloud AI task contract.' => '已注册能力未发布 Cloud AI 任务契约。',
				'The Toolkit contract source is unavailable for this Ability.' => '此能力的 Toolkit 契约来源不可用。',
				'The Toolkit contract is missing for this Ability.' => '此能力缺少 Toolkit 契约。',
				'The WordPress Ability schema does not match the Toolkit contract.' => 'WordPress 能力架构与 Toolkit 契约不匹配。',
				'The Toolkit contract version or Ability identity is invalid.' => 'Toolkit 契约版本或能力身份无效。',
				'The Toolkit contract permission or write posture is incompatible with the local Ability.' => 'Toolkit 契约权限或写入姿态与本地能力不兼容。',
				'The Toolkit risk level does not match the registered WordPress Ability.' => 'Toolkit 风险级别与已注册 WordPress 能力不匹配。',
				'The Toolkit approval requirement does not match the registered WordPress Ability.' => 'Toolkit 审批要求与已注册 WordPress 能力不匹配。',
				'Suggests short, readable permalink slugs from post title or content. Requires an AI connector that includes support for text generation models.' => '根据文章标题或内容生成简短、易读的永久链接别名建议。需要支持文本生成模型的 AI 连接器。',
				'Adds editorial suggestions to posts block-by-block, covering Accessibility, Readability, and Grammar. Requires an AI connector that includes support for text generation models.' => '按区块为文章添加编辑建议，覆盖无障碍、可读性和语法。需要支持文本生成模型的 AI 连接器。',
				'SEO/AEO/GEO specialist assistance is paused.' => 'SEO/AEO/GEO 专项辅助功能已暂停。',
				'AI task contracts require ai_task_contract.v1.' => 'AI 任务契约必须使用 ai_task_contract.v1。',
				'AI task contracts require a registered ability, task, and supported task family.' => 'AI 任务契约必须包含已注册能力、任务和受支持的任务类型。',
				'AI task contract verification must be mapping_current before Cloud execution.' => 'Cloud 执行前，AI 任务契约验证状态必须为 mapping_current。',
				'AI task contracts contain an unsupported context requirement or constraint.' => 'AI 任务契约包含不受支持的上下文要求或约束。',
				'AI task contracts require a stable sha256 schema hash.' => 'AI 任务契约必须包含稳定的 sha256 架构哈希。',
				'The Ability output schema is too large for runtime projection.' => '能力输出架构过大，无法投影到运行时请求。',
				'The AI task contract schema hash does not match its input and output schemas.' => 'AI 任务契约架构哈希与输入、输出架构不匹配。',
				'AI task contract list fields must be arrays.' => 'AI 任务契约列表字段必须是数组。',
				'AI task contract list fields must contain strings.' => 'AI 任务契约列表字段必须只包含字符串。',
				'AI task contract list fields contain an unsupported value.' => 'AI 任务契约列表字段包含不受支持的值。',
				'AI task contract context none cannot be combined with other values.' => 'AI 任务契约上下文 none 不能与其他值组合。',
				'Activate the WordPress AI plugin' => '启用 WordPress 官方 AI 插件',
				'Install the WordPress AI plugin' => '安装 WordPress 官方 AI 插件',
				'Verify the Cloud connection before reading Site Knowledge usage.' => '请先验证 Cloud 连接，再读取知识库用量。',
				'A Site Knowledge usage refresh is already running. Wait briefly, then retry.' => '知识库用量正在刷新，请稍候再试。',
				'Cloud did not return complete Site Knowledge usage. Check this site in Cloud and report the validation time.' => 'Cloud 未返回完整的知识库用量。请进入 Cloud 查看此站点，并反馈验证时间。',
				'Site Knowledge usage could not be read. Check the Cloud connection and this site in Cloud before retrying.' => '无法读取知识库用量。请先检查 Cloud 连接及 Cloud 中的此站点状态，再重试。',
			);
		}
	}
}
