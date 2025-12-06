<?php
/**
 * Plugin Uninstall Handler.
 *
 * Fired when the plugin is deleted via WordPress admin.
 * Removes all plugin data from the database.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

// Exit if accessed directly or not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'aico_settings' );

// Delete all plugin transients.
global $wpdb;

// Find transient keys only (not timeout entries, as delete_transient handles both).
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$transient_keys = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options}
		WHERE option_name LIKE %s
		AND option_name NOT LIKE %s",
		'_transient_aico_%',
		'_transient_timeout_%'
	)
);

foreach ( $transient_keys as $key ) {
	// Remove the '_transient_' prefix to get the actual key.
	$transient_name = substr( $key, 11 ); // strlen( '_transient_' ) = 11.
	delete_transient( $transient_name );
}
// phpcs:enable

// Clear any rewrite rules that might be cached.
flush_rewrite_rules();
