<?php
// includes/send-report.php

// Enable error reporting for debugging
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

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
        function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
        {
            error_log("MOCK MAIL SENDING to $to with subject: $subject");
            error_log("Attachments: " . print_r($attachments, true));
            return true;
        }
    }
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir()
        {
            return [
                'basedir' => __DIR__ . '/../uploads',
                'baseurl' => 'http://localhost:8000/uploads'
            ];
        }
    }
    if (!function_exists('sanitize_file_name')) {
        function sanitize_file_name($filename)
        {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
        }
    }
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($dir)
        {
            if (!file_exists($dir))
                mkdir($dir, 0755, true);
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

    // Generate PDFs for ALL languages (En, Hi, Gu)
    $languages = ['en', 'hi', 'gu'];
    $generated_pdfs = [];

    foreach ($languages as $lang) {
        $booking_data['language'] = $lang;
        $result = GemAstroPDF::generate_report($booking_data);

        if (is_array($result) && !empty($result['path']) && file_exists($result['path'])) {
            $generated_pdfs[] = $result['path'];
        }
    }

    if (!empty($generated_pdfs)) {
        // Send email with ALL generated PDFs
        $to = $email;

        // Use configured email template or defaults
        $default_subject = '🌟 Your Personalized GEM Astrology Report';
        $default_body = "<h1>Namaste {name},</h1><p>Thank you for choosing Nion Gem Astro. Your personalized astrology report is attached in English, Hindi, and Gujarati.</p><p><strong>Note:</strong> Save these files for future reference.</p><p>Regards,<br>Team Nion Gem Astro</p>";

        if (function_exists('get_option')) {
            $subject = get_option('gem_astro_email_subject', $default_subject);
            $body = get_option('gem_astro_email_body', $default_body);
        } else {
            $subject = $default_subject;
            $body = $default_body;
        }

        // Replace {name} placeholder
        $subject = str_replace('{name}', $name, $subject);
        $body = str_replace('{name}', $name, $body);

        $headers = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Add Admin Email to Bcc
        if (function_exists('get_option')) {
            $admin_email = get_option('gem_astro_contact_email', '');
            if (!empty($admin_email) && is_email($admin_email)) {
                $headers[] = 'Bcc: ' . $admin_email;
            }
        }

        $sent = wp_mail($to, $subject, $body, $headers, $generated_pdfs);

        if ($sent) {
            echo "Success: Email sent with English, Hindi, and Gujarati reports.";
        } else {
            echo "Error: Failed to send email.";
        }
    } else {
        echo "Error: No reports could be generated.";
    }
} else {
    echo "Invalid Request";
}
