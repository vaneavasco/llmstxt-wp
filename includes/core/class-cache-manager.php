<?php
/**
 * Cache Manager.
 *
 * Manages caching using WordPress Transients API.
 * Provides a consistent caching interface for all plugin components.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

use WP_Post;

/**
 * Cache Manager class.
 *
 * Uses WordPress Transients API for caching, which automatically
 * uses object caching if available (Redis, Memcached, etc.).
 */
final class Cache_Manager {

	/**
	 * Cache key prefix for all plugin transients.
	 */
	public const PREFIX = 'aico_';

	/**
	 * Marker value for cached "not found" results.
	 */
	public const NOT_FOUND_MARKER = '__AICO_NOT_FOUND__';

	/**
	 * TTL for "not found" cache entries (in seconds).
	 */
	public const NOT_FOUND_TTL = 300; // 5 minutes.

	/**
	 * Lock suffix for stampede protection.
	 */
	private const LOCK_SUFFIX = '_lock';

	/**
	 * Lock TTL in seconds (short to prevent deadlocks).
	 */
	private const LOCK_TTL = 30;

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
	 * Get a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return mixed Cached value, or false if not found.
	 */
	public function get( string $key ) {
		$value = get_transient( self::PREFIX . $key );

		// Check for "not found" marker.
		if ( self::NOT_FOUND_MARKER === $value ) {
			return self::NOT_FOUND_MARKER;
		}

		return $value;
	}

	/**
	 * Set a cached value.
	 *
	 * @param string   $key        Cache key (without prefix).
	 * @param mixed    $value      Value to cache.
	 * @param int|null $expiration Optional. TTL in seconds. Uses default if not provided.
	 * @return bool True if cached successfully.
	 */
	public function set( string $key, $value, ?int $expiration = null ): bool {
		if ( null === $expiration ) {
			$expiration = (int) $this->settings->get( 'content_cache_ttl', 600 );
		}
		return set_transient( self::PREFIX . $key, $value, $expiration );
	}

	/**
	 * Set a "not found" cache entry.
	 *
	 * Caches the fact that content was not found to prevent repeated DB queries.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return bool True if cached successfully.
	 */
	public function set_not_found( string $key ): bool {
		return $this->set( $key, self::NOT_FOUND_MARKER, self::NOT_FOUND_TTL );
	}

	/**
	 * Check if a value is a "not found" marker.
	 *
	 * @param mixed $value Value to check.
	 * @return bool True if value is the not found marker.
	 */
	public function is_not_found( $value ): bool {
		return self::NOT_FOUND_MARKER === $value;
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return bool True if deleted successfully.
	 */
	public function delete( string $key ): bool {
		return delete_transient( self::PREFIX . $key );
	}

	/**
	 * Batch size for paginated cache operations.
	 */
	private const BATCH_SIZE = 100;

	/**
	 * Clear all plugin caches.
	 *
	 * Uses pagination to prevent memory issues on large sites.
	 *
	 * @return int Number of transients deleted.
	 */
	public function clear_all(): int {
		global $wpdb;

		$total_deleted = 0;
		$offset        = 0;

		do {
			// Fetch transients in batches to prevent memory issues.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$transient_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options}
					WHERE option_name LIKE %s
					LIMIT %d OFFSET %d",
					'_transient_' . self::PREFIX . '%',
					self::BATCH_SIZE,
					$offset
				)
			);
			// phpcs:enable

			$batch_count   = count( $transient_keys );
			$batch_deleted = 0;

			foreach ( $transient_keys as $transient ) {
				// Remove the '_transient_' prefix to get the actual key.
				$key = str_replace( '_transient_', '', $transient );
				if ( delete_transient( $key ) ) {
					++$batch_deleted;
				}
			}

			$total_deleted += $batch_deleted;

			// If we fetched keys but couldn't delete any, increment offset
			// to avoid infinite loop (keys might be locked or protected).
			if ( $batch_count > 0 && 0 === $batch_deleted ) {
				$offset += $batch_count;
			}
			// Otherwise offset stays at 0 since rows are deleted.

		} while ( $batch_count === self::BATCH_SIZE );

		/**
		 * Fires after all caches are cleared.
		 *
		 * @param int $count Number of transients deleted.
		 */
		do_action( 'aico_cache_cleared', $total_deleted );

