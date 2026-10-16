<?php
/**
 * Plugin Name: NeonLib Users
 * Description: KorisniÄŤki raÄŤuni, ovlasti i korisniÄŤko suÄŤelje za NeonLib.
 * Version: 0.7.5
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: NeonLib
 * Text Domain: neonlib-users
 */

defined( 'ABSPATH' ) || exit;

define( 'NEONLIB_USERS_VERSION', '0.7.5' );
define( 'NEONLIB_USERS_FILE', __FILE__ );
define( 'NEONLIB_USERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEONLIB_USERS_URL', plugin_dir_url( __FILE__ ) );

require_once NEONLIB_USERS_PATH . 'includes/class-neonlib-api-client.php';
require_once NEONLIB_USERS_PATH . 'includes/class-neonlib-users.php';
require_once NEONLIB_USERS_PATH . 'includes/class-neonlib-mobile-transfer.php';

register_activation_hook( __FILE__, array( 'NeonLib_Users', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NeonLib_Users', 'deactivate' ) );

NeonLib_Users::instance();
NeonLib_Mobile_Transfer::instance();

require_once NEONLIB_USERS_PATH . 'includes/class-neonlib-managed-ai.php';
NeonLib_Managed_AI::register();
