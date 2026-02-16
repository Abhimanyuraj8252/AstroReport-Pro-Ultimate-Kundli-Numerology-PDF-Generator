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

        if (!empty($filters['date'])) {
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

        $sql = "SELECT * FROM $table_name WHERE $where ORDER BY id DESC LIMIT 100";

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
}
