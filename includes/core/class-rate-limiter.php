<?php
/**
 * Rate Limiter.
 *
 * Limits requests per IP address to prevent abuse of AI content endpoints.
 * Uses WordPress Transients API for storage.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

/**
 * Rate Limiter class.
 *
 * Rate limiting using transients to track request counts per IP.
 * Uses atomic operations when object cache is available to prevent
 * race conditions under concurrent requests.
 */
final class Rate_Limiter {

	/**
	 * Cache key prefix for rate limit counters.
	 */
	public const PREFIX = 'aico_rate_';

	/**
	 * Lock key suffix for concurrency control.
	 */
	private const LOCK_SUFFIX = '_lock';

	/**
	 * Maximum lock wait attempts before giving up.
	 */
	private const MAX_LOCK_ATTEMPTS = 10;

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
	 * Check if the current request should be rate limited.
	 *
	 * Uses atomic increment when object cache is available,
	 * falls back to lock-based approach for database transients.
	 *
	 * @return bool True if request should be blocked.
	 */
	public function is_rate_limited(): bool {
		// Check if rate limiting is enabled.
		if ( ! $this->settings->get( 'rate_limit_enabled' ) ) {
			return false;
		}

		$ip     = $this->get_client_ip();
		$key    = self::PREFIX . md5( $ip );
		$max    = (int) $this->settings->get( 'rate_limit_requests', 60 );
		$window = (int) $this->settings->get( 'rate_limit_window', 60 );

		// Use atomic increment if external object cache is available.
		if ( wp_using_ext_object_cache() ) {
			return $this->check_rate_limit_atomic( $ip, $key, $max, $window );
		}

		// Fall back to lock-based approach for database transients.
		return $this->check_rate_limit_with_lock( $ip, $key, $max, $window );
	}

	/**
	 * Check rate limit using atomic increment (for object cache).
	 *
	 * @param string $ip     Client IP address.
	 * @param string $key    Cache key.
	 * @param int    $max    Maximum requests allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if rate limited.
	 */
	private function check_rate_limit_atomic( string $ip, string $key, int $max, int $window ): bool {
		// Try to add key with initial value of 1 (atomic).
		$added = wp_cache_add( $key, 1, '', $window );

		if ( $added ) {
			// First request in window.
			return false;
		}

		// Key exists, atomically increment.
		$count = wp_cache_incr( $key );

		if ( false === $count ) {
			// Increment failed (key expired between add and incr), try again.
			wp_cache_add( $key, 1, '', $window );
			return false;
		}

		if ( $count > $max ) {
			$this->fire_rate_limited_action( $ip, $count, $max );
			return true;
		}

		return false;
	}

	/**
	 * Check rate limit using lock-based approach (for database transients).
	 *
	 * @param string $ip     Client IP address.
	 * @param string $key    Cache key.
	 * @param int    $max    Maximum requests allowed.
	 * @param int    $window Time window in seconds.
	 * @return bool True if rate limited.
	 */
	private function check_rate_limit_with_lock( string $ip, string $key, int $max, int $window ): bool {
		$lock_key = $key . self::LOCK_SUFFIX;

		// Acquire lock with retry.
		if ( ! $this->acquire_lock( $lock_key ) ) {
			// Could not acquire lock after retries.
			// Fail open: allow request but don't count it.
			return false;
		}

		try {
			$count = (int) get_transient( $key );

			if ( $count >= $max ) {
				$this->fire_rate_limited_action( $ip, $count, $max );
				return true;
			}

			// Increment counter.
			set_transient( $key, $count + 1, $window );
			return false;
		} finally {
			$this->release_lock( $lock_key );
		}
	}

