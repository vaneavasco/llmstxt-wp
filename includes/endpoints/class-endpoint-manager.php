<?php
/**
 * Endpoint Manager.
 *
 * Registers custom endpoints using WordPress Rewrite API.
 * Handles requests for /llms.txt and /ai/content/* routes.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Endpoints;

use LLMSTXT_WP\Content\Content_Fetcher;
use LLMSTXT_WP\Content\Markdown_Generator;
use LLMSTXT_WP\Core\Bot_Detector;
use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Core\Rate_Limiter;
use LLMSTXT_WP\Core\Rewrite_Rules;
use LLMSTXT_WP\Core\Settings_Interface;
use WP_Post;

/**
 * Endpoint Manager class.
 *
 * Manages custom URL endpoints using WordPress Rewrite API
 * and template_redirect hook for request handling.
 */
final class Endpoint_Manager {

	/**
	 * Query variable for endpoint routing.
	 *
	 * @deprecated Use Rewrite_Rules::QUERY_VAR instead.
	 */
	public const QUERY_VAR = 'aico_endpoint';

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
	 * Constructor.
	 *
	 * @param Settings_Interface $settings           Settings manager.
	 * @param Cache_Manager      $cache              Cache manager.
	 * @param Bot_Detector       $bot_detector       Bot detector.
	 * @param Rate_Limiter       $rate_limiter       Rate limiter.
	 * @param Content_Fetcher    $content_fetcher    Content fetcher.
	 * @param Markdown_Generator $markdown_generator Markdown generator.
	 */
	public function __construct(
		Settings_Interface $settings,
		Cache_Manager $cache,
		Bot_Detector $bot_detector,
		Rate_Limiter $rate_limiter,
		Content_Fetcher $content_fetcher,
		Markdown_Generator $markdown_generator
	) {
		$this->settings           = $settings;
		$this->cache              = $cache;
		$this->bot_detector       = $bot_detector;
		$this->rate_limiter       = $rate_limiter;
		$this->content_fetcher    = $content_fetcher;
		$this->markdown_generator = $markdown_generator;
	}

	/**
	 * Initialize endpoint manager.
	 *
	 * Registers all WordPress hooks.
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_endpoint' ] );

		// Bot redirect on regular pages (runs before endpoint handling).
		if ( $this->settings->get( 'bot_redirect_enabled' ) ) {
			add_action( 'template_redirect', [ $this, 'maybe_redirect_bot' ], 5 );
		}
	}

	/**
	 * Register custom rewrite rules.
	 *
	 * Called on 'init' hook. Delegates to shared Rewrite_Rules class.
	 */
	public function register_rewrite_rules(): void {
		Rewrite_Rules::register();
	}

	/**
	 * Register query variables.
	 *
	 * @param array<int, string> $vars Existing query vars.
	 * @return array<int, string> Modified query vars.
	 */
	public function register_query_vars( array $vars ): array {
		return array_merge( $vars, Rewrite_Rules::get_query_vars() );
	}

	/**
	 * Handle custom endpoint requests.
	 *
	 * Called on 'template_redirect' hook.
	 */
	public function handle_endpoint(): void {
		$endpoint = get_query_var( Rewrite_Rules::QUERY_VAR );

		if ( empty( $endpoint ) ) {
			return;
		}

		// Check if plugin is enabled.
		if ( ! $this->settings->get( 'enabled' ) ) {
			$this->send_error( 404, 'Service Unavailable', 'LLMs.txt is currently disabled.' );
			return;
		}

		// Check rate limiting.
		if ( $this->rate_limiter->is_rate_limited() ) {
			$this->send_rate_limited_error();
			return;
		}

		switch ( $endpoint ) {
			case 'llms-txt':
				$this->handle_llms_txt();
				break;
			case 'ai-content':
				$this->handle_ai_content();
				break;
			case 'ai-page':
				$this->handle_ai_page();
				break;
			default:
				$this->send_error( 404, 'Not Found', 'Unknown endpoint.' );
				break;
		}
	}

