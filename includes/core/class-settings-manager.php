<?php
/**
 * Settings Manager.
 *
 * Manages plugin settings using WordPress Settings API.
 * Provides a centralized interface for all configuration options.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

/**
 * Settings Manager class.
 *
 * Handles reading, writing, and sanitizing plugin settings
 * stored in wp_options table.
 */
final class Settings_Manager implements Settings_Interface {

	/**
	 * Option name in wp_options table.
	 */
	public const OPTION_NAME = 'aico_settings';

	/**
	 * Settings group for Settings API.
	 */
	public const SETTINGS_GROUP = 'aico_settings_group';

	/**
	 * Default settings values.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = [
		// General settings.
		'enabled'              => true,
		'llms_txt_enabled'     => true,
		'ai_endpoints_enabled' => true,
		'bot_redirect_enabled' => true,

		// Post types to include.
		'post_types'           => [ 'post', 'page' ],

		// Cache settings (in seconds).
		'llms_txt_cache_ttl'   => 3600,    // 1 hour.
		'content_cache_ttl'    => 600,     // 10 minutes.

		// Rate limiting.
		'rate_limit_enabled'   => true,
		'rate_limit_requests'  => 60,      // Per minute.
		'rate_limit_window'    => 60,      // Seconds.

		// Security settings.
		'trusted_proxy_ips'    => [],      // IPs of trusted proxies (e.g., Cloudflare).

		// Site information for markdown output.
		'site_description'     => '',
		'contact_email'        => '',

		// Advanced settings.
		'noindex_ai_content'   => true,
		'custom_bots'          => '',      // Additional bot patterns (one per line).
	];

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional. Default value if setting not found.
	 * @return mixed Setting value.
	 */
	public function get( string $key, $default = null ) {
		$options = get_option( self::OPTION_NAME, [] );
		$options = wp_parse_args( $options, $this->defaults );

		// Use class default if no default provided.
		if ( null === $default ) {
			$default = $this->defaults[ $key ] ?? null;
		}

		return $options[ $key ] ?? $default;
	}

