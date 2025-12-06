<?php
/**
 * Settings Keys.
 *
 * Constants for all plugin settings keys.
 * Prevents magic strings and enables IDE autocompletion.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

/**
 * Settings Keys class.
 *
 * Defines all setting key constants used throughout the plugin.
 */
final class Settings_Keys {

	/**
	 * General settings.
	 */
	public const ENABLED              = 'enabled';
	public const LLMS_TXT_ENABLED     = 'llms_txt_enabled';
	public const AI_ENDPOINTS_ENABLED = 'ai_endpoints_enabled';
	public const BOT_REDIRECT_ENABLED = 'bot_redirect_enabled';

	/**
	 * Content settings.
	 */
	public const POST_TYPES       = 'post_types';
	public const SITE_DESCRIPTION = 'site_description';
	public const CONTACT_EMAIL    = 'contact_email';

	/**
	 * Cache settings.
	 */
	public const LLMS_TXT_CACHE_TTL = 'llms_txt_cache_ttl';
	public const CONTENT_CACHE_TTL  = 'content_cache_ttl';

	/**
	 * Rate limiting settings.
	 */
	public const RATE_LIMIT_ENABLED  = 'rate_limit_enabled';
	public const RATE_LIMIT_REQUESTS = 'rate_limit_requests';
	public const RATE_LIMIT_WINDOW   = 'rate_limit_window';

	/**
	 * Security settings.
	 */
	public const TRUSTED_PROXY_IPS = 'trusted_proxy_ips';

	/**
	 * Advanced settings.
	 */
	public const NOINDEX_AI_CONTENT = 'noindex_ai_content';
	public const CUSTOM_BOTS        = 'custom_bots';
}