	/**
	 * Handle /llms.txt requests.
	 *
	 * Uses stampede protection to prevent multiple concurrent requests
	 * from regenerating the cache simultaneously.
	 */
	protected function handle_llms_txt(): void {
		if ( ! $this->settings->get( 'llms_txt_enabled' ) ) {
			$this->send_error(
				404,
				'Not Found',
				'The llms.txt endpoint is currently disabled.',
				[ 'suggestion' => 'Contact the site administrator if you believe this is an error.' ]
			);
			return;
		}

		// Check cache with stampede protection.
		$cache_key = $this->cache->get_llms_txt_key();
		$result    = $this->cache->get_with_lock( $cache_key );

		if ( $result['should_regenerate'] ) {
			try {
				// Generate content (we hold the lock).
				$posts   = $this->content_fetcher->get_all_published();
				$content = $this->markdown_generator->generate_llms_txt( $posts );

				// Cache it.
				$ttl = $this->cache->get_llms_txt_ttl();
				$this->cache->set( $cache_key, $content, $ttl );

				/**
				 * Fires after llms.txt content is generated.
				 *
				 * @param string $content The generated content.
				 * @param array  $posts   The posts included.
				 */
				do_action( 'aico_after_llms_txt', $content, $posts );
			} finally {
				// Always release the lock.
				$this->cache->release_regeneration_lock( $cache_key );
			}
		} else {
			// Use cached content.
			$content = $result['content'];
		}

		// Handle edge case where content is still false (very unlikely).
		if ( false === $content ) {
			$this->send_error(
				503,
				'Service Temporarily Unavailable',
				'Content is being generated. Please try again in a few seconds.',
				[ 'suggestion' => 'Retry the request after a brief wait.' ]
			);
			return;
		}

		$this->send_markdown( (string) $content, $this->cache->get_llms_txt_ttl() );
	}

	/**
	 * Handle /ai/content/{post_type}/{slug} requests.
	 *
	 * Uses stampede protection to prevent multiple concurrent requests
	 * from regenerating the cache simultaneously.
	 */
	protected function handle_ai_content(): void {
		if ( ! $this->settings->get( 'ai_endpoints_enabled' ) ) {
			$this->send_error(
				404,
				'Not Found',
				'AI content endpoints are currently disabled.'
			);
			return;
		}

		$post_type = sanitize_key( get_query_var( 'aico_post_type' ) );
		$slug      = sanitize_title( get_query_var( 'aico_slug' ) );

		if ( empty( $post_type ) || empty( $slug ) ) {
			$this->send_error(
				400,
				'Bad Request',
				'Missing post_type or slug parameter.',
				[ 'suggestion' => 'URL format: /ai/content/{post_type}/{slug}' ]
			);
			return;
		}

		// Validate post type is allowed (use generic message to prevent enumeration).
		if ( ! $this->content_fetcher->is_allowed_post_type( $post_type ) ) {
			$this->send_error(
				404,
				'Not Found',
				'The requested content was not found.',
				[ 'suggestion' => 'Check /llms.txt for available content.' ]
			);
			return;
		}

		// Check cache with stampede protection.
		$cache_key = $this->cache->get_content_key( $post_type, $slug );
		$result    = $this->cache->get_with_lock( $cache_key );

		if ( $result['should_regenerate'] ) {
			$post = $this->content_fetcher->get_by_slug( $post_type, $slug );

			if ( ! $post instanceof WP_Post ) {
				// Release lock BEFORE sending error (send_error calls exit).
				$this->cache->release_regeneration_lock( $cache_key );
				// Cache negative result briefly.
				$this->cache->set_not_found( $cache_key );
				$this->send_error(
					404,
					'Not Found',
					'The requested content was not found.',
					[ 'suggestion' => 'Check /llms.txt for available content.' ]
				);
				return;
			}

			$content = $this->markdown_generator->generate_post_detail( $post );
			$this->cache->set( $cache_key, $content );
			// Release lock after successful generation.
			$this->cache->release_regeneration_lock( $cache_key );
		} else {
			$content = $result['content'];
		}

		// Handle cached "not found" marker.
		if ( $this->cache->is_not_found( $content ) ) {
			$this->send_error( 404, 'Not Found', 'The requested content was not found.' );
			return;
		}

		// Handle edge case where content is still false.
		if ( false === $content ) {
			$this->send_error(
				503,
				'Service Temporarily Unavailable',
				'Content is being generated. Please try again in a few seconds.',
				[ 'suggestion' => 'Retry the request after a brief wait.' ]
			);
			return;
		}

		$this->send_markdown( (string) $content );
	}

