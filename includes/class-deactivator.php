<?php
/**
 * Plugin Deactivator.
 *
 * Handles plugin deactivation tasks including clearing caches
 * and flushing rewrite rules.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP;

use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Core\Settings_Manager;

/**
 * Deactivator class.
 *
 * Called when the plugin is deactivated.
 */
final class Deactivator {

	/**
	 * Deactivation handler.
	 *
	 * Clears caches and flushes rewrite rules.
	 * Does NOT delete options (use uninstall.php for that).
	 */
	public static function deactivate(): void {
		// Clear all plugin caches.
		$settings = new Settings_Manager();
		$cache    = new Cache_Manager( $settings );
		$cache->clear_all();

		// Flush rewrite rules to remove our endpoints.
		flush_rewrite_rules();
	}
}
