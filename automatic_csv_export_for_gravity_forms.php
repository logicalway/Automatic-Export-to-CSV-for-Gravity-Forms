<?php
/*
Plugin Name: Automatic Export to CSV for Gravity Forms
Plugin URI: http://gravitycsv.com
Description: Automatically send an email containing a CSV export of your Gravity Form entries on a schedule.
Version: 0.3.3
Author: Alex Cavender
Author URI: http://alexcavender.com/
Text Domain: automatic_csv_export_for_gravity_forms
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();

define( 'GF_AUTOMATIC_CSV_VERSION', '0.3.3' );

require_once( 'inc/excelwriter.inc.php' );

// require 'api.php';

/**
 * Deserialise une valeur d'export sans instancier d'objets (anti injection d'objet PHP).
 *
 * @param mixed $value
 * @return array
 */
function gf_auto_csv_safe_unserialize( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	$list = is_string( $value ) ? @unserialize( $value, array( 'allowed_classes' => false ) ) : false;
	return is_array( $list ) ? $list : array();
}

class GravityFormsAutomaticCSVExport {

	public function __construct() {

		// GFAPI peut ne pas etre charge a ce stade (ordre alphabetique des plugins) :
		// les hooks sont donc toujours enregistres et testent GFAPI au moment de l'appel
		add_filter( 'cron_schedules', array( $this, 'add_weekly' ) );
		add_filter( 'cron_schedules', array( $this, 'add_monthly' ) );
		add_action( 'admin_init', array( $this, 'gforms_create_schedules' ) );
		add_action( 'init', array( $this, 'register_cron_hooks' ) );
		add_action( 'wp_ajax_gf_auto_csv_send_test', array( $this, 'ajax_send_test' ) );

	}


	/**
		* Attache l'export aux evenements cron des formulaires actives
		*
		* @return void
	*/
	public function register_cron_hooks() {

		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		foreach ( GFAPI::get_forms() as $form ) {
			$settings = isset( $form['automatic_csv_export_for_gravity_forms'] ) ? $form['automatic_csv_export_for_gravity_forms'] : array();
			if ( ! empty( $settings['enabled'] ) ) {
				add_action( 'csv_export_' . $form['id'], array( $this, 'gforms_automated_export' ) );
			}
		}

	}