	/**
	 * Handle /ai/content/{page_type} for static pages.
	 */
	protected function handle_ai_page(): void {
		if ( ! $this->settings->get( 'ai_endpoints_enabled' ) ) {
			$this->send_error( 404, 'Not Found', 'AI content endpoints are currently disabled.' );
			return;
		}

		$page_type = sanitize_key( get_query_var( 'aico_page_type' ) );

		/**
		 * Filter static page markdown content.
		 *
		 * Use this to provide custom static page content (e.g., homepage, about).
		 *
		 * @param string|null $content   The markdown content (null to use default).
		 * @param string      $page_type The page type requested.
		 */
		$content = apply_filters( 'aico_static_page_content', null, $page_type );

		if ( null === $content ) {
			// Try to find a page with this slug.
			$post = $this->content_fetcher->get_by_slug( 'page', $page_type );
			if ( $post instanceof WP_Post ) {
				$content = $this->markdown_generator->generate_post_detail( $post );
			}
		}

		if ( empty( $content ) ) {
			$this->send_error(
				404,
				'Not Found',
				sprintf( 'Page "%s" not found.', esc_html( $page_type ) ),
				[ 'suggestion' => 'Check /llms.txt for available pages.' ]
			);
			return;
		}

		$this->send_markdown( $content );
	}

