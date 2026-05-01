<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Salon_Booking_Payment {
    public static function process_payment( $booking_id, $payment_method ) {
        try {
            $booking = self::get_booking( $booking_id );
            if ( ! $booking ) {
                throw new Exception( __( 'Ungültige Buchung.', 'salon-booking' ), 1001 );
            }

            if ( ! in_array( $payment_method, array( 'paypal', 'klarna' ), true ) ) {
                throw new Exception( __( 'Ungültige Zahlungsmethode.', 'salon-booking' ), 1002 );
            }

            $amount = self::calculate_amount( $booking );
            if ( $amount <= 0 ) {
                throw new Exception( __( 'Ungültiger Betrag.', 'salon-booking' ), 1003 );
            }

            if ( 'paypal' === $payment_method ) {
                return self::process_paypal_payment( $booking, $amount );
            } elseif ( 'klarna' === $payment_method ) {
                return self::process_klarna_payment( $booking, $amount );
            }
        } catch ( Exception $e ) {
            self::log_error( 'Payment processing failed: ' . $e->getMessage(), $e->getCode() );
            return new WP_Error( 'payment_error', $e->getMessage(), array( 'code' => $e->getCode() ) );
        }
    }

    private static function process_paypal_payment( $booking, $amount ) {
        try {
            $client_id = get_option( 'salon_booking_paypal_client_id' );
            $secret = get_option( 'salon_booking_paypal_client_secret' );
            $mode = get_option( 'salon_booking_paypal_mode', 'sandbox' );

            if ( ! $client_id || ! $secret ) {
                throw new Exception( __( 'PayPal API-Schlüssel nicht konfiguriert.', 'salon-booking' ), 2001 );
            }

            $base_url = 'sandbox' === $mode ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';

            // Access Token holen
            $token_response = wp_remote_post( $base_url . '/v1/oauth2/token', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => 'grant_type=client_credentials',
                'timeout' => 30,
            ) );

            if ( is_wp_error( $token_response ) ) {
                throw new Exception( __( 'PayPal Token-Anfrage fehlgeschlagen: ', 'salon-booking' ) . $token_response->get_error_message(), 2002 );
            }

            $response_code = wp_remote_retrieve_response_code( $token_response );
            if ( 200 !== $response_code ) {
                throw new Exception( __( 'PayPal Token-Fehler: HTTP ', 'salon-booking' ) . $response_code, 2003 );
            }

            $token_data = json_decode( wp_remote_retrieve_body( $token_response ), true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( __( 'PayPal Token-JSON-Fehler.', 'salon-booking' ), 2004 );
            }

            if ( ! isset( $token_data['access_token'] ) ) {
                throw new Exception( __( 'PayPal Access Token fehlt.', 'salon-booking' ), 2005 );
            }

            $access_token = $token_data['access_token'];

            // Zahlung erstellen
            $payment_data = array(
                'intent' => 'sale',
                'payer'  => array( 'payment_method' => 'paypal' ),
                'transactions' => array(
                    array(
                        'amount' => array(
                            'total'    => number_format( $amount, 2, '.', '' ),
                            'currency' => 'EUR',
                        ),
                        'description' => sprintf( 'Buchung für %s', $booking->customer_name ),
                    ),
                ),
                'redirect_urls' => array(
                    'return_url' => home_url( '/salon-booking/payment-success?booking_id=' . $booking->id ),
                    'cancel_url' => home_url( '/salon-booking/payment-cancel?booking_id=' . $booking->id ),
                ),
            );

            $payment_response = wp_remote_post( $base_url . '/v1/payments/payment', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( $payment_data ),
                'timeout' => 30,
            ) );

            if ( is_wp_error( $payment_response ) ) {
                throw new Exception( __( 'PayPal Zahlungsanfrage fehlgeschlagen: ', 'salon-booking' ) . $payment_response->get_error_message(), 2006 );
            }

            $payment_code = wp_remote_retrieve_response_code( $payment_response );
            if ( 201 !== $payment_code ) {
                $body = wp_remote_retrieve_body( $payment_response );
                throw new Exception( __( 'PayPal Zahlungserstellung fehlgeschlagen: HTTP ', 'salon-booking' ) . $payment_code . ' - ' . $body, 2007 );
            }

            $payment_result = json_decode( wp_remote_retrieve_body( $payment_response ), true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( __( 'PayPal Zahlungs-JSON-Fehler.', 'salon-booking' ), 2008 );
            }

            if ( ! isset( $payment_result['id'] ) || ! isset( $payment_result['links'] ) ) {
                throw new Exception( __( 'PayPal Zahlungsdaten unvollständig.', 'salon-booking' ), 2009 );
            }

            $approval_url = '';
            foreach ( $payment_result['links'] as $link ) {
                if ( isset( $link['rel'], $link['href'] ) && 'approval_url' === $link['rel'] ) {
                    $approval_url = $link['href'];
                    break;
                }
            }

            if ( empty( $approval_url ) ) {
                throw new Exception( __( 'PayPal Genehmigungs-URL nicht gefunden.', 'salon-booking' ), 2010 );
            }

            self::update_booking_payment( $booking->id, 'paypal', $payment_result['id'], 'pending' );
            return array( 'redirect_url' => $approval_url );
        } catch ( Exception $e ) {
            self::log_error( 'PayPal payment failed: ' . $e->getMessage(), $e->getCode() );
            return new WP_Error( 'paypal_error', $e->getMessage(), array( 'code' => $e->getCode() ) );
        }
    }

    private static function process_klarna_payment( $booking, $amount ) {
        try {
            $username = get_option( 'salon_booking_klarna_username' );
            $password = get_option( 'salon_booking_klarna_password' );
            $mode = get_option( 'salon_booking_klarna_mode', 'playground' );

            if ( ! $username || ! $password ) {
                throw new Exception( __( 'Klarna API-Schlüssel nicht konfiguriert.', 'salon-booking' ), 3001 );
            }

            $base_url = 'playground' === $mode ? 'https://api.playground.klarna.com' : 'https://api.klarna.com';

            // Session erstellen
            $session_data = array(
                'purchase_country'  => 'DE',
                'purchase_currency' => 'EUR',
                'locale'            => 'de-DE',
                'order_amount'      => intval( $amount * 100 ), // In Cent
                'order_tax_amount'  => 0,
                'order_lines'       => array(
                    array(
                        'type'             => 'physical',
                        'reference'        => 'booking-' . $booking->id,
                        'name'             => 'Frisörtermin',
                        'quantity'         => 1,
                        'unit_price'       => intval( $amount * 100 ),
                        'tax_rate'         => 0,
                        'total_amount'     => intval( $amount * 100 ),
                        'total_tax_amount' => 0,
                    ),
                ),
                'merchant_urls' => array(
                    'terms'        => home_url( '/terms' ),
                    'checkout'     => home_url( '/salon-booking/checkout?booking_id=' . $booking->id ),
                    'confirmation' => home_url( '/salon-booking/klarna-confirmation?booking_id=' . $booking->id . '&order_id={checkout.order_id}' ),
                    'push'         => home_url( '/wp-json/salon-booking/v1/klarna-push?booking_id=' . $booking->id ),
                ),
            );

            $session_response = wp_remote_post( $base_url . '/payments/v2/sessions', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( $session_data ),
                'timeout' => 30,
            ) );

            if ( is_wp_error( $session_response ) ) {
                throw new Exception( __( 'Klarna Session-Anfrage fehlgeschlagen: ', 'salon-booking' ) . $session_response->get_error_message(), 3002 );
            }

            $session_code = wp_remote_retrieve_response_code( $session_response );
            if ( 200 !== $session_code && 201 !== $session_code ) {
                $body = wp_remote_retrieve_body( $session_response );
                throw new Exception( __( 'Klarna Session-Erstellung fehlgeschlagen: HTTP ', 'salon-booking' ) . $session_code . ' - ' . $body, 3003 );
            }

            $session_result = json_decode( wp_remote_retrieve_body( $session_response ), true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( __( 'Klarna Session-JSON-Fehler.', 'salon-booking' ), 3004 );
            }

            if ( ! isset( $session_result['session_id'] ) || ! isset( $session_result['client_token'] ) ) {
                throw new Exception( __( 'Klarna Session-Daten unvollständig.', 'salon-booking' ), 3005 );
            }

            self::update_booking_payment( $booking->id, 'klarna', $session_result['session_id'], 'pending' );
            self::store_klarna_client_token( $booking->id, $session_result['client_token'] );

            return array( 'checkout_url' => home_url( '/salon-booking/checkout?booking_id=' . $booking->id ) );
        } catch ( Exception $e ) {
            self::log_error( 'Klarna payment failed: ' . $e->getMessage(), $e->getCode() );
            return new WP_Error( 'klarna_error', $e->getMessage(), array( 'code' => $e->getCode() ) );
        }
    }

    private static function get_booking( $booking_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $booking_id ) );
    }

    public static function complete_paypal_payment( $booking_id, $payment_id, $payer_id ) {
        try {
            $booking = self::get_booking( $booking_id );
            if ( ! $booking ) {
                throw new Exception( __( 'Ungültige Buchung.', 'salon-booking' ), 6001 );
            }

            $client_id = get_option( 'salon_booking_paypal_client_id' );
            $secret = get_option( 'salon_booking_paypal_client_secret' );
            $mode = get_option( 'salon_booking_paypal_mode', 'sandbox' );

            if ( ! $client_id || ! $secret ) {
                throw new Exception( __( 'PayPal API-Schlüssel nicht konfiguriert.', 'salon-booking' ), 6002 );
            }

            $base_url = 'sandbox' === $mode ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
            $token_response = wp_remote_post( $base_url . '/v1/oauth2/token', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body' => 'grant_type=client_credentials',
                'timeout' => 30,
            ) );

            if ( is_wp_error( $token_response ) ) {
                throw new Exception( $token_response->get_error_message(), 6003 );
            }

            $token_data = json_decode( wp_remote_retrieve_body( $token_response ), true );
            if ( ! isset( $token_data['access_token'] ) ) {
                throw new Exception( __( 'PayPal Access Token fehlt.', 'salon-booking' ), 6004 );
            }

            $access_token = $token_data['access_token'];
            $execute_response = wp_remote_post( $base_url . '/v1/payments/payment/' . rawurlencode( $payment_id ) . '/execute', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( array( 'payer_id' => $payer_id ) ),
                'timeout' => 30,
            ) );

            if ( is_wp_error( $execute_response ) ) {
                throw new Exception( $execute_response->get_error_message(), 6005 );
            }

            $execute_code = wp_remote_retrieve_response_code( $execute_response );
            if ( 200 !== $execute_code && 201 !== $execute_code ) {
                throw new Exception( __( 'PayPal Ausführung fehlgeschlagen: HTTP ', 'salon-booking' ) . $execute_code, 6006 );
            }

            $execute_result = json_decode( wp_remote_retrieve_body( $execute_response ), true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( __( 'PayPal Ausführungs-JSON-Fehler.', 'salon-booking' ), 6007 );
            }

            self::update_booking_payment( $booking_id, 'paypal', $payment_id, 'completed' );
            global $wpdb;
            $table_name = $wpdb->prefix . 'salon_appointments';
            $wpdb->update( $table_name, array( 'status' => 'confirmed' ), array( 'id' => $booking_id ), array( '%s' ), array( '%d' ) );

            return true;
        } catch ( Exception $e ) {
            self::log_error( 'PayPal execute failed: ' . $e->getMessage(), $e->getCode() );
            return new WP_Error( 'paypal_execute_error', $e->getMessage(), array( 'code' => $e->getCode() ) );
        }
    }

    private static function store_klarna_client_token( $booking_id, $client_token ) {
        set_transient( 'salon_booking_klarna_token_' . $booking_id, $client_token, HOUR_IN_SECONDS );
    }

    public static function get_klarna_client_token( $booking_id ) {
        return get_transient( 'salon_booking_klarna_token_' . $booking_id );
    }

    private static function calculate_amount( $booking ) {
        // Hier könntest du Preise basierend auf Service definieren
        // Für jetzt ein fester Betrag, z.B. 50€
        return 50.00;
    }

    private static function update_booking_payment( $booking_id, $method, $payment_id, $status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';
        $wpdb->update(
            $table_name,
            array(
                'payment_method' => $method,
                'payment_id'     => $payment_id,
                'payment_status' => $status,
            ),
            array( 'id' => $booking_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    private static function log_error( $message, $code = 0 ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Salon Booking Payment Error ' . $code . '] ' . $message );
        }
        // Optional: In eine benutzerdefinierte Log-Tabelle schreiben oder E-Mail senden
    }

    public static function handle_paypal_webhook( $data ) {
        try {
            if ( ! isset( $data['resource']['id'] ) ) {
                throw new Exception( __( 'PayPal Webhook-Daten unvollständig.', 'salon-booking' ), 4001 );
            }

            $payment_id = sanitize_text_field( $data['resource']['id'] );
            $status = isset( $data['resource']['state'] ) && 'completed' === $data['resource']['state'] ? 'completed' : 'failed';

            self::update_payment_status( $payment_id, $status );
            self::log_error( 'PayPal Webhook processed: ' . $payment_id . ' - ' . $status, 0 );
        } catch ( Exception $e ) {
            self::log_error( 'PayPal Webhook failed: ' . $e->getMessage(), $e->getCode() );
        }
    }

    public static function handle_klarna_webhook( $data ) {
        try {
            if ( ! isset( $data['order_id'] ) ) {
                throw new Exception( __( 'Klarna Webhook-Daten unvollständig.', 'salon-booking' ), 5001 );
            }

            $order_id = sanitize_text_field( $data['order_id'] );
            $status = isset( $data['status'] ) && 'AUTHORIZED' === $data['status'] ? 'completed' : 'failed';

            self::update_payment_status( $order_id, $status );
            self::log_error( 'Klarna Webhook processed: ' . $order_id . ' - ' . $status, 0 );
        } catch ( Exception $e ) {
            self::log_error( 'Klarna Webhook failed: ' . $e->getMessage(), $e->getCode() );
        }
    }

    private static function update_payment_status( $payment_id, $status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';
        $wpdb->update(
            $table_name,
            array( 'payment_status' => $status ),
            array( 'payment_id' => $payment_id ),
            array( '%s' ),
            array( '%s' )
        );
    }
}