<?php
/**
 * Uninstall handler.
 *
 * Removes everything the plugin writes to the database: every `mbr_cc_`
 * option (and, on multisite, network/site-meta option) plus the consent-log
 * table. Runs only when the plugin is deleted from the Plugins screen, never
 * on ordinary deactivation.
 *
 * @package MBR_Cookie_Consent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete every `mbr_cc_`-prefixed option on the current site.
 *
 * A LIKE-based bulk delete is used instead of naming each option: the
 * plugin writes several dozen settings keys, all under the same static
 * prefix, and keeping an exhaustive list in sync with every new setting
 * added over time is itself a source of leftover data at uninstall.
 *
 * @return void
 */
function mbr_cc_uninstall_delete_site_options() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time bulk cleanup on plugin deletion, not a runtime query; nothing here is read back.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'mbr_cc_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$mbr_cc_site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $mbr_cc_site_ids as $mbr_cc_site_id ) {
		switch_to_blog( $mbr_cc_site_id );
		mbr_cc_uninstall_delete_site_options();
		restore_current_blog();
	}

	global $wpdb;

	// Network-wide settings saved via update_site_option() live in sitemeta,
	// not options, and are not touched by the per-site loop above.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time bulk cleanup on plugin deletion, not a runtime query; nothing here is read back.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'mbr_cc_' ) . '%'
		)
	);

	// The consent-log table is a single shared table keyed by blog_id, not
	// one table per site (see MBR_CC_Database::create_tables()), so it is
	// dropped once here rather than inside the per-site loop.
	// %i ($wpdb->prepare() identifier placeholder) needs WP 6.2+, which this
	// plugin's "Requires at least: 5.8" does not guarantee, so the table name
	// is interpolated directly instead — it is a static-prefixed literal
	// ($wpdb->base_prefix . 'mbr_cc_consent_logs'), never attacker input, and
	// identifiers cannot be bound as query parameters regardless of WP version.
	$mbr_cc_table = $wpdb->base_prefix . 'mbr_cc_consent_logs';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-time schema cleanup on plugin deletion, not a runtime query; table name is a static-prefixed literal, not attacker input.
	$wpdb->query( "DROP TABLE IF EXISTS `{$mbr_cc_table}`" );
} else {
	mbr_cc_uninstall_delete_site_options();

	global $wpdb;
	$mbr_cc_table = $wpdb->prefix . 'mbr_cc_consent_logs';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-time schema cleanup on plugin deletion, not a runtime query; table name is a static-prefixed literal, not attacker input.
	$wpdb->query( "DROP TABLE IF EXISTS `{$mbr_cc_table}`" );
}
