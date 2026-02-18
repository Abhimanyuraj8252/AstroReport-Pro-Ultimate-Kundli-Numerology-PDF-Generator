<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package GemAstrology
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
$options = [
    'gem_astro_razorpay_key',
    'gem_astro_razorpay_secret',
    'gem_astro_pdf_price',
    'gem_astro_brand_title',
    'gem_astro_brand_tagline',
    'gem_astro_website_name',
    'gem_astro_website_url',
    'gem_astro_contact_phone',
    'gem_astro_contact_email',
    'gem_astro_cover_logo',
    'gem_astro_cover_welcome_text',
    'gem_astro_email_subject',
    'gem_astro_email_body',
];

foreach ($options as $option) {
    delete_option($option);
}

// Drop the custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'gem_astro_bookings';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Remove uploaded PDF reports directory
$upload_dir = wp_upload_dir();
$gem_dir = $upload_dir['basedir'] . '/gem-astrology-reports/';
if (is_dir($gem_dir)) {
    $files = glob($gem_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($gem_dir);
}

// Clear scheduled cron events
wp_clear_scheduled_hook('gem_astro_daily_cleanup');
