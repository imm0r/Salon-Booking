<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Salon_Booking_Calendar {
    public static function sync_booking( $booking_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'salon_appointments';
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $booking_id ) );
        if ( ! $booking ) {
            return false;
        }

        $updated = array();

        if ( self::is_google_enabled() ) {
            $google_event_id = self::sync_google_event( $booking );
            if ( $google_event_id ) {
                $updated['google_event_id'] = $google_event_id;
            }
        }

        if ( self::is_outlook_enabled() ) {
            $outlook_event_id = self::sync_outlook_event( $booking );
            if ( $outlook_event_id ) {
                $updated['outlook_event_id'] = $outlook_event_id;
            }
        }

        if ( ! empty( $updated ) ) {
            $wpdb->update(
                $table_name,
                $updated,
                array( 'id' => $booking_id ),
                array_fill( 0, count( $updated ), '%s' ),
                array( '%d' )
            );
        }

        return ! empty( $updated );
    }

    private static function is_google_enabled() {
        return '1' === get_option( 'salon_booking_google_enabled', '0' );
    }

    private static function is_outlook_enabled() {
        return '1' === get_option( 'salon_booking_outlook_enabled', '0' );
    }

    private static function sync_google_event( $booking ) {
        $calendar_id    = get_option( 'salon_booking_google_calendar_id' );
        $access_token   = self::get_google_access_token();

        if ( ! $calendar_id || ! $access_token ) {
            return false;
        }

        $event = self::build_google_event_payload( $booking );
        $event_id = $booking->google_event_id;
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $calendar_id ) . '/events';
        $method = 'POST';

        if ( $event_id ) {
            $url = $url . '/' . rawurlencode( $event_id );
            $method = 'PUT';
        }

        $response = wp_remote_request( $url, array(
            'method'      => $method,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'        => wp_json_encode( $event ),
            'data_format' => 'body',
            'timeout'     => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['id'] ) ? $body['id'] : false;
    }

    private static function sync_outlook_event( $booking ) {
        $calendar_id    = get_option( 'salon_booking_outlook_calendar_id' );
        $access_token   = self::get_outlook_access_token();

        if ( ! $calendar_id || ! $access_token ) {
            return false;
        }

        $event = self::build_outlook_event_payload( $booking );
        $event_id = $booking->outlook_event_id;
        $url = 'https://graph.microsoft.com/v1.0/me/calendars/' . rawurlencode( $calendar_id ) . '/events';
        $method = 'POST';

        if ( $event_id ) {
            $url = $url . '/' . rawurlencode( $event_id );
            $method = 'PATCH';
        }

        $response = wp_remote_request( $url, array(
            'method'      => $method,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'body'        => wp_json_encode( $event ),
            'data_format' => 'body',
            'timeout'     => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $body['id'] ) ? $body['id'] : false;
    }

    private static function build_google_event_payload( $booking ) {
        $title = sprintf(
            '%s: %s',
            Salon_Booking::get_stylist_name( $booking->stylist_id ),
            Salon_Booking::get_service_name( $booking->service_id )
        );

        $description = sprintf(
            '%s\nService: %s\nFriseur: %s\nNotizen: %s',
            esc_html( $booking->customer_name ),
            Salon_Booking::get_service_name( $booking->service_id ),
            Salon_Booking::get_stylist_name( $booking->stylist_id ),
            esc_html( $booking->notes )
        );

        return array(
            'summary'     => $title,
            'description' => $description,
            'start'       => array(
                'dateTime' => date( 'c', strtotime( $booking->start_time ) ),
                'timeZone' => self::get_timezone(),
            ),
            'end'         => array(
                'dateTime' => date( 'c', strtotime( $booking->end_time ) ),
                'timeZone' => self::get_timezone(),
            ),
        );
    }

    private static function build_outlook_event_payload( $booking ) {
        $subject = sprintf(
            '%s - %s',
            Salon_Booking::get_service_name( $booking->service_id ),
            Salon_Booking::get_stylist_name( $booking->stylist_id )
        );

        return array(
            'subject' => $subject,
            'body'    => array(
                'contentType' => 'HTML',
                'content'     => sprintf(
                    '<p><strong>%s</strong></p><p>%s</p><p>%s</p><p>%s</p>',
                    esc_html( $booking->customer_name ),
                    esc_html( Salon_Booking::get_service_name( $booking->service_id ) ),
                    esc_html( Salon_Booking::get_stylist_name( $booking->stylist_id ) ),
                    esc_html( $booking->notes )
                ),
            ),
            'start'   => array(
                'dateTime' => date( 'c', strtotime( $booking->start_time ) ),
                'timeZone' => self::get_timezone(),
            ),
            'end'     => array(
                'dateTime' => date( 'c', strtotime( $booking->end_time ) ),
                'timeZone' => self::get_timezone(),
            ),
        );
    }

    private static function get_timezone() {
        return get_option( 'timezone_string', 'Europe/Berlin' );
    }

    private static function get_google_access_token() {
        $access_token = get_option( 'salon_booking_google_access_token' );
        $expires      = intval( get_option( 'salon_booking_google_token_expires', 0 ) );

        if ( $access_token && time() + 60 < $expires ) {
            return $access_token;
        }

        $refresh_token = get_option( 'salon_booking_google_refresh_token' );
        $client_id     = get_option( 'salon_booking_google_client_id' );
        $client_secret = get_option( 'salon_booking_google_client_secret' );

        if ( ! $refresh_token || ! $client_id || ! $client_secret ) {
            return false;
        }

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body'    => array(
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) {
            return false;
        }

        update_option( 'salon_booking_google_access_token', sanitize_text_field( $body['access_token'] ) );
        if ( ! empty( $body['refresh_token'] ) ) {
            update_option( 'salon_booking_google_refresh_token', sanitize_text_field( $body['refresh_token'] ) );
        }
        update_option( 'salon_booking_google_token_expires', time() + intval( $body['expires_in'] ) );

        return $body['access_token'];
    }

    private static function get_outlook_access_token() {
        $access_token = get_option( 'salon_booking_outlook_access_token' );
        $expires      = intval( get_option( 'salon_booking_outlook_token_expires', 0 ) );

        if ( $access_token && time() + 60 < $expires ) {
            return $access_token;
        }

        $refresh_token = get_option( 'salon_booking_outlook_refresh_token' );
        $client_id     = get_option( 'salon_booking_outlook_client_id' );
        $client_secret = get_option( 'salon_booking_outlook_client_secret' );
        $tenant_id     = get_option( 'salon_booking_outlook_tenant_id' );

        if ( ! $refresh_token || ! $client_id || ! $client_secret || ! $tenant_id ) {
            return false;
        }

        $token_url = 'https://login.microsoftonline.com/' . rawurlencode( $tenant_id ) . '/oauth2/v2.0/token';
        $response = wp_remote_post( $token_url, array(
            'body'    => array(
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
                'scope'         => 'https://graph.microsoft.com/.default offline_access',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) {
            return false;
        }

        update_option( 'salon_booking_outlook_access_token', sanitize_text_field( $body['access_token'] ) );
        if ( ! empty( $body['refresh_token'] ) ) {
            update_option( 'salon_booking_outlook_refresh_token', sanitize_text_field( $body['refresh_token'] ) );
        }
        update_option( 'salon_booking_outlook_token_expires', time() + intval( $body['expires_in'] ) );

        return $body['access_token'];
    }
}
