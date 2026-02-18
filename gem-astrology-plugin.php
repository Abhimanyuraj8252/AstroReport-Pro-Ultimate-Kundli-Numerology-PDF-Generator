<?php
/**
 * Plugin Name: AstroReport Pro - Ultimate Kundli & Numerology PDF Generator
 * Plugin URI: https://www.niongemastro.com/
 * Description: A professional, fully automatic engine to generate personalized Numerology and Kundli PDF reports with multi-language support and integrated payments.
 * Version: 1.0.1
 * Author: Trikrypta
 * License: GPL v2 or later
 * Text Domain: gem-astrology-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('GEM_ASTRO_PATH', plugin_dir_path(__FILE__));
define('GEM_ASTRO_URL', plugin_dir_url(__FILE__));
define('GEM_ASTRO_VERSION', '1.0.1');

// Include necessary files
require_once GEM_ASTRO_PATH . 'includes/data-mulank.php';
require_once GEM_ASTRO_PATH . 'includes/class-gem-astro-db.php';
require_once GEM_ASTRO_PATH . 'includes/compat-curl.php'; // Compatibility for missing cURL
require_once GEM_ASTRO_PATH . 'includes/class-gem-astro-pdf.php';
require_once GEM_ASTRO_PATH . 'includes/hindi.php';
require_once GEM_ASTRO_PATH . 'includes/english.php';
require_once GEM_ASTRO_PATH . 'includes/gujarati.php';
require_once GEM_ASTRO_PATH . 'includes/class-gem-astro-admin.php';

// Activation Hook
register_activation_hook(__FILE__, ['GemAstroDB', 'create_table']);

class GemAstrologyPlugin
{

    public function __construct()
    {
        // Initialize Admin Dashboard
        if (is_admin()) {
            new GemAstroAdmin();
        }

        // Enqueue Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Shortcodes
        add_shortcode('astro_report', [$this, 'render_booking_form']);

        // AJAX Handlers
        add_action('wp_ajax_nion_get_booking_config', [$this, 'get_booking_config']);
        add_action('wp_ajax_nopriv_nion_get_booking_config', [$this, 'get_booking_config']);

        add_action('wp_ajax_nion_create_rzp_order', [$this, 'create_razorpay_order']);
        add_action('wp_ajax_nopriv_nion_create_rzp_order', [$this, 'create_razorpay_order']);

        add_action('wp_ajax_nion_verify_and_save', [$this, 'verify_and_save_booking']);
        add_action('wp_ajax_nopriv_nion_verify_and_save', [$this, 'verify_and_save_booking']);

        add_action('wp_ajax_get_booked_slots', [$this, 'get_booked_slots']);
        add_action('wp_ajax_nopriv_get_booked_slots', [$this, 'get_booked_slots']);

        // Report display handler
        add_action('wp_ajax_gem_astro_get_report', [$this, 'get_report_html']);
        add_action('wp_ajax_nopriv_gem_astro_get_report', [$this, 'get_report_html']);
    }

    public function enqueue_assets()
    {
        wp_enqueue_script('razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);

        wp_enqueue_script('gem-astro-script', GEM_ASTRO_URL . 'assets/js/gem-astro-script.js', ['jquery', 'razorpay-checkout'], GEM_ASTRO_VERSION, true);

        // Pass PHP variables to JS
        wp_localize_script('gem-astro-script', 'NION_BOOKING', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gem_astro_nonce'),
            'razorpay_key' => get_option('gem_astro_razorpay_key', ''),
            'pdf_price' => get_option('gem_astro_pdf_price', 1)
        ]);
    }

    public function render_booking_form()
    {
        ob_start();
        include GEM_ASTRO_PATH . 'templates/booking-form.php';
        return ob_get_clean();
    }

    public function get_booking_config()
    {
        // You would typically get these from a settings page
        // For now, hardcoded as per request to work easily
        $config = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gem_astro_nonce'),
            'razorpay_key' => get_option('gem_astro_razorpay_key', 'rzp_test_YOUR_KEY_HERE'),
            'pdf_price' => get_option('gem_astro_pdf_price', 1)
        ];
        wp_send_json_success($config);
    }

    public function create_razorpay_order()
    {
        check_ajax_referer('gem_astro_nonce', 'nonce');

        $price = get_option('gem_astro_pdf_price', 1);
        $amount = floatval($_POST['amount']);

        // Validate amount matches server config (optional but good security)
        if ($amount != $price) {
            $amount = $price;
        }

        $service = sanitize_text_field($_POST['service']);

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
        }

        // Razorpay Order Creation via cURL (since we may not have the SDK installed via Composer)
        $key_id = get_option('gem_astro_razorpay_key', 'rzp_test_YOUR_KEY_HERE');
        $key_secret = get_option('gem_astro_razorpay_secret', 'YOUR_SECRET_HERE');

        $url = 'https://api.razorpay.com/v1/orders';
        $data = [
            'amount' => $amount * 100, // in paise
            'currency' => 'INR',
            'receipt' => 'order_rcptid_' . time(),
            'notes' => [
                'service' => $service
            ]
        ];

        $ch = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($key_id . ':' . $key_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $data,
            'timeout' => 30,
        ]);

        if (is_wp_error($ch)) {
            wp_send_json_error(['message' => 'Could not connect to Razorpay: ' . $ch->get_error_message()]);
        }

        $http_status = wp_remote_retrieve_response_code($ch);
        $response = wp_remote_retrieve_body($ch);

        if ($http_status === 200) {
            $order = json_decode($response, true);
            wp_send_json_success(['order_id' => $order['id']]);
        } else {
            wp_send_json_error(['message' => 'Could not create Razorpay order. Check credentials.']);
        }
    }

    public function verify_and_save_booking()
    {
        check_ajax_referer('gem_astro_nonce', 'nonce');

        $rzp_order_id = sanitize_text_field($_POST['razorpay_order_id']);
        $rzp_payment_id = sanitize_text_field($_POST['razorpay_payment_id']);
        $rzp_signature = sanitize_text_field($_POST['razorpay_signature']);
        $key_secret = get_option('gem_astro_razorpay_secret', 'YOUR_SECRET_HERE');

        // Verify Signature
        $generated_signature = hash_hmac('sha256', $rzp_order_id . "|" . $rzp_payment_id, $key_secret);

        if ($generated_signature !== $rzp_signature) {
            wp_send_json_error(['message' => 'Payment verification failed']);
        }

        $selected_language = sanitize_text_field($_POST['language'] ?? 'hi');
        $language_map = [
            'hindi' => 'hi',
            'english' => 'en',
            'gujarati' => 'gu',
            'hi' => 'hi',
            'en' => 'en',
            'gu' => 'gu'
        ];
        $selected_language = $language_map[$selected_language] ?? 'hi';

        $booking_data = [
            'name' => sanitize_text_field($_POST['name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'dob' => sanitize_text_field($_POST['dob']),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'service_type' => sanitize_text_field($_POST['booking_type']),
            'date' => sanitize_text_field($_POST['date'] ?? ''),
            'time' => sanitize_text_field($_POST['time'] ?? ''),
            'payment_id' => $rzp_payment_id,
            'payment_status' => 'paid',
            'amount' => floatval($_POST['price']),
            'language' => $selected_language
        ];

        $booking_id = GemAstroDB::insert_booking($booking_data);

        if ($booking_id) {
            $pdf_url = '';

            if ($booking_data['service_type'] === 'pdf') {
                // Generate PDF in selected language for both download and email
                $booking_data['language'] = $selected_language;
                $download_result = GemAstroPDF::generate_report($booking_data);
                $selected_pdf_path = '';

                if (is_array($download_result) && isset($download_result['url'])) {
                    $pdf_url = $download_result['url'];
                    $selected_pdf_path = $download_result['path'] ?? '';
                } else {
                    error_log('PDF Generation failed for booking ' . $booking_id);
                }

                // Send email with selected language PDF only
                if (!empty($selected_pdf_path) && file_exists($selected_pdf_path) && !empty($booking_data['email'])) {
                    $to = $booking_data['email'];

                    // Use configured email template or defaults
                    $default_subject = '🌟 Your Personalized AstroReport Pro Report';
                    $default_body = '<h1>Namaste {name},</h1><p>Thank you for choosing AstroReport Pro. Your personalized astrology report is attached in your selected language.</p><p><strong>Note:</strong> Save these files for future reference.</p><p>Regards,<br>Team Trikrypta</p>';

                    $subject = get_option('gem_astro_email_subject', $default_subject);
                    $body = get_option('gem_astro_email_body', $default_body);

                    // Replace {name} placeholder
                    $subject = str_replace('{name}', esc_html($booking_data['name']), $subject);
                    $body = str_replace('{name}', esc_html($booking_data['name']), $body);

                    $headers = ['Content-Type: text/html; charset=UTF-8'];
                    wp_mail($to, $subject, $body, $headers, [$selected_pdf_path]);
                }
            }

            wp_send_json_success(['message' => 'Booking confirmed', 'pdf_url' => $pdf_url]);
        } else {
            wp_send_json_error(['message' => 'Failed to save booking']);
        }
    }

    public function get_booked_slots()
    {
        $date = sanitize_text_field($_POST['date']);
        $slots = GemAstroDB::get_booked_slots($date);
        wp_send_json_success(['data' => $slots]);
    }

    /**
     * Generate full report HTML for display on the website
     * Same logic as index.php — cover page + all kundli sections
     */
    public function get_report_html()
    {
        check_ajax_referer('gem_astro_nonce', 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $dob = sanitize_text_field($_POST['dob'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $language = sanitize_text_field($_POST['language'] ?? 'hi');

        if (empty($name) || empty($dob)) {
            wp_send_json_error(['message' => 'Name and DOB required']);
        }

        // Calculate Mulank
        $timestamp = strtotime($dob);
        if (!$timestamp) {
            wp_send_json_error(['message' => 'Invalid date of birth']);
        }
        $day = date('d', $timestamp);
        while ($day > 9) {
            $sum = 0;
            foreach (str_split((string) $day) as $d) {
                $sum += (int) $d;
            }
            $day = $sum;
        }
        $mulank = (int) $day;

        // Get language data
        $langMap = ['hi' => 'hindi', 'en' => 'english', 'gu' => 'gujarati', 'hindi' => 'hindi', 'english' => 'english', 'gujarati' => 'gujarati'];
        $langKey = $langMap[$language] ?? 'hindi';

        if ($langKey === 'hindi') {
            $allData = getHindiData();
        } elseif ($langKey === 'english') {
            $allData = getEnglishData();
        } elseif ($langKey === 'gujarati') {
            $allData = getGujaratiData();
        } else {
            $allData = getHindiData();
        }

        if (!isset($allData[$mulank])) {
            wp_send_json_error(['message' => 'No data for Mulank ' . $mulank]);
        }

        $reportData = $allData[$mulank];

        // Seed random for consistent but unique per user
        $seed = crc32($name . $dob);
        srand($seed);

        // Build logo URL
        $logoUrl = GEM_ASTRO_URL . 'fonts/logo.jpg';

        // Build HTML — same as index.php
        $html = '';

        // Cover Page
        $html .= '<div class="gem-page gem-cover-page">';
        $html .= '<div class="gem-cover-left">';
        $html .= '<div class="gem-half-circle"></div>';
        $html .= '<img src="' . esc_url($logoUrl) . '" class="gem-logo-img" alt="Logo" onerror="this.style.display=\'none\'">';
        $html .= '</div>';
        $html .= '<div class="gem-cover-content">';
        $html .= '<div class="gem-cover-small">Let\'s bring you the new life</div>';
        $html .= '<div class="gem-cover-main">Hello ' . esc_html($name) . ',</div>';
        $html .= '<div class="gem-cover-sub">Welcome To<br>Your GEM<br>ASTROLOGY<br>Report</div>';
        $html .= '<div class="gem-cover-footer">';
        $html .= '<b>Prepared by</b><br>';
        $html .= 'www.niongemastro.com<br>';
        $html .= '+91 910 430 1456<br>';
        $html .= 'niongemastro@gmail.com';
        $html .= '</div></div></div>';

        // Content Pages
        $html .= '<div class="gem-page" style="height:auto; min-height:1123px; padding-top:40px; padding-bottom:80px;">';
        $html .= '<div class="gem-content-inner">';

        $counter = 0;
        foreach ($reportData as $section) {
            $counter++;
            $heading = $section['heading'];
            $contentRaw = $section['content'];
            $content = '';

            if (is_array($contentRaw)) {
                if (isset($contentRaw['variations'])) {
                    $variations = $contentRaw['variations'];
                    $content = $variations[rand(0, count($variations) - 1)];
                } elseif (isset($contentRaw[0])) {
                    $content = $contentRaw[rand(0, count($contentRaw) - 1)];
                } else {
                    $content = json_encode($contentRaw);
                }
            } else {
                $content = $contentRaw;
            }

            // Page break every 4 sections
            if ($counter > 1 && $counter % 4 == 0) {
                $html .= '</div></div>';
                $html .= '<div class="gem-page" style="height:auto; min-height:1123px; padding-top:40px; padding-bottom:80px;">';
                $html .= '<div class="gem-content-inner">';
            }

            $html .= '<div class="gem-section-box">';
            $html .= '<div class="gem-section-heading">' . esc_html($heading) . '</div>';
            $html .= '<div class="gem-section-content">' . nl2br(esc_html(str_replace('\\n', "\n", $content))) . '</div>';
            $html .= '</div>';
        }

        // Footer note
        $html .= '<div class="gem-footer-note">';
        $html .= 'NOTE: You can call us round the clock for any query regarding your numerology report on +91 910 430 1456';
        $html .= '</div>';
        $html .= '</div></div>';

        wp_send_json_success(['html' => $html]);
    }
}

// Initialize the plugin
new GemAstrologyPlugin();