	/**
		* Dossier prive des exports temporaires (acces web interdit)
		*
		* @return string Chemin avec slash final, ou '' en cas d'echec
	*/
	public static function get_export_dir() {

		$upload_dir = wp_upload_dir();
		$dir = trailingslashit( $upload_dir['basedir'] ) . 'gf-automatic-csv-export/';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) ) {
			return '';
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" );
		}

		return $dir;

	}


	/**
		* Set up weekly schedule as an interval
		*
		* @since 0.1
		*
		* @param array $schedules.
		* @return array $schedules.
	*/
	public function add_weekly( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __('Once Weekly')
		);
		return $schedules;
	}


	/**
		* Set up monthly schedule as an interval
		*
		* @since 0.1
		*
		* @param array $schedules.
		* @return array $schedules.
	*/
	public function add_monthly( $schedules ) {
		// add a 'weekly' schedule to the existing set
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display' => __('Once Monthly')
		);
		return $schedules;
	}


	/**
		* Create schedules for each enabled form
		*
		* @since 0.1
		*
		* @param void
		* @return void
	*/
	public function gforms_create_schedules(){

		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$forms = GFAPI::get_forms();

		foreach ( $forms as $form ) {

			$form_id = $form['id'];

			$enabled = isset( $form['automatic_csv_export_for_gravity_forms'] ) ? $form['automatic_csv_export_for_gravity_forms']['enabled'] : 0;

			if ( $enabled == 1 ) {

				$hook      = 'csv_export_' . $form_id;
				$frequency = ! empty( $form['automatic_csv_export_for_gravity_forms']['csv_export_frequency'] ) ? $form['automatic_csv_export_for_gravity_forms']['csv_export_frequency'] : 'daily';

				// replanifie si la frequence a change
				if ( wp_next_scheduled( $hook ) && wp_get_schedule( $hook ) !== $frequency ) {
					wp_clear_scheduled_hook( $hook );
				}

				if ( ! wp_next_scheduled( $hook ) ) {
					wp_schedule_event( time(), $frequency, $hook );
				}

			} elseif ( wp_next_scheduled( 'csv_export_' . $form_id ) ) {

				wp_clear_scheduled_hook( 'csv_export_' . $form_id );

			}

		}

	}


	/**
		* Run Automated Exports
		*
		* @since 0.1
		*
		* @param void
		* @return void
	*/
	public function gforms_automated_export() {

		if ( ! preg_match( '/^csv_export_(\d+)$/', current_filter(), $matches ) ) {
			return;
		}

		$this->run_export( (int) $matches[1] );

	}


	/**
		* Genere l'export d'un formulaire et l'envoie par e-mail
		*
		* @param int   $form_id
		* @param array $overrides Reglages surchargeant ceux du formulaire (test depuis l'ecran de reglages)
		* @param bool  $is_test   Mode test : envoie meme sans entree, sujet prefixe [TEST]
		* @return array array( 'success' => bool, 'message' => string )
	*/
	public function run_export( $form_id, $overrides = array(), $is_test = false ) {

		if ( ! class_exists( 'GFAPI' ) ) {
			return array( 'success' => false, 'message' => 'Gravity Forms is not active.' );
		}

		$form = GFAPI::get_form( $form_id ); // get form by ID
		if ( ! $form ) {
			return array( 'success' => false, 'message' => 'Form not found.' );
		}

		$form['automatic_csv_export_for_gravity_forms'] = array_merge(
			isset( $form['automatic_csv_export_for_gravity_forms'] ) ? (array) $form['automatic_csv_export_for_gravity_forms'] : array(),
			$overrides
		);

		$search_criteria = array();

        $format_export = isset( $form['automatic_csv_export_for_gravity_forms']['format_export'] ) ? $form['automatic_csv_export_for_gravity_forms']['format_export'] : 'csv';

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'all' ) {
			$search_criteria = array();
		}

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'previous_day' ) {
			$search_criteria['start_date'] = date('Y-m-d', time() - 60 * 60 * 24 );
			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 );
		}

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'previous_week' ) {
			$search_criteria['start_date'] = date('Y-m-d', time() - 7 * 24 * 60 * 60 );
			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 );

		}

		if ( $form['automatic_csv_export_for_gravity_forms']['search_criteria'] == 'previous_month' ) {
			$search_criteria['start_date'] = date('Y-m-d', time() - 31 * 24 * 60 * 60 );
			$search_criteria['end_date'] = date('Y-m-d', time() - 60 * 60 * 24 );
		}

		require_once( GFCommon::get_base_path() . '/export.php' );

		$export_fields = array();

        foreach( $form['fields'] as $field ) {

            //see if this is a multi-field, like name or address
            if ( is_array($field["inputs"] ) ) {
                // loop through inputs
                foreach( $field["inputs"] as $input ) {
                    $export_fields[] = $input["id"];
                }
            } else {
                $export_fields[] = $field->id;
            }

		}

        // aditionnal field
        $export_fields[] = 'created_by';
        $export_fields[] = 'id';
        $export_fields[] = 'date_created';
        $export_fields[] = 'source_url';
        $export_fields[] = 'user_agent';
        $export_fields[] = 'ip';

		$date_start = (( isset($search_criteria['start_date']) )?$search_criteria['start_date']:'');
		$date_end   = (( isset($search_criteria['end_date']) )?$search_criteria['end_date']:'');

		// jeton aleatoire : nom de fichier imprevisible
		$export_id = $form_id . '-' . date('Y-m-d-giA') . '-' . wp_generate_password( 12, false );

		$email_address = isset( $form['automatic_csv_export_for_gravity_forms']['email_address'] ) ? trim( $form['automatic_csv_export_for_gravity_forms']['email_address'] ) : '';

		if ( ! is_email( $email_address ) ) {
			GFCommon::log_error( __METHOD__ . '(): adresse e-mail invalide pour le formulaire #' . $form_id );
			return array( 'success' => false, 'message' => 'Invalid e-mail address.' );
		}

		$export = self::start_automated_export( $form, 0, $export_id, $export_fields, $date_start, $date_end );

		$path = untrailingslashit( self::get_export_dir() );

		$email_subject = isset( $form['automatic_csv_export_for_gravity_forms']['email_subject'] ) ? $form['automatic_csv_export_for_gravity_forms']['email_subject'] : '';
		if ( ! $email_subject ) {
			$email_subject = 'Automatic Form Export';
		}

		$email_content = isset( $form['automatic_csv_export_for_gravity_forms']['email_content'] ) ? $form['automatic_csv_export_for_gravity_forms']['email_content'] : '';
		if ( ! $email_content ) {
			$email_content = 'CSV export is attached to this message'."\n\r\n\r\n\r";
		}

		if ( $is_test ) {
			$email_subject = '[TEST] ' . $email_subject;
		}

		$file_csv = $path . '/export-' . $export_id . '.csv';
		$file_xls = $path . '/export-' . $export_id . '.xls';

		$cleanup = function () use ( $file_csv, $file_xls ) {
			foreach ( array( $file_csv, $file_xls ) as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		};

		// le cron n'envoie rien sans entree ; le test envoie toujours (en-tetes seuls)
		$has_file = file_exists( $file_csv ) && filesize( $file_csv ) > 0;
		if ( ! $has_file || ( ! $is_test && empty( $export['total'] ) ) ) {

			GFCommon::log_error( __METHOD__ . '(): aucun export a envoyer pour le formulaire #' . $form_id );
			$cleanup();

			return array( 'success' => false, 'message' => 'No entries to export.' );

		}

		$attachment = ( 'xls' === $format_export && file_exists( $file_xls ) ) ? $file_xls : $file_csv;

		$mail_error = '';
		$on_failure = function ( $error ) use ( &$mail_error ) {
			$mail_error = $error->get_error_message();
		};
		add_action( 'wp_mail_failed', $on_failure );

		// https://developer.wordpress.org/reference/functions/get_option/
		$headers = array( 'From: ' . get_option( 'blogname' ) . ' <' . get_option( 'admin_email' ) . '>' );
		$sent    = wp_mail( $email_address, $email_subject, $email_content, $headers, array( $attachment ) );

		remove_action( 'wp_mail_failed', $on_failure );
		$cleanup();

		if ( ! $sent ) {
			GFCommon::log_error( __METHOD__ . '(): echec wp_mail formulaire #' . $form_id . ' ' . $mail_error );
			return array( 'success' => false, 'message' => $mail_error ? $mail_error : 'wp_mail() failed.' );
		}

		return array( 'success' => true, 'message' => $email_address );

	}


	/**
		* AJAX : envoie un export de test avec les reglages affiches (meme non enregistres)
		*
		* @return void
	*/
	public function ajax_send_test() {

		check_ajax_referer( 'gf_auto_csv_test', 'nonce' );

		if ( ! class_exists( 'GFCommon' ) || ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		if ( ! $form_id ) {
			wp_send_json_error( array( 'message' => 'Invalid form.' ), 400 );
		}

		$format   = isset( $_POST['format_export'] ) && 'xls' === $_POST['format_export'] ? 'xls' : 'csv';
		$criteria = isset( $_POST['search_criteria'] ) ? sanitize_key( wp_unslash( $_POST['search_criteria'] ) ) : 'previous_day';
		if ( ! in_array( $criteria, array( 'all', 'previous_day', 'previous_week', 'previous_month' ), true ) ) {
			$criteria = 'previous_day';
		}

		$result = $this->run_export( $form_id, array(
			'email_address'   => isset( $_POST['email_address'] ) ? sanitize_email( wp_unslash( $_POST['email_address'] ) ) : '',
			'email_subject'   => isset( $_POST['email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['email_subject'] ) ) : '',
			'email_content'   => isset( $_POST['email_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['email_content'] ) ) : '',
			'format_export'   => $format,
			'search_criteria' => $criteria,
		), true );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );

	}


	/**
		* Get GMT date
		*
		* @param String	$local_date Local date
		* @return String $date GMT date
	*/
	public static function get_gmt_date( $local_date ) {
		$local_timestamp = strtotime( $local_date );
		$gmt_timestamp   = GFCommon::get_gmt_timestamp( $local_timestamp );
		$date            = gmdate( 'Y-m-d H:i:s', $gmt_timestamp );
		return $date;
	}


	public static function start_automated_export( $form, $offset = 0, $export_id = '', $fields = null, $export_date_start = '', $export_date_end = '' ) {

		$time_start = microtime( true );

		$format_export = $form['automatic_csv_export_for_gravity_forms']['format_export'];

        /***
		 * Allows the export max execution time to be changed.
		 *
		 * When the max execution time is reached, the export routine stop briefly and submit another AJAX request to continue exporting entries from the point it stopped.
		 *
		 * @since 2.0.3.10
		 *
		 * @param int   20    The amount of time, in seconds, that each request should run for.  Defaults to 20 seconds.
		 * @param array $form The Form Object
		 */
		$max_execution_time = apply_filters( 'gform_export_max_execution_time', 20, $form ); // seconds
		$page_size = 20;

		$form_id = $form['id'];
		$fields  = is_array( $fields ) ? $fields : array();


        $path = self::get_export_dir();

        $file_name = "export-" . $export_id . ".csv";
        $file_path = trailingslashit( $path ) . $file_name;

        // repart d'un fichier vide : les pages sont ajoutees a la suite
        if ( file_exists( $file_path ) ) {
            unlink( $file_path );
        }

        if( isset($format_export) && $format_export == 'xls' ) {
            $file_xls = trailingslashit( $path ) . "export-" . $export_id . ".xls";
            $excel = new ExcelWriter( $file_xls );
            if ( $excel == false ) {
                echo $excel->error;
            }
        }

		$start_date = empty( $export_date_start ) ? '' : self::get_gmt_date( $export_date_start . ' 00:00:00' );
		$end_date   = empty( $export_date_end ) ? '' : self::get_gmt_date( $export_date_end . ' 23:59:59' );

		$search_criteria['status']        = 'active';
		$search_criteria['field_filters'] = array();
		if ( ! empty( $start_date ) ) {
			$search_criteria['start_date'] = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$search_criteria['end_date'] = $end_date;
		}

		// $sorting = array( 'key' => 'date_created', 'direction' => 'DESC', 'type' => 'info' );
		$sorting = array( 'key' => 'id', 'direction' => 'DESC', 'type' => 'info' );

		$form = GFExport::add_default_export_fields( $form );

		$total_entry_count     = GFAPI::count_entries( $form_id, $search_criteria );
		$remaining_entry_count = $offset == 0 ? $total_entry_count : $total_entry_count - $offset;

		// Adding BOM marker for UTF-8
		$lines = '';

		// Set the separator
		$separator = gf_apply_filters( array( 'gform_export_separator', $form_id ), ',', $form_id );

		$field_rows = GFExport::get_field_row_count( $form, $fields, $remaining_entry_count );

        // writing header
        $headers = array();

		if ( $offset == 0 ) {

			// Adding BOM marker for UTF-8
			$lines = chr( 239 ) . chr( 187 ) . chr( 191 );

			foreach ( $fields as $field_id ) {

				$field = RGFormsModel::get_field( $form, $field_id );
				$label = gf_apply_filters( array( 'gform_entries_field_header_pre_export', $form_id, $field_id ), GFCommon::get_label( $field, $field_id ), $form, $field );
				$value = str_replace( '"', '""', $label );

				GFCommon::log_debug( "GFExport::start_export(): Header for field ID {$field_id}: {$value}" );

				if ( strpos( $value, '=' ) === 0 ) {
					// Prevent Excel formulas
					$value = "'" . $value;
				}

				$headers[ $field_id ] = $value;

				$subrow_count = isset( $field_rows[ $field_id ] ) ? intval( $field_rows[ $field_id ] ) : 0;
				if ( $subrow_count == 0 ) {
					$lines .= '"' . $value . '"' . $separator;
				} else {
					for ( $i = 1; $i <= $subrow_count; $i ++ ) {
						$lines .= '"' . $value . ' ' . $i . '"' . $separator;
					}
				}

				// GFCommon::log_debug( "GFExport::start_export(): Lines: {$lines}" );
			}
			$lines = substr( $lines, 0, strlen( $lines ) - 1 ) . "\n";

			if ( $remaining_entry_count == 0 ) {
				GFExport::write_file( $lines, $export_id );
				file_put_contents( $file_path, $lines, FILE_APPEND );
			}

		}

        if( isset($format_export) && $format_export == 'xls' ) {
            $excel->writeLine($headers);
            $lines_xls = array();
        }

        // Paging through results for memory issues
		while ( $remaining_entry_count > 0 ) {

			$paging = array(
				'offset'    => $offset,
				'page_size' => $page_size,
			);
			$leads = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );

			$leads = gf_apply_filters( array( 'gform_leads_before_export', $form_id ), $leads, $form, $paging );

			GFCommon::log_debug( __METHOD__ . '(): search criteria: ' . print_r( $search_criteria, true ) );
			GFCommon::log_debug( __METHOD__ . '(): sorting: ' . print_r( $sorting, true ) );
			GFCommon::log_debug( __METHOD__ . '(): paging: ' . print_r( $paging, true ) );

            foreach ( $leads as $lead ) {

				GFCommon::log_debug( __METHOD__ . '(): Processing entry #' . $lead['id'] );

				$lines_xls = array();

				foreach ( $fields as $field_id ) {

					switch ( $field_id ) {
						case 'date_created' :
							$lead_gmt_time   = mysql2date( 'G', $lead['date_created'] );
							$lead_local_time = GFCommon::get_local_timestamp( $lead_gmt_time );
							$value           = date_i18n( 'Y-m-d H:i:s', $lead_local_time, true );
							break;
						default :
							$field = RGFormsModel::get_field( $form, $field_id );

							$value = is_object( $field ) ? $field->get_value_export( $lead, $field_id, false, true ) : rgar( $lead, $field_id );
							$value = apply_filters( 'gform_export_field_value', $value, $form_id, $field_id, $lead );

							// GFCommon::log_debug( "GFExport::start_export(): Value for field ID {$field_id}: {$value}" );
							break;
					}

					if ( isset( $field_rows[ $field_id ] ) ) {

						$list = empty( $value ) ? array() : gf_auto_csv_safe_unserialize( $value );

						foreach ( $list as $row ) {
							$row_values = array_values( $row );
							$row_str    = implode( '|', $row_values );

							if ( strpos( $row_str, '=' ) === 0 ) {
								// Prevent Excel formulas
								$row_str = "'" . $row_str;
							}

							$lines .= '"' . str_replace( '"', '""', $row_str ) . '"' . $separator;

                            $lines_xls[] = $row_str;

                        }

						// filling missing subrow columns (if any)
						$missing_count = intval( $field_rows[ $field_id ] ) - count( $list );
						for ( $i = 0; $i < $missing_count; $i ++ ) {
							$lines .= '""' . $separator;
						}

					} else {

						$value = maybe_unserialize( $value );
						if ( is_array( $value ) ) {
							$value = implode( '|', $value );
						}

						// PHP 8.1+ : strpos()/str_replace() n'acceptent plus null
						$value = (string) $value;

						if ( strpos( $value, '=' ) === 0 ) {
							// Prevent Excel formulas
							$value = "'" . $value;
						}

						$lines .= '"' . str_replace( '"', '""', $value ) . '"' . $separator;

                        $lines_xls[] = $value;
					}
				}

				$lines = substr( $lines, 0, strlen( $lines ) - 1 );

                if( isset($format_export) && $format_export == 'xls' ) {
                    // $row = explode($separator,str_replace( '""', '', $lines));
                    // remove header from row
                    // array_splice($row, 0, count($headers));
                    $excel->writeLine($lines_xls);
                }

                // on supprime le tableau courrant
                unset($lines_xls);

				// GFCommon::log_debug( "GFExport::start_export(): Lines: {$lines}" );

				$lines .= "\n";
			}

			$offset += $page_size;
			$remaining_entry_count -= $page_size;

			if ( ! seems_utf8( $lines ) ) {
				$lines = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $lines, 'UTF-8', 'ISO-8859-1' ) : utf8_encode( $lines );
			}

			$lines = apply_filters( 'gform_export_lines', $lines );

			GFExport::write_file( $lines, $export_id );

			// ajoute la page courante au fichier (au lieu de l'ecraser)
			file_put_contents( $file_path, $lines, FILE_APPEND );

            $time_end = microtime( true );
			$execution_time = ( $time_end - $time_start );

			if ( $execution_time >= $max_execution_time ) {
				break;
			}

			$lines = '';
		}

		if ( isset( $excel ) ) {
			$excel->close();
		}

		$complete = $remaining_entry_count <= 0;

		if ( $complete ) {
			/**
			 * Fires after exporting all the entries in form
			 *
			 * @param array  $form       The Form object to get the entries from
			 * @param string $start_date The start date for when the export of entries should take place
			 * @param string $end_date   The end date for when the export of entries should stop
			 * @param array  $fields     The specified fields where the entries should be exported from
			 */
			do_action( 'gform_post_export_entries', $form, $start_date, $end_date, $fields );
		}

		$offset = $complete ? 0 : $offset;

		$status = array(
			'status'   => $complete ? 'complete' : 'in_progress',
			'offset'   => $offset,
			'exportId' => $export_id,
			'total'    => $total_entry_count,
			'progress' => $remaining_entry_count > 0 ? intval( 100 - ( $remaining_entry_count / $total_entry_count ) * 100 ) . '%' : '',
		);

		GFCommon::log_debug( __METHOD__ . '(): Status: ' . print_r( $status, 1 ) );

		return $status;
	}

}
$automatedexportclass = new GravityFormsAutomaticCSVExport();


add_action( 'gform_loaded', array( 'GF_Automatic_Csv_Bootstrap', 'load' ), 5 );

class GF_Automatic_Csv_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

         require_once( 'class-gf-automatic-csv-addon.php' );

        GFAddOn::register( 'GFAutomaticCSVAddOn' );
    }

}

function gf_simple_addon() {
    return GFAutomaticCSVAddOn::get_instance();
}