	/**
	 * Redirect AI bots to markdown endpoints.
	 *
	 * Called early in template_redirect to intercept bot requests.
	 */
	public function maybe_redirect_bot(): void {
		// Skip if already on our endpoints.
		if ( get_query_var( Rewrite_Rules::QUERY_VAR ) ) {
			return;
		}

		// Skip if not an AI bot.
		if ( ! $this->bot_detector->is_ai_bot() ) {
			return;
		}

		// Skip if AI endpoints are disabled.
		if ( ! $this->settings->get( 'ai_endpoints_enabled' ) ) {
			return;
		}

		/**
		 * Fires when an AI bot is detected.
		 *
		 * @param string $bot_name The detected bot name.
		 */
		do_action( 'aico_bot_detected', $this->bot_detector->get_bot_name() );

		// Handle single posts.
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post && $this->content_fetcher->is_allowed_post_type( $post->post_type ) ) {
				$redirect_url = home_url( "/ai/content/{$post->post_type}/{$post->post_name}" );
				wp_safe_redirect( $redirect_url, 307 );
				exit;
			}
		}

		// Handle homepage.
		if ( is_front_page() || is_home() ) {
			// Check if there's a static front page.
			$front_page_id = (int) get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$front_page = get_post( $front_page_id );
				if ( $front_page instanceof WP_Post ) {
					$redirect_url = home_url( "/ai/content/page/{$front_page->post_name}" );
					wp_safe_redirect( $redirect_url, 307 );
					exit;
				}
			}
			// Redirect to llms.txt as a fallback for blog homepage.
			wp_safe_redirect( home_url( '/llms.txt' ), 307 );
			exit;
		}
	}

	/**
	 * Send markdown response.
	 *
	 * @param string   $content   Markdown content.
	 * @param int|null $cache_ttl Cache TTL for headers.
	 */
	protected function send_markdown( string $content, ?int $cache_ttl = null ): void {
		if ( null === $cache_ttl ) {
			$cache_ttl = $this->cache->get_content_ttl();
		}

		// Set response headers.
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( sprintf( 'Cache-Control: public, max-age=%d', $cache_ttl ) );

		// Add noindex header if enabled.
		if ( $this->settings->get( 'noindex_ai_content' ) ) {
			header( 'X-Robots-Tag: noindex' );
		}

		// Add rate limit headers.
		foreach ( $this->rate_limiter->get_headers() as $header => $value ) {
			header( "{$header}: {$value}" );
		}

		// Sanitize markdown to prevent XSS in clients that render HTML.
		$content = $this->sanitize_markdown_output( $content );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is sanitized above.
		echo $content;
		exit;
	}

	/**
	 * Sanitize markdown output to prevent XSS attacks.
	 *
	 * Uses WordPress's wp_kses() for reliable HTML sanitization.
	 * Allows safe HTML elements that may appear in converted markdown
	 * while removing dangerous scripts, iframes, and event handlers.
	 *
	 * @param string $content Markdown content to sanitize.
	 * @return string Sanitized markdown content.
	 */
	protected function sanitize_markdown_output( string $content ): string {
		// Define safe HTML elements that may legitimately appear in markdown.
		// This is more restrictive than wp_kses_post() to prevent XSS.
		$allowed_html = [
			'a'          => [
				'href'   => true,
				'title'  => true,
				'rel'    => true,
				'target' => true,
			],
			'abbr'       => [ 'title' => true ],
			'b'          => [],
			'blockquote' => [ 'cite' => true ],
			'br'         => [],
			'cite'       => [],
			'code'       => [ 'class' => true ],
			'del'        => [ 'datetime' => true ],
			'em'         => [],
			'h1'         => [],
			'h2'         => [],
			'h3'         => [],
			'h4'         => [],
			'h5'         => [],
			'h6'         => [],
			'hr'         => [],
			'i'          => [],
			'img'        => [
				'alt'    => true,
				'src'    => true,
				'title'  => true,
				'width'  => true,
				'height' => true,
			],
			'li'         => [],
			'ol'         => [ 'start' => true ],
			'p'          => [],
			'pre'        => [],
			's'          => [],
			'strong'     => [],
			'sub'        => [],
			'sup'        => [],
			'table'      => [],
			'tbody'      => [],
			'td'         => [
				'colspan' => true,
				'rowspan' => true,
			],
			'th'         => [
				'colspan' => true,
				'rowspan' => true,
				'scope'   => true,
			],
			'thead'      => [],
			'tr'         => [],
			'ul'         => [],
		];

		// Define allowed URL protocols (blocks javascript:, data:, vbscript:).
		$allowed_protocols = [ 'http', 'https', 'mailto', 'tel' ];

		return wp_kses( $content, $allowed_html, $allowed_protocols );
	}

	/**
	 * Send error response in markdown format.
	 *
	 * @param int                  $status  HTTP status code.
	 * @param string               $title   Error title.
	 * @param string               $message Error message.
	 * @param array<string, mixed> $details Additional details (retry_after, suggestion, documentation).
	 */
	protected function send_error( int $status, string $title, string $message, array $details = [] ): void {
		status_header( $status );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: no-cache' );

		// Add Retry-After header for 503 responses.
		if ( 503 === $status ) {
			$retry_after = $details['retry_after'] ?? 5;
			header( 'Retry-After: ' . $retry_after );
		}

		$content = "# {$status} {$title}\n\n{$message}\n";

		if ( ! empty( $details['suggestion'] ) ) {
			$content .= "\n## Suggestion\n\n{$details['suggestion']}\n";
		}

		if ( ! empty( $details['documentation'] ) ) {
			$content .= "\n## Documentation\n\n{$details['documentation']}\n";
		}

		$content .= "\n---\n\n*LLMs.txt for WordPress*\n";

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}

	/**
	 * Send rate limited error response.
	 */
	protected function send_rate_limited_error(): void {
		$window = $this->rate_limiter->get_window();
		$limit  = $this->rate_limiter->get_limit();

		status_header( 429 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Retry-After: ' . $window );

		// Add rate limit headers.
		foreach ( $this->rate_limiter->get_headers() as $header => $value ) {
			header( "{$header}: {$value}" );
		}

		$content  = "# 429 Too Many Requests\n\n";
		$content .= "Rate limit exceeded. You have made too many requests.\n\n";
		$content .= "## Limits\n\n";
		$content .= "- **Maximum requests:** {$limit} per {$window} seconds\n";
		$content .= "- **Retry after:** {$window} seconds\n\n";
		$content .= "---\n\n*LLMs.txt for WordPress*\n";

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}
}
