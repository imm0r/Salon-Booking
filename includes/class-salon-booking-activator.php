<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Salon_Booking_Activator {
    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'salon_appointments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50) NOT NULL,
            stylist_id mediumint(9) NOT NULL,
            service_id mediumint(9) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            payment_status varchar(50) NOT NULL DEFAULT 'unpaid',
            payment_method varchar(50),
            payment_id varchar(255),
            google_event_id text,
            outlook_event_id text,
            notes text,
            reminder_email tinyint(1) NOT NULL DEFAULT 1,
            reminder_sms tinyint(1) NOT NULL DEFAULT 0,
            reminder_push tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY start_time (start_time),
            KEY stylist_id (stylist_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
