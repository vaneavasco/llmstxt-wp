<?php
/**
 * LLMs.txt for WordPress
 *
 * Implements the llms.txt standard for WordPress, serving AI-optimized
 * content endpoints for language models and AI crawlers.
 *
 * @package           LLMSTXT_WP
 * @author            vaneavasco
 * @copyright         2025 vaneavasco
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       LLMs.txt for WordPress
 * Plugin URI:        https://github.com/vaneavasco/llmstxt-wp
 * Description:       Serve AI-optimized content to ChatGPT, Claude, Perplexity and 40+ AI crawlers. Implements the llms.txt standard with markdown endpoints.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            vaneavasco
 * Author URI:        https://github.com/vaneavasco
 * Text Domain:       llmstxt-wp
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// PHP version check.
if ( version_compare( PHP_VERSION, '7.4.0', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="error"><p>%s</p></div>',
				esc_html__( 'LLMs.txt for WordPress requires PHP 7.4 or higher. Please upgrade your PHP version.', 'llmstxt-wp' )
			);
		}
	);
	return;
}

// Plugin constants.
define( 'AICO_VERSION', '1.0.0' );
define( 'AICO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AICO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AICO_PLUGIN_FILE', __FILE__ );
define( 'AICO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 style autoloader for plugin classes.
 *
 * Maps LLMSTXT_WP namespace to includes/ directory.
 * Class names use WordPress naming convention (class-{name}.php).
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(
	static function ( string $class ): void {
		// Only autoload our namespace.
		$prefix   = 'LLMSTXT_WP\\';
		$base_dir = AICO_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, $len );

		// Convert namespace separators and underscores to directory separators.
		// LLMSTXT_WP\Core\Settings_Manager -> core/class-settings-manager.php
		$parts      = explode( '\\', $relative_class );
		$class_name = array_pop( $parts );

		// Build directory path from namespace parts (lowercase).
		$subdir = '';
		if ( ! empty( $parts ) ) {
			$subdir = strtolower( implode( '/', $parts ) ) . '/';
		}

		// Convert class name: Settings_Manager -> settings-manager.
		$file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		$file = $base_dir . $subdir . $file_name;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Plugin activation hook.
 *
 * Sets default options and flushes rewrite rules.
 */
function aico_activate(): void {
	LLMSTXT_WP\Activator::activate();
}
register_activation_hook( __FILE__, 'aico_activate' );

/**
 * Plugin deactivation hook.
 *
 * Clears caches and flushes rewrite rules.
 */
function aico_deactivate(): void {
	LLMSTXT_WP\Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'aico_deactivate' );

/**
 * Initialize the plugin.
 *
 * Runs on plugins_loaded hook to ensure all dependencies are available.
 */
function aico_init(): void {
	// Load text domain for translations.
	load_plugin_textdomain(
		'llmstxt-wp',
		false,
		dirname( AICO_PLUGIN_BASENAME ) . '/languages'
	);

	// Initialize main plugin class.
	$plugin = LLMSTXT_WP\LLMSTXT_WP::get_instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'aico_init' );

/**
 * Invalidate cache when posts are saved or deleted.
 *
 * Uses the singleton instance to ensure consistent state
 * and avoid creating duplicate service instances.
 *
 * @param int $post_id The post ID being saved/deleted.
 */
function aico_invalidate_on_save( int $post_id ): void {
	// Skip autosaves and revisions.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Use singleton instance to maintain consistent state.
	$plugin = LLMSTXT_WP\LLMSTXT_WP::get_instance();
	$plugin->get_cache()->invalidate_post( $post_id );
}
add_action( 'save_post', 'aico_invalidate_on_save' );
add_action( 'delete_post', 'aico_invalidate_on_save' );
add_action( 'wp_trash_post', 'aico_invalidate_on_save' );

/**
 * Invalidate cache when post status changes.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function aico_invalidate_on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
	// Only invalidate when publishing/unpublishing.
	if ( $new_status !== $old_status &&
		( 'publish' === $new_status || 'publish' === $old_status ) ) {
		aico_invalidate_on_save( $post->ID );
	}
}
add_action( 'transition_post_status', 'aico_invalidate_on_status_change', 10, 3 );

/**
 * Add settings link on plugins page.
 *
 * @param array<string, string> $links Existing plugin action links.
 * @return array<string, string> Modified plugin action links.
 */
function aico_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=llmstxt' ) ),
		esc_html__( 'Settings', 'llmstxt-wp' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . AICO_PLUGIN_BASENAME, 'aico_plugin_action_links' );
