<?php
/**
 * AstroReport Pro — Premium Admin Dashboard
 * Modern Dark Theme with Glassmorphism, Live Stats, Analytics
 * Author: Trikrypta
 */

if (!defined('ABSPATH')) {
    exit;
}

class GemAstroAdmin
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Removed admin_styles enqueue as we are now inlining for guaranteed render
        // add_action('admin_enqueue_scripts', [$this, 'admin_styles']);

        // AJAX: Live stats refresh
        add_action('wp_ajax_gem_astro_live_stats', [$this, 'ajax_live_stats']);
        // AJAX: CSV Export
        add_action('wp_ajax_gem_astro_export_csv', [$this, 'ajax_export_csv']);
        // AJAX: Delete Booking
        add_action('wp_ajax_gem_astro_delete_booking', [$this, 'ajax_delete_booking']);
        // AJAX: Get Booking Details
        add_action('wp_ajax_gem_astro_get_booking_details', [$this, 'ajax_get_booking_details']);
        // AJAX: Filter Bookings
        add_action('wp_ajax_gem_astro_filter_bookings', [$this, 'ajax_filter_bookings']);

        // Schedule Cron
        if (!wp_next_scheduled('gem_astro_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'gem_astro_daily_cleanup');
        }
        add_action('gem_astro_daily_cleanup', [$this, 'cleanup_old_pdfs']);
    }

    public function register_menus()
    {
        add_menu_page(
            'AstroReport Pro',
            'AstroReport Pro',
            'manage_options',
            'gem-astrology',
            [$this, 'render_dashboard'],
            'dashicons-star-filled',
            30
        );

        add_submenu_page(
            'gem-astrology',
            'Dashboard & Bookings',
            'Dashboard',
            'manage_options',
            'gem-astrology',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'gem-astrology',
            'Settings',
            'Settings',
            'manage_options',
            'gem-astrology-settings',
            [$this, 'render_settings']
        );
    }

    public function register_settings()
    {
        register_setting('gem_astro_settings', 'gem_astro_razorpay_key');
        register_setting('gem_astro_settings', 'gem_astro_razorpay_secret');
        register_setting('gem_astro_settings', 'gem_astro_pdf_price');
        register_setting('gem_astro_settings', 'gem_astro_brand_title');
        register_setting('gem_astro_settings', 'gem_astro_brand_tagline');
        register_setting('gem_astro_settings', 'gem_astro_website_name');
        register_setting('gem_astro_settings', 'gem_astro_website_url');
        register_setting('gem_astro_settings', 'gem_astro_contact_phone');
        register_setting('gem_astro_settings', 'gem_astro_contact_email');
        register_setting('gem_astro_settings', 'gem_astro_cover_logo');
        register_setting('gem_astro_settings', 'gem_astro_cover_welcome_text');
        register_setting('gem_astro_settings', 'gem_astro_email_subject');
        register_setting('gem_astro_settings', 'gem_astro_email_subject');
        register_setting('gem_astro_settings', 'gem_astro_email_body');
        register_setting('gem_astro_settings', 'gem_astro_services'); // New: JSON string of services
    }

    public function enqueue_admin_assets($hook)
    {
        if (!isset($_GET['page'])) {
            return;
        }

        $page = sanitize_key($_GET['page']);
        if ($page !== 'gem-astrology' && $page !== 'gem-astrology-settings') {
            return;
        }

        wp_enqueue_media();
    }

    /**
     * AJAX: Live Stats
     */
    public function ajax_live_stats()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '7d';
        $stats = GemAstroDB::get_advanced_stats($range);

        // We only need basic stats for the top cards, but get_advanced_stats returns everything including chart data

        wp_send_json_success([
            'stats' => $stats,
            'time' => current_time('H:i:s')
        ]);
    }

    /**
     * AJAX: CSV Export
     */
    public function ajax_export_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $bookings = GemAstroDB::get_all_bookings([]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=astroreport-bookings-' . date('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Date', 'Name', 'Phone', 'Email', 'DOB', 'Service', 'Language', 'Payment Status', 'Payment ID', 'Amount', 'Created']);

        foreach ($bookings as $b) {
            fputcsv($out, [
                $b->id,
                $b->date,
                $b->name,
                $b->phone,
                $b->email,
                $b->dob,
                $b->service_type,
                $b->language,
                $b->payment_status,
                $b->payment_id,
                $b->amount,
                $b->created_at
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * AJAX: Delete Booking
     */
    public function ajax_delete_booking()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = intval($_POST['id']);
        if (!$id) {
            wp_send_json_error('Invalid ID');
        }

        $result = GemAstroDB::delete_booking($id);

        if ($result !== false) {
            wp_send_json_success(['message' => 'Booking deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete booking']);
        }
    }

    /**
     * AJAX: Get Booking Details (For View Modal)
     */
    public function ajax_get_booking_details()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = intval($_POST['id']);
        if (!$id) {
            wp_send_json_error('Invalid ID');
        }

        $booking = GemAstroDB::get_booking_by_id($id);

        if ($booking) {
            wp_send_json_success($booking);
        } else {
            wp_send_json_error('Booking not found');
        }
    }

    /**
     * AJAX: Filter Bookings Table
     */
    public function ajax_filter_bookings()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $filters = [
            'range' => isset($_POST['range']) ? sanitize_text_field($_POST['range']) : 'all',
            'sort_by' => isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'date',
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
        ];

        $bookings = GemAstroDB::get_all_bookings($filters);

        ob_start();
        if (empty($bookings)) {
            echo '<tr><td colspan="7" class="ga-empty">No bookings found for this period.</td></tr>';
        } else {
            foreach ($bookings as $b) {
                $ll = ['hi' => '🇮🇳 HI', 'en' => '🇬🇧 EN', 'gu' => '🇮🇳 GU'];
                ?>
                <tr>
                    <td><span class="ga-id">#<?php echo $b->id; ?></span></td>
                    <td>
                        <div class="ga-user-info">
                            <div class="ga-avatar"><?php echo strtoupper(substr($b->name, 0, 1)); ?></div>
                            <div>
                                <div class="ga-name"><?php echo esc_html($b->name); ?></div>
                                <small>📱 <?php echo esc_html($b->phone); ?> · 🎂 <?php echo esc_html($b->dob); ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="ga-badge <?php echo $b->service_type === 'pdf' ? 'ga-badge-blue' : 'ga-badge-purple'; ?>">
                            <?php echo $b->service_type === 'pdf' ? '📄 PDF' : '🗓️ Consult'; ?>
                        </span>
                    </td>
                    <td>
                        <span class="ga-badge ga-badge-lang"><?php echo $ll[$b->language] ?? strtoupper($b->language); ?></span>
                    </td>
                    <td>
                        <span class="ga-badge <?php echo $b->payment_status === 'paid' ? 'ga-badge-green' : 'ga-badge-red'; ?>">
                            <?php echo $b->payment_status === 'paid' ? '✓ Paid' : '✗ ' . ucfirst($b->payment_status); ?>
                        </span>
                        <div class="ga-payment-id"><?php echo esc_html($b->payment_id); ?></div>
                    </td>
                    <td><span class="ga-amount">₹<?php echo number_format($b->amount, 0); ?></span></td>
                    <td><span class="ga-date"><?php echo esc_html($b->created_at); ?></span></td>
                    <td>
                        <div class="ga-actions">
                            <button class="ga-btn-icon ga-view-btn" data-id="<?php echo $b->id; ?>" title="View Details">👁️</button>
                            <button class="ga-btn-icon ga-delete-btn" data-id="<?php echo $b->id; ?>" title="Delete">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php
            }
        }
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Cron: Cleanup Old PDFs
     * Deletes generated reports older than 24 hours to save space
     */
    public function cleanup_old_pdfs()
    {
        $upload_dir = wp_upload_dir();
        $gem_astro_dir = $upload_dir['basedir'] . '/gem-astrology-reports/';

        if (!is_dir($gem_astro_dir)) {
            return;
        }

        $files = glob($gem_astro_dir . '*.pdf');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 24 * 60 * 60) { // 24 hours
                    unlink($file);
                }
            }
        }
    }

    /**
     * =============================================
     * PREMIUM DASHBOARD PAGE
     * =============================================
     */
    public function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Output Styles Directly (Guarantees Rendering)
        echo '<style>' . $this->get_admin_css() . '</style>';

        $stats = GemAstroDB::get_advanced_stats();
        $recent = GemAstroDB::get_recent_bookings(5);
        $filters = [
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'date' => isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '',
        ];
        $bookings = GemAstroDB::get_all_bookings($filters);

        $razorpay_key = get_option('gem_astro_razorpay_key', '');
        $brand_title = get_option('gem_astro_brand_title', 'Trikrypta');
        $brand_tagline = get_option('gem_astro_brand_tagline', 'Let\'s bring you the new life');
        $website_name = get_option('gem_astro_website_name', 'Trikrypta');
        $website_url = get_option('gem_astro_website_url', 'https://abhimanyu-raj-cse.vercel.app/');
        $contact_phone = get_option('gem_astro_contact_phone', '+91 9801834437');
        $contact_email = get_option('gem_astro_contact_email', 'novanexusltd001@gmail.com');
        $cover_logo = get_option('gem_astro_cover_logo', '');
        $cover_welcome_text = get_option('gem_astro_cover_welcome_text', "Welcome To\nYour GEM\nASTROLOGY\nReport");
        $rzp_mode = '';
        if (empty($razorpay_key)) {
            $rzp_mode = 'not_set';
        } elseif (strpos($razorpay_key, 'rzp_test_') === 0) {
            $rzp_mode = 'test';
        } else {
            $rzp_mode = 'live';
        }

        // Revenue chart data
        $chart_data = $stats['revenue_chart'];
        $max_revenue = max(array_values($chart_data) ?: [1]);
        if ($max_revenue == 0)
            $max_revenue = 1;

        // Language distribution
        $lang_dist = $stats['language_distribution'];
        $lang_total = array_sum($lang_dist) ?: 1;
        $lang_labels = ['hi' => 'Hindi', 'en' => 'English', 'gu' => 'Gujarati'];
        $lang_colors = ['hi' => '#F5A623', 'en' => '#8B5CF6', 'gu' => '#06B6D4'];

        // Trend
        $trend_today = $stats['today'];
        $trend_yesterday = $stats['yesterday'];
        $trend_dir = $trend_today >= $trend_yesterday ? 'up' : 'down';
        $trend_diff = abs($trend_today - $trend_yesterday);

        ?>
        <div class="ga-wrap">
            <!-- Header -->
            <div class="ga-header">
                <div class="ga-header-left">
                    <div class="ga-logo-icon">✦</div>
                    <div>
                        <h1 class="ga-title">AstroReport Pro</h1>
                        <span class="ga-subtitle">Premium Analytics Dashboard</span>
                    </div>
                </div>
                <div class="ga-header-right">
                    <select id="ga-stats-range" class="ga-select" onchange="refreshStats()" style="margin-right:15px;padding:6px;border-radius:6px;background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);">
                        <option value="today">Today</option>
                        <option value="7d" selected>Last 7 Days</option>
                        <option value="15d">Last 15 Days</option>
                        <option value="1m">Last 1 Month</option>
                        <option value="3m">Last 3 Months</option>
                        <option value="6m">Last 6 Months</option>
                        <option value="1y">Last 1 Year</option>
                        <option value="3y">Last 3 Years</option>
                        <option value="5y">Last 5 Years</option>
                        <option value="all">All Time</option>
                    </select>
                    <span class="ga-live-badge" id="ga-live-badge">
                        <span class="ga-live-dot"></span> LIVE
                    </span>
                    <span class="ga-version">v<?php echo GEM_ASTRO_VERSION; ?></span>
                    <span class="ga-updated" id="ga-last-updated">Updated: <?php echo current_time('H:i:s'); ?></span>
                </div>
            </div>

            <!-- Razorpay Config Banner (Prominent) -->
            <?php if ($rzp_mode === 'not_set'): ?>
                <div class="ga-config-banner ga-banner-red">
                    <div class="ga-banner-icon">⚠️</div>
                    <div class="ga-banner-content">
                        <h2>Payment Gateway Not Configured</h2>
                        <p>You must set your Razorpay API Keys to start accepting payments.</p>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=gem-astrology-settings'); ?>" class="ga-btn ga-banner-btn">⚙
                        Configure Now</a>
                </div>
            <?php elseif ($rzp_mode === 'test'): ?>
                <div class="ga-config-banner ga-banner-orange">
                    <div class="ga-banner-icon">🧪</div>
                    <div class="ga-banner-content">
                        <h2>Test Mode Active</h2>
                        <p>Payments are currently in Test Mode. Real money will not be charged.</p>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=gem-astrology-settings'); ?>" class="ga-btn ga-banner-btn">Switch
                        to Live</a>
                </div>
            <?php else: ?>
                <div class="ga-config-banner ga-banner-green">
                    <div class="ga-banner-icon">✅</div>
                    <div class="ga-banner-content">
                        <h2>Payments Live & Active</h2>
                        <p>Your Razorpay gateway is connected and ready for business.</p>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=gem-astrology-settings'); ?>"
                        class="ga-btn ga-banner-btn ga-btn-outline">Manage Keys</a>
                </div>
            <?php endif; ?>

            <!-- Quick Edit (Home Dashboard) -->
            <div class="ga-card">
                <div class="ga-card-header">
                    <h3>✏️ Quick Edit - PDF Branding & Contact</h3>
                </div>
                <p class="ga-desc">Yahin se first & last PDF page ka title, website, phone aur email update karo.</p>

                <form method="post" action="options.php">
                    <?php settings_fields('gem_astro_settings'); ?>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="ga-form-group">
                            <label for="ga_home_brand_title">Brand Title</label>
                            <input type="text" id="ga_home_brand_title" name="gem_astro_brand_title"
                                value="<?php echo esc_attr($brand_title); ?>" class="ga-input">
                        </div>

                        <div class="ga-form-group">
                            <label for="ga_home_brand_tagline">Tagline</label>
                            <input type="text" id="ga_home_brand_tagline" name="gem_astro_brand_tagline"
                                value="<?php echo esc_attr($brand_tagline); ?>" class="ga-input">
                        </div>

                        <div class="ga-form-group">
                            <label for="ga_home_website_name">Website Name</label>
                            <input type="text" id="ga_home_website_name" name="gem_astro_website_name"
                                value="<?php echo esc_attr($website_name); ?>" class="ga-input">
                        </div>

                        <div class="ga-form-group">
                            <label for="ga_home_website_url">Website URL</label>
                            <input type="text" id="ga_home_website_url" name="gem_astro_website_url"
                                value="<?php echo esc_attr($website_url); ?>" class="ga-input ga-input-mono">
                        </div>

                        <div class="ga-form-group">
                            <label for="ga_home_phone">Phone</label>
                            <input type="text" id="ga_home_phone" name="gem_astro_contact_phone"
                                value="<?php echo esc_attr($contact_phone); ?>" class="ga-input ga-input-mono">
                        </div>

                        <div class="ga-form-group">
                            <label for="ga_home_email">Email</label>
                            <input type="text" id="ga_home_email" name="gem_astro_contact_email"
                                value="<?php echo esc_attr($contact_email); ?>" class="ga-input ga-input-mono">
                        </div>

                        <div class="ga-form-group" style="grid-column:1/-1;">
                            <label for="ga_home_cover_logo">Cover Logo (Top + Side)</label>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="text" id="ga_home_cover_logo" name="gem_astro_cover_logo"
                                    value="<?php echo esc_attr($cover_logo); ?>" class="ga-input ga-input-mono ga-logo-url"
                                    placeholder="https://example.com/logo.png" style="flex:1;min-width:280px;">
                                <button type="button" class="ga-btn ga-btn-ghost ga-logo-picker">Select Logo</button>
                            </div>
                        </div>

                        <div class="ga-form-group" style="grid-column:1/-1;">
                            <label for="ga_home_cover_welcome_text">Cover Welcome Text</label>
                            <textarea id="ga_home_cover_welcome_text" name="gem_astro_cover_welcome_text" rows="4"
                                class="ga-input"><?php echo esc_textarea($cover_welcome_text); ?></textarea>
                            <small class="ga-help">Yeh text cover page par "Welcome To Your GEM ASTROLOGY Report" ki jagah show
                                hoga.</small>
                        </div>
                    </div>

                    <button type="submit" class="ga-btn ga-btn-primary" style="margin-top:8px;">💾 Save Home Settings</button>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="ga-stats-grid" id="ga-stats-grid">
                <div class="ga-stat-card ga-stat-gold">
                    <div class="ga-stat-accent"></div>
                    <div class="ga-stat-body">
                        <div class="ga-stat-top">
                            <span class="ga-stat-label">Today's Bookings</span>
                            <span class="ga-stat-icon-wrap">📅</span>
                        </div>
                        <div class="ga-stat-value" id="ga-stat-today"><?php echo intval($stats['today']); ?></div>
                        <div class="ga-stat-trend ga-trend-<?php echo $trend_dir; ?>">
                            <?php echo $trend_dir === 'up' ? '▲' : '▼'; ?>         <?php echo $trend_diff; ?> vs yesterday
                        </div>
                    </div>
                </div>

                <div class="ga-stat-card ga-stat-purple">
                    <div class="ga-stat-accent"></div>
                    <div class="ga-stat-body">
                        <div class="ga-stat-top">
                            <span class="ga-stat-label">Total Bookings</span>
                            <span class="ga-stat-icon-wrap">📊</span>
                        </div>
                        <div class="ga-stat-value" id="ga-stat-total"><?php echo intval($stats['total']); ?></div>
                        <div class="ga-stat-trend ga-trend-neutral">All time</div>
                    </div>
                </div>

                <div class="ga-stat-card ga-stat-emerald">
                    <div class="ga-stat-accent"></div>
                    <div class="ga-stat-body">
                        <div class="ga-stat-top">
                            <span class="ga-stat-label">Total Revenue</span>
                            <span class="ga-stat-icon-wrap">💰</span>
                        </div>
                        <div class="ga-stat-value" id="ga-stat-revenue">₹<?php echo number_format($stats['revenue'], 0); ?>
                        </div>
                        <div class="ga-stat-trend ga-trend-neutral">Paid bookings</div>
                    </div>
                </div>

                <div class="ga-stat-card ga-stat-cyan">
                    <div class="ga-stat-accent"></div>
                    <div class="ga-stat-body">
                        <div class="ga-stat-top">
                            <span class="ga-stat-label">PDF Reports</span>
                            <span class="ga-stat-icon-wrap">📄</span>
                        </div>
                        <div class="ga-stat-value" id="ga-stat-pdf"><?php echo intval($stats['pdf_count']); ?></div>
                        <div class="ga-stat-trend ga-trend-neutral">Generated</div>
                    </div>
                </div>

                <div class="ga-stat-card ga-stat-rose">
                    <div class="ga-stat-accent"></div>
                    <div class="ga-stat-body">
                        <div class="ga-stat-top">
                            <span class="ga-stat-label">Consultations</span>
                            <span class="ga-stat-icon-wrap">🗓️</span>
                        </div>
                        <div class="ga-stat-value" id="ga-stat-consult"><?php echo intval($stats['consultation_count']); ?>
                        </div>
                        <div class="ga-stat-trend ga-trend-neutral">Booked</div>
                    </div>
                </div>

                <div class="ga-stat-card ga-stat-lime">
                    <div class="ga-stat-accent"></div>
                    <div class="ga-stat-body">
                        <div class="ga-stat-top">
                            <span class="ga-stat-label">Success Rate</span>
                            <span class="ga-stat-icon-wrap">✅</span>
                        </div>
                        <div class="ga-stat-value" id="ga-stat-rate"><?php echo $stats['success_rate']; ?>%</div>
                        <div class="ga-stat-trend ga-trend-neutral"><?php echo $stats['paid_count']; ?> paid</div>
                    </div>
                </div>
            </div>

            <!-- Analytics Row -->
            <div class="ga-analytics-row">
                <!-- Revenue Chart -->
                <div class="ga-card ga-chart-card">
                    <div class="ga-card-header">
                        <h3>📈 Revenue — Last 7 Days</h3>
                    </div>
                    <div class="ga-chart" id="ga-chart">
                        <?php foreach ($chart_data as $date => $amount): ?>
                            <div class="ga-chart-bar-wrap">
                                <div class="ga-chart-amount">₹<?php echo number_format($amount, 0); ?></div>
                                <div class="ga-chart-bar" style="height: <?php echo max(($amount / $max_revenue) * 100, 4); ?>%;">
                                </div>
                                <div class="ga-chart-label"><?php echo date('d M', strtotime($date)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Language Distribution -->
                <div class="ga-card ga-lang-card">
                    <div class="ga-card-header">
                        <h3>🌐 Language Distribution</h3>
                    </div>
                    <div class="ga-lang-list">
                        <?php foreach ($lang_labels as $code => $label):
                            $count = $lang_dist[$code] ?? 0;
                            $pct = $lang_total > 0 ? round(($count / $lang_total) * 100) : 0;
                            $color = $lang_colors[$code];
                            ?>
                            <div class="ga-lang-item">
                                <div class="ga-lang-info">
                                    <span class="ga-lang-dot" style="background:<?php echo $color; ?>;"></span>
                                    <span class="ga-lang-name"><?php echo $label; ?></span>
                                    <span class="ga-lang-count"><?php echo $count; ?></span>
                                </div>
                                <div class="ga-lang-bar-bg">
                                    <div class="ga-lang-bar-fill"
                                        style="width:<?php echo $pct; ?>%; background:<?php echo $color; ?>;"></div>
                                </div>
                                <span class="ga-lang-pct"><?php echo $pct; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Razorpay Status -->
                    <div class="ga-rzp-status">
                        <div class="ga-rzp-label">🔑 Razorpay</div>
                        <?php if ($rzp_mode === 'live'): ?>
                            <span class="ga-badge ga-badge-green">● Live</span>
                        <?php elseif ($rzp_mode === 'test'): ?>
                            <span class="ga-badge ga-badge-yellow">● Test Mode</span>
                        <?php else: ?>
                            <span class="ga-badge ga-badge-red">● Not Set</span>
                        <?php endif; ?>
                        <a href="<?php echo admin_url('admin.php?page=gem-astrology-settings'); ?>" class="ga-rzp-link">Manage
                            →</a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="ga-card ga-activity-card">
                    <div class="ga-card-header">
                        <h3>⚡ Recent Activity</h3>
                    </div>
                    <div class="ga-activity-list" id="ga-activity-list">
                        <?php if (empty($recent)): ?>
                            <div class="ga-activity-empty">No activity yet</div>
                        <?php else: ?>
                            <?php foreach ($recent as $r): ?>
                                <div class="ga-activity-item">
                                    <div class="ga-activity-avatar"><?php echo strtoupper(substr($r->name, 0, 1)); ?></div>
                                    <div class="ga-activity-info">
                                        <strong><?php echo esc_html($r->name); ?></strong>
                                        <span class="ga-activity-type">
                                            <?php echo $r->service_type === 'pdf' ? '📄 PDF Report' : '🗓️ Consultation'; ?>
                                        </span>
                                    </div>
                                    <div class="ga-activity-meta">
                                        <span
                                            class="ga-badge <?php echo $r->payment_status === 'paid' ? 'ga-badge-green' : 'ga-badge-red'; ?>">
                                            <?php echo $r->payment_status === 'paid' ? '✓ Paid' : '✗ Failed'; ?>
                                        </span>
                                        <small>₹<?php echo number_format($r->amount, 0); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Filters & Export -->
            <div class="ga-card ga-filters-card">
                <form method="get" action="" class="ga-filter-form">
                    <input type="hidden" name="page" value="gem-astrology">
                    <div class="ga-filter-row">
                        <div class="ga-filter-group">
                            <label>📅 Filter by Date</label>
                            <input type="date" name="filter_date" value="<?php echo esc_attr($filters['date']); ?>"
                                class="ga-input">
                        </div>
                        <div class="ga-filter-group">
                            <label>🔍 Search</label>
                            <input type="text" name="s" value="<?php echo esc_attr($filters['search']); ?>"
                                placeholder="Name, email, phone, payment ID..." class="ga-input">
                        </div>
                        <div class="ga-filter-actions">
                            <button type="submit" class="ga-btn ga-btn-primary">Apply Filters</button>
                            <a href="<?php echo admin_url('admin.php?page=gem-astrology'); ?>"
                                class="ga-btn ga-btn-ghost">Reset</a>
                            <a href="<?php echo admin_url('admin-ajax.php?action=gem_astro_export_csv'); ?>"
                                class="ga-btn ga-btn-outline" download>⬇ Export CSV</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bookings Table -->
            <div class="ga-card ga-table-card">
                <div class="ga-card-header" style="flex-wrap:wrap;gap:15px;">
                    <h3>📋 Bookings</h3>
                    
                    <div class="ga-table-filters" style="display:flex;gap:10px;flex:1;justify-content:flex-end;">
                        <select id="ga-bookings-range" class="ga-select ga-select-small" onchange="filterBookings()" style="background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.1);border-radius:4px;padding:4px 8px;">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="7d">Last 7 Days</option>
                            <option value="1m">Last 1 Month</option>
                            <option value="1y">Last 1 Year</option>
                        </select>
                        <select id="ga-bookings-sort" class="ga-select ga-select-small" onchange="filterBookings()" style="background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.1);border-radius:4px;padding:4px 8px;">
                            <option value="date">Newest First</option>
                            <option value="date_asc">Oldest First</option>
                            <option value="amount_high">Amount (High -> Low)</option>
                            <option value="amount_low">Amount (Low -> High)</option>
                            <option value="name">Name (A-Z)</option>
                        </select>
                        <input type="text" id="ga-bookings-search" placeholder="Search..." class="ga-input ga-input-small" oninput="filterBookings()" style="width:150px;padding:4px 8px;font-size:13px;">
                    </div>
                </div>
                <div class="ga-table-wrap">
                    <table class="ga-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Language</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="8" class="ga-empty-state">
                                        <div class="ga-empty-icon">📭</div>
                                        <div class="ga-empty-text">No bookings found</div>
                                        <div class="ga-empty-sub">Bookings will appear here once customers start ordering reports
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $i => $b): ?>
                                    <tr class="ga-row-animate" style="animation-delay: <?php echo $i * 0.03; ?>s;">
                                        <td><span class="ga-id-badge">#<?php echo intval($b->id); ?></span></td>
                                        <td>
                                            <div class="ga-client-cell">
                                                <div class="ga-client-avatar"><?php echo strtoupper(substr($b->name, 0, 1)); ?></div>
                                                <div>
                                                    <strong><?php echo esc_html($b->name); ?></strong>
                                                    <small><?php echo esc_html($b->email); ?></small>
                                                    <small>📱 <?php echo esc_html($b->phone); ?> · 🎂
                                                        <?php echo esc_html($b->dob); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                class="ga-badge <?php echo $b->service_type === 'pdf' ? 'ga-badge-blue' : 'ga-badge-purple'; ?>">
                                                <?php echo $b->service_type === 'pdf' ? '📄 PDF' : '🗓️ Consult'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $ll = ['hi' => '🇮🇳 HI', 'en' => '🇬🇧 EN', 'gu' => '🇮🇳 GU'];
                                            ?>
                                            <span
                                                class="ga-badge ga-badge-lang"><?php echo $ll[$b->language] ?? strtoupper($b->language); ?></span>
                                        </td>
                                        <td>
                                            <span
                                                class="ga-badge <?php echo $b->payment_status === 'paid' ? 'ga-badge-green' : 'ga-badge-red'; ?>">
                                                <?php echo $b->payment_status === 'paid' ? '✓ Paid' : '✗ ' . ucfirst($b->payment_status); ?>
                                            </span>
                                            <div class="ga-payment-id"><?php echo esc_html($b->payment_id); ?></div>
                                        </td>
                                        <td><span class="ga-amount">₹<?php echo number_format($b->amount, 0); ?></span></td>
                                        <td><span class="ga-date"><?php echo esc_html($b->created_at); ?></span></td>
                                        <td>
                                            <div class="ga-actions">
                                                <button class="ga-btn-icon ga-view-btn" data-id="<?php echo $b->id; ?>"
                                                    title="View Details">👁️</button>
                                                <button class="ga-btn-icon ga-delete-btn" data-id="<?php echo $b->id; ?>"
                                                    title="Delete">🗑️</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Booking Details Modal -->
        <div id="ga-booking-modal" class="ga-modal">
            <div class="ga-modal-content">
                <span class="ga-close-modal">&times;</span>
                <div class="ga-modal-header">
                    <h2>Booking Details <span id="ga-modal-id"></span></h2>
                </div>
                <div class="ga-modal-body" id="ga-modal-body">
                    <!-- Content populated by JS -->
                    <div class="ga-loader">Loading...</div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // View Booking
                $('.ga-view-btn').on('click', function (e) {
                    e.preventDefault();
                    var id = $(this).data('id');
                    $('#ga-booking-modal').css('display', 'flex');
                    $('#ga-modal-id').text('#' + id);
                    $('#ga-modal-body').html('<div class="ga-loader">Loading...</div>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gem_astro_get_booking_details',
                            id: id
                        },
                        success: function (response) {
                            if (response.success) {
                                var b = response.data;
                                var html = `
                                <div class="ga-detail-grid">
                                    <div class="ga-detail-item"><strong>Name:</strong> ${b.name}</div>
                                    <div class="ga-detail-item"><strong>Email:</strong> ${b.email}</div>
                                    <div class="ga-detail-item"><strong>Phone:</strong> ${b.phone}</div>
                                    <div class="ga-detail-item"><strong>DOB:</strong> ${b.dob}</div>
                                    <div class="ga-detail-item"><strong>Time of Birth:</strong> ${b.time || 'N/A'}</div>
                                    <div class="ga-detail-item"><strong>Place of Birth:</strong> ${b.place || 'N/A'}</div>
                                    <div class="ga-detail-item"><strong>Service:</strong> ${b.service_type}</div>
                                    <div class="ga-detail-item"><strong>Language:</strong> ${b.language}</div>
                                    <div class="ga-detail-item"><strong>Payment ID:</strong> ${b.payment_id}</div>
                                    <div class="ga-detail-item"><strong>Amount:</strong> ₹${b.amount}</div>
                                    <div class="ga-detail-item"><strong>Status:</strong> ${b.payment_status}</div>
                                    <div class="ga-detail-item"><strong>Date:</strong> ${b.created_at}</div>
                                </div>
                                <div class="ga-detail-notes">
                                    <strong>Notes:</strong><br>
                                    <p>${b.notes || 'No notes provided.'}</p>
                                </div>
                            `;
                                $('#ga-modal-body').html(html);
                            } else {
                                $('#ga-modal-body').html('<p class="ga-error">Failed to load details.</p>');
                            }
                        },
                        error: function () {
                            $('#ga-modal-body').html('<p class="ga-error">Server error.</p>');
                        }
                    });
                });

                // Close Modal
                $('.ga-close-modal, .ga-modal').on('click', function (e) {
                    if (e.target === this) {
                        $('#ga-booking-modal').hide();
                    }
                });

                // Delete Booking
                $('.ga-delete-btn').on('click', function (e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to delete this booking? This cannot be undone.')) {
                        return;
                    }

                    var btn = $(this);
                    var row = btn.closest('tr');
                    var id = btn.data('id');

                    btn.prop('disabled', true).text('...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gem_astro_delete_booking',
                            id: id
                        },
                        success: function (response) {
                            if (response.success) {
                                row.fadeOut(300, function () {
                                    $(this).remove();
                                });
                            } else {
                                alert(response.data.message || 'Failed to delete');
                                btn.prop('disabled', false).text('🗑️');
                            }
                        },
                        error: function () {
                            alert('Server error.');
                            btn.prop('disabled', false).text('🗑️');
                        }
                    });
                });
            });
        </script>

        <!-- Live Stats JS -->
        <script>
            (function () {
                var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
                window.refreshStats = function () {
                    var range = document.getElementById('ga-stats-range').value;
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            try {
                                var r = JSON.parse(xhr.responseText);
                                if (r.success && r.data) {
                                    var s = r.data.stats;
                                    var el = function (id) { return document.getElementById(id); };
                                    if (el('ga-stat-today')) el('ga-stat-today').textContent = s.today;
                                    if (el('ga-stat-total')) el('ga-stat-total').textContent = s.total;
                                    if (el('ga-stat-revenue')) el('ga-stat-revenue').textContent = '₹' + Number(s.revenue).toLocaleString('en-IN');
                                    if (el('ga-stat-pdf')) el('ga-stat-pdf').textContent = s.pdf_count;
                                    if (el('ga-stat-consult')) el('ga-stat-consult').textContent = s.consultation_count;
                                    if (el('ga-stat-rate')) el('ga-stat-rate').textContent = s.success_rate + '%';
                                    if (el('ga-last-updated')) el('ga-last-updated').textContent = 'Updated: ' + r.data.time;
                                    // Pulse the live badge
                                    var badge = el('ga-live-badge');
                                    if (badge) { badge.classList.remove('ga-pulse'); void badge.offsetWidth; badge.classList.add('ga-pulse'); }
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send('action=gem_astro_live_stats&range=' + range);
                }

                // Initial Load
                refreshStats();
                setInterval(refreshStats, 30000);

                // Bookings Filter
                window.filterBookings = function () {
                    var range = document.getElementById('ga-bookings-range').value;
                    var sort = document.getElementById('ga-bookings-sort').value;
                    var search = document.getElementById('ga-bookings-search').value;

                    var tbody = document.getElementById('ga-bookings-body');
                    tbody.style.opacity = '0.5';

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            try {
                                var r = JSON.parse(xhr.responseText);
                                if (r.success) {
                                    tbody.innerHTML = r.data.html;
                                }
                            } catch (e) { }
                        }
                        tbody.style.opacity = '1';
                    };
                    xhr.send('action=gem_astro_filter_bookings&range=' + range + '&sort_by=' + sort + '&search=' + search);
                };
            })();
        </script>
        <?php echo $this->get_logo_picker_script(); ?>
    <?php
    }

    /**
     * =============================================
     * PREMIUM SETTINGS PAGE
     * =============================================
     */
    public function render_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Output Styles Directly
        echo '<style>' . $this->get_admin_css() . '</style>';

        $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
        $key = get_option('gem_astro_razorpay_key', '');
        $is_live = !empty($key) && strpos($key, 'rzp_test_') !== 0;
        $is_test = !empty($key) && strpos($key, 'rzp_test_') === 0;
        ?>
        <div class="ga-wrap">
            <div class="ga-header">
                <div class="ga-header-left">
                    <div class="ga-logo-icon">⚙</div>
                    <div>
                        <h1 class="ga-title">Settings</h1>
                        <span class="ga-subtitle">AstroReport Pro Configuration</span>
                    </div>
                </div>
                <div class="ga-header-right">
                    <a href="<?php echo admin_url('admin.php?page=gem-astrology'); ?>" class="ga-btn ga-btn-ghost">←
                        Dashboard</a>
                </div>
            </div>

            <?php if ($saved): ?>
                <div class="ga-notice ga-notice-success">✅ Settings saved successfully!</div>
            <?php endif; ?>

            <div class="ga-card">
                <div class="ga-card-header">
                    <h3>🔑 Razorpay Payment Gateway</h3>
                    <?php if ($is_live): ?>
                        <span class="ga-badge ga-badge-green">● Live Mode</span>
                    <?php elseif ($is_test): ?>
                        <span class="ga-badge ga-badge-yellow">● Test Mode</span>
                    <?php else: ?>
                        <span class="ga-badge ga-badge-red">● Not Configured</span>
                    <?php endif; ?>
                </div>
                <p class="ga-desc">Enter your Razorpay credentials from <a href="https://dashboard.razorpay.com/app/keys"
                        target="_blank" class="ga-link">Razorpay Dashboard → API Keys</a></p>

                <form method="post" action="options.php">
                    <?php settings_fields('gem_astro_settings'); ?>

                    <div class="ga-form-group">
                        <label for="gem_astro_razorpay_key">Key ID</label>
                        <input type="text" id="gem_astro_razorpay_key" name="gem_astro_razorpay_key"
                            value="<?php echo esc_attr($key); ?>" placeholder="rzp_live_xxxxxxxxxxxxxx"
                            class="ga-input ga-input-mono">
                        <small class="ga-help">Starts with <code>rzp_live_</code> (live) or <code>rzp_test_</code>
                            (test)</small>
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_razorpay_secret">Secret Key</label>
                        <input type="password" id="gem_astro_razorpay_secret" name="gem_astro_razorpay_secret"
                            value="<?php echo esc_attr(get_option('gem_astro_razorpay_secret', '')); ?>"
                            placeholder="Enter Secret Key..." class="ga-input ga-input-mono">
                        <small class="ga-help">⚠️ Never share this key publicly</small>
                    </div>

                    <div class="ga-form-group">
                        <label>Services & Pricing</label>
                        <div class="ga-services-wrap">
                            <table class="ga-services-table" id="ga-services-table">
                                <thead>
                                    <tr>
                                        <th>Service Name</th>
                                        <th>Price (₹)</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="ga-services-list">
                                    <!-- Rows injected by JS -->
                                </tbody>
                            </table>
                            <button type="button" class="ga-btn ga-btn-small" id="ga-add-service">+ Add Service</button>
                            <input type="hidden" name="gem_astro_services" id="gem_astro_services"
                                value="<?php echo esc_attr(get_option('gem_astro_services', '[]')); ?>">

                            <!-- Migration fallback: If services are empty, we can use this logic in JS to seed from pdf_price -->
                            <input type="hidden" id="old_pdf_price"
                                value="<?php echo esc_attr(get_option('gem_astro_pdf_price', '1')); ?>">
                        </div>
                        <small class="ga-help">Define your services here. These will appear in the booking form
                            dropdown.</small>
                    </div>

                    <style>
                        .ga-services-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 10px;
                        }

                        .ga-services-table th {
                            text-align: left;
                            padding: 8px;
                            color: #aaa;
                            font-size: 12px;
                            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                        }

                        .ga-services-table td {
                            padding: 8px;
                            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                        }

                        .ga-service-input {
                            width: 100%;
                            background: rgba(0, 0, 0, 0.2);
                            border: 1px solid rgba(255, 255, 255, 0.1);
                            padding: 8px;
                            border-radius: 4px;
                            color: #fff;
                        }

                        .ga-service-select {
                            width: 100%;
                            background: rgba(0, 0, 0, 0.2);
                            border: 1px solid rgba(255, 255, 255, 0.1);
                            padding: 8px;
                            border-radius: 4px;
                            color: #fff;
                        }

                        .ga-btn-small {
                            padding: 6px 12px;
                            font-size: 13px;
                            background: rgba(255, 255, 255, 0.1);
                            color: #fff;
                            border: none;
                            cursor: pointer;
                            border-radius: 4px;
                        }

                        .ga-btn-small:hover {
                            background: rgba(255, 255, 255, 0.2);
                        }

                        .ga-btn-danger {
                            color: #ff6b6b;
                            background: transparent;
                        }

                        .ga-btn-danger:hover {
                            color: #ff4c4c;
                            background: rgba(255, 0, 0, 0.1);
                        }
                    </style>

                    <script>
                        (function () {
                            const raw = document.getElementById('gem_astro_services').value;
                            let services = [];
                            try {
                                services = JSON.parse(raw);
                            } catch (e) { }

                            // Fallback/Migration: If no services, add default PDF service
                            if (!Array.isArray(services) || services.length === 0) {
                                const oldPrice = document.getElementById('old_pdf_price').value || '1';
                                services = [
                                    { name: 'Kundali Report (PDF)', price: oldPrice, type: 'pdf' }
                                ];
                            }

                            const tbody = document.getElementById('ga-services-list');
                            const hiddenInput = document.getElementById('gem_astro_services');

                            function render() {
                                tbody.innerHTML = '';
                                services.forEach((s, idx) => {
                                    const tr = document.createElement('tr');
                                    tr.innerHTML = `
                                        <td><input type="text" class="ga-service-input" value="${s.name}" oninput="updateService(${idx}, 'name', this.value)"></td>
                                        <td><input type="number" class="ga-service-input" value="${s.price}" oninput="updateService(${idx}, 'price', this.value)"></td>
                                        <td>
                                            <select class="ga-service-select" onchange="updateService(${idx}, 'type', this.value)">
                                                <option value="pdf" ${s.type === 'pdf' ? 'selected' : ''}>PDF Report</option>
                                                <option value="consult" ${s.type === 'consult' ? 'selected' : ''}>Consultation</option>
                                            </select>
                                        </td>
                                        <td><button type="button" class="ga-btn ga-btn-small ga-btn-danger" onclick="removeService(${idx})">×</button></td>
                                    `;
                                    tbody.appendChild(tr);
                                });
                                hiddenInput.value = JSON.stringify(services);
                            }

                            window.updateService = function (idx, key, val) {
                                services[idx][key] = val;
                                hiddenInput.value = JSON.stringify(services);
                            };

                            window.removeService = function (idx) {
                                if (confirm('Remove this service?')) {
                                    services.splice(idx, 1);
                                    render();
                                }
                            };

                            document.getElementById('ga-add-service').onclick = function () {
                                services.push({ name: 'New Service', price: '100', type: 'pdf' });
                                render();
                            };

                            render();
                        })();
                    </script>


                    <hr style="border-color:rgba(255,255,255,0.08);margin:18px 0;">
                    <h4 style="margin:0 0 12px 0;color:#fff;">🪪 PDF Brand & Contact (Cover + Last Page)</h4>

                    <div class="ga-form-group">
                        <label for="gem_astro_brand_title">Brand Title</label>
                        <input type="text" id="gem_astro_brand_title" name="gem_astro_brand_title"
                            value="<?php echo esc_attr(get_option('gem_astro_brand_title', 'Trikrypta')); ?>"
                            placeholder="Trikrypta" class="ga-input">
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_brand_tagline">Tagline</label>
                        <input type="text" id="gem_astro_brand_tagline" name="gem_astro_brand_tagline"
                            value="<?php echo esc_attr(get_option('gem_astro_brand_tagline', 'Let\'s bring you the new life')); ?>"
                            placeholder="Let's bring you the new life" class="ga-input">
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_website_name">Website Name</label>
                        <input type="text" id="gem_astro_website_name" name="gem_astro_website_name"
                            value="<?php echo esc_attr(get_option('gem_astro_website_name', 'Trikrypta')); ?>"
                            placeholder="Trikrypta" class="ga-input">
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_website_url">Website URL</label>
                        <input type="text" id="gem_astro_website_url" name="gem_astro_website_url"
                            value="<?php echo esc_attr(get_option('gem_astro_website_url', 'https://abhimanyu-raj-cse.vercel.app/')); ?>"
                            placeholder="https://example.com" class="ga-input ga-input-mono">
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_contact_phone">Phone</label>
                        <input type="text" id="gem_astro_contact_phone" name="gem_astro_contact_phone"
                            value="<?php echo esc_attr(get_option('gem_astro_contact_phone', '+91 9801834437')); ?>"
                            placeholder="+91 9801834437" class="ga-input ga-input-mono">
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_contact_email">Email</label>
                        <input type="text" id="gem_astro_contact_email" name="gem_astro_contact_email"
                            value="<?php echo esc_attr(get_option('gem_astro_contact_email', 'novanexusltd001@gmail.com')); ?>"
                            placeholder="support@example.com" class="ga-input ga-input-mono">
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_cover_logo">Cover Logo (Top + Side)</label>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <input type="text" id="gem_astro_cover_logo" name="gem_astro_cover_logo"
                                value="<?php echo esc_attr(get_option('gem_astro_cover_logo', '')); ?>"
                                placeholder="https://example.com/logo.png" class="ga-input ga-input-mono ga-logo-url"
                                style="flex:1;min-width:280px;">
                            <button type="button" class="ga-btn ga-btn-ghost ga-logo-picker">Select Logo</button>
                        </div>
                        <?php $logo_url = get_option('gem_astro_cover_logo', ''); ?>
                        <div id="ga-logo-preview"
                            style="margin-top:10px;<?php echo empty($logo_url) ? 'display:none;' : ''; ?>">
                            <img id="ga-logo-preview-img" src="<?php echo esc_attr($logo_url); ?>"
                                style="max-width:120px;max-height:80px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.05);padding:4px;">
                        </div>
                        <small class="ga-help">Recommended: 200×200px PNG with transparent background</small>
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_cover_welcome_text">Cover Welcome Text</label>
                        <textarea id="gem_astro_cover_welcome_text" name="gem_astro_cover_welcome_text" rows="4"
                            class="ga-input"><?php echo esc_textarea(get_option('gem_astro_cover_welcome_text', "Welcome To\nYour GEM\nASTROLOGY\nReport")); ?></textarea>
                        <small class="ga-help">Yeh text cover page par "Welcome To Your GEM ASTROLOGY Report" ki jagah show
                            hoga.</small>
                    </div>

                    <hr style="border-color:rgba(255,255,255,0.08);margin:18px 0;">
                    <h4 style="margin:0 0 12px 0;color:#fff;">📧 Email Template (Report Delivery)</h4>

                    <div class="ga-form-group">
                        <label for="gem_astro_email_subject">Email Subject</label>
                        <input type="text" id="gem_astro_email_subject" name="gem_astro_email_subject"
                            value="<?php echo esc_attr(get_option('gem_astro_email_subject', '🌟 Your Personalized GEM Astrology Report')); ?>"
                            placeholder="🌟 Your Personalized GEM Astrology Report" class="ga-input">
                        <small class="ga-help">Use <code>{name}</code> to insert the customer name</small>
                    </div>

                    <div class="ga-form-group">
                        <label for="gem_astro_email_body">Email Body (HTML)</label>
                        <textarea id="gem_astro_email_body" name="gem_astro_email_body" rows="6" class="ga-input"
                            style="font-family:monospace;font-size:13px;"><?php echo esc_textarea(get_option('gem_astro_email_body', '<h1>Namaste {name},</h1><p>Thank you for choosing Nion Gem Astro. Your personalized astrology report is attached in your selected language.</p><p><strong>Note:</strong> Save these files for future reference.</p><p>Regards,<br>Team Nion Gem Astro</p>')); ?></textarea>
                        <small class="ga-help">HTML allowed. Use <code>{name}</code> to insert the customer name.</small>
                    </div>

                    <button type="submit" class="ga-btn ga-btn-primary ga-btn-lg">💾 Save Settings</button>
                </form>
            </div>

            <!-- Engine Info -->
            <div class="ga-card">
                <div class="ga-card-header">
                    <h3>ℹ️ Engine Information</h3>
                </div>
                <table class="ga-info-table">
                    <tr>
                        <td>Product</td>
                        <td><strong>AstroReport Pro</strong></td>
                    </tr>
                    <tr>
                        <td>Version</td>
                        <td><?php echo GEM_ASTRO_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td>Developer</td>
                        <td>Trikrypta</td>
                    </tr>
                    <tr>
                        <td>Website</td>
                        <td><a href="https://www.niongemastro.com/" target="_blank" class="ga-link">niongemastro.com</a></td>
                    </tr>
                    <tr>
                        <td>Shortcode</td>
                        <td>
                            <code class="ga-code-copy"
                                onclick="navigator.clipboard.writeText('[astro_report]');this.textContent='Copied!';var t=this;setTimeout(function(){t.textContent='[astro_report]'},1500);">[astro_report]</code>
                            <small style="color:rgba(255,255,255,0.4);margin-left:8px;">Click to copy</small>
                        </td>
                    </tr>
                    <tr>
                        <td>PDF Engine</td>
                        <td>TCPDF + Digital Numerology Core</td>
                    </tr>
                    <tr>
                        <td>Languages</td>
                        <td>Hindi · English · Gujarati</td>
                    </tr>
                </table>
            </div>

            <!-- How to Use -->
            <div class="ga-card">
                <div class="ga-card-header">
                    <h3>📖 Quick Start Guide</h3>
                </div>
                <div class="ga-steps">
                    <div class="ga-step"><span class="ga-step-num">1</span><span>Set your Razorpay Key ID and Secret
                            above</span></div>
                    <div class="ga-step"><span class="ga-step-num">2</span><span>Add shortcode <code>[astro_report]</code> to
                            any page</span></div>
                    <div class="ga-step"><span class="ga-step-num">3</span><span>Users select language, fill form, and pay via
                            Razorpay</span></div>
                    <div class="ga-step"><span class="ga-step-num">4</span><span>PDF downloads instantly + 3-language email
                            sent</span></div>
                    <div class="ga-step"><span class="ga-step-num">5</span><span>Track all bookings from the <a
                                href="<?php echo admin_url('admin.php?page=gem-astrology'); ?>"
                                class="ga-link">Dashboard</a></span></div>
                </div>
            </div>
        </div>
        <?php echo $this->get_logo_picker_script(); ?>
    <?php
    }

    private function get_logo_picker_script()
    {
        return '<script>
            jQuery(document).ready(function($){
                var file_frame;

                $(".ga-logo-picker").on("click", function(event){
                    event.preventDefault();
                    var button = $(this);
                    var wrapper = button.closest("div");
                    var input_field = wrapper.find(".ga-logo-url");

                    // If the media frame already exists, reopen it.
                    if ( file_frame ) {
                        // Open frame
                        file_frame.open();
                        return;
                    }

                    // Create the media frame.
                    file_frame = wp.media.frames.file_frame = wp.media({
                        title: "Select PDF Cover Logo",
                        button: {
                            text: "Use this logo"
                        },
                        multiple: false  // Set to true to allow multiple files to be selected
                    });

                    // When an image is selected, run a callback.
                    file_frame.on( "select", function() {
                        // We set multiple to false so only get one image from the uploader
                        attachment = file_frame.state().get("selection").first().toJSON();

                        // Do something with attachment.id and/or attachment.url here
                        if (attachment && attachment.url) {
                            input_field.val(attachment.url);
                            input_field.trigger("change");
                            // Update logo preview
                            $("#ga-logo-preview-img").attr("src", attachment.url);
                            $("#ga-logo-preview").show();
                        }
                    });

                    // Finally, open the modal
                    file_frame.open();
                });
            });
        </script>';
    }

    /**
     * PREMIUM CSS — Dark Glassmorphism Theme
     */
    private function get_admin_css()
    {
        return '
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap");

        /* Reset WP admin padding for our pages */
        .ga-wrap {
            max-width: 1400px;
            margin: -8px -20px 0 -2px;
            padding: 24px;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(160deg, #0a0a1a 0%, #1a1033 40%, #0d1526 100%);
            min-height: 100vh;
            color: #e2e8f0;
        }
        .ga-wrap *, .ga-wrap *::before, .ga-wrap *::after {
            box-sizing: border-box;
        }

        /* ====== HEADER ====== */
        .ga-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            margin-bottom: 24px;
        }
        .ga-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .ga-logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #F5A623, #ff6b35);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
            box-shadow: 0 4px 20px rgba(245,166,35,0.3);
        }
        .ga-title {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin: 0;
            padding: 0;
            letter-spacing: -0.02em;
        }
        .ga-subtitle {
            font-size: 12px;
            color: rgba(255,255,255,0.45);
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .ga-header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .ga-version {
            background: rgba(255,255,255,0.08);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
        }
        .ga-updated {
            font-size: 11px;
            color: rgba(255,255,255,0.35);
            font-weight: 500;
        }

        /* Live Badge */
        .ga-live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16,185,129,0.12);
            color: #10B981;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .ga-live-dot {
            width: 7px;
            height: 7px;
            background: #10B981;
            border-radius: 50%;
            animation: ga-blink 1.5s ease-in-out infinite;
            box-shadow: 0 0 8px #10B981;
        }
        @keyframes ga-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .ga-pulse {
            animation: ga-pulse-anim 0.4s ease;
        }
        @keyframes ga-pulse-anim {
            0% { transform: scale(1); }
            50% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }

        /* ====== RAZORPAY BANNER (Prominent) ====== */
        .ga-config-banner {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px 30px;
            border-radius: 18px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .ga-banner-red {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            border-color: #ef4444;
        }
        .ga-banner-orange {
            background: linear-gradient(135deg, #7c2d12 0%, #9a3412 100%);
            border-color: #f97316;
        }
        .ga-banner-green {
            background: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
            border-color: #10b981;
        }
        
        .ga-banner-icon {
            font-size: 42px;
            background: rgba(255,255,255,0.1);
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .ga-banner-content { flex: 1; }
        .ga-banner-content h2 {
            margin: 0 0 6px 0;
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .ga-banner-content p {
            margin: 0;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            font-weight: 500;
        }
        
        .ga-banner-btn {
            padding: 12px 28px;
            font-size: 14px;
            background: #fff;
            color: #1a1033;
            box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        }
        .ga-banner-btn:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            color: #1a1033;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        .ga-banner-red .ga-banner-btn { color: #b91c1c; }
        .ga-banner-orange .ga-banner-btn { color: #c2410c; }
        .ga-banner-green .ga-banner-btn { color: #047857; }

        /* ====== STAT CARDS ====== */
        .ga-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .ga-stat-card {
            position: relative;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 18px;
            overflow: hidden;
            backdrop-filter: blur(12px);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .ga-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        .ga-stat-accent {
            height: 3px;
            width: 100%;
        }
        .ga-stat-gold .ga-stat-accent { background: linear-gradient(90deg, #F5A623, #ff6b35); }
        .ga-stat-purple .ga-stat-accent { background: linear-gradient(90deg, #8B5CF6, #a78bfa); }
        .ga-stat-emerald .ga-stat-accent { background: linear-gradient(90deg, #10B981, #34d399); }
        .ga-stat-cyan .ga-stat-accent { background: linear-gradient(90deg, #06B6D4, #22d3ee); }
        .ga-stat-rose .ga-stat-accent { background: linear-gradient(90deg, #F43F5E, #fb7185); }
        .ga-stat-lime .ga-stat-accent { background: linear-gradient(90deg, #84cc16, #a3e635); }

        .ga-stat-gold:hover { box-shadow: 0 12px 40px rgba(245,166,35,0.15); }
        .ga-stat-purple:hover { box-shadow: 0 12px 40px rgba(139,92,246,0.15); }
        .ga-stat-emerald:hover { box-shadow: 0 12px 40px rgba(16,185,129,0.15); }
        .ga-stat-cyan:hover { box-shadow: 0 12px 40px rgba(6,182,212,0.15); }
        .ga-stat-rose:hover { box-shadow: 0 12px 40px rgba(244,63,94,0.15); }
        .ga-stat-lime:hover { box-shadow: 0 12px 40px rgba(132,204,22,0.15); }

        .ga-stat-body { padding: 18px 20px; }
        .ga-stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .ga-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .ga-stat-icon-wrap { font-size: 20px; opacity: 0.7; }
        .ga-stat-value {
            font-size: 30px;
            font-weight: 900;
            color: #fff;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .ga-stat-trend {
            font-size: 11px;
            font-weight: 600;
        }
        .ga-trend-up { color: #10B981; }
        .ga-trend-down { color: #F43F5E; }
        .ga-trend-neutral { color: rgba(255,255,255,0.35); }

        /* ====== CARDS (generic) ====== */
        .ga-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 18px;
            padding: 24px;
            backdrop-filter: blur(12px);
            margin-bottom: 20px;
        }
        .ga-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .ga-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }

        /* ====== ANALYTICS ROW ====== */
        .ga-analytics-row {
            display: grid;
            grid-template-columns: 1.4fr 0.8fr 0.8fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Chart */
        .ga-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 180px;
            padding-top: 10px;
        }
        .ga-chart-bar-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }
        .ga-chart-amount {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            font-weight: 600;
            margin-bottom: 6px;
        }
        .ga-chart-bar {
            width: 100%;
            max-width: 50px;
            background: linear-gradient(180deg, #F5A623, #ff6b35);
            border-radius: 8px 8px 4px 4px;
            min-height: 4px;
            transition: height 0.6s ease;
            box-shadow: 0 0 16px rgba(245,166,35,0.2);
        }
        .ga-chart-label {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            font-weight: 600;
            margin-top: 8px;
        }

        /* Language Dist */
        .ga-lang-list { display: flex; flex-direction: column; gap: 14px; }
        .ga-lang-item { display: flex; align-items: center; gap: 10px; }
        .ga-lang-info { display: flex; align-items: center; gap: 8px; min-width: 100px; }
        .ga-lang-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .ga-lang-name { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.8); }
        .ga-lang-count { font-size: 11px; color: rgba(255,255,255,0.35); font-weight: 600; }
        .ga-lang-bar-bg {
            flex: 1;
            height: 8px;
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            overflow: hidden;
        }
        .ga-lang-bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.8s ease;
        }
        .ga-lang-pct { font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.5); min-width: 36px; text-align: right; }

        /* Razorpay Status */
        .ga-rzp-status {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ga-rzp-label { font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.6); }
        .ga-rzp-link {
            margin-left: auto;
            font-size: 12px;
            color: #F5A623;
            text-decoration: none;
            font-weight: 600;
        }
        .ga-rzp-link:hover { color: #ff6b35; }

        /* Activity Feed */
        .ga-activity-list { display: flex; flex-direction: column; gap: 10px; }
        .ga-activity-empty { text-align: center; color: rgba(255,255,255,0.3); padding: 30px; font-size: 13px; }
        .ga-activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            transition: background 0.2s;
        }
        .ga-activity-item:hover { background: rgba(255,255,255,0.05); }
        .ga-activity-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #8B5CF6, #06B6D4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            color: #fff;
            flex-shrink: 0;
        }
        .ga-activity-info { flex: 1; }
        .ga-activity-info strong { display: block; font-size: 13px; color: #fff; font-weight: 600; }
        .ga-activity-type { font-size: 11px; color: rgba(255,255,255,0.4); }
        .ga-activity-meta { text-align: right; }
        .ga-activity-meta small { display: block; font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 2px; }

        /* ====== BADGES ====== */
        .ga-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }
        .ga-badge-green { background: rgba(16,185,129,0.12); color: #34d399; }
        .ga-badge-red { background: rgba(244,63,94,0.12); color: #fb7185; }
        .ga-badge-yellow { background: rgba(245,166,35,0.12); color: #F5A623; }
        .ga-badge-blue { background: rgba(59,130,246,0.12); color: #60a5fa; }
        .ga-badge-purple { background: rgba(139,92,246,0.12); color: #a78bfa; }
        .ga-badge-lang { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.7); }

        /* ====== FILTERS ====== */
        .ga-filters-card { margin-bottom: 20px; padding: 20px 24px; }
        .ga-filter-form { width: 100%; }
        .ga-filter-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .ga-filter-group { flex: 1; min-width: 200px; }
        .ga-filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255,255,255,0.5);
            margin-bottom: 6px;
        }
        .ga-filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ====== INPUTS ====== */
        .ga-input {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #e2e8f0;
            font-size: 14px;
            font-family: "Inter", sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .ga-input:focus {
            outline: none;
            border-color: #F5A623;
            box-shadow: 0 0 0 3px rgba(245,166,35,0.15);
        }
        .ga-input::placeholder { color: rgba(255,255,255,0.25); }
        .ga-input-mono { font-family: "JetBrains Mono", "Fira Code", monospace; }

        /* ====== BUTTONS ====== */
        .ga-btn {
            padding: 10px 22px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            font-family: "Inter", sans-serif;
            white-space: nowrap;
        }
        .ga-btn-primary {
            background: linear-gradient(135deg, #F5A623, #ff6b35);
            color: #fff;
            box-shadow: 0 4px 16px rgba(245,166,35,0.25);
        }
        .ga-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(245,166,35,0.35);
            color: #fff;
        }
        .ga-btn-ghost {
            background: rgba(255,255,255,0.06);
            color: rgba(255,255,255,0.7);
        }
        .ga-btn-ghost:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .ga-btn-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.7);
        }
        .ga-btn-outline:hover {
            border-color: rgba(255,255,255,0.3);
            color: #fff;
        }
        .ga-btn-lg { padding: 14px 32px; font-size: 15px; margin-top: 10px; }

        /* ====== TABLE ====== */
        .ga-table-card { padding: 0; overflow: hidden; }
        .ga-table-card .ga-card-header { padding: 20px 24px; margin-bottom: 0; }
        .ga-table-count { font-size: 12px; color: rgba(255,255,255,0.35); font-weight: 600; }
        .ga-table-wrap { overflow-x: auto; }
        .ga-table { width: 100%; border-collapse: collapse; }
        .ga-table th {
            padding: 12px 18px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.02);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            white-space: nowrap;
        }
        .ga-table td {
            padding: 14px 18px;
            font-size: 13px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            vertical-align: middle;
        }
        .ga-table tr:hover td { background: rgba(255,255,255,0.02); }

        .ga-id-badge {
            background: rgba(255,255,255,0.06);
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            color: rgba(255,255,255,0.6);
        }
        .ga-client-cell { display: flex; align-items: center; gap: 12px; }
        .ga-actions {
            display: flex;
            gap: 6px;
        }
        .ga-btn-icon {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            padding: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .ga-btn-icon:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
            transform: scale(1.1);
        }
        .ga-btn-icon:disabled { opacity: 0.5; cursor: not-allowed; }
        .ga-view-btn:hover { border-color: #60a5fa; color: #60a5fa; }
        .ga-delete-btn:hover { border-color: #fb7185; color: #fb7185; }

        .ga-client-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #F5A623, #8B5CF6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 15px;
            color: #fff;
            flex-shrink: 0;
        }
        .ga-client-cell strong { display: block; color: #fff; font-size: 13px; }
        .ga-client-cell small { display: block; color: rgba(255,255,255,0.35); font-size: 11px; }
        .ga-payment-id { font-size: 10px; color: rgba(255,255,255,0.25); word-break: break-all; margin-top: 4px; }
        .ga-amount { font-weight: 800; color: #10B981; font-size: 14px; }
        .ga-date { font-size: 11px; color: rgba(255,255,255,0.4); }

        /* Empty State */
        .ga-empty-state { text-align: center; padding: 60px 20px !important; }
        .ga-empty-icon { font-size: 48px; margin-bottom: 12px; }
        .ga-empty-text { font-size: 16px; font-weight: 700; color: rgba(255,255,255,0.6); margin-bottom: 6px; }
        .ga-empty-sub { font-size: 13px; color: rgba(255,255,255,0.3); }

        /* Row Animation */
        .ga-row-animate {
            animation: ga-row-in 0.3s ease forwards;
            opacity: 0;
        }
        @keyframes ga-row-in {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ====== SETTINGS ====== */
        .ga-desc { color: rgba(255,255,255,0.5); font-size: 13px; margin-bottom: 24px; }
        .ga-link { color: #F5A623; text-decoration: none; font-weight: 600; }
        .ga-link:hover { color: #ff6b35; }
        .ga-form-group { margin-bottom: 22px; }
        .ga-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
        }
        .ga-form-group .ga-input { max-width: 520px; }
        .ga-help { display: block; margin-top: 6px; font-size: 11px; color: rgba(255,255,255,0.35); }
        .ga-help code {
            background: rgba(255,255,255,0.08);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            color: rgba(255,255,255,0.6);
        }

        .ga-notice {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .ga-notice-success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.2);
            color: #34d399;
        }

        /* Info Table */
        .ga-info-table { width: 100%; }
        .ga-info-table td {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 14px;
        }
        .ga-info-table td:first-child { color: rgba(255,255,255,0.4); width: 160px; font-weight: 600; }
        .ga-info-table a { color: #F5A623; text-decoration: none; }
        .ga-code-copy {
            background: rgba(255,255,255,0.08);
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-family: monospace;
            font-size: 14px;
            color: #F5A623;
            border: 1px solid rgba(245,166,35,0.2);
            transition: background 0.2s;
        }
        .ga-code-copy:hover { background: rgba(245,166,35,0.1); }

        /* Steps */
        .ga-steps { display: flex; flex-direction: column; gap: 12px; }
        .ga-step {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            font-size: 14px;
            color: rgba(255,255,255,0.75);
        }
        .ga-step code {
            background: rgba(245,166,35,0.1);
            padding: 3px 8px;
            border-radius: 5px;
            color: #F5A623;
            font-weight: 600;
            font-size: 13px;
        }
        .ga-step-num {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, #8B5CF6, #06B6D4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 13px;
            color: #fff;
            flex-shrink: 0;
        }

        /* ====== RESPONSIVE ====== */
        @media (max-width: 1200px) {
            .ga-analytics-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 782px) {
            .ga-wrap { padding: 12px; margin: -8px -12px 0 -2px; }
            .ga-stats-grid { grid-template-columns: 1fr 1fr; }
            .ga-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .ga-filter-row { flex-direction: column; }
            .ga-table th, .ga-table td { padding: 10px 12px; }
        }
        @media (max-width: 480px) {
            .ga-stats-grid { grid-template-columns: 1fr; }
        }
        /* Modal */
        .ga-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }
        .ga-modal-content {
            background: linear-gradient(160deg, #1a1033 0%, #0d1526 100%);
            margin: auto;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            position: relative;
        }
        .ga-close-modal {
            color: rgba(255,255,255,0.5);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 15px;
            transition: color 0.2s;
        }
        .ga-close-modal:hover { color: #fff; }
        .ga-modal-header h2 { margin: 0 0 20px 0; font-size: 20px; color: #fff; }
        .ga-modal-body { color: rgba(255,255,255,0.8); font-size: 14px; line-height: 1.6; }
        
        .ga-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 20px;
            margin-bottom: 20px;
        }
        .ga-detail-item strong { color: rgba(255,255,255,0.4); font-size: 12px; display: block; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
        .ga-detail-notes {
            background: rgba(255,255,255,0.04);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .ga-detail-notes p { margin: 5px 0 0 0; font-size: 13px; font-style: italic; color: rgba(255,255,255,0.6); }

        /* Loader */
        .ga-loader { text-align: center; padding: 20px; color: rgba(255,255,255,0.5); font-style: italic; }
        ';
    }
}
