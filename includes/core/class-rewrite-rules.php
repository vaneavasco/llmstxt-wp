<?php
/**
 * Rewrite Rules.
 *
 * Centralized registration of custom URL rewrite rules.
 * Used by both Activator and Endpoint_Manager to ensure consistency.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

/**
 * Rewrite Rules class.
 *
 * Registers custom URL endpoints using WordPress Rewrite API.
 */
final class Rewrite_Rules {

	/**
	 * Query variable for endpoint routing.
	 */
	public const QUERY_VAR = 'aico_endpoint';

	/**
	 * Query variable for post type.
	 */
	public const QUERY_VAR_POST_TYPE = 'aico_post_type';

	/**
	 * Query variable for slug.
	 */
	public const QUERY_VAR_SLUG = 'aico_slug';

	/**
	 * Query variable for page type.
	 */
	public const QUERY_VAR_PAGE_TYPE = 'aico_page_type';

	/**
	 * Register all custom rewrite rules.
	 *
	 * Called during plugin activation and on 'init' hook.
	 */
	public static function register(): void {
		// /llms.txt endpoint.
		add_rewrite_rule(
			'^llms\.txt$',
			'index.php?' . self::QUERY_VAR . '=llms-txt',
			'top'
		);

		// /ai/content/{post_type}/{slug} endpoint for individual posts.
		add_rewrite_rule(
			'^ai/content/([^/]+)/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=ai-content&' . self::QUERY_VAR_POST_TYPE . '=$matches[1]&' . self::QUERY_VAR_SLUG . '=$matches[2]',
			'top'
		);

		// /ai/content/{page_type} for static pages (homepage, about, etc.).
		add_rewrite_rule(
			'^ai/content/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=ai-page&' . self::QUERY_VAR_PAGE_TYPE . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Get all query variables used by the plugin.
	 *
	 * @return array<int, string> List of query variable names.
	 */
	public static function get_query_vars(): array {
		return [
			self::QUERY_VAR,
			self::QUERY_VAR_POST_TYPE,
			self::QUERY_VAR_SLUG,
			self::QUERY_VAR_PAGE_TYPE,
		];
	}
}