		return $total_deleted;
	}

	/**
	 * Get cache key for llms.txt content.
	 *
	 * Includes post types in key to cache different configurations separately.
	 *
	 * @return string Cache key.
	 */
	public function get_llms_txt_key(): string {
		$post_types = $this->settings->get( 'post_types', [ 'post', 'page' ] );
		return 'llms_txt_' . md5( implode( ',', $post_types ) );
	}

	/**
	 * Get cache key for single content item.
	 *
	 * @param string     $post_type  Post type.
	 * @param string|int $identifier Post slug or ID.
	 * @return string Cache key.
	 */
	public function get_content_key( string $post_type, $identifier ): string {
		return sprintf( 'content_%s_%s', sanitize_key( $post_type ), sanitize_key( (string) $identifier ) );
	}

	/**
	 * Invalidate cache for a specific post.
	 *
	 * Called when a post is saved, deleted, or status changes.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$failures = [];

		// Delete specific post cache by ID.
		$id_key = $this->get_content_key( $post->post_type, $post_id );
		if ( ! $this->delete( $id_key ) && false !== get_transient( self::PREFIX . $id_key ) ) {
			$failures[] = $id_key;
		}

		// Delete specific post cache by slug.
		$slug_key = $this->get_content_key( $post->post_type, $post->post_name );
		if ( ! $this->delete( $slug_key ) && false !== get_transient( self::PREFIX . $slug_key ) ) {
			$failures[] = $slug_key;
		}

		// Invalidate the full llms.txt listing since content changed.
		$llms_key = $this->get_llms_txt_key();
		if ( ! $this->delete( $llms_key ) && false !== get_transient( self::PREFIX . $llms_key ) ) {
			$failures[] = $llms_key;
		}

		// Log any failures for debugging.
		if ( ! empty( $failures ) ) {
			$this->log_invalidation_failure( $post_id, $failures );
		}

		/**
		 * Fires after post cache is invalidated.
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		do_action( 'aico_post_cache_invalidated', $post_id, $post );
	}

	/**
	 * Log cache invalidation failures.
	 *
	 * @param int             $post_id  Post ID that triggered invalidation.
	 * @param array<int, string> $failures Array of cache keys that failed to invalidate.
	 * @return void
	 */
	private function log_invalidation_failure( int $post_id, array $failures ): void {
		$message = sprintf(
			'[AI Content Optimizer] Cache invalidation failed for post %d. Keys: %s',
			$post_id,
			implode( ', ', $failures )
		);

		// Use WordPress error logging.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );

		/**
		 * Fires when cache invalidation fails.
		 *
		 * Allows plugins to hook in custom logging solutions.
		 *
		 * @param int   $post_id  Post ID.
		 * @param array $failures Array of failed cache keys.
		 */
		do_action( 'aico_cache_invalidation_failed', $post_id, $failures );
	}

	/**
	 * Get the llms.txt cache TTL from settings.
	 *
	 * @return int TTL in seconds.
	 */
	public function get_llms_txt_ttl(): int {
		return (int) $this->settings->get( 'llms_txt_cache_ttl', 3600 );
	}

	/**
	 * Get the content cache TTL from settings.
	 *
	 * @return int TTL in seconds.
	 */
	public function get_content_ttl(): int {
		return (int) $this->settings->get( 'content_cache_ttl', 600 );
	}

	/**
	 * Attempt to acquire a regeneration lock for a cache key.
	 *
	 * Used for cache stampede protection. Only one request should
	 * regenerate content while others wait or serve stale.
	 *
	 * @param string $key Cache key.
	 * @return bool True if lock acquired, false if already locked.
	 */
	public function acquire_regeneration_lock( string $key ): bool {
		$lock_key = self::PREFIX . $key . self::LOCK_SUFFIX;

		// Use wp_cache_add for atomic check-and-set if object cache available.
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $lock_key, 1, '', self::LOCK_TTL );
		}

		// Fallback for database transients.
		if ( false === get_transient( $lock_key ) ) {
			set_transient( $lock_key, 1, self::LOCK_TTL );
			return true;
		}

		return false;
	}

	/**
	 * Release a regeneration lock.
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public function release_regeneration_lock( string $key ): void {
		$lock_key = self::PREFIX . $key . self::LOCK_SUFFIX;

		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $lock_key );
		} else {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Get cached content with stampede protection.
	 *
	 * Returns cached content and a flag indicating if caller should regenerate.
	 * Handles concurrent requests by allowing only one to regenerate while
	 * others serve stale content or wait.
	 *
	 * @param string $key           Cache key.
	 * @param bool   $allow_stale   Whether to return stale content if regeneration in progress.
	 * @return array{content: mixed, should_regenerate: bool} Content and regeneration flag.
	 */
	public function get_with_lock( string $key, bool $allow_stale = true ): array {
		$content = $this->get( $key );

		// Fresh cache hit.
		if ( false !== $content && ! $this->is_not_found( $content ) ) {
			return [
				'content'           => $content,
				'should_regenerate' => false,
			];
		}

		// Cache miss or stale - try to acquire regeneration lock.
		if ( $this->acquire_regeneration_lock( $key ) ) {
			return [
				'content'           => false,
				'should_regenerate' => true,
			];
		}

		// Lock not acquired - another request is regenerating.
		// If we have stale content, return it.
		if ( $allow_stale && false !== $content && ! $this->is_not_found( $content ) ) {
			return [
				'content'           => $content,
				'should_regenerate' => false,
			];
		}

		// No stale content available - return immediately without blocking.
		// Caller should handle this case (e.g., return 503 with Retry-After).
		return [
			'content'           => false,
			'should_regenerate' => false,
		];
	}
}
