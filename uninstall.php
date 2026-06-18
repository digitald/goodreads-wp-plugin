<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Goodreads_RSS_Widget
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'goodreads_feed_url' );
delete_option( 'goodreads_default_count' );

global $wpdb;

if ( ! isset( $wpdb ) ) {
	return;
}

$transient_like = $wpdb->esc_like( '_transient_goodreads_feed_' ) . '%';
$timeout_like   = $wpdb->esc_like( '_transient_timeout_goodreads_feed_' ) . '%';

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$transient_like
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$timeout_like
	)
);
