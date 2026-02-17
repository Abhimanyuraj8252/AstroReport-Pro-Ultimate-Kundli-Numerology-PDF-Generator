<?php
// includes/send-report.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log the request
error_log("Report Generation Request Received: " . print_r($_POST, true));

// Load WordPress environment if available
// We need to find wp-load.php. Since we are in a plugin/theme structure, 
// we might be deep. Let's try standard paths.
$wp_load_path = '';
$potential_paths = [
    '../../../../wp-load.php', // If in plugins/plugin-name/includes/
    '../../../../../wp-load.php', // If in plugins/plugin-name/includes/
];

foreach ($potential_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

if ($wp_load_path) {
    require_once $wp_load_path;
} else {
    // If WP not found, we mock wp_mail and other functions for standalone testing
    if (!function_exists('wp_mail')) {
        function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
            error_log("MOCK MAIL SENDING to $to with subject: $subject");
            error_log("Attachments: " . print_r($attachments, true));
            return true;
        }
    }
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir() {
            return [
                'basedir' => __DIR__ . '/../uploads',
                'baseurl' => 'http://localhost:8000/uploads'
            ];
        }
    }
    if (!function_exists('sanitize_file_name')) {
        function sanitize_file_name($filename) {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
        }
    }
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($dir) {
            if (!file_exists($dir)) mkdir($dir, 0755, true);
        }
    }
    // Define constants if not defined
    if (!defined('GEM_ASTRO_PATH')) {
        define('GEM_ASTRO_PATH', dirname(__DIR__) . '/');
    }
}

require_once 'class-gem-astro-pdf.php';
require_once 'hindi.php';
require_once 'english.php';
require_once 'gujarati.php';

// Functions to get data (assuming they are in the included files)
// We need to make sure these functions are available globally or we re-include them if they are scoped.
// The includes above should work if they define global functions.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $email = $_POST['email'] ?? '';
    $language = $_POST['language'] ?? 'hi';

    $langMap = [
        'hindi' => 'hi',
        'english' => 'en',
        'gujarati' => 'gu',
        'hi' => 'hi',
        'en' => 'en',
        'gu' => 'gu'
    ];
    $language = $langMap[$language] ?? 'hi';

    if (empty($name) || empty($dob) || empty($email)) {
        echo "Error: Missing required fields.";
        exit;
    }

    $booking_data = [
        'name' => $name,
        'dob' => $dob,
        'email' => $email,
        'language' => $language
    ];

    // Generate report in selected language only
    $result = GemAstroPDF::generate_report($booking_data);
    $selected_attachment = (is_array($result) && !empty($result['path']) && file_exists($result['path']))
        ? $result['path']
        : '';

    if (!empty($selected_attachment)) {
        // Send one email with selected language attachment
        $to = $email;
        $subject = '🌟 Your Personalized GEM Astrology Report';
        $body = "<h1>Namaste $name,</h1>";
        $body .= "<p>Thank you for choosing Nion Gem Astro. Your personalized astrology report is attached in your selected language.</p>";
        $body .= "<p><strong>Note:</strong> Save these files for future reference.</p>";
        $body .= "<p>Regards,<br>Team Nion Gem Astro</p>";
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $sent = wp_mail($to, $subject, $body, $headers, [$selected_attachment]);
        
        if ($sent) {
            echo "Success: Email sent with selected language report.";
        } else {
            echo "Error: Failed to send email.";
        }
    } else {
        echo "Error: No reports could be generated.";
    }
} else {
    echo "Invalid Request";
}
