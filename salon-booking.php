<?php /**

Plugin Name: Salon Booking

Description: Online-Terminbuchung für einen Frisörsalon.

Version: 0.1.0

Author: Benjamin

Text Domain: salon-booking */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**

Plugin Konstanten */ define( 'SALON_BOOKING_VERSION', '0.1.0' ); define( 'SALON_BOOKING_PLUGIN_DIR', plugin_dir_path( FILE ) ); define( 'SALON_BOOKING_PLUGIN_URL', plugin_dir_url( FILE ) );

/**

Autoloader (falls Composer genutzt wird) */ $autoload = SALON_BOOKING_PLUGIN_DIR . 'vendor/autoload.php'; if ( file_exists( $autoload ) ) { require_once $autoload; }

/**

Plugin-Klassen laden */ require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-activator.php'; require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking-deactivator.php'; require_once SALON_BOOKING_PLUGIN_DIR . 'includes/class-salon-booking.php';

/**

Aktivierung / Deaktivierung */ register_activation_hook( FILE, array( 'Salon_Booking_Activator', 'activate' ) );

register_deactivation_hook( FILE, array( 'Salon_Booking_Deactivator', 'deactivate' ) );

/**

Plugin starten */ function run_salon_booking() { $plugin = new Salon_Booking(); $plugin->run(); } run_salon_booking();
