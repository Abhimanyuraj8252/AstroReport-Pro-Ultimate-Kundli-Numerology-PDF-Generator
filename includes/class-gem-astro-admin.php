<?php
/**
 * AstroReport Pro Admin Dashboard
 * Settings, Bookings, Stats
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
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
    }

    /**
     * Register admin menu pages
     */
    public function register_menus()
    {
        // Main menu
        add_menu_page(
            'AstroReport Pro',
            'AstroReport Pro',
            'manage_options',
            'gem-astrology',
            [$this, 'render_dashboard'],
            'dashicons-star-filled',
            30
        );

        // Submenu: Dashboard
        add_submenu_page(
            'gem-astrology',
            'Dashboard & Bookings',
            'Dashboard',
            'manage_options',
            'gem-astrology',
            [$this, 'render_dashboard']
        );

        // Submenu: Settings
        add_submenu_page(
            'gem-astrology',
            'Settings',
            'Settings',
            'manage_options',
            'gem-astrology-settings',
            [$this, 'render_settings']
        );
    }

    /**
     * Register settings for Razorpay keys
     */
    public function register_settings()
    {
        register_setting('gem_astro_settings', 'gem_astro_razorpay_key');
        register_setting('gem_astro_settings', 'gem_astro_razorpay_secret');
    }

    /**
     * Enqueue admin CSS inline
     */
    public function admin_styles($hook)
    {
        // Only on our pages
        if (strpos($hook, 'gem-astrology') === false) {
            return;
        }
        wp_enqueue_style('gem-astro-admin', false);
        wp_add_inline_style('gem-astro-admin', $this->get_admin_css());
    }

    /**
     * =============================================
     * DASHBOARD PAGE — Bookings + Stats
     * =============================================
     */
    public function render_dashboard()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = GemAstroDB::get_stats();
        $filters = [
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'date' => isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '',
        ];
        $bookings = GemAstroDB::get_all_bookings($filters);

        ?>
        <div class="wrap gem-admin-wrap">
            <div class="gem-header">
                <h1>🌟 AstroReport Pro Dashboard</h1>
                <span class="gem-version">v
                    <?php echo GEM_ASTRO_VERSION; ?>
                </span>
            </div>

            <!-- Stats Cards -->
            <div class="gem-stats-row">
                <div class="gem-stat-card">
                    <div class="gem-stat-icon">📅</div>
                    <div class="gem-stat-info">
                        <span class="gem-stat-label">Bookings Today</span>
                        <span class="gem-stat-value">
                            <?php echo intval($stats['today']); ?>
                        </span>
                    </div>
                </div>
                <div class="gem-stat-card">
                    <div class="gem-stat-icon">📊</div>
                    <div class="gem-stat-info">
                        <span class="gem-stat-label">Total Bookings</span>
                        <span class="gem-stat-value">
                            <?php echo intval($stats['total']); ?>
                        </span>
                    </div>
                </div>
                <div class="gem-stat-card">
                    <div class="gem-stat-icon">💰</div>
                    <div class="gem-stat-info">
                        <span class="gem-stat-label">Total Revenue</span>
                        <span class="gem-stat-value">₹
                            <?php echo number_format($stats['revenue'], 0); ?>
                        </span>
                    </div>
                </div>
                <div class="gem-stat-card">
                    <div class="gem-stat-icon">📄</div>
                    <div class="gem-stat-info">
                        <span class="gem-stat-label">PDF Reports</span>
                        <span class="gem-stat-value">
                            <?php echo intval($stats['pdf_count']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Razorpay Key Quick View -->
            <div class="gem-key-bar">
                <strong>🔑 Razorpay Key ID:</strong>
                <code><?php echo esc_html(get_option('gem_astro_razorpay_key', 'Not Set')); ?></code>
                <a href="<?php echo admin_url('admin.php?page=gem-astrology-settings'); ?>" class="gem-btn-small">Manage
                    Keys</a>
            </div>

            <!-- Search & Filters -->
            <div class="gem-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="gem-astrology">
                    <div class="gem-filter-row">
                        <div class="gem-filter-group">
                            <label>Filter Date (YYYY-MM-DD)</label>
                            <input type="date" name="filter_date" value="<?php echo esc_attr($filters['date']); ?>">
                        </div>
                        <div class="gem-filter-group">
                            <label>Search (name/phone/email/payment)</label>
                            <input type="text" name="s" value="<?php echo esc_attr($filters['search']); ?>"
                                placeholder="Search bookings...">
                        </div>
                        <div class="gem-filter-actions">
                            <button type="submit" class="gem-btn gem-btn-primary">🔍 Apply</button>
                            <a href="<?php echo admin_url('admin.php?page=gem-astrology'); ?>"
                                class="gem-btn gem-btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bookings Table -->
            <div class="gem-table-wrap">
                <h2>Bookings
                    <?php if (!empty($filters['search']) || !empty($filters['date']))
                        echo '<span class="gem-filter-tag">Filtered</span>'; ?>
                </h2>
                <table class="gem-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date / Time</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Language</th>
                            <th>Payment</th>
                            <th>Amount</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="gem-empty">No bookings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td><strong>#
                                            <?php echo intval($b->id); ?>
                                        </strong></td>
                                    <td>
                                        <?php echo esc_html($b->date ?: '—'); ?>
                                        <?php if ($b->time): ?>
                                            <br><small>
                                                <?php echo esc_html($b->time); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php echo esc_html($b->name); ?>
                                        </strong><br>
                                        <small>📱
                                            <?php echo esc_html($b->phone); ?>
                                        </small><br>
                                        <small>📧
                                            <?php echo esc_html($b->email); ?>
                                        </small><br>
                                        <small>🎂 DOB:
                                            <?php echo esc_html($b->dob); ?>
                                        </small>
                                        <?php if ($b->notes): ?>
                                            <br><small>📝
                                                <?php echo esc_html($b->notes); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="gem-badge <?php echo $b->service_type === 'pdf' ? 'gem-badge-blue' : 'gem-badge-purple'; ?>">
                                            <?php echo $b->service_type === 'pdf' ? '📄 PDF Report' : '🗓️ Consultation'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="gem-badge gem-badge-lang">
                                            <?php
                                            $lang_labels = ['hi' => '🇮🇳 Hindi', 'en' => '🇬🇧 English', 'gu' => '🇮🇳 Gujarati'];
                                            echo $lang_labels[$b->language] ?? strtoupper($b->language);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="gem-badge <?php echo $b->payment_status === 'paid' ? 'gem-badge-green' : 'gem-badge-red'; ?>">
                                            <?php echo $b->payment_status === 'paid' ? '✅ Paid' : '❌ ' . ucfirst($b->payment_status); ?>
                                        </span>
                                        <br><small class="gem-payment-id">
                                            <?php echo esc_html($b->payment_id); ?>
                                        </small>
                                    </td>
                                    <td><strong>₹
                                            <?php echo number_format($b->amount, 0); ?>
                                        </strong></td>
                                    <td><small>
                                            <?php echo esc_html($b->created_at); ?>
                                        </small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * =============================================
     * SETTINGS PAGE — Razorpay Keys
     * =============================================
     */
    public function render_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $saved = false;
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            $saved = true;
        }

        ?>
        <div class="wrap gem-admin-wrap">
            <div class="gem-header">
                <h1>⚙️ AstroReport Pro Settings</h1>
            </div>

            <?php if ($saved): ?>
                <div class="gem-notice gem-notice-success">
                    ✅ Settings saved successfully!
                </div>
            <?php endif; ?>

            <div class="gem-settings-card">
                <h2>🔑 Razorpay Payment Gateway</h2>
                <p class="gem-desc">Enter your Razorpay credentials. You can find them at <a
                        href="https://dashboard.razorpay.com/app/keys" target="_blank">Razorpay Dashboard → API Keys</a></p>

                <form method="post" action="options.php">
                    <?php settings_fields('gem_astro_settings'); ?>

                    <div class="gem-form-group">
                        <label for="gem_astro_razorpay_key">Key ID <code>(rzp_live_... / rzp_test_...)</code></label>
                        <input type="text" id="gem_astro_razorpay_key" name="gem_astro_razorpay_key"
                            value="<?php echo esc_attr(get_option('gem_astro_razorpay_key', '')); ?>"
                            placeholder="rzp_live_xxxxxxxxxxxxxx" class="gem-input">
                    </div>

                    <div class="gem-form-group">
                        <label for="gem_astro_razorpay_secret">Secret Key</label>
                        <input type="password" id="gem_astro_razorpay_secret" name="gem_astro_razorpay_secret"
                            value="<?php echo esc_attr(get_option('gem_astro_razorpay_secret', '')); ?>"
                            placeholder="Enter Secret Key..." class="gem-input">
                        <small class="gem-help">⚠️ Keep this secret. Never share it publicly.</small>
                    </div>

                    <button type="submit" class="gem-btn gem-btn-primary gem-btn-save">💾 Save Settings</button>
                </form>
            </div>

            <!-- Plugin Info -->
            <div class="gem-settings-card gem-info-card">
                <h2>ℹ️ Engine Information</h2>
                <table class="gem-info-table">
                    <tr>
                        <td>Product Name</td>
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
                        <td><a href="https://www.niongemastro.com/" target="_blank">niongemastro.com</a></td>
                    </tr>
                    <tr>
                        <td>Shortcode</td>
                        <td><code>[astro_report]</code></td>
                    </tr>
                    <tr>
                        <td>Generation Engine</td>
                        <td>TCPDF + Digital Numerology Core</td>
                    </tr>
                    <tr>
                        <td>Supported Languages</td>
                        <td>Hindi, English, Gujarati</td>
                    </tr>
                    <tr>
                        <td>Payment Status</td>
                        <td>
                            <?php
                            $key = get_option('gem_astro_razorpay_key', '');
                            if (empty($key)) {
                                echo '<span class="gem-badge gem-badge-red">❌ Not Configured</span>';
                            } elseif (strpos($key, 'rzp_test_') === 0) {
                                echo '<span class="gem-badge gem-badge-orange">🧪 Test Mode</span>';
                            } else {
                                echo '<span class="gem-badge gem-badge-green">✅ Live</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- How to Use -->
            <div class="gem-settings-card">
                <h2>📖 How to Use</h2>
                <ol class="gem-steps">
                    <li><strong>Set Razorpay Keys</strong> above with your live/test credentials</li>
                    <li>Add shortcode <code>[astro_report]</code> to any page or post</li>
                    <li>Users select language, fill form, and pay via Razorpay</li>
                    <li>After payment: PDF downloads instantly + email sent with all 3 language reports</li>
                    <li>View all bookings on the <a
                            href="<?php echo admin_url('admin.php?page=gem-astrology'); ?>">Dashboard</a></li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * Admin CSS
     */
    private function get_admin_css()
    {
        return '
        .gem-admin-wrap {
            max-width: 1200px;
            margin: 20px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .gem-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px 25px;
            background: linear-gradient(135deg, #7c1e22 0%, #5a1417 100%);
            border-radius: 16px;
            color: #fff;
            box-shadow: 0 8px 32px rgba(124,30,34,0.3);
        }
        .gem-header h1 {
            margin: 0;
            color: #fff;
            font-size: 24px;
        }
        .gem-version {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Stats */
        .gem-stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .gem-stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #ead0ce;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: transform 0.2s;
        }
        .gem-stat-card:hover { transform: translateY(-3px); }
        .gem-stat-icon { font-size: 32px; }
        .gem-stat-label { display: block; color: #6b6b6b; font-size: 13px; }
        .gem-stat-value { display: block; font-size: 28px; font-weight: 800; color: #7c1e22; }

        /* Key Bar */
        .gem-key-bar {
            background: #fff7ea;
            padding: 14px 20px;
            border-radius: 12px;
            border: 1px solid #f0dbb3;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gem-key-bar code {
            background: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        .gem-btn-small {
            padding: 5px 14px;
            background: #d4af37;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            margin-left: auto;
        }
        .gem-btn-small:hover { background: #b8911f; color: #fff; }

        /* Filters */
        .gem-filters {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid #ead0ce;
            margin-bottom: 20px;
        }
        .gem-filter-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .gem-filter-group { flex: 1; min-width: 200px; }
        .gem-filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
        }
        .gem-filter-group input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .gem-filter-actions {
            display: flex;
            gap: 8px;
        }

        /* Buttons */
        .gem-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .gem-btn-primary { background: #d4af37; color: #fff; }
        .gem-btn-primary:hover { background: #b8911f; color: #fff; }
        .gem-btn-secondary { background: #f0f0f0; color: #333; }
        .gem-btn-secondary:hover { background: #ddd; color: #333; }
        .gem-btn-save { font-size: 16px; padding: 12px 30px; margin-top: 10px; }

        /* Table */
        .gem-table-wrap {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #ead0ce;
            padding: 20px;
            overflow-x: auto;
        }
        .gem-table-wrap h2 { margin-top: 0; color: #7c1e22; }
        .gem-filter-tag {
            background: #d4af37;
            color: #fff;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 8px;
        }
        .gem-table {
            width: 100%;
            border-collapse: collapse;
        }
        .gem-table th {
            background: #f9f2f2;
            padding: 12px 14px;
            text-align: left;
            font-size: 13px;
            color: #7c1e22;
            border-bottom: 2px solid #ead0ce;
            white-space: nowrap;
        }
        .gem-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f0e6e5;
            font-size: 13px;
            vertical-align: top;
        }
        .gem-table tr:hover td { background: #fdf8f7; }
        .gem-empty { text-align: center; color: #999; padding: 40px !important; }

        /* Badges */
        .gem-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .gem-badge-green { background: rgba(21,128,61,0.1); color: #15803d; }
        .gem-badge-red { background: rgba(185,28,28,0.1); color: #b91c1c; }
        .gem-badge-orange { background: rgba(234,160,0,0.1); color: #b87800; }
        .gem-badge-blue { background: rgba(37,99,235,0.1); color: #2563eb; }
        .gem-badge-purple { background: rgba(147,51,234,0.1); color: #9333ea; }
        .gem-badge-lang { background: rgba(124,30,34,0.1); color: #7c1e22; }
        .gem-payment-id { color: #999; font-size: 11px; word-break: break-all; }

        /* Settings */
        .gem-settings-card {
            background: #fff;
            padding: 25px;
            border-radius: 14px;
            border: 1px solid #ead0ce;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .gem-settings-card h2 { margin-top: 0; color: #7c1e22; }
        .gem-desc { color: #666; margin-bottom: 20px; }
        .gem-form-group { margin-bottom: 20px; }
        .gem-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .gem-input {
            width: 100%;
            max-width: 500px;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: monospace;
        }
        .gem-input:focus {
            border-color: #7c1e22;
            outline: none;
            box-shadow: 0 0 0 3px rgba(124,30,34,0.1);
        }
        .gem-help { display: block; margin-top: 5px; color: #999; font-size: 12px; }

        /* Notice */
        .gem-notice {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .gem-notice-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }

        /* Info Table */
        .gem-info-table { width: 100%; }
        .gem-info-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .gem-info-table td:first-child { color: #888; width: 40%; }

        /* Steps */
        .gem-steps li {
            padding: 8px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .gem-steps code {
            background: #f5f0e8;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        @media (max-width: 782px) {
            .gem-stats-row { grid-template-columns: 1fr 1fr; }
            .gem-filter-row { flex-direction: column; }
            .gem-key-bar { flex-wrap: wrap; }
        }
        ';
    }
}
