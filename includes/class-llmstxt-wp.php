<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP;

use LLMSTXT_WP\Admin\Admin_Page;
use LLMSTXT_WP\Content\Content_Fetcher;
use LLMSTXT_WP\Content\Markdown_Generator;
use LLMSTXT_WP\Core\Bot_Detector;
use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Core\Rate_Limiter;
use LLMSTXT_WP\Core\Settings_Manager;
use LLMSTXT_WP\Endpoints\Endpoint_Manager;

/**
 * Main plugin class.
 *
 * Coordinates all plugin functionality through dependency injection
 * and WordPress hooks.
 */
final class LLMSTXT_WP {

	/**
	 * Plugin version.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Plugin slug.
	 */
	public const SLUG = 'llmstxt-wp';

	/**
	 * Single instance of this class.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings manager instance.
	 *
	 * @var Settings_Manager
	 */
	private Settings_Manager $settings;

	/**
	 * Cache manager instance.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Bot detector instance.
	 *
	 * @var Bot_Detector
	 */
	private Bot_Detector $bot_detector;

	/**
	 * Rate limiter instance.
	 *
	 * @var Rate_Limiter
	 */
	private Rate_Limiter $rate_limiter;

	/**
	 * Content fetcher instance.
	 *
	 * @var Content_Fetcher
	 */
	private Content_Fetcher $content_fetcher;

	/**
	 * Markdown generator instance.
	 *
	 * @var Markdown_Generator
	 */
	private Markdown_Generator $markdown_generator;

	/**
	 * Endpoint manager instance.
	 *
	 * @var Endpoint_Manager
	 */
	private Endpoint_Manager $endpoint_manager;

	/**
	 * Admin page instance.
	 *
	 * @var Admin_Page|null
	 */
	private ?Admin_Page $admin_page = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Initializes dependencies. Private to enforce singleton pattern.
	 */
	private function __construct() {
		$this->load_dependencies();
	}

	/**
	 * Load and instantiate all dependencies.
	 *
	 * Uses manual dependency injection to wire up components.
	 */
	private function load_dependencies(): void {
		// Core services.
		$this->settings     = new Settings_Manager();
		$this->cache        = new Cache_Manager( $this->settings );
		$this->bot_detector = new Bot_Detector( $this->settings );
		$this->rate_limiter = new Rate_Limiter( $this->settings );

		// Content services.
		$this->content_fetcher    = new Content_Fetcher( $this->settings );
		$this->markdown_generator = new Markdown_Generator( $this->settings );

		// Endpoint manager.
		$this->endpoint_manager = new Endpoint_Manager(
			$this->settings,
			$this->cache,
			$this->bot_detector,
			$this->rate_limiter,
			$this->content_fetcher,
			$this->markdown_generator
		);

		// Admin page (only in admin context).
		if ( is_admin() ) {
			$this->admin_page = new Admin_Page( $this->settings, $this->cache );
		}
	}

	/**
	 * Run the plugin.
	 *
	 * Registers all hooks with WordPress.
	 */
	public function run(): void {
		// Always initialize admin page so settings can be changed even when disabled.
		if ( is_admin() && $this->admin_page instanceof Admin_Page ) {
			$this->admin_page->init();
		}

		// Check if plugin is enabled for public-facing features.
		if ( ! $this->settings->get( 'enabled' ) ) {
			return;
		}

		// Initialize endpoints (registers rewrite rules and handlers).
		$this->endpoint_manager->init();
	}

	/**
	 * Get the settings manager.
	 *
	 * @return Settings_Manager
	 */
	public function get_settings(): Settings_Manager {
		return $this->settings;
	}

	/**
	 * Get the cache manager.
	 *
	 * @return Cache_Manager
	 */
	public function get_cache(): Cache_Manager {
		return $this->cache;
	}

	/**
	 * Get the bot detector.
	 *
	 * @return Bot_Detector
	 */
	public function get_bot_detector(): Bot_Detector {
		return $this->bot_detector;
	}

	/**
	 * Get the content fetcher.
	 *
	 * @return Content_Fetcher
	 */
	public function get_content_fetcher(): Content_Fetcher {
		return $this->content_fetcher;
	}

	/**
	 * Get the markdown generator.
	 *
	 * @return Markdown_Generator
	 */
	public function get_markdown_generator(): Markdown_Generator {
		return $this->markdown_generator;
	}
}
