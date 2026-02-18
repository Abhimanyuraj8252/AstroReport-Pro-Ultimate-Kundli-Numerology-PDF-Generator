<?php

if (!defined('ABSPATH')) {
    exit;
}

class GemAstroDB
{

    private static $table_name = 'gem_astro_bookings';

    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    public static function create_table()
    {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            name tinytext NOT NULL,
            phone tinytext NOT NULL,
            email tinytext NOT NULL,
            dob tinytext NOT NULL,
            notes text DEFAULT '',
            service_type tinytext NOT NULL,
            date tinytext,
            time tinytext,
            payment_id tinytext NOT NULL,
            payment_status tinytext NOT NULL,
            amount float NOT NULL,
            language tinytext NOT NULL DEFAULT 'hi',
            pdf_generated boolean DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function insert_booking($data)
    {
        global $wpdb;
        $table_name = self::get_table_name();

        $defaults = [
            'language' => 'hi',
            'notes' => '',
            'date' => '',
            'time' => ''
        ];

        $data = array_merge($defaults, $data);

        $result = $wpdb->insert(
            $table_name,
            [
                'created_at' => current_time('mysql'),
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'dob' => $data['dob'],
                'notes' => $data['notes'],
                'service_type' => $data['service_type'],
                'date' => $data['date'],
                'time' => $data['time'],
                'payment_id' => $data['payment_id'],
                'payment_status' => $data['payment_status'],
                'amount' => $data['amount'],
                'language' => $data['language'],
                'pdf_generated' => 0
            ]
        );

        if ($result) {
            return $wpdb->insert_id;
        }
        return false;
    }

    public static function get_booked_slots($date)
    {
        global $wpdb;
        $table_name = self::get_table_name();

        // Fetch times for Consultation service for the specific date
        // Assuming we want to block slots regardless of payment status? 
        // Or only paid? Logic in JS suggested simple time check.
        // Let's filter by paid or created recently to be safe?
        // Actually, let's just fetch all times for now.

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT time FROM $table_name WHERE date = %s AND service_type = 'consultation'",
                $date
            )
        );

