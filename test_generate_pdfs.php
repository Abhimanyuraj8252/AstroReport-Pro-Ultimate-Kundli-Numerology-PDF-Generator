<?php
// test_generate_pdfs.php
// Run this to verify that all 3 language PDFs are generated correctly.

// Define GEM_ASTRO_PATH as current directory
define('GEM_ASTRO_PATH', __DIR__ . '/');

// Mock WordPress functions
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir()
    {
        return ['basedir' => __DIR__ . '/uploads', 'baseurl' => 'http://localhost/uploads'];
    }
}
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir)
    {
        if (!file_exists($dir))
            mkdir($dir, 0755, true);
    }
}
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($str)
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $str);
    }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return __DIR__ . '/';
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'http://localhost/';
    }
}

// Require necessary files
require_once 'includes/vendor/autoload.php';
require_once 'includes/class-gem-astro-pdf.php';
// Include language files
require_once 'includes/hindi.php';
require_once 'includes/english.php';
require_once 'includes/gujarati.php';

// Mock Data
$booking_data = [
    'name' => 'Test User',
    'dob' => '1990-01-01',
    'email' => 'test@example.com',
    'phone' => '1234567890',
    'price' => '499',
    'service_type' => 'pdf'
];

$languages = ['en', 'hi', 'gu'];
echo "Starting PDF Generation...\n";

foreach ($languages as $lang) {
    $booking_data['language'] = $lang;
    echo "Generating PDF for: " . strtoupper($lang) . "... ";

    try {
        $result = GemAstroPDF::generate_report($booking_data);
        if (is_array($result) && isset($result['path']) && file_exists($result['path'])) {
            echo "SUCCESS! \n";
            echo "File: " . $result['path'] . "\n";
            echo "Size: " . filesize($result['path']) . " bytes\n";
        } else {
            echo "FAILED! (No path returned or file missing)\n";
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "--------------------------\n";
}