	/**
	 * Acquire a lock for atomic operations.
	 *
	 * @param string $lock_key Lock key name.
	 * @return bool True if lock acquired.
	 */
	private function acquire_lock( string $lock_key ): bool {
		for ( $i = 0; $i < self::MAX_LOCK_ATTEMPTS; $i++ ) {
			// Use set_transient with a very short TTL as a lock.
			// The lock expires after 5 seconds to prevent deadlocks.
			$lock_value = wp_rand() . '_' . time();
			$existing   = get_transient( $lock_key );

			if ( false === $existing ) {
				// No lock exists, try to acquire.
				set_transient( $lock_key, $lock_value, 5 );

				// Verify we got the lock (check-then-act is safe here
				// because we're the only ones trying to set this specific value).
				if ( get_transient( $lock_key ) === $lock_value ) {
					return true;
				}
			}

			// Lock exists or race condition, wait briefly and retry.
			usleep( 5000 ); // 5ms.
		}

		return false;
	}

	/**
	 * Release a lock.
	 *
	 * @param string $lock_key Lock key name.
	 * @return void
	 */
	private function release_lock( string $lock_key ): void {
		delete_transient( $lock_key );
	}

	/**
	 * Fire the rate limited action hook.
	 *
	 * @param string $ip    Client IP address.
	 * @param int    $count Current request count.
	 * @param int    $max   Maximum allowed requests.
	 * @return void
	 */
	private function fire_rate_limited_action( string $ip, int $count, int $max ): void {
		/**
		 * Fires when rate limit is exceeded.
		 *
		 * @param string $ip    Client IP address.
		 * @param int    $count Current request count.
		 * @param int    $max   Maximum allowed requests.
		 */
		do_action( 'aico_rate_limited', $ip, $count, $max );
	}

	/**
	 * Get the number of remaining requests for current IP.
	 *
	 * @return int Remaining requests (-1 if unlimited).
	 */
	public function get_remaining(): int {
		if ( ! $this->settings->get( 'rate_limit_enabled' ) ) {
			return -1; // Unlimited.
		}

		$ip    = $this->get_client_ip();
		$key   = self::PREFIX . md5( $ip );
		$max   = (int) $this->settings->get( 'rate_limit_requests', 60 );
		$count = (int) get_transient( $key );

		return max( 0, $max - $count );
	}

	/**
	 * Get the rate limit window in seconds.
	 *
	 * @return int Window in seconds.
	 */
	public function get_window(): int {
		return (int) $this->settings->get( 'rate_limit_window', 60 );
	}

	/**
	 * Get the maximum requests per window.
	 *
	 * @return int Maximum requests.
	 */
	public function get_limit(): int {
		return (int) $this->settings->get( 'rate_limit_requests', 60 );
	}

	/**
	 * Reset rate limit for current IP.
	 *
	 * Useful for testing or admin override.
	 *
	 * @return bool True if reset successful.
	 */
	public function reset(): bool {
		$ip  = $this->get_client_ip();
		$key = self::PREFIX . md5( $ip );
		return delete_transient( $key );
	}

