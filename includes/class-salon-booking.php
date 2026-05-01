<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Salon_Booking {
    public function __construct() {
        $this->load_dependencies();
        $this->ensure_database_schema();
        $this->admin = new Salon_Booking_Admin();
        $this->public = new Salon_Booking_Public();
        $this->reminder = new Salon_Booking_Reminder();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-admin.php';
        require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-public.php';
        require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-calendar.php';
        require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-payment.php';
        require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-reminder.php';
    }

    private function define_admin_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_post_salon_booking_google_callback', array( 'Salon_Booking_Calendar', 'handle_google_callback' ) );
        add_action( 'admin_post_salon_booking_outlook_callback', array( 'Salon_Booking_Calendar', 'handle_outlook_callback' ) );
    }

    private function define_public_hooks() {
        add_shortcode( 'salon_booking_form', array( $this, 'render_booking_form' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'wp_ajax_salon_booking_submit', array( $this, 'handle_booking_submission' ) );
        add_action( 'wp_ajax_nopriv_salon_booking_submit', array( $this, 'handle_booking_submission' ) );
        add_action( 'template_redirect', array( $this, 'handle_public_routes' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function run() {
        // Plugin execution entry point.
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Salon Booking', 'salon-booking' ),
            __( 'Salon Booking', 'salon-booking' ),
            'manage_options',
            'salon-booking',
            array( $this, 'display_admin_page' ),
            'dashicons-calendar-alt',
            26
        );
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style( 'salon-booking-admin', SALON_BOOKING_PLUGIN_URL . 'assets/css/admin.css', array(), SALON_BOOKING_VERSION );
    }

    public function enqueue_public_assets() {
        wp_enqueue_style( 'salon-booking-public', SALON_BOOKING_PLUGIN_URL . 'assets/css/style.css', array(), SALON_BOOKING_VERSION );
        wp_enqueue_script( 'salon-booking-public', SALON_BOOKING_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), SALON_BOOKING_VERSION, true );

        $one_signal_app_id = get_option( 'salon_booking_onesignal_app_id', '' );
        if ( $one_signal_app_id ) {
            wp_enqueue_script( 'onesignal-sdk', 'https://cdn.onesignal.com/sdks/OneSignalSDK.js', array(), null, true );
        }

        wp_localize_script( 'salon-booking-public', 'salonBooking', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'salon_booking_nonce' ),
            'oneSignalAppId' => $one_signal_app_id,
        ) );
    }

    public function display_admin_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Salon Booking', 'salon-booking' ) . '</h1><p>' . esc_html__( 'Buchungsverwaltung und Einstellungen werden hier angezeigt.', 'salon-booking' ) . '</p></div>';
    }

    public function render_booking_form() {
        $services = self::get_services();
        $stylists = self::get_stylists();

        ob_start();
        ?>
        <form id="salon-booking-form" method="post">
            <label for="customer_name"><?php esc_html_e( 'Name', 'salon-booking' ); ?></label>
            <input type="text" id="customer_name" name="customer_name" required>

            <label for="customer_email"><?php esc_html_e( 'E-Mail', 'salon-booking' ); ?></label>
            <input type="email" id="customer_email" name="customer_email" required>

            <label for="customer_phone"><?php esc_html_e( 'Telefon', 'salon-booking' ); ?></label>
            <input type="tel" id="customer_phone" name="customer_phone" required>

            <label for="stylist_id"><?php esc_html_e( 'Stylist', 'salon-booking' ); ?></label>
            <select id="stylist_id" name="stylist_id" required>
                <?php foreach ( $stylists as $id => $stylist ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $stylist['name'] . ' (' . $stylist['level'] . ')' ); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="service_id"><?php esc_html_e( 'Service', 'salon-booking' ); ?></label>
            <select id="service_id" name="service_id" required>
                <?php foreach ( $services as $id => $service ) : ?>
                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $service['name'] . ' - ' . $service['duration'] . ' Min. | ' . $service['price'] . '€' ); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="payment_method"><?php esc_html_e( 'Zahlungsmethode', 'salon-booking' ); ?></label>
            <select id="payment_method" name="payment_method" required>
                <option value=""><?php esc_html_e( 'Bitte wählen', 'salon-booking' ); ?></option>
                <option value="paypal"><?php esc_html_e( 'PayPal', 'salon-booking' ); ?></option>
                <option value="klarna"><?php esc_html_e( 'Klarna', 'salon-booking' ); ?></option>
            </select>

            <label for="appointment_date"><?php esc_html_e( 'Datum', 'salon-booking' ); ?></label>
            <input type="date" id="appointment_date" name="appointment_date" required>

            <label for="appointment_time"><?php esc_html_e( 'Uhrzeit', 'salon-booking' ); ?></label>
            <input type="time" id="appointment_time" name="appointment_time" required>

            <label for="notes"><?php esc_html_e( 'Notizen', 'salon-booking' ); ?></label>
            <textarea id="notes" name="notes"></textarea>

            <fieldset>
                <legend><?php esc_html_e( 'Terminerinnerungen', 'salon-booking' ); ?></legend>
                <label><input type="checkbox" name="reminder_email" value="1" checked> <?php esc_html_e( 'E-Mail-Erinnerung', 'salon-booking' ); ?></label><br>
                <label><input type="checkbox" name="reminder_sms" value="1"> <?php esc_html_e( 'SMS-Erinnerung', 'salon-booking' ); ?></label><br>
                <label><input type="checkbox" name="reminder_push" value="1"> <?php esc_html_e( 'Push-Benachrichtigung', 'salon-booking' ); ?></label>
            </fieldset>

            <button type="submit"><?php esc_html_e( 'Termin buchen', 'salon-booking' ); ?></button>
        </form>
        <div id="salon-booking-message"></div>
        <?php
        return ob_get_clean();
    }

    public function handle_booking_submission() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'salon_booking_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Ungültige Anfrage.', 'salon-booking' ) ) );
        }

        $customer_name   = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
        $customer_email  = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
        $customer_phone  = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) );
        $stylist_id      = absint( $_POST['stylist_id'] ?? 0 );
        $service_id      = absint( $_POST['service_id'] ?? 0 );
        $appointment_date = sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) );
        $appointment_time = sanitize_text_field( wp_unslash( $_POST['appointment_time'] ?? '' ) );
        $notes           = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
        $payment_method  = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );
        $reminder_email  = isset( $_POST['reminder_email'] ) ? 1 : 0;
        $reminder_sms    = isset( $_POST['reminder_sms'] ) ? 1 : 0;
        $reminder_push   = isset( $_POST['reminder_push'] ) ? 1 : 0;
        $one_signal_user_id = sanitize_text_field( wp_unslash( $_POST['one_signal_user_id'] ?? '' ) );

        if ( empty( $customer_name ) || empty( $customer_email ) || empty( $customer_phone ) || empty( $stylist_id ) || empty( $service_id ) || empty( $appointment_date ) || empty( $appointment_time ) || empty( $payment_method ) ) {
            wp_send_json_error( array( 'message' => __( 'Bitte alle Pflichtfelder ausfüllen.', 'salon-booking' ) ) );
        }

        $service = self::get_service( $service_id );
        if ( ! $service ) {
            wp_send_json_error( array( 'message' => __( 'Ungültiger Service.', 'salon-booking' ) ) );
        }

        $start_time = sanitize_text_field( $appointment_date . ' ' . $appointment_time );
        $start_time = date( 'Y-m-d H:i:s', strtotime( $start_time ) );
        $end_time   = date( 'Y-m-d H:i:s', strtotime( $start_time . ' +' . absint( $service['duration'] ) . ' minutes' ) );

        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';

        $conflict = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE stylist_id = %d AND start_time < %s AND end_time > %s AND status != %s",
            $stylist_id,
            $end_time,
            $start_time,
            'cancelled'
        ) );

        if ( $conflict > 0 ) {
            wp_send_json_error( array( 'message' => __( 'Dieser Friseur ist in der gewählten Zeit bereits gebucht. Bitte wählen Sie eine andere Zeit.', 'salon-booking' ) ) );
        }

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'customer_name'       => $customer_name,
                'customer_email'      => $customer_email,
                'customer_phone'      => $customer_phone,
                'stylist_id'          => $stylist_id,
                'service_id'          => $service_id,
                'start_time'          => $start_time,
                'end_time'            => $end_time,
                'notes'               => $notes,
                'reminder_email'      => $reminder_email,
                'reminder_sms'        => $reminder_sms,
                'reminder_push'       => $reminder_push,
                'onesignal_player_id' => $one_signal_user_id,
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s'
            )
        );

        if ( false === $inserted ) {
            wp_send_json_error( array( 'message' => __( 'Termin konnte nicht gespeichert werden.', 'salon-booking' ) ) );
        }

        $booking_id = $wpdb->insert_id;

        // Zahlung verarbeiten
        try {
            $payment_result = Salon_Booking_Payment::process_payment( $booking_id, $payment_method );
            if ( is_wp_error( $payment_result ) ) {
                // Zahlung fehlgeschlagen, Buchung trotzdem speichern aber als unpaid markieren
                $wpdb->update(
                    $table_name,
                    array( 'status' => 'payment_failed' ),
                    array( 'id' => $booking_id ),
                    array( '%s' ),
                    array( '%d' )
                );
                wp_send_json_error( array( 'message' => $payment_result->get_error_message() ) );
            }

            // Für PayPal: Redirect URL zurückgeben
            if ( isset( $payment_result['redirect_url'] ) ) {
                wp_send_json_success( array( 'redirect_url' => $payment_result['redirect_url'] ) );
            }

            if ( isset( $payment_result['checkout_url'] ) ) {
                wp_send_json_success( array( 'checkout_url' => $payment_result['checkout_url'] ) );
            }

            // Zahlung abgeschlossen oder kein Weiterleitungsbedarf
            Salon_Booking_Calendar::sync_booking( $booking_id );

            wp_send_json_success( array( 'message' => __( 'Termin erfolgreich gebucht.', 'salon-booking' ) ) );
        } catch ( Exception $e ) {
            error_log( '[Salon Booking] Payment processing exception: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.', 'salon-booking' ) ) );
        }
    }

    public function handle_public_routes() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );
        $home_path = trim( parse_url( home_url(), PHP_URL_PATH ), '/' );

        if ( $home_path && strpos( $path, $home_path ) === 0 ) {
            $path = trim( substr( $path, strlen( $home_path ) ), '/' );
        }

        if ( preg_match( '#^salon-booking/payment-success/?$#', $path ) ) {
            $this->render_payment_success_page();
            exit;
        }

        if ( preg_match( '#^salon-booking/payment-cancel/?$#', $path ) ) {
            $this->render_payment_cancel_page();
            exit;
        }

        if ( preg_match( '#^salon-booking/checkout/?$#', $path ) ) {
            $this->render_klarna_checkout_page();
            exit;
        }

        if ( preg_match( '#^salon-booking/klarna-confirmation/?$#', $path ) ) {
            $this->render_klarna_confirmation_page();
            exit;
        }
    }

    private function render_public_page( $title, $message ) {
        status_header( 200 );
        nocache_headers();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title>';
        wp_head();
        echo '</head><body><div class="wrap"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></div>';
        wp_footer();
        echo '</body></html>';
    }

    private function render_payment_success_page() {
        $booking_id = absint( $_GET['booking_id'] ?? 0 );
        $payment_id = sanitize_text_field( wp_unslash( $_GET['paymentId'] ?? '' ) );
        $payer_id   = sanitize_text_field( wp_unslash( $_GET['PayerID'] ?? '' ) );

        if ( ! $booking_id ) {
            $this->render_public_page( __( 'Zahlungsfehler', 'salon-booking' ), __( 'Die Buchungs-ID fehlt. Bitte prüfen Sie den Link oder kontaktieren Sie den Salon.', 'salon-booking' ) );
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $booking_id ) );
        if ( ! $booking ) {
            $this->render_public_page( __( 'Zahlungsfehler', 'salon-booking' ), __( 'Die Buchung wurde nicht gefunden. Bitte kontaktieren Sie den Salon.', 'salon-booking' ) );
            return;
        }

        if ( $payment_id && $payer_id ) {
            $result = Salon_Booking_Payment::complete_paypal_payment( $booking_id, $payment_id, $payer_id );
            if ( is_wp_error( $result ) ) {
                $this->render_public_page( __( 'Zahlungsfehler', 'salon-booking' ), $result->get_error_message() );
                return;
            }
        } else {
            $this->render_public_page( __( 'Zahlungsfehler', 'salon-booking' ), __( 'Unbekannter Zahlungsabschluss. Bitte nutzen Sie die Klarna-Bestätigung oder kontaktieren Sie den Salon.', 'salon-booking' ) );
            return;
        }

        Salon_Booking_Calendar::sync_booking( $booking_id );
        $this->render_public_page( __( 'Zahlung erfolgreich', 'salon-booking' ), __( 'Vielen Dank! Ihre Zahlung wurde bestätigt und Ihr Termin ist gebucht.', 'salon-booking' ) );
    }

    private function render_payment_cancel_page() {
        $booking_id = absint( $_GET['booking_id'] ?? 0 );
        if ( $booking_id ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'salon_appointments';
            $wpdb->update( $table_name, array( 'status' => 'cancelled' ), array( 'id' => $booking_id ), array( '%s' ), array( '%d' ) );
        }

        $this->render_public_page( __( 'Zahlung abgebrochen', 'salon-booking' ), __( 'Die Zahlung wurde abgebrochen. Ihre Buchung ist noch nicht bestätigt.', 'salon-booking' ) );
    }

    private function render_klarna_confirmation_page() {
        $booking_id = absint( $_GET['booking_id'] ?? 0 );
        $order_id   = sanitize_text_field( wp_unslash( $_GET['order_id'] ?? '' ) );

        if ( ! $booking_id || ! $order_id ) {
            $this->render_public_page( __( 'Klarna Bestätigung fehlgeschlagen', 'salon-booking' ), __( 'Die Klarna-Bestätigung konnte nicht abgeschlossen werden. Bitte kontaktieren Sie den Salon.', 'salon-booking' ) );
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';
        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $booking_id ) );

        if ( ! $booking || 'klarna' !== $booking->payment_method ) {
            $this->render_public_page( __( 'Klarna Bestätigung fehlgeschlagen', 'salon-booking' ), __( 'Ungültige Buchung oder Zahlungsart. Bitte kontaktieren Sie den Salon.', 'salon-booking' ) );
            return;
        }

        $wpdb->update(
            $table_name,
            array(
                'status'         => 'confirmed',
                'payment_status' => 'completed',
                'payment_id'     => $order_id,
            ),
            array( 'id' => $booking_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        Salon_Booking_Calendar::sync_booking( $booking_id );
        $this->render_public_page( __( 'Klarna Zahlung erfolgreich', 'salon-booking' ), __( 'Vielen Dank! Ihre Klarna-Zahlung wurde bestätigt und Ihr Termin ist gebucht.', 'salon-booking' ) );
    }

    private function ensure_database_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'salon_appointments';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table_name LIKE %s", 'onesignal_player_id' ) ) !== 'onesignal_player_id' ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN onesignal_player_id varchar(255) NULL" );
        }
    }

    private function render_klarna_checkout_page() {
        $booking_id = absint( $_GET['booking_id'] ?? 0 );
        $client_token = Salon_Booking_Payment::get_klarna_client_token( $booking_id );

        if ( ! $booking_id || ! $client_token ) {
            $this->render_public_page( __( 'Klarna Checkout Fehler', 'salon-booking' ), __( 'Klarna Checkout konnte nicht gestartet werden. Bitte versuchen Sie es später erneut.', 'salon-booking' ) );
            return;
        }

        status_header( 200 );
        nocache_headers();
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Klarna Checkout', 'salon-booking' ) . '</title>';
        wp_head();
        echo '<script>function initKlarna(){ if ( window.Klarna && window.Klarna.Payments ){ Klarna.Payments.init({ client_token: "' . esc_js( $client_token ) . '" }); Klarna.Payments.load({ container: "#klarna-checkout", payment_method_category: "pay_later" }, function(response){ if ( response && response.show_form ) { return; } document.getElementById("klarna-checkout").innerHTML = "<p>' . esc_js( __( 'Klarna konnte nicht geladen werden. Bitte aktualisieren Sie die Seite.', 'salon-booking' ) ) . '</p>"; }); } else { setTimeout(initKlarna, 250); } }</script>';
        echo '<script async src="https://x.klarnacdn.net/kp/lib/v1/api.js" onload="initKlarna()"></script>';
        echo '</head><body><div class="wrap"><h1>' . esc_html__( 'Klarna Checkout', 'salon-booking' ) . '</h1>';
        echo '<div id="klarna-checkout"></div>';
        echo '</div>';
        wp_footer();
        echo '</body></html>';
    }

    public function register_rest_routes() {
        register_rest_route( 'salon-booking/v1', '/paypal-webhook', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_paypal_webhook' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'salon-booking/v1', '/klarna-push', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'handle_klarna_webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_paypal_webhook( $request ) {
        $data = $request->get_json_params();
        Salon_Booking_Payment::handle_paypal_webhook( $data );
        return new WP_REST_Response( 'OK', 200 );
    }

    public function handle_klarna_webhook( $request ) {
        $data = $request->get_params();
        Salon_Booking_Payment::handle_klarna_webhook( $data );
        return new WP_REST_Response( 'OK', 200 );
    }

    public static function get_services() {
        return array(
            1 => array(
                'name'     => __( 'Haarschnitt', 'salon-booking' ),
                'duration' => 60,
                'price'    => 45,
            ),
            2 => array(
                'name'     => __( 'Färben', 'salon-booking' ),
                'duration' => 120,
                'price'    => 90,
            ),
            3 => array(
                'name'     => __( 'Styling', 'salon-booking' ),
                'duration' => 45,
                'price'    => 35,
            ),
        );
    }

    public static function get_service( $id ) {
        $services = self::get_services();
        return isset( $services[ $id ] ) ? $services[ $id ] : null;
    }

    public static function get_stylists() {
        return array(
            1 => array( 'name' => __( 'Maria', 'salon-booking' ), 'level' => __( 'Vollzeit / Saloninhaber / Meisterin', 'salon-booking' ) ),
            2 => array( 'name' => __( 'Sonia', 'salon-booking' ), 'level' => __( 'Vollzeit', 'salon-booking' ) ),
            3 => array( 'name' => __( 'Karina', 'salon-booking' ), 'level' => __( 'Teilzeit', 'salon-booking' ) ),
            4 => array( 'name' => __( 'Charleen', 'salon-booking' ), 'level' => __( 'Teilzeit', 'salon-booking' ) ),
            5 => array( 'name' => __( 'Laura', 'salon-booking' ), 'level' => __( 'Auszubildende', 'salon-booking' ) ),
        );
    }

    public static function get_stylist( $id ) {
        $stylists = self::get_stylists();
        return isset( $stylists[ $id ] ) ? $stylists[ $id ] : null;
    }

    public static function get_service_name( $id ) {
        $service = self::get_service( $id );
        return $service ? $service['name'] : '';
    }

    public static function get_stylist_name( $id ) {
        $stylist = self::get_stylist( $id );
        return $stylist ? $stylist['name'] : '';
    }
}
