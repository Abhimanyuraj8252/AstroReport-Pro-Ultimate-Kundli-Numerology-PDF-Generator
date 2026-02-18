<?php
// Test Script for Gem Astro PDF Generation
// Run with: php test_pdf_gen.php

// 1. Mock WordPress Environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('GEM_ASTRO_PATH')) {
    define('GEM_ASTRO_PATH', __DIR__ . '/');
}

// Mock WP functions
if (!function_exists('get_option')) {
    function get_option($key, $default = false)
    {
        $options = [
            'gem_astro_brand_title' => 'Trikrypta',
            'gem_astro_brand_tagline' => 'Let\'s bring you the new life',
            'gem_astro_website_name' => 'Trikrypta',
            'gem_astro_website_url' => 'https://abhimanyu-raj-cse.vercel.app/',
            'gem_astro_contact_phone' => '+91 9801834437',
            'gem_astro_contact_email' => 'novanexusltd001@gmail.com',
            'gem_astro_cover_welcome_text' => "Welcome To\nYour GEM\nASTROLOGY\nReport",
            'gem_astro_cover_logo' => '/home/abhimanyu/.gemini/antigravity/brain/4ae1994e-c991-4628-98c9-f500f6bc55da/pdf_cover_page_new_1771372541255.png' // Mock logo for testing
        ];
        return isset($options[$key]) ? $options[$key] : $default;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path)
    {
        if (!file_exists($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename)
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir()
    {
        return [
            'basedir' => __DIR__ . '/uploads',
            'baseurl' => 'http://localhost/uploads'
        ];
    }
}

// 2. Adjust include paths
// The script is in the root, so includes are in ./includes/
require_once 'includes/english.php'; // For getEnglishData
require_once 'includes/class-gem-astro-pdf.php';

// 3. Prepare Data
$booking_data = [
    'name' => 'Ashutosh Ranjan',
    'dob' => '2000-01-01', // Mulank 1
    'language' => 'en',
    'output_mode' => 'F'
];

// 4. Generate Report
echo "Generating PDF...\n";
$result = GemAstroPDF::generate_report($booking_data);

if ($result) {
    if (is_array($result)) {
        echo "Success! PDF saved at: " . $result['path'] . "\n";
    } else {
        echo "Success! (Result: " . print_r($result, true) . ")\n";
    }
} else {
    echo "Failed to generate PDF.\n";
}
