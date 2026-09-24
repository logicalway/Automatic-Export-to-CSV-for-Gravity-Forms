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
                    ),
                    array(
                        'label' => esc_html__( 'Test', 'automatic_csv_export_for_gravity_forms' ),
                        'type'  => 'send_test',
                        'name'  => 'send_test',
                        'tooltip' => esc_html__( 'Sends an export now to the e-mail address above, using the settings currently displayed (even if not saved yet).', 'automatic_csv_export_for_gravity_forms' ),
                    )
                ),
            ),
        );
    }

    /**
     * Champ personnalise : bouton de test d'envoi (rien n'est enregistre)
     *
     * @param array $field
     * @param bool  $echo
     * @return string
     */
    public function settings_send_test( $field, $echo = true ) {

        $nonce   = wp_create_nonce( 'gf_auto_csv_test' );
        $form_id = absint( rgget( 'id' ) );
        if ( ! $form_id && isset( $_GET['id'] ) ) {
            $form_id = absint( $_GET['id'] );
        }

        $html  = '<button type="button" class="button" id="gf-auto-csv-send-test">' . esc_html__( 'Send a test export', 'automatic_csv_export_for_gravity_forms' ) . '</button> ';
        $html .= '<span id="gf-auto-csv-test-result" role="status" style="margin-left:8px;"></span>';
        $html .= '<script>
        (function () {
            var btn = document.getElementById("gf-auto-csv-send-test");
            var out = document.getElementById("gf-auto-csv-test-result");
            if (!btn) { return; }
            function val(name) {
                var el = document.querySelector("[name=\"_gform_setting_" + name + "\"], [name=\"_gaddon_setting_" + name + "\"]");
                return el ? el.value : "";
            }
            function radio(name) {
                var el = document.querySelector("[name=\"_gform_setting_" + name + "\"]:checked, [name=\"_gaddon_setting_" + name + "\"]:checked");
                return el ? el.value : "csv";
            }
            btn.addEventListener("click", function () {
                var data = new FormData();
                data.append("action", "gf_auto_csv_send_test");
                data.append("nonce", ' . wp_json_encode( $nonce ) . ');
                var fid = ' . wp_json_encode( $form_id ) . ' || new URLSearchParams(window.location.search).get("id") || (window.gf_vars && window.gf_vars.formId) || "";
                data.append("form_id", fid);
                if (!fid) {
                    out.style.color = "#b32d2e";
                    out.textContent = ' . wp_json_encode( __( 'Form ID not found on this page.', 'automatic_csv_export_for_gravity_forms' ) ) . ';
                    return;
                }
                ["email_address", "email_subject", "email_content", "search_criteria"].forEach(function (n) { data.append(n, val(n)); });
                data.append("format_export", radio("format_export"));
                btn.disabled = true;
                out.style.color = "";
                out.textContent = ' . wp_json_encode( __( 'Sending…', 'automatic_csv_export_for_gravity_forms' ) ) . ';
                fetch(ajaxurl, { method: "POST", credentials: "same-origin", body: data })
                    .then(function (resp) {
                        return resp.text().then(function (txt) {
                            try { var j = JSON.parse(txt); if (j && typeof j === "object") { return j; } throw 0; }
                            catch (e) { return { success: false, data: { message: "HTTP " + resp.status + " (" + txt.slice(0, 120) + ")" + (txt.trim() === "0" ? " - action AJAX non enregistrée : le plugin actif n\'est pas à jour" : "") } }; }
                        });
                    })
                    .then(function (r) {
                        var ok = r && r.success;
                        out.style.color = ok ? "#1a7f37" : "#b32d2e";
                        out.textContent = (ok ? ' . wp_json_encode( __( 'Test sent to ', 'automatic_csv_export_for_gravity_forms' ) ) . ' : ' . wp_json_encode( __( 'Failed: ', 'automatic_csv_export_for_gravity_forms' ) ) . ') + ((r && r.data && r.data.message) || "");
                    })
                    .catch(function () {
                        out.style.color = "#b32d2e";
                        out.textContent = ' . wp_json_encode( __( 'Request failed.', 'automatic_csv_export_for_gravity_forms' ) ) . ';
                    })
                    .then(function () { btn.disabled = false; });
            });
        })();
        </script>';

        if ( $echo ) {
            echo $html;
        }

        return $html;
    }
}