	/**
	 * Update a single setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True if updated, false otherwise.
	 */
	public function update( string $key, $value ): bool {
		$options         = get_option( self::OPTION_NAME, [] );
		$options[ $key ] = $this->sanitize_setting( $key, $value );
		return update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed> All settings with defaults applied.
	 */
	public function get_all(): array {
		$options = get_option( self::OPTION_NAME, [] );
		return wp_parse_args( $options, $this->defaults );
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public function get_defaults(): array {
		return $this->defaults;
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return bool True if reset successful.
	 */
	public function reset(): bool {
		return update_option( self::OPTION_NAME, $this->defaults );
	}

	/**
	 * Register settings with WordPress Settings API.
	 *
	 * Called during admin_init hook.
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => $this->defaults,
			]
		);
	}

	/**
	 * Sanitize all settings on save.
	 *
	 * @param array<string, mixed>|null $input Raw input from form.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( ?array $input ): array {
		$input     = $input ?? [];
		$sanitized = [];

		// Boolean settings.
		$bool_keys = [
			'enabled',
			'llms_txt_enabled',
			'ai_endpoints_enabled',
			'bot_redirect_enabled',
			'rate_limit_enabled',
			'noindex_ai_content',
		];
		foreach ( $bool_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Integer settings.
		$sanitized['llms_txt_cache_ttl']  = $this->sanitize_int( $input['llms_txt_cache_ttl'] ?? 3600, 60, 86400 );
		$sanitized['content_cache_ttl']   = $this->sanitize_int( $input['content_cache_ttl'] ?? 600, 60, 86400 );
		$sanitized['rate_limit_requests'] = $this->sanitize_int( $input['rate_limit_requests'] ?? 60, 10, 1000 );
		$sanitized['rate_limit_window']   = $this->sanitize_int( $input['rate_limit_window'] ?? 60, 10, 3600 );

		// Post types array.
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
			// Ensure at least one post type is selected.
			if ( empty( $sanitized['post_types'] ) ) {
				$sanitized['post_types'] = [ 'post', 'page' ];
			}
		} else {
			$sanitized['post_types'] = [ 'post', 'page' ];
		}

		// Text settings.
		$sanitized['site_description'] = sanitize_textarea_field( $input['site_description'] ?? '' );
		$sanitized['contact_email']    = sanitize_email( $input['contact_email'] ?? '' );
		$sanitized['custom_bots']      = sanitize_textarea_field( $input['custom_bots'] ?? '' );

		// Trusted proxy IPs (array of IP addresses or CIDR notations).
		$sanitized['trusted_proxy_ips'] = $this->sanitize_ip_list( $input['trusted_proxy_ips'] ?? [] );

		return $sanitized;
	}

	/**
	 * Sanitize a list of IP addresses or CIDR notations.
	 *
	 * @param mixed $input Raw input (array or string).
	 * @return array<int, string> Sanitized array of valid IPs/CIDRs.
	 */
	private function sanitize_ip_list( $input ): array {
		// Handle string input (from textarea, one per line).
		if ( is_string( $input ) ) {
			$input = array_filter( array_map( 'trim', explode( "\n", $input ) ) );
		}

		if ( ! is_array( $input ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $input as $ip_or_cidr ) {
			$ip_or_cidr = trim( sanitize_text_field( (string) $ip_or_cidr ) );

			if ( empty( $ip_or_cidr ) ) {
				continue;
			}

			// Validate IP or CIDR notation.
			if ( $this->is_valid_ip_or_cidr( $ip_or_cidr ) ) {
				$sanitized[] = $ip_or_cidr;
			}
		}

		return $sanitized;
	}

	/**
	 * Check if a string is a valid IP address or CIDR notation.
	 *
	 * Supports both IPv4 and IPv6.
	 *
	 * @param string $ip_or_cidr IP address or CIDR notation.
	 * @return bool True if valid.
	 */
	private function is_valid_ip_or_cidr( string $ip_or_cidr ): bool {
		// Check for CIDR notation.
		if ( false !== strpos( $ip_or_cidr, '/' ) ) {
			list( $ip, $bits ) = explode( '/', $ip_or_cidr, 2 );

			// Validate the IP part.
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return false;
			}

			// Validate the bits part.
			$bits    = (int) $bits;
			$is_ipv6 = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );

			if ( $is_ipv6 ) {
				return $bits >= 0 && $bits <= 128;
			} else {
				return $bits >= 0 && $bits <= 32;
			}
		}

		// Plain IP address.
		return (bool) filter_var( $ip_or_cidr, FILTER_VALIDATE_IP );
	}

	/**
	 * Sanitize a single setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_setting( string $key, $value ) {
		$bool_keys = [
			'enabled',
			'llms_txt_enabled',
			'ai_endpoints_enabled',
			'bot_redirect_enabled',
			'rate_limit_enabled',
			'noindex_ai_content',
		];

		$int_keys = [
			'llms_txt_cache_ttl',
			'content_cache_ttl',
			'rate_limit_requests',
			'rate_limit_window',
		];

		if ( in_array( $key, $bool_keys, true ) ) {
			return (bool) $value;
		}

		if ( in_array( $key, $int_keys, true ) ) {
			return absint( $value );
		}

		if ( 'post_types' === $key && is_array( $value ) ) {
			return array_map( 'sanitize_key', $value );
		}

		if ( 'contact_email' === $key ) {
			return sanitize_email( (string) $value );
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Sanitize integer with min/max bounds.
	 *
	 * @param mixed $value Value to sanitize.
	 * @param int   $min   Minimum allowed value.
	 * @param int   $max   Maximum allowed value.
	 * @return int Sanitized integer within bounds.
	 */
	private function sanitize_int( $value, int $min, int $max ): int {
		$value = absint( $value );
		return max( $min, min( $max, $value ) );
	}
}
