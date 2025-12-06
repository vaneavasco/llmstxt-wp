<?php
/**
 * Plugin Activator.
 *
 * Handles plugin activation tasks including setting defaults
 * and flushing rewrite rules.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP;

use LLMSTXT_WP\Core\Rewrite_Rules;
use LLMSTXT_WP\Core\Settings_Manager;

/**
 * Activator class.
 *
 * Called when the plugin is activated.
 */
final class Activator {

	/**
	 * Activation handler.
	 *
	 * Sets default options and flushes rewrite rules.
	 */
	public static function activate(): void {
		// Set default options if not already set.
		// Uses Settings_Manager as single source of truth for defaults.
		if ( false === get_option( Settings_Manager::OPTION_NAME ) ) {
			$settings = new Settings_Manager();
			add_option( Settings_Manager::OPTION_NAME, $settings->get_defaults() );
		}

		// Register rewrite rules before flushing (uses shared class).
		Rewrite_Rules::register();

		// Flush rewrite rules to register our endpoints.
		flush_rewrite_rules();

		// Set activation flag for admin notice.
		set_transient( 'aico_activated', true, 60 );
	}
}
