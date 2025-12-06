<?php
/**
 * Admin Settings Page.
 *
 * Provides the settings interface under Settings > LLMs.txt.
 * Uses WordPress Settings API for form handling and validation.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Admin;

use LLMSTXT_WP\Core\Bot_Detector;
use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Core\Settings_Interface;
use LLMSTXT_WP\Core\Settings_Manager;

/**
 * Admin Page class.
 *
 * Renders and handles the plugin settings page.
 */
final class Admin_Page {

	/**
	 * Settings manager instance.
	 *
	 * @var Settings_Interface
	 */
	private Settings_Interface $settings;

	/**
	 * Cache manager instance.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @param Settings_Interface $settings Settings manager.
	 * @param Cache_Manager      $cache    Cache manager.
	 */
	public function __construct( Settings_Interface $settings, Cache_Manager $cache ) {
		$this->settings = $settings;
		$this->cache    = $cache;
	}

	/**
	 * Initialize admin page hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_aico_clear_cache', [ $this, 'handle_clear_cache' ] );
	}

	/**
	 * Add menu page under Settings.
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'LLMs.txt', 'llmstxt-wp' ),
			__( 'LLMs.txt', 'llmstxt-wp' ),
			'manage_options',
			'llmstxt',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register settings and sections.
	 */
	public function register_settings(): void {
		$this->settings->register_settings();

		// General Settings Section.
		add_settings_section(
			'aico_general',
			__( 'General Settings', 'llmstxt-wp' ),
			[ $this, 'render_general_section' ],
			'llmstxt'
		);

		$this->add_checkbox_field(
			'enabled',
			__( 'Enable Plugin', 'llmstxt-wp' ),
			__( 'Enable or disable all AI content optimization features.', 'llmstxt-wp' ),
			'aico_general'
		);

		$this->add_checkbox_field(
			'llms_txt_enabled',
			__( 'Enable /llms.txt', 'llmstxt-wp' ),
			__( 'Serve an llms.txt file listing all your published content.', 'llmstxt-wp' ),
			'aico_general'
		);

		$this->add_checkbox_field(
			'ai_endpoints_enabled',
			__( 'Enable AI Endpoints', 'llmstxt-wp' ),
			__( 'Enable /ai/content/* endpoints for individual posts.', 'llmstxt-wp' ),
			'aico_general'
		);

		$this->add_checkbox_field(
			'bot_redirect_enabled',
			__( 'Redirect AI Bots', 'llmstxt-wp' ),
			__( 'Automatically redirect AI crawlers to optimized markdown endpoints.', 'llmstxt-wp' ),
			'aico_general'
		);

		// Content Settings Section.
		add_settings_section(
			'aico_content',
			__( 'Content Settings', 'llmstxt-wp' ),
			[ $this, 'render_content_section' ],
			'llmstxt'
		);

		add_settings_field(
			'post_types',
			__( 'Post Types', 'llmstxt-wp' ),
			[ $this, 'render_post_types_field' ],
			'llmstxt',
			'aico_content'
		);

		add_settings_field(
			'site_description',
			__( 'Site Description', 'llmstxt-wp' ),
			[ $this, 'render_textarea_field' ],
			'llmstxt',
			'aico_content',
			[
				'id'          => 'site_description',
				'description' => __( 'Description shown in llms.txt header. Leave empty to use site tagline.', 'llmstxt-wp' ),
			]
		);

		add_settings_field(
			'contact_email',
			__( 'Contact Email', 'llmstxt-wp' ),
			[ $this, 'render_text_field' ],
			'llmstxt',
			'aico_content',
			[
				'id'          => 'contact_email',
				'type'        => 'email',
				'description' => __( 'Contact email shown in llms.txt. Leave empty to use admin email.', 'llmstxt-wp' ),
			]
		);

		// Cache Settings Section.
		add_settings_section(
			'aico_cache',
			__( 'Cache Settings', 'llmstxt-wp' ),
			[ $this, 'render_cache_section' ],
			'llmstxt'
		);

		add_settings_field(
			'llms_txt_cache_ttl',
			__( 'llms.txt Cache TTL', 'llmstxt-wp' ),
			[ $this, 'render_number_field' ],
			'llmstxt',
			'aico_cache',
			[
				'id'          => 'llms_txt_cache_ttl',
				'min'         => 60,
				'max'         => 86400,
				'description' => __( 'Cache duration in seconds for llms.txt (default: 3600 = 1 hour).', 'llmstxt-wp' ),
			]
		);

		add_settings_field(
			'content_cache_ttl',
			__( 'Content Cache TTL', 'llmstxt-wp' ),
			[ $this, 'render_number_field' ],
			'llmstxt',
			'aico_cache',
			[
				'id'          => 'content_cache_ttl',
				'min'         => 60,
				'max'         => 86400,
				'description' => __( 'Cache duration in seconds for individual posts (default: 600 = 10 minutes).', 'llmstxt-wp' ),
			]
		);

		// Advanced Settings Section.
		add_settings_section(
			'aico_advanced',
			__( 'Advanced Settings', 'llmstxt-wp' ),
			[ $this, 'render_advanced_section' ],
			'llmstxt'
		);

		$this->add_checkbox_field(
			'rate_limit_enabled',
			__( 'Enable Rate Limiting', 'llmstxt-wp' ),
			__( 'Limit requests per IP to prevent abuse.', 'llmstxt-wp' ),
			'aico_advanced'
		);

		add_settings_field(
			'rate_limit_requests',
			__( 'Rate Limit', 'llmstxt-wp' ),
			[ $this, 'render_number_field' ],
			'llmstxt',
			'aico_advanced',
			[
				'id'          => 'rate_limit_requests',
				'min'         => 10,
				'max'         => 1000,
				'description' => __( 'Maximum requests per minute per IP (default: 60).', 'llmstxt-wp' ),
			]
		);

		$this->add_checkbox_field(
			'noindex_ai_content',
			__( 'Noindex AI Content', 'llmstxt-wp' ),
			__( 'Add X-Robots-Tag: noindex to prevent search engine indexing of AI endpoints.', 'llmstxt-wp' ),
			'aico_advanced'
		);

		add_settings_field(
			'custom_bots',
			__( 'Custom Bot Patterns', 'llmstxt-wp' ),
			[ $this, 'render_textarea_field' ],
			'llmstxt',
			'aico_advanced',
			[
				'id'          => 'custom_bots',
				'description' => __( 'Additional bot User-Agent patterns to detect (one per line).', 'llmstxt-wp' ),
			]
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show success message after cache clear.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading query param for display message
		if ( isset( $_GET['cache_cleared'] ) ) {
			add_settings_error(
				'aico_messages',
				'aico_cache_cleared',
				__( 'Cache cleared successfully.', 'llmstxt-wp' ),
				'updated'
			);
		}

		settings_errors( 'aico_messages' );

		$llms_txt_url = home_url( '/llms.txt' );
		?>
		<div class="wrap aico-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="aico-info-box">
				<h3><?php esc_html_e( 'Quick Links', 'llmstxt-wp' ); ?></h3>
				<p>
					<strong>llms.txt:</strong>
					<a href="<?php echo esc_url( $llms_txt_url ); ?>" target="_blank" class="aico-external-link">
						<?php echo esc_html( $llms_txt_url ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
				</p>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( Settings_Manager::SETTINGS_GROUP );
				do_settings_sections( 'llmstxt' );
				submit_button();
				?>
			</form>

			<div class="aico-section">
				<h2><?php esc_html_e( 'Cache Management', 'llmstxt-wp' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Clear all cached content. This will regenerate llms.txt and individual post caches on next request.', 'llmstxt-wp' ); ?>
				</p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="aico-cache-form">
					<?php wp_nonce_field( 'aico_clear_cache', 'aico_nonce' ); ?>
					<input type="hidden" name="action" value="aico_clear_cache">
					<?php
					submit_button(
						__( 'Clear All Caches', 'llmstxt-wp' ),
						'secondary',
						'submit',
						false
					);
					?>
				</form>
			</div>

			<div class="aico-section">
				<h2><?php esc_html_e( 'Detected AI Bots', 'llmstxt-wp' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'The following AI bots are automatically detected:', 'llmstxt-wp' ); ?>
				</p>
				<div class="aico-bot-patterns">
					<code><?php echo esc_html( implode( ', ', Bot_Detector::get_known_patterns() ) ); ?></code>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle cache clear action.
	 */
	public function handle_clear_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'llmstxt-wp' ) );
		}

		check_admin_referer( 'aico_clear_cache', 'aico_nonce' );

		$this->cache->clear_all();

		wp_safe_redirect(
			add_query_arg( 'cache_cleared', '1', admin_url( 'options-general.php?page=llmstxt' ) )
		);
		exit;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_llmstxt' !== $hook ) {
			return;
		}

