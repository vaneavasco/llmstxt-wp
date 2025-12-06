<?php
/**
 * Settings Interface.
 *
 * Defines the contract for settings management.
 * Allows for dependency injection and testing.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

/**
 * Settings Interface.
 *
 * Provides a contract for classes that manage plugin settings.
 */
interface Settings_Interface {

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed Setting value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed> All settings with defaults applied.
	 */
	public function get_all(): array;

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public function get_defaults(): array;

	/**
	 * Register settings with WordPress Settings API.
	 *
	 * Called during admin_init hook.
	 *
	 * @return void
	 */
	public function register_settings(): void;
}