	/**
	 * Get the client IP address.
	 *
	 * Only trusts proxy headers (X-Forwarded-For, etc.) when the request
	 * comes from a configured trusted proxy IP. This prevents IP spoofing
	 * attacks where an attacker sends fake headers to bypass rate limiting.
	 *
	 * @return string Client IP address.
	 */
	protected function get_client_ip(): string {
		// Get the direct connection IP first.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';

		// Check if the direct connection is from a trusted proxy.
		$trusted_proxies = $this->settings->get( 'trusted_proxy_ips', [] );

		// If no trusted proxies configured, or request isn't from a trusted proxy,
		// only use REMOTE_ADDR to prevent IP spoofing.
		if ( empty( $trusted_proxies ) || ! $this->is_trusted_proxy( $remote_addr, $trusted_proxies ) ) {
			return filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '0.0.0.0';
		}

		// Request is from trusted proxy - check forwarded headers.
		$ip_headers = [
			'HTTP_CF_CONNECTING_IP',    // Cloudflare.
			'HTTP_X_FORWARDED_FOR',     // Standard proxy header.
			'HTTP_X_REAL_IP',           // nginx proxy.
			'HTTP_CLIENT_IP',           // Shared internet.
		];

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// X-Forwarded-For can contain multiple IPs, use the first one.
				if ( false !== strpos( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP format.
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		// Fallback to REMOTE_ADDR.
		return filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '0.0.0.0';
	}

	/**
	 * Check if an IP is from a trusted proxy.
	 *
	 * Supports both individual IPs and CIDR notation.
	 *
	 * @param string             $ip              IP address to check.
	 * @param array<int, string> $trusted_proxies List of trusted proxy IPs/CIDRs.
	 * @return bool True if IP is trusted.
	 */
	private function is_trusted_proxy( string $ip, array $trusted_proxies ): bool {
		foreach ( $trusted_proxies as $trusted ) {
			$trusted = trim( $trusted );

			// Exact IP match.
			if ( $ip === $trusted ) {
				return true;
			}

			// CIDR notation check.
			if ( false !== strpos( $trusted, '/' ) && $this->ip_in_cidr( $ip, $trusted ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP is within a CIDR range.
	 *
	 * Supports both IPv4 and IPv6 addresses.
	 *
	 * @param string $ip   IP address to check.
	 * @param string $cidr CIDR notation (e.g., 192.168.1.0/24 or 2001:db8::/32).
	 * @return bool True if IP is in range.
	 */
	private function ip_in_cidr( string $ip, string $cidr ): bool {
		list( $subnet, $bits ) = explode( '/', $cidr );
		$bits                  = (int) $bits;

		// Check if IP and subnet are same version.
		$ip_is_v6     = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
		$subnet_is_v6 = filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );

		// Mixed versions can't match.
		if ( $ip_is_v6 !== $subnet_is_v6 ) {
			return false;
		}

		if ( $ip_is_v6 ) {
			return $this->ipv6_in_cidr( $ip, $subnet, $bits );
		}

		return $this->ipv4_in_cidr( $ip, $subnet, $bits );
	}

	/**
	 * Check if an IPv4 address is within a CIDR range.
	 *
	 * @param string $ip     IPv4 address to check.
	 * @param string $subnet Subnet address.
	 * @param int    $bits   CIDR bits.
	 * @return bool True if IP is in range.
	 */
	private function ipv4_in_cidr( string $ip, string $subnet, int $bits ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		$ip_long     = ip2long( $ip );
		$subnet_long = ip2long( $subnet );

		if ( false === $ip_long || false === $subnet_long ) {
			return false;
		}

		$mask = -1 << ( 32 - $bits );

		return ( $ip_long & $mask ) === ( $subnet_long & $mask );
	}

	/**
	 * Check if an IPv6 address is within a CIDR range.
	 *
	 * @param string $ip     IPv6 address to check.
	 * @param string $subnet Subnet address.
	 * @param int    $bits   CIDR bits.
	 * @return bool True if IP is in range.
	 */
	private function ipv6_in_cidr( string $ip, string $subnet, int $bits ): bool {
		// Convert IPv6 addresses to binary strings.
		$ip_bin     = $this->ipv6_to_binary( $ip );
		$subnet_bin = $this->ipv6_to_binary( $subnet );

		if ( null === $ip_bin || null === $subnet_bin ) {
			return false;
		}

		// Compare the first $bits bits.
		$ip_prefix     = substr( $ip_bin, 0, $bits );
		$subnet_prefix = substr( $subnet_bin, 0, $bits );

		return $ip_prefix === $subnet_prefix;
	}

	/**
	 * Convert an IPv6 address to a binary string.
	 *
	 * @param string $ip IPv6 address.
	 * @return string|null Binary string (128 chars of 0s and 1s) or null on failure.
	 */
	private function ipv6_to_binary( string $ip ): ?string {
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			return null;
		}

		$binary = '';
		foreach ( str_split( $packed ) as $char ) {
			$binary .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}

		return $binary;
	}

	/**
	 * Get rate limit headers for response.
	 *
	 * Returns headers that inform clients about rate limit status.
	 *
	 * @return array<string, int> Associative array of headers.
	 */
	public function get_headers(): array {
		$remaining = $this->get_remaining();

		return [
			'X-RateLimit-Limit'     => $this->get_limit(),
			'X-RateLimit-Remaining' => max( 0, $remaining ),
			'X-RateLimit-Reset'     => time() + $this->get_window(),
		];
	}
}
