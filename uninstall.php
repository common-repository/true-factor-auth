<?php

/**
 * AkWallet Uninstall
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( defined( 'TRUE_FACTOR_UNINSTALL_KEEP_DATA' ) ) {
	return;
}

require_once 'init.php';

global $wpdb, $wp_version;

// Tables.
foreach (
	[
		\TrueFactor\Orm\AccessRule::getTableName(),
	] as $table
) {
	$wpdb->query( "DROP TABLE IF EXISTS " . $table );
}

// Delete options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_tfa\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tfa\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_tfa\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'tfa\_%'" );

// Clear any cached data that has been removed
wp_cache_flush();