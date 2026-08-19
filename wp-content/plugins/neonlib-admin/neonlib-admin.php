<?php
/**
 * Plugin Name: NeonLib Admin
 * Description: WordPress administratorsko sučelje za NeonLib račune i subscriptione.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: NeonLib
 * Text Domain: neonlib-admin
 */

defined( 'ABSPATH' ) || exit;

define( 'NEONLIB_ADMIN_VERSION', '0.1.0' );
define( 'NEONLIB_ADMIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEONLIB_ADMIN_URL', plugin_dir_url( __FILE__ ) );

require_once NEONLIB_ADMIN_PATH . 'includes/class-neonlib-admin-api-client.php';
require_once NEONLIB_ADMIN_PATH . 'includes/class-neonlib-admin.php';

NeonLib_Admin::instance();
