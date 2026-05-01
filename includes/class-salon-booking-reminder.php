<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Salon_Booking_Reminder {
    public function __construct() {
        add_action( 'salon_booking_send_reminders', array( $this, 'send_reminders' ) );
        if ( ! wp_next_scheduled( 'salon_booking_send_reminders' ) ) {
            wp_schedule_event( time(), 'hourly', 'salon_booking_send_reminders' );
        }
    }

    public function send_reminders() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';

        // Termine in den nächsten X Stunden finden
        $reminder_hours = get_option( 'salon_booking_reminder_hours', 24 );
        $reminder_time = date( 'Y-m-d H:i:s', strtotime( "+$reminder_hours hours" ) );
        $now = date( 'Y-m-d H:i:s' );

        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE start_time BETWEEN %s AND %s AND status = 'confirmed'",
            $now,
            $reminder_time
        ) );

        foreach ( $bookings as $booking ) {
            if ( $booking->reminder_email ) {
                $this->send_email_reminder( $booking );
            }
            if ( $booking->reminder_sms ) {
                $this->send_sms_reminder( $booking );
            }
            if ( $booking->reminder_push ) {
                $this->send_push_reminder( $booking );
            }
        }
    }

    private function send_email_reminder( $booking ) {
        $template = get_option( 'salon_booking_email_template', __( 'Hallo {name}, dies ist eine Erinnerung an Ihren Termin am {date} um {time} bei {stylist} für {service}.', 'salon-booking' ) );

        $replacements = array(
            '{name}' => $booking->customer_name,
            '{date}' => date_i18n( get_option( 'date_format' ), strtotime( $booking->start_time ) ),
            '{time}' => date_i18n( get_option( 'time_format' ), strtotime( $booking->start_time ) ),
            '{stylist}' => Salon_Booking::get_stylist_name( $booking->stylist_id ),
            '{service}' => Salon_Booking::get_service_name( $booking->service_id ),
        );

        $message = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
        $subject = __( 'Terminerinnerung', 'salon-booking' );

        wp_mail( $booking->customer_email, $subject, $message );
    }

    private function send_sms_reminder( $booking ) {
        $sms_provider = get_option( 'salon_booking_sms_provider', '' );

        if ( 'twilio' === $sms_provider ) {
            $this->send_twilio_sms( $booking );
        } elseif ( 'massenversand' === $sms_provider ) {
            $this->send_massenversand_sms( $booking );
        }
    }

    private function send_twilio_sms( $booking ) {
        $sid = get_option( 'salon_booking_twilio_sid', '' );
        $token = get_option( 'salon_booking_twilio_token', '' );
        $from = get_option( 'salon_booking_twilio_from', '' );

        if ( ! $sid || ! $token || ! $from ) {
            return;
        }

        $message = sprintf(
            'Erinnerung: Ihr Termin am %s um %s.',
            date_i18n( get_option( 'date_format' ), strtotime( $booking->start_time ) ),
            date_i18n( get_option( 'time_format' ), strtotime( $booking->start_time ) )
        );

        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $data = array(
            'From' => $from,
            'To' => $booking->customer_phone,
            'Body' => $message,
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
            ),
            'body' => $data,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Twilio SMS failed: ' . $response->get_error_message() );
        }
    }

    private function send_massenversand_sms( $booking ) {
        $username = get_option( 'salon_booking_massenversand_username', '' );
        $password = get_option( 'salon_booking_massenversand_password', '' );
        $sender = get_option( 'salon_booking_massenversand_sender', '' );

        if ( ! $username || ! $password || ! $sender ) {
            return;
        }

        $message = sprintf(
            'Erinnerung: Ihr Termin am %s um %s.',
            date_i18n( get_option( 'date_format' ), strtotime( $booking->start_time ) ),
            date_i18n( get_option( 'time_format' ), strtotime( $booking->start_time ) )
        );

        $url = 'https://api.massenversand.de/sms';
        $body = array(
            'username' => $username,
            'password' => $password,
            'sender'   => $sender,
            'recipient'=> $booking->customer_phone,
            'message'  => $message,
        );

        $response = wp_remote_post( $url, array(
            'body' => $body,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Massenversand SMS failed: ' . $response->get_error_message() );
        }
    }

    private function send_push_reminder( $booking ) {
        $app_id = get_option( 'salon_booking_onesignal_app_id', '' );
        $api_key = get_option( 'salon_booking_onesignal_api_key', '' );

        if ( ! $app_id || ! $api_key ) {
            return;
        }

        $message = sprintf(
            'Erinnerung: Ihr Termin am %s um %s.',
            date_i18n( get_option( 'date_format' ), strtotime( $booking->start_time ) ),
            date_i18n( get_option( 'time_format' ), strtotime( $booking->start_time ) )
        );

        $body = array(
            'app_id' => $app_id,
            'included_segments' => array( 'All' ),
            'headings' => array( 'en' => 'Terminerinnerung' ),
            'contents' => array( 'en' => $message ),
            'url' => home_url(),
        );

        $response = wp_remote_post( 'https://onesignal.com/api/v1/notifications', array(
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . $api_key,
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'OneSignal push failed: ' . $response->get_error_message() );
        }
    }
}