		// Only enqueue if CSS file exists.
		$css_file = AICO_PLUGIN_DIR . 'assets/css/admin.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aico-admin',
				AICO_PLUGIN_URL . 'assets/css/admin.css',
				[],
				AICO_VERSION
			);
		}
	}

	/**
	 * Helper to add checkbox field.
	 *
	 * @param string $id          Field ID.
	 * @param string $label       Field label.
	 * @param string $description Field description.
	 * @param string $section     Section ID.
	 */
	private function add_checkbox_field( string $id, string $label, string $description, string $section ): void {
		add_settings_field(
			$id,
			$label,
			[ $this, 'render_checkbox_field' ],
			'llmstxt',
			$section,
			[
				'id'          => $id,
				'description' => $description,
			]
		);
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_checkbox_field( array $args ): void {
		$value = $this->settings->get( $args['id'] );
		?>
		<label>
			<input type="checkbox"
				   name="<?php echo esc_attr( Settings_Manager::OPTION_NAME . '[' . $args['id'] . ']' ); ?>"
				   value="1"
				   <?php checked( (bool) $value ); ?>>
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_text_field( array $args ): void {
		$value = $this->settings->get( $args['id'] );
		$type  = $args['type'] ?? 'text';
		?>
		<input type="<?php echo esc_attr( $type ); ?>"
			   name="<?php echo esc_attr( Settings_Manager::OPTION_NAME . '[' . $args['id'] . ']' ); ?>"
			   value="<?php echo esc_attr( (string) $value ); ?>"
			   class="regular-text">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_number_field( array $args ): void {
		$value = $this->settings->get( $args['id'] );
		$min   = $args['min'] ?? 0;
		$max   = $args['max'] ?? 99999;
		?>
		<input type="number"
			   name="<?php echo esc_attr( Settings_Manager::OPTION_NAME . '[' . $args['id'] . ']' ); ?>"
			   value="<?php echo esc_attr( (string) $value ); ?>"
			   min="<?php echo esc_attr( (string) $min ); ?>"
			   max="<?php echo esc_attr( (string) $max ); ?>"
			   class="small-text">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render textarea field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_textarea_field( array $args ): void {
		$value = $this->settings->get( $args['id'] );
		?>
		<textarea name="<?php echo esc_attr( Settings_Manager::OPTION_NAME . '[' . $args['id'] . ']' ); ?>"
				  rows="4"
				  cols="50"
				  class="large-text"><?php echo esc_textarea( (string) $value ); ?></textarea>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render post types field.
	 */
	public function render_post_types_field(): void {
		$selected   = $this->settings->get( 'post_types', [ 'post', 'page' ] );
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<fieldset class="aico-post-types">
			<?php foreach ( $post_types as $post_type ) : ?>
				<?php
				// Skip attachments.
				if ( 'attachment' === $post_type->name ) {
					continue;
				}
				?>
				<label class="aico-post-type-label">
					<input type="checkbox"
						   name="<?php echo esc_attr( Settings_Manager::OPTION_NAME . '[post_types][]' ); ?>"
						   value="<?php echo esc_attr( $post_type->name ); ?>"
						   <?php checked( in_array( $post_type->name, (array) $selected, true ) ); ?>>
					<?php echo esc_html( $post_type->labels->name ); ?>
					<code>(<?php echo esc_html( $post_type->name ); ?>)</code>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
			<?php esc_html_e( 'Select which post types to include in llms.txt and AI content endpoints.', 'llmstxt-wp' ); ?>
		</p>
		<?php
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure the main features of LLMs.txt.', 'llmstxt-wp' ) . '</p>';
	}

	/**
	 * Render content section description.
	 */
	public function render_content_section(): void {
		echo '<p>' . esc_html__( 'Configure which content to include and how it appears in AI-optimized output.', 'llmstxt-wp' ) . '</p>';
	}

	/**
	 * Render cache section description.
	 */
	public function render_cache_section(): void {
		echo '<p>' . esc_html__( 'Configure caching behavior for AI content endpoints. Higher values reduce server load but delay content updates.', 'llmstxt-wp' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 */
	public function render_advanced_section(): void {
		echo '<p>' . esc_html__( 'Advanced configuration options for security and custom bot detection.', 'llmstxt-wp' ) . '</p>';
	}
}
