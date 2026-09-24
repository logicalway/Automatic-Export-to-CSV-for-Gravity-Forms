<?php


if ( class_exists( 'GFForms' ) ) {

    GFForms::include_addon_framework();

}


class GFAutomaticCSVAddOn extends GFAddOn {

    protected $_version = GF_AUTOMATIC_CSV_VERSION;
    protected $_min_gravityforms_version = '2.0';
    protected $_slug = 'automatic_csv_export_for_gravity_forms';
    protected $_path = 'gravityforms-automatic-csv-export/automatic_csv_export_for_gravity_forms.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Automatic CSV Export for Gravity Forms Add-On';
    protected $_short_title = 'Automatic CSV Export';

    private static $_instance = null;

    public static function get_instance() {
        if ( self::$_instance == null ) {
            self::$_instance = new GFAutomaticCSVAddOn();
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();
    }

    public function init_admin() {
        parent::init_admin();
    }

    // https://docs.gravityforms.com/gfaddon/
    public function form_settings_fields( $form ) {
        return array(
            array(
                'title'  => esc_html__( 'CSV Export Settings', 'automatic_csv_export_for_gravity_forms' ),
                'fields' => array(
                    array(
                        'label'   => esc_html__( 'Enable Automatic export', 'automatic_csv_export_for_gravity_forms' ),
                        'type'    => 'checkbox',
                        'name'    => 'enable_export',
                        'tooltip' => esc_html__( 'This will enable the automatic export of csv for this form.', 'automatic_csv_export_for_gravity_forms' ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Enabled', 'automatic_csv_export_for_gravity_forms' ),
                                'name'  => 'enabled'
                            ),
                        )
                    ),
                    array(
                        'label'   => esc_html__( 'Search Criteria', 'automatic_csv_export_for_gravity_forms' ),
                        'type'    => 'select',
                        'name'    => 'search_criteria',
                        'tooltip' => esc_html__( 'Choose the search criteria (yesterday, last seven days) for your export.', 'automatic_csv_export_for_gravity_forms' ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Previous Day', 'automatic_csv_export_for_gravity_forms' ),
                                'value'  => 'previous_day'
                            ),
                            array(
                                'label' => esc_html__( 'Previous Week', 'automatic_csv_export_for_gravity_forms' ),
                                'value'  => 'previous_week'
                            ),
                            array(
                                'label' => esc_html__( 'Previous Month', 'automatic_csv_export_for_gravity_forms' ),
                                'value'  => 'previous_month'
                            ),
                            array(
                                'label' => esc_html__( 'Everything', 'automatic_csv_export_for_gravity_forms' ),
                                'value'  => 'all'
                            ),
                        )
                    ),
                    array(
                        'label'   => esc_html__( 'Export frequency', 'automatic_csv_export_for_gravity_forms' ),
                        'type'    => 'select',
                        'name'    => 'csv_export_frequency',
                        'tooltip' => esc_html__( 'This determines how frequently the export will be run and emailed to you.', 'automatic_csv_export_for_gravity_forms' ),
                        'choices' => array(
                            array(
                                'label' => esc_html__( 'Hourly', 'automatic_csv_export_for_gravity_forms' ),
                                'value' => 'hourly'
                            ),
                            array(
                                'label' => esc_html__( 'Daily', 'automatic_csv_export_for_gravity_forms' ),
                                'value' => 'daily'
                            ),
                            array(
                                'label' => esc_html__( 'Weekly', 'automatic_csv_export_for_gravity_forms' ),
                                'value' => 'weekly'
                            ),
                            array(
                                'label' => esc_html__( 'Monthly', 'automatic_csv_export_for_gravity_forms' ),
                                'value' => 'monthly'
                            )
                        )
                    ),
                    array(
                        'type'          => 'radio',
                        'name'          => 'format_export',
                        'label'         => esc_html__( 'Choose the export Format CSV/XLS', 'automatic_csv_export_for_gravity_forms' ),
                        'default_value' => 'csv',
                        'horizontal'    => true,
                        'choices'       => array(
                            array(
                                'name'    => 'format_export',
                                'tooltip' => esc_html__( 'Sent the CSV Format', 'automatic_csv_export_for_gravity_forms' ),
                                'label'   => esc_html__( 'Sent the CSV Format', 'automatic_csv_export_for_gravity_forms' ),
                                'value'   => 'csv'
                            ),
                            array(
                                'name'    => 'format_export',
                                'tooltip' => esc_html__( 'Sent the XLS Format', 'automatic_csv_export_for_gravity_forms' ),
                                'label'   => esc_html__( 'Sent the XLS Format', 'automatic_csv_export_for_gravity_forms' ),
                                'value' => 'xls'
                            )
                        )
                    ),
                    array(
                        'label' => esc_html__( 'Email Subject', 'automatic_csv_export_for_gravity_forms' ),
                        'type' => 'text',
                        'name' => 'email_subject',
                        'tooltip' => esc_html__( 'The e-mail will be sent with this subject', 'automatic_csv_export_for_gravity_forms' ),
                        'class' => 'medium',
                        'placeholder' => 'Automatic Form Export'
                    ),
                    array(
                        'label' => esc_html__( 'E-mail Content', 'automatic_csv_export_for_gravity_forms' ),
                        'type' => 'textarea',
                        'name' => 'email_content',
                        'tooltip' => esc_html__( 'The export will be sent with this content e-mail', 'automatic_csv_export_for_gravity_forms' ),
                        'class' => 'medium',
                        'placeholder' => 'Export is attached to this message'
                    ),
                    array(
                        'label' => esc_html__( 'E-mail Address', 'automatic_csv_export_for_gravity_forms' ),
                        'type' => 'text',
                        'name' => 'email_address',
                        'tooltip' => esc_html__( 'The export will be sent to this email address', 'automatic_csv_export_for_gravity_forms' ),
                        'class' => 'medium'
                    )
                ),
            ),
        );
    }
}
