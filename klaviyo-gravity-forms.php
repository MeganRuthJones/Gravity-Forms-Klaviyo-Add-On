<?php
/**
 * Plugin Name: Gravity Forms Klaviyo Add-On
 * Plugin URI: 
 * Description: Integrates Gravity Forms with the Klaviyo email marketing platform to create and update subscribers automatically.
 * Version: 1.0.0
 * Author: Megan Jones
 * Text Domain: gravityforms-klaviyo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'GF_KLAVIYO_VERSION', '1.0.0' );
define( 'GF_KLAVIYO_MIN_GF_VERSION', '2.5' );
define( 'GF_KLAVIYO_PLUGIN_FILE', __FILE__ );
define( 'GF_KLAVIYO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF_KLAVIYO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the plugin
 */
function gf_klaviyo_init() {
	// Check if Gravity Forms is installed and activated
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_notices', 'gf_klaviyo_gravity_forms_required_notice' );
		return;
	}

	// Check Gravity Forms version
	if ( ! version_compare( GFCommon::$version, GF_KLAVIYO_MIN_GF_VERSION, '>=' ) ) {
		add_action( 'admin_notices', 'gf_klaviyo_gravity_forms_version_notice' );
		return;
	}

	// Load the add-on
	require_once GF_KLAVIYO_PLUGIN_DIR . 'class-gf-klaviyo.php';
	GFAddOn::register( 'GF_Klaviyo' );
}
add_action( 'gform_loaded', 'gf_klaviyo_init', 5 );

/**
 * Display notice if Gravity Forms is not installed
 */
function gf_klaviyo_gravity_forms_required_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Gravity Forms Klaviyo Add-On requires Gravity Forms to be installed and activated.', 'gravityforms-klaviyo' ); ?></p>
	</div>
	<?php
}

/**
 * Display notice if Gravity Forms version is too old
 */
function gf_klaviyo_gravity_forms_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Minimum required version, 2: Current version */
				esc_html__( 'Gravity Forms Klaviyo Add-On requires Gravity Forms version %1$s or higher. You are running version %2$s.', 'gravityforms-klaviyo' ),
				esc_html( GF_KLAVIYO_MIN_GF_VERSION ),
				esc_html( GFCommon::$version )
			);
			?>
		</p>
	</div>
	<?php
}

