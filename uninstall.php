<?php
defined('ABSPATH') || die;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpunotifications_options',
    'wpunotifications__cron_hook_lastexec',
    'wpunotifications__cron_hook_croninterval',
    'wpunotifications_wpunotifications_version'
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}

/* Delete tables */
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpunotifications");
