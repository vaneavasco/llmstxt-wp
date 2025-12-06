<?php
/**
 * Content Fetcher.
 *
 * Fetches WordPress content for markdown generation.
 * Provides a consistent interface for querying posts across post types.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Content;

use LLMSTXT_WP\Core\Settings_Interface;
use WP_Post;
use WP_Query;

/**
 * Content Fetcher class.
 *
 * Uses WP_Query to fetch content based on configured post types.
 * Includes filters for extensibility.
 */
final class Content_Fetcher {

	/**
	 * Settings manager instance.
	 *
	 * @var Settings_Interface
	 */
	private Settings_Interface $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings_Interface $settings Settings manager instance.
	 */
	public function __construct( Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Default maximum posts to include in llms.txt.
	 * Prevents memory issues on large sites.
	 */
	public const DEFAULT_POST_LIMIT = 500;

	/**
	 * Get all published content for llms.txt listing.
	 *
	 * @return array<int, WP_Post> Array of WP_Post objects.
	 */
	public function get_all_published(): array {
		$post_types = $this->settings->get( 'post_types', [ 'post', 'page' ] );

		/**
		 * Filter post types included in llms.txt.
		 *
		 * @param array $post_types Post type slugs.
		 */
		$post_types = apply_filters( 'aico_llms_txt_post_types', $post_types );

		/**
		 * Filter maximum posts to include in llms.txt.
		 *
		 * Use -1 for unlimited (not recommended for large sites).
		 * Default is 500 to prevent memory issues.
		 *
		 * @param int $limit Maximum number of posts.
		 */
		$post_limit = apply_filters( 'aico_llms_txt_post_limit', self::DEFAULT_POST_LIMIT );

		$args = [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $post_limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true, // Performance: skip counting total rows.
		];

		/**
		 * Filter WP_Query arguments for llms.txt content.
		 *
		 * Use this to add custom query parameters, exclude certain posts, etc.
		 *
		 * @param array $args WP_Query arguments.
		 */
		$args = apply_filters( 'aico_llms_txt_query_args', $args );

		/**
		 * Fires before fetching llms.txt content.
		 */
		do_action( 'aico_before_llms_txt' );

		$query = new WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get a single published post by slug.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug      Post slug (post_name).
	 * @return WP_Post|null Post object or null if not found.
	 */
	public function get_by_slug( string $post_type, string $slug ): ?WP_Post {
		$allowed_types = $this->get_allowed_post_types();

		// Security: Only allow configured post types.
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			return null;
		}

		$args = [
			'name'           => sanitize_title( $slug ),
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		];

		/**
		 * Filter WP_Query arguments for single post fetch.
		 *
		 * @param array  $args      WP_Query arguments.
		 * @param string $post_type Post type being fetched.
		 * @param string $slug      Post slug being fetched.
		 */
		$args = apply_filters( 'aico_single_post_query_args', $args, $post_type, $slug );

		$query = new WP_Query( $args );

		return $query->have_posts() ? $query->posts[0] : null;
	}

	/**
	 * Get a single published post by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null Post object or null if not found/not allowed.
	 */
	public function get_by_id( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		$allowed_types = $this->get_allowed_post_types();

		// Security: Only allow configured post types.
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Get allowed post types from settings with filter.
	 *
	 * @return array<int, string> Array of allowed post type slugs.
	 */
	public function get_allowed_post_types(): array {
		$post_types = $this->settings->get( 'post_types', [ 'post', 'page' ] );

		/**
		 * Filter allowed post types for AI content endpoints.
		 *
		 * @param array $post_types Post type slugs.
		 */
		return apply_filters( 'aico_allowed_post_types', $post_types );
	}

	/**
	 * Check if a post type is allowed.
	 *
	 * @param string $post_type Post type to check.
	 * @return bool True if allowed.
	 */
	public function is_allowed_post_type( string $post_type ): bool {
		return in_array( $post_type, $this->get_allowed_post_types(), true );
	}

	/**
	 * Get content count by post type.
	 *
	 * Useful for admin display.
	 *
	 * @return array<string, int> Associative array of post_type => count.
	 */
	public function get_content_counts(): array {
		$post_types = $this->get_allowed_post_types();
		$counts     = [];

		foreach ( $post_types as $post_type ) {
			$count_obj            = wp_count_posts( $post_type );
			$counts[ $post_type ] = isset( $count_obj->publish ) ? (int) $count_obj->publish : 0;
		}

		return $counts;
	}

	/**
	 * Get total content count across all allowed post types.
	 *
	 * @return int Total count.
	 */
	public function get_total_count(): int {
		return array_sum( $this->get_content_counts() );
	}
}
