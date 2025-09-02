<?php
/**
 * Plugin Name: Gravity Forms - Choice Inventory
 * Plugin URI:  https://github.com/refactorau/gf-choice-inventory
 * Description: Per-choice inventory limits for Gravity Forms choice fields (Radio/Checkbox/Select, incl. image choices). Editor UI inside the field settings. Optional: allow submissions with sold-out choices. Entries are tagged when a sold-out choice was selected at submission time and shown in list/detail views.
 * Version:     1.8.0
 * Author:      Refactor
 * Author URI:  https://refactor.com.au/
 * Text Domain: gfci
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: MIT
 *
 * @package GFCI
 */
if (!defined('ABSPATH')) {
	exit;
}

define('GFCI_VERSION', '1.8.0');
define('GFCI_FILE', __FILE__);
define('GFCI_BASENAME', plugin_basename(__FILE__));
define('GFCI_DIR', plugin_dir_path(__FILE__));
define('GFCI_URL', plugin_dir_url(__FILE__));

/**
 * Load i18n.
 */
function gfci_load_textdomain()
{
	load_plugin_textdomain('gfci', false, dirname(GFCI_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'gfci_load_textdomain');

/**
 * Boot the Add-On once Gravity Forms is loaded.
 */
function gfci_gravityforms_loaded()
{
	if (!class_exists('GFForms')) {
		add_action('admin_notices', function () {
			if (current_user_can('activate_plugins')) {
				echo '<div class="notice notice-error"><p><strong>Gravity Forms - Choice Inventory</strong> requires Gravity Forms to be installed and active.</p></div>';
			}
		});
		return;
	}
	require_once GFCI_DIR . 'includes/class-gfci-addon.php';
	GF_Choice_Inventory_AddOn::get_instance();
}
add_action('gform_loaded', 'gfci_gravityforms_loaded', 5);
