<?php
/**
 * Plugin Name: Salon Booking
 * Description: Online-Terminbuchungs System für Ayla's-HAARmonie.
 * Version: 0.1.0
 * Author: Benjamin Reimer
 * Text Domain: salon-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SALON_BOOKING_VERSION', '0.1.0' );
define( 'SALON_BOOKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SALON_BOOKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( SALON_BOOKING_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once SALON_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-activator.php';
require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-deactivator.php';
require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking.php';

register_activation_hook( __FILE__, array( 'Salon_Booking_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Salon_Booking_Deactivator', 'deactivate' ) );

function run_salon_booking() {
    $plugin = new Salon_Booking();
    $plugin->run();
}
run_salon_booking();
