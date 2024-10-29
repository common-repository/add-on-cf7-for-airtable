<?php
/**
 * Hooks used by the plugin.
 *
 * @package add-on-cf7-for-airtable
 */

namespace WPC_WPCF7_AT\Hooks;

use WPC_WPCF7_AT\Options;
use WPC_WPCF7_AT\Helpers;
use WPC_WPCF7_AT\CFP;

defined( 'ABSPATH' ) || exit;


// On plugin activation, keep the current plugin version in an option.
add_action( 'add-on-cf7-for-airtable/plugin-activated', 'wpconnect_wpcf7_airtable_save_plugin_version', 10, 1 );

// Register WPCF7 Airtable service.
add_action( 'wpcf7_init', 'wpconnect_wpcf7_airtable_register_service', 1, 0 );

// *******************************
// *** CONTACT FORM PROPERTIES ***
// *******************************

// Register the wpc_airtable contact form property.
add_filter( 'wpcf7_pre_construct_contact_form_properties', 'WPC_WPCF7_AT\CFP\register_property', 10, 1 );
// Build the editor panel for the wpc_airtable property.
add_filter( 'wpcf7_editor_panels', 'WPC_WPCF7_AT\CFP\editor_panels', 10, 1 );
// Save the wpc_airtable property value.
add_action( 'wpcf7_save_contact_form', 'WPC_WPCF7_AT\CFP\save_contact_form', 10, 1 );

// ***********************************
// *** CONTACT FORM FIELDS MAPPING ***
// ***********************************

add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_text' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_email' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_url' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_tel' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_number' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_range' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_textarea' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_select' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_checkbox' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_radio' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_acceptance' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_date' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_file' );
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_hidden');
add_filter( 'add-on-cf7-for-airtable/wpcf7-field-mapper/fields', 'WPC_WPCF7_AT\Fields\map_wpcf7_custom');

// *******************************
// *** ENTRY ***
// *******************************

// Save contact form submission to Airtable table.
add_action( 'wpcf7_before_send_mail', 'WPC_WPCF7_AT\Entry\save_wpcf7_entry_in_airtable_table', 10, 3 );

// Delete files uploads after the form has been processed.
add_action( 'add-on-cf7-for-airtable/delete-upload-files', 'WPC_WPCF7_AT\Entry\delete_uploads', 10, 3 );

/**
 * AJAX callback function to fetch tables from Airtable for a specific base.
 *
 * Reads the base ID from the AJAX request, fetches the tables from Airtable,
 * and then returns them as a JSON response.
 *
 * Hooks:
 * 'wp_ajax_fetch_airtable_tables' - For logged in users.
 */
function fetch_airtable_tables_callback() {
	if ( ! check_ajax_referer( 'my-ajax-nonce', 'security' ) || ! isset( $_POST['app_id'] ) ) {
		echo wp_json_encode( array() );
		wp_die();
	}
	$base_id = sanitize_text_field( wp_unslash( $_POST['app_id'] ) );
	$tables  = Helpers\get_airtable_tables_token( $base_id );
	echo wp_json_encode( $tables );
	wp_die();
}
add_action( 'wp_ajax_fetch_airtable_tables', 'WPC_WPCF7_AT\Hooks\fetch_airtable_tables_callback' );

/**
 * Checks all Contact Form 7 forms to determine if any are configured with a file field mapped for Airtable integration.
 *
 * This function iterates through all CF7 forms and checks if they have metadata indicating a mapping to Airtable fields.
 * It specifically looks for file fields because their presence requires additional server configuration to ensure
 * files can be uploaded and accessed correctly.
 *
 * @return void
 */
function check_cf7_forms_for_file_fields(){
	if (function_exists('wpcf7')) {
		$forms = \WPCF7_ContactForm::find();
		foreach ($forms as $form) {
			$meta = get_post_meta($form->id(), '_wpc_airtable', true);
			if ($meta) {
				$meta_array = maybe_unserialize($meta);
				if (!empty($meta_array['mapping'])) {
					foreach ($meta_array['mapping'] as $field_key => $airtable_field) {
						if (strpos($field_key, 'file') !== false) {
							Helpers\validate_cf7_airtable_file_integration();
							break 2;
						}
					}
				}
			}
		}
	}
};
add_action('admin_init', 'WPC_WPCF7_AT\Hooks\check_cf7_forms_for_file_fields');