        return $results ? $results : [];
    }

    /**
     * Get all bookings with optional search and date filters
     */
    public static function get_all_bookings($filters = [])
    {
        global $wpdb;
        $table_name = self::get_table_name();

        $where = '1=1';
        $params = [];

        // Date Range Filter
        if (!empty($filters['range']) && $filters['range'] !== 'all') {
            $interval = '7 DAY'; // default
            switch ($filters['range']) {
                case '1d':
                    $interval = '1 DAY';
                    break;
                case '7d':
                    $interval = '7 DAY';
                    break;
                case '15d':
                    $interval = '15 DAY';
                    break;
                case '1m':
                    $interval = '1 MONTH';
                    break;
                case '3m':
                    $interval = '3 MONTH';
                    break;
                case '6m':
                    $interval = '6 MONTH';
                    break;
                case '1y':
                    $interval = '1 YEAR';
                    break;
                case '3y':
                    $interval = '3 YEAR';
                    break;
                case '5y':
                    $interval = '5 YEAR';
                    break;
            }
            $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)";
        } elseif (!empty($filters['date'])) {
            // Specific single date fallback
            $where .= ' AND DATE(created_at) = %s';
            $params[] = $filters['date'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where .= ' AND (name LIKE %s OR phone LIKE %s OR email LIKE %s OR payment_id LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Sorting
        $order_by = 'id';
        $order_dir = 'DESC';

        if (!empty($filters['sort_by'])) {
            switch ($filters['sort_by']) {
                case 'amount_high':
                    $order_by = 'amount';
                    $order_dir = 'DESC';
                    break;
                case 'amount_low':
                    $order_by = 'amount';
                    $order_dir = 'ASC';
                    break;
                case 'date':
                    $order_by = 'created_at';
                    $order_dir = 'DESC';
                    break; // Newest first
                case 'date_asc':
                    $order_by = 'created_at';
                    $order_dir = 'ASC';
                    break; // Oldest first
                case 'name':
                    $order_by = 'name';
                    $order_dir = 'ASC';
                    break;
            }
        }

        $sql = "SELECT * FROM $table_name WHERE $where ORDER BY $order_by $order_dir LIMIT 200";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get booking stats
     */
    public static function get_stats()
    {
        global $wpdb;
        $table_name = self::get_table_name();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $today = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )
        );
        $revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM $table_name WHERE payment_status = 'paid'"
        );
        $pdf_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE service_type = 'pdf'"
        );

        return [
            'total' => $total,
            'today' => $today,
            'revenue' => $revenue,
            'pdf_count' => $pdf_count,
        ];
    }

    /**
     * Get single booking by ID
     */
    public static function get_booking_by_id($id)
    {
        global $wpdb;
        $table_name = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)
        );
    }

    /**
     * Get advanced stats for premium dashboard
     */
    public static function get_advanced_stats($range = '7d')
    {
        global $wpdb;
        $table_name = self::get_table_name();

        // Determine date condition based on range
        $date_cond = '1=1'; // default all time
        $interval_days = 7; // for chart

        if ($range !== 'all') {
            switch ($range) {
                case '1d':
                    $interval = '1 DAY';
                    $interval_days = 1;
                    break;
                case '7d':
                    $interval = '7 DAY';
                    $interval_days = 7;
                    break;
                case '15d':
                    $interval = '15 DAY';
                    $interval_days = 15;
                    break;
                case '1m':
                    $interval = '1 MONTH';
                    $interval_days = 30;
                    break;
                case '3m':
                    $interval = '3 MONTH';
                    $interval_days = 90;
                    break;
                case '6m':
                    $interval = '6 MONTH';
                    $interval_days = 180;
                    break;
                case '1y':
                    $interval = '1 YEAR';
                    $interval_days = 365;
                    break;
                case '3y':
                    $interval = '3 YEAR';
                    $interval_days = 1095;
                    break;
                case '5y':
                    $interval = '5 YEAR';
                    $interval_days = 1825;
                    break;
                default:
                    $interval = '7 DAY';
                    $interval_days = 7;
                    break;
            }
            $date_cond = "created_at >= DATE_SUB(NOW(), INTERVAL $interval)";
        } else {
            // For 'all' time chart, maybe limit to last 30 days or 1 year to avoid overcrowding?
            // Or just group by month if range is huge? 
            // For now, let's keep chart to max 30 days if 'all' or large range is selected to prevent UI breakage, 
            // BUT revenue total should be 'all'.
            // Actually user asked for analysis of 1,3,5 years. 
            // Handling chart for 5 years day-by-day is impossible.
            // Let's adapt chart grouping based on range.
        }

        // Basic Stats (filtered by range)
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $date_cond");
        $revenue = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM $table_name WHERE payment_status = 'paid' AND $date_cond");
        $pdf_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE service_type = 'pdf' AND $date_cond");
        $consultation_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE service_type = 'consultation' AND $date_cond");

        // Success rate
        $paid_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE payment_status = 'paid' AND $date_cond");
        $success_rate = $total > 0 ? round(($paid_count / $total) * 100, 1) : 0;

        // Chart Data logic
        // If range is > 1 month, group by WEEK or MONTH?
        // Simple approach: Group by DATE always, but query might return many rows. JS chart handles many points okay-ish up to a few hundred.
        // For 5 years (1800 points), it's too much.
        // Let's switch grouping:
        // <= 1M: Day
        // <= 6M: Week
        // > 6M: Month

        $group_by = "DATE(created_at)";
        $select_date = "DATE(created_at) as day";
        if ($interval_days > 180) { // > 6 months
            $group_by = "DATE_FORMAT(created_at, '%Y-%m')"; // Month
            $select_date = "DATE_FORMAT(created_at, '%Y-%m-01') as day"; // Force 1st of month for valid JS Date
        } elseif ($interval_days > 31) { // 1-6 months
            $group_by = "YEARWEEK(created_at, 1)"; // Weekweek (Mon-Sun)
            $select_date = "MIN(DATE(created_at)) as day"; // Logic: Use first booking date of the week as label
        }

        $revenue_chart_data = $wpdb->get_results(
            "SELECT $select_date, COALESCE(SUM(amount), 0) as total
             FROM $table_name
             WHERE payment_status = 'paid' AND $date_cond
             GROUP BY $group_by
             ORDER BY created_at ASC"
        );

        // Format for JS
        $revenue_chart = [];
        foreach ($revenue_chart_data as $row) {
            $revenue_chart[$row->day] = (float) $row->total;
        }

        // If data is empty for range, might want to fill 0s? 
        // For dynamic grouping, filling 0s is complex. Let's send sparse data, Chart.js handles it or we instruct frontend.

        // Language distribution (filtered)
        $lang_dist = $wpdb->get_results("SELECT language, COUNT(*) as cnt FROM $table_name WHERE $date_cond GROUP BY language");
        $language_distribution = [];
        foreach ($lang_dist as $row) {
            $language_distribution[$row->language] = (int) $row->cnt;
        }

        // Yesterday/Comparison (hard to do efficiently for arbitrary ranges, skipping specific 'yesterday' if detailed range used)
        $yesterday_count = 0;
        if ($range === '1d' || $range === '7d') {
            $yesterday_count = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s", date('Y-m-d', strtotime('-1 day')))
            );
        }

        return [
            'total' => $total,
            'today' => $paid_count, // Reusing 'today' key as 'successful bookings' count or just total? 
            'revenue' => $revenue,
            'pdf_count' => $pdf_count,
            'consultation_count' => $consultation_count,
            'success_rate' => $success_rate,
            'paid_count' => $paid_count,
            'revenue_chart' => $revenue_chart,
            'language_distribution' => $language_distribution,
            'yesterday' => $yesterday_count,
        ];
    }

    /**
     * Delete a booking by ID
     */
    public static function delete_booking($id)
    {
        global $wpdb;
        $table_name = self::get_table_name();

        return $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );
    }

    /**
     * Get recent bookings for activity feed
     */
    public static function get_recent_bookings($limit = 5)
    {
        global $wpdb;
        $table_name = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }
}
