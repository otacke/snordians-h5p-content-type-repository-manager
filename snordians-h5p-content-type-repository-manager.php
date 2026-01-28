<?php
/**
 * Plugin Name: Snordian's H5P Content Type Repository Manager
 * Plugin URI: https://github.com/otacke/snordians-h5p-content-type-repository-manager
 * Text Domain: snordians-h5p-content-type-repository-manager
 * Description: Manage the H5P Content Type Hub.
 * Version: 1.0.10
 * Author: Oliver Tacke (SNORDIAN), Sustainum
 * Author URI: https://snordian.de
 * License: MIT
 *
 * @package snordians-h5p-content-type-repository-manager
 */

namespace Snordian\H5PContentTypeRepositoryManager;

// as suggested by the WordPress community.
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

// In theory, the scheduling should work without this, but in practice it does not.
define( 'ALTERNATE_WP_CRON', true );

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-capabilities.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-contenttyperepositoryconnector.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-main.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-options.php';

/**
 * Main plugin class.
 *
 * @return object NDLAH5PCARETAKER
 */
function init() {
	if ( ! is_admin() ) {
		return;
	}

	return new Main();
}

/**
 * Handle plugin activation.
 */
function on_activation() {
	Options::set_defaults();
	Capabilities::add_capabilities();
}

/**
 * Handle plugin deactivation.
 */
function on_deactivation() {
	$timestamp = wp_next_scheduled( 'snordiansh5pcontenttyperepositorymanager_update_libraries' );
	wp_unschedule_event( $timestamp, 'snordiansh5pcontenttyperepositorymanager_update_libraries' );
}

/**
 * Handle plugin uninstallation.
 */
function on_uninstall() {
	Options::delete_options();
	Capabilities::remove_capabilities();
	Main::update_endpoint_in_h5p_core( Options::get_default_endpoint_url_base() );

	$timestamp = wp_next_scheduled( 'snordiansh5pcontenttyperepositorymanager_update_libraries' );
	wp_unschedule_event( $timestamp, 'snordiansh5pcontenttyperepositorymanager_update_libraries' );
}

register_activation_hook( __FILE__, 'Snordian\H5PContentTypeRepositoryManager\on_activation' );
register_deactivation_hook( __FILE__, 'Snordian\H5PContentTypeRepositoryManager\on_deactivation' );
register_uninstall_hook( __FILE__, 'Snordian\H5PContentTypeRepositoryManager\on_uninstall' );

add_action( 'init', 'Snordian\H5PContentTypeRepositoryManager\init' );
