<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Salon_Booking_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_post_salon_booking_update_status', array( $this, 'handle_booking_action' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'salon-booking',
            __( 'Buchungen', 'salon-booking' ),
            __( 'Buchungen', 'salon-booking' ),
            'manage_options',
            'salon-booking-bookings',
            array( $this, 'display_bookings_page' )
        );

        add_submenu_page(
            'salon-booking',
            __( 'Kalendersynchronisation', 'salon-booking' ),
            __( 'Kalendersynchronisation', 'salon-booking' ),
            'manage_options',
            'salon-booking-calendar-settings',
            array( $this, 'display_calendar_settings_page' )
        );

        add_submenu_page(
            'salon-booking',
            __( 'Erinnerungseinstellungen', 'salon-booking' ),
            __( 'Erinnerungseinstellungen', 'salon-booking' ),
            'manage_options',
            'salon-booking-reminder-settings',
            array( $this, 'display_reminder_settings_page' )
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'salon-booking' ) === false ) {
            return;
        }

        wp_enqueue_style( 'salon-booking-admin', SALON_BOOKING_PLUGIN_URL . 'assets/css/admin.css', array(), SALON_BOOKING_VERSION );
        wp_enqueue_style( 'salon-booking-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.css', array(), '6.1.4' );
        wp_enqueue_script( 'salon-booking-fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.4/main.min.js', array(), '6.1.4', true );
        wp_enqueue_script( 'salon-booking-admin', SALON_BOOKING_PLUGIN_URL . 'assets/js/admin.js', array( 'salon-booking-fullcalendar' ), SALON_BOOKING_VERSION, true );
        wp_localize_script( 'salon-booking-admin', 'salonBookingAdmin', array(
            'events' => $this->get_booking_events(),
        ) );
    }

    public function display_bookings_page() {
        $bookings = $this->get_bookings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Buchungen', 'salon-booking' ); ?></h1>
            <p><?php esc_html_e( 'Übersicht über alle Termine inklusive Kalenderansicht.', 'salon-booking' ); ?></p>

            <div id="salon-booking-calendar"></div>

            <h2><?php esc_html_e( 'Terminliste', 'salon-booking' ); ?></h2>
            <table class="salon-booking-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Datum', 'salon-booking' ); ?></th>
                        <th><?php esc_html_e( 'Kunde', 'salon-booking' ); ?></th>
                        <th><?php esc_html_e( 'Friseur', 'salon-booking' ); ?></th>
                        <th><?php esc_html_e( 'Service', 'salon-booking' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'salon-booking' ); ?></th>
                        <th><?php esc_html_e( 'Aktionen', 'salon-booking' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $bookings ) ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'Keine Buchungen vorhanden.', 'salon-booking' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $bookings as $booking ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $booking->start_time ) ) ); ?></td>
                                <td><?php echo esc_html( $booking->customer_name ); ?></td>
                                <td><?php echo esc_html( Salon_Booking::get_stylist_name( $booking->stylist_id ) ); ?></td>
                                <td><?php echo esc_html( Salon_Booking::get_service_name( $booking->service_id ) ); ?></td>
                                <td><?php echo esc_html( ucfirst( $booking->status ) ); ?></td>
                                <td>
                                    <?php if ( 'confirmed' !== $booking->status ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=salon_booking_update_status&booking_id=' . $booking->id . '&status=confirmed' ), 'salon_booking_update_status_' . $booking->id ) ); ?>"><?php esc_html_e( 'Bestätigen', 'salon-booking' ); ?></a> |
                                    <?php endif; ?>
                                    <?php if ( 'cancelled' !== $booking->status ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=salon_booking_update_status&booking_id=' . $booking->id . '&status=cancelled' ), 'salon_booking_update_status_' . $booking->id ) ); ?>"><?php esc_html_e( 'Stornieren', 'salon-booking' ); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function display_calendar_settings_page() {
        $message = '';

        if ( isset( $_POST['salon_booking_settings'] ) && check_admin_referer( 'salon_booking_save_settings', 'salon_booking_nonce' ) ) {
            $this->save_calendar_settings();
            $message = __( 'Kalendersynchronisation-Einstellungen gespeichert.', 'salon-booking' );
        }

        $google_enabled   = get_option( 'salon_booking_google_enabled', '0' );
        $google_calendar  = get_option( 'salon_booking_google_calendar_id', '' );
        $google_client_id = get_option( 'salon_booking_google_client_id', '' );
        $google_secret    = get_option( 'salon_booking_google_client_secret', '' );
        $google_refresh   = get_option( 'salon_booking_google_refresh_token', '' );
        $google_access    = get_option( 'salon_booking_google_access_token', '' );

        $outlook_enabled   = get_option( 'salon_booking_outlook_enabled', '0' );
        $outlook_tenant    = get_option( 'salon_booking_outlook_tenant_id', '' );
        $outlook_calendar  = get_option( 'salon_booking_outlook_calendar_id', '' );
        $outlook_client_id = get_option( 'salon_booking_outlook_client_id', '' );
        $outlook_secret    = get_option( 'salon_booking_outlook_client_secret', '' );
        $outlook_refresh   = get_option( 'salon_booking_outlook_refresh_token', '' );
        $outlook_access    = get_option( 'salon_booking_outlook_access_token', '' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Kalendersynchronisation', 'salon-booking' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'salon_booking_save_settings', 'salon_booking_nonce' ); ?>
                <input type="hidden" name="salon_booking_settings" value="1">

                <h2><?php esc_html_e( 'Google Calendar', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Aktivieren', 'salon-booking' ); ?></th>
                        <td><input type="checkbox" name="google_enabled" value="1" <?php checked( $google_enabled, '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Calendar ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="google_calendar_id" value="<?php echo esc_attr( $google_calendar ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Client ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="google_client_id" value="<?php echo esc_attr( $google_client_id ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Client Secret', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="google_client_secret" value="<?php echo esc_attr( $google_secret ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Refresh Token', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="google_refresh_token" value="<?php echo esc_attr( $google_refresh ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Access Token', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" readonly value="<?php echo esc_attr( $google_access ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Verbindung', 'salon-booking' ); ?></th>
                        <td>
                            <?php if ( $google_client_id && $google_secret ) : ?>
                                <a class="button button-primary" href="<?php echo esc_url( Salon_Booking_Calendar::get_google_authorize_url() ); ?>"><?php esc_html_e( 'Mit Google verbinden', 'salon-booking' ); ?></a>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e( 'Bitte Client ID und Secret speichern, bevor Sie verbinden.', 'salon-booking' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Outlook Calendar', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Aktivieren', 'salon-booking' ); ?></th>
                        <td><input type="checkbox" name="outlook_enabled" value="1" <?php checked( $outlook_enabled, '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Tenant ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="outlook_tenant_id" value="<?php echo esc_attr( $outlook_tenant ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Calendar ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="outlook_calendar_id" value="<?php echo esc_attr( $outlook_calendar ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Client ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="outlook_client_id" value="<?php echo esc_attr( $outlook_client_id ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Client Secret', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="outlook_client_secret" value="<?php echo esc_attr( $outlook_secret ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Refresh Token', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="outlook_refresh_token" value="<?php echo esc_attr( $outlook_refresh ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Access Token', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" readonly value="<?php echo esc_attr( $outlook_access ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Verbindung', 'salon-booking' ); ?></th>
                        <td>
                            <?php if ( $outlook_client_id && $outlook_secret ) : ?>
                                <a class="button button-primary" href="<?php echo esc_url( Salon_Booking_Calendar::get_outlook_authorize_url() ); ?>"><?php esc_html_e( 'Mit Outlook verbinden', 'salon-booking' ); ?></a>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e( 'Bitte Client ID und Secret speichern, bevor Sie verbinden.', 'salon-booking' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Einstellungen speichern', 'salon-booking' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function display_payment_settings_page() {
        $message = '';
        if ( isset( $_GET['status'] ) ) {
            if ( 'paypal_connected' === $_GET['status'] ) {
                $message = __( 'PayPal verbunden.', 'salon-booking' );
            } elseif ( 'klarna_connected' === $_GET['status'] ) {
                $message = __( 'Klarna verbunden.', 'salon-booking' );
            } elseif ( 'error' === $_GET['status'] && ! empty( $_GET['error'] ) ) {
                $message = sanitize_text_field( wp_unslash( $_GET['error'] ) );
            }
        }

        if ( isset( $_POST['salon_booking_payment_settings'] ) && check_admin_referer( 'salon_booking_save_payment_settings', 'salon_booking_payment_nonce' ) ) {
            $this->save_payment_settings();
            $message = __( 'Zahlungseinstellungen gespeichert.', 'salon-booking' );
        }

        $paypal_enabled = get_option( 'salon_booking_paypal_enabled', '0' );
        $paypal_client_id = get_option( 'salon_booking_paypal_client_id', '' );
        $paypal_secret = get_option( 'salon_booking_paypal_client_secret', '' );
        $paypal_mode = get_option( 'salon_booking_paypal_mode', 'sandbox' );

        $klarna_enabled = get_option( 'salon_booking_klarna_enabled', '0' );
        $klarna_username = get_option( 'salon_booking_klarna_username', '' );
        $klarna_password = get_option( 'salon_booking_klarna_password', '' );
        $klarna_mode = get_option( 'salon_booking_klarna_mode', 'playground' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Zahlungseinstellungen', 'salon-booking' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'salon_booking_save_payment_settings', 'salon_booking_payment_nonce' ); ?>
                <input type="hidden" name="salon_booking_payment_settings" value="1">

                <h2><?php esc_html_e( 'PayPal', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Aktivieren', 'salon-booking' ); ?></th>
                        <td><input type="checkbox" name="paypal_enabled" value="1" <?php checked( $paypal_enabled, '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Modus', 'salon-booking' ); ?></th>
                        <td>
                            <select name="paypal_mode">
                                <option value="sandbox" <?php selected( $paypal_mode, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (Test)', 'salon-booking' ); ?></option>
                                <option value="live" <?php selected( $paypal_mode, 'live' ); ?>><?php esc_html_e( 'Live (Produktion)', 'salon-booking' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Client ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="paypal_client_id" value="<?php echo esc_attr( $paypal_client_id ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Client Secret', 'salon-booking' ); ?></th>
                        <td><input type="password" class="regular-text" name="paypal_client_secret" value="<?php echo esc_attr( $paypal_secret ); ?>"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Klarna', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Aktivieren', 'salon-booking' ); ?></th>
                        <td><input type="checkbox" name="klarna_enabled" value="1" <?php checked( $klarna_enabled, '1' ); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Modus', 'salon-booking' ); ?></th>
                        <td>
                            <select name="klarna_mode">
                                <option value="playground" <?php selected( $klarna_mode, 'playground' ); ?>><?php esc_html_e( 'Playground (Test)', 'salon-booking' ); ?></option>
                                <option value="production" <?php selected( $klarna_mode, 'production' ); ?>><?php esc_html_e( 'Production', 'salon-booking' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Username', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="klarna_username" value="<?php echo esc_attr( $klarna_username ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Password', 'salon-booking' ); ?></th>
                        <td><input type="password" class="regular-text" name="klarna_password" value="<?php echo esc_attr( $klarna_password ); ?>"></td>
                    </tr>
                </table>

                <?php submit_button( __( 'Einstellungen speichern', 'salon-booking' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function display_reminder_settings_page() {
        $message = '';
        if ( isset( $_GET['status'] ) ) {
            if ( 'saved' === $_GET['status'] ) {
                $message = __( 'Erinnerungseinstellungen gespeichert.', 'salon-booking' );
            }
        }

        if ( isset( $_POST['salon_booking_reminder_settings'] ) && check_admin_referer( 'salon_booking_save_reminder_settings', 'salon_booking_reminder_nonce' ) ) {
            $this->save_reminder_settings();
            $message = __( 'Erinnerungseinstellungen gespeichert.', 'salon-booking' );
        }

        $reminder_hours = get_option( 'salon_booking_reminder_hours', 24 );
        $email_template = get_option( 'salon_booking_email_template', __( 'Hallo {name}, dies ist eine Erinnerung an Ihren Termin am {date} um {time} bei {stylist} für {service}.', 'salon-booking' ) );
        $sms_provider = get_option( 'salon_booking_sms_provider', '' );
        $twilio_sid = get_option( 'salon_booking_twilio_sid', '' );
        $twilio_token = get_option( 'salon_booking_twilio_token', '' );
        $twilio_from = get_option( 'salon_booking_twilio_from', '' );
        $massenversand_username = get_option( 'salon_booking_massenversand_username', '' );
        $massenversand_password = get_option( 'salon_booking_massenversand_password', '' );
        $massenversand_sender = get_option( 'salon_booking_massenversand_sender', '' );
        $onesignal_app_id = get_option( 'salon_booking_onesignal_app_id', '' );
        $onesignal_api_key = get_option( 'salon_booking_onesignal_api_key', '' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Erinnerungseinstellungen', 'salon-booking' ); ?></h1>
            <?php if ( $message ) : ?>
                <div class="updated notice is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <div class="notice notice-info inline">
                <p><?php esc_html_e( 'Für SMS-Erinnerungen können Sie Massenversand.de oder Twilio verwenden. Für Push-Benachrichtigungen nutzen Sie OneSignal. Tragen Sie die Zugangsdaten ein und speichern Sie die Einstellungen.', 'salon-booking' ); ?></p>
                <p><?php esc_html_e( 'OneSignal: Erstellen Sie eine App in OneSignal und kopieren Sie App ID und REST API Key hierher. Push-Nachrichten werden an alle Abonnenten gesendet.', 'salon-booking' ); ?></p>
                <p><?php esc_html_e( 'Massenversand.de: Benutzername, Passwort und Absenderkennung (Sender ID) angeben. Der Anbieter übernimmt den Versand der SMS.', 'salon-booking' ); ?></p>
            </div>
            <form method="post" action="">
                <?php wp_nonce_field( 'salon_booking_save_reminder_settings', 'salon_booking_reminder_nonce' ); ?>
                <input type="hidden" name="salon_booking_reminder_settings" value="1">

                <h2><?php esc_html_e( 'Allgemein', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Stunden vor Termin', 'salon-booking' ); ?></th>
                        <td><input type="number" name="reminder_hours" value="<?php echo esc_attr( $reminder_hours ); ?>" min="1" max="168"> Stunden</td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'E-Mail-Template', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Template', 'salon-booking' ); ?></th>
                        <td>
                            <textarea name="email_template" rows="5" cols="50"><?php echo esc_textarea( $email_template ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Verfügbare Platzhalter: {name}, {date}, {time}, {stylist}, {service}', 'salon-booking' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'SMS-Einstellungen', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'SMS-Anbieter', 'salon-booking' ); ?></th>
                        <td>
                            <select name="sms_provider">
                                <option value=""><?php esc_html_e( 'Keiner', 'salon-booking' ); ?></option>
                                <option value="twilio" <?php selected( $sms_provider, 'twilio' ); ?>>Twilio</option>
                                <option value="massenversand" <?php selected( $sms_provider, 'massenversand' ); ?>>Massenversand.de</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Twilio SID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="twilio_sid" value="<?php echo esc_attr( $twilio_sid ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Twilio Token', 'salon-booking' ); ?></th>
                        <td><input type="password" class="regular-text" name="twilio_token" value="<?php echo esc_attr( $twilio_token ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Twilio From-Nummer', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="twilio_from" value="<?php echo esc_attr( $twilio_from ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Massenversand Benutzer', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="massenversand_username" value="<?php echo esc_attr( $massenversand_username ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Massenversand Passwort', 'salon-booking' ); ?></th>
                        <td><input type="password" class="regular-text" name="massenversand_password" value="<?php echo esc_attr( $massenversand_password ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Absenderkennung', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="massenversand_sender" value="<?php echo esc_attr( $massenversand_sender ); ?>"></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Push-Einstellungen', 'salon-booking' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'OneSignal App ID', 'salon-booking' ); ?></th>
                        <td><input type="text" class="regular-text" name="onesignal_app_id" value="<?php echo esc_attr( $onesignal_app_id ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'OneSignal REST API Key', 'salon-booking' ); ?></th>
                        <td><input type="password" class="regular-text" name="onesignal_api_key" value="<?php echo esc_attr( $onesignal_api_key ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Abonnementhinweis', 'salon-booking' ); ?></th>
                        <td><p class="description"><?php esc_html_e( 'Push-Erinnerungen werden an alle OneSignal-Abonnenten gesendet.', 'salon-booking' ); ?></p></td>
                    </tr>
                </table>

                <?php submit_button( __( 'Einstellungen speichern', 'salon-booking' ) ); ?>
            </form>
        </div>
        <?php
    }

    private function save_reminder_settings() {
        update_option( 'salon_booking_reminder_hours', absint( $_POST['reminder_hours'] ?? 24 ) );
        update_option( 'salon_booking_email_template', sanitize_textarea_field( wp_unslash( $_POST['email_template'] ?? '' ) ) );
        update_option( 'salon_booking_sms_provider', sanitize_text_field( wp_unslash( $_POST['sms_provider'] ?? '' ) ) );
        update_option( 'salon_booking_twilio_sid', sanitize_text_field( wp_unslash( $_POST['twilio_sid'] ?? '' ) ) );
        update_option( 'salon_booking_twilio_token', sanitize_text_field( wp_unslash( $_POST['twilio_token'] ?? '' ) ) );
        update_option( 'salon_booking_twilio_from', sanitize_text_field( wp_unslash( $_POST['twilio_from'] ?? '' ) ) );
        update_option( 'salon_booking_massenversand_username', sanitize_text_field( wp_unslash( $_POST['massenversand_username'] ?? '' ) ) );
        update_option( 'salon_booking_massenversand_password', sanitize_text_field( wp_unslash( $_POST['massenversand_password'] ?? '' ) ) );
        update_option( 'salon_booking_massenversand_sender', sanitize_text_field( wp_unslash( $_POST['massenversand_sender'] ?? '' ) ) );
        update_option( 'salon_booking_onesignal_app_id', sanitize_text_field( wp_unslash( $_POST['onesignal_app_id'] ?? '' ) ) );
        update_option( 'salon_booking_onesignal_api_key', sanitize_text_field( wp_unslash( $_POST['onesignal_api_key'] ?? '' ) ) );
    }

    private function get_bookings() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';

        return $wpdb->get_results( "SELECT * FROM $table_name ORDER BY start_time DESC LIMIT 50" );
    }

    private function get_booking_events() {
        $events = array();
        $bookings = $this->get_bookings();

        foreach ( $bookings as $booking ) {
            $events[] = array(
                'title' => sprintf( '%s - %s', $booking->customer_name, Salon_Booking::get_service_name( $booking->service_id ) ),
                'start' => $booking->start_time,
                'end'   => $booking->end_time,
            );
        }

        return $events;
    }
}
