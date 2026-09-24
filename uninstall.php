<?php
// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}


if ( class_exists( 'GFAPI' ) ){

	$forms = GFAPI::get_forms();

	foreach ( $forms as $form ) {

		$form_id = $form['id'];

		wp_clear_scheduled_hook( 'csv_export_' . $form_id );

		// supprime les reglages du plugin stockes dans les metadonnees du formulaire
		if ( isset( $form['automatic_csv_export_for_gravity_forms'] ) ) {
			unset( $form['automatic_csv_export_for_gravity_forms'] );
			GFAPI::update_form( $form, $form_id );
		}

	}

}

// supprime le dossier des exports temporaires
$upload_dir = wp_upload_dir();
$export_dir = trailingslashit( $upload_dir['basedir'] ) . 'gf-automatic-csv-export/';

if ( is_dir( $export_dir ) ) {
	foreach ( (array) glob( $export_dir . '{,.}*', GLOB_BRACE ) as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $export_dir );
}
