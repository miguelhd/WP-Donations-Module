<?php
/*
Plugin Name: Donations Module
Plugin URI: https://miguelhd.com
Description: Un plugin para aceptar donaciones a través de PayPal Donate SDK para organizaciones sin fines de lucro.
Version: 1.1.0
Author: Miguel Hernández Domenech
Author URI: https://miguelhd.com
License: GPLv2 or later
*/

if (!defined("ABSPATH")) {
    exit(); // Salir si se accede directamente
}

class Donations_Module
{
    public function __construct()
    {
        add_action("plugins_loaded", ["Donations_Module", "init"]);
    }

    public static function activate()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "donations";
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(255) NOT NULL,
            amount decimal(10, 2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);
    }

    public static function deactivate()
    {
        // Placeholder for future deactivation logic
    }

    public static function init()
    {
        add_action("admin_menu", [__CLASS__, "add_admin_menu"]);
        add_action("admin_init", [__CLASS__, "register_settings"]);
        add_shortcode("donations_form", [
            __CLASS__,
            "donations_form_shortcode",
        ]);
        add_action("wp_ajax_save_donation", [__CLASS__, "save_donation"]);
        add_action("wp_ajax_nopriv_save_donation", [
            __CLASS__,
            "save_donation",
        ]);
        add_action("wp_enqueue_scripts", [
            __CLASS__,
            "conditionally_enqueue_scripts",
        ]);
    }

    public static function conditionally_enqueue_scripts()
    {
        if (
            is_singular() &&
            has_shortcode(get_post()->post_content, "donations_form")
        ) {
            wp_enqueue_script(
                "paypal-donate-sdk",
                "https://www.paypalobjects.com/donate/sdk/donate-sdk.js",
                [],
                null,
                true
            );
        }
    }

    public static function add_admin_menu()
    {
        add_menu_page(
            "Configuración del módulo de donaciones",
            "Módulo de donaciones",
            "manage_options",
            "donations-module",
            [__CLASS__, "settings_page"]
        );

        add_submenu_page(
            "donations-module",
            "Configuración",
            "Configuración",
            "manage_options",
            "donations-module",
            [__CLASS__, "settings_page"]
        );

        add_submenu_page(
            "donations-module",
            "Lista de Donaciones",
            "Lista de Donaciones",
            "manage_options",
            "donations-module-donations-list",
            [__CLASS__, "donations_list_page"]
        );
    }

    public static function register_settings()
    {
        register_setting("donations_module", "donations_goal", [
            "sanitize_callback" => "intval",
        ]);
        register_setting("donations_module", "paypal_hosted_button_id");
        register_setting(
            "donations_module",
            "cta_paragraph",
            "sanitize_textarea_field"
        );
        register_setting("donations_module", "number_of_donations", [
            "default" => 0,
        ]);
        register_setting("donations_module", "total_amount_raised", [
            "default" => 0,
        ]);

        register_setting("donations_module", "show_amount_raised", [
            "default" => "1",
        ]);
        register_setting("donations_module", "show_percentage_of_goal", [
            "default" => "1",
        ]);
        register_setting("donations_module", "show_number_of_donations", [
            "default" => "1",
        ]);
        register_setting("donations_module", "show_cta_paragraph", [
            "default" => "1",
        ]);
        register_setting("donations_module", "content_alignment", [
            "default" => "center",
        ]);

        register_setting("donations_module", "progress_bar_color");
        register_setting("donations_module", "progress_bar_height");
        register_setting("donations_module", "progress_bar_well_color");
        register_setting("donations_module", "progress_bar_well_width");
        register_setting("donations_module", "progress_bar_border_radius");
    }

    public static function settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Configuración del módulo de donaciones</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields("donations_module");
                do_settings_sections("donations_module");
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Meta de donaciones</th>
                        <td><input type="number" name="donations_goal" value="<?php echo esc_attr(
                            intval(get_option("donations_goal"))
                        ); ?>" placeholder="1000" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Texto para incentivar donaciones</th>
                        <td>
                            <textarea name="cta_paragraph" rows="5" cols="50" placeholder="¡Ayúdanos a alcanzar nuestra meta! Dona ahora a través de PayPal y marca la diferencia."><?php echo esc_textarea(
                                get_option("cta_paragraph")
                            ); ?></textarea>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">ID del botón alojado</th>
                        <td><input type="text" name="paypal_hosted_button_id" value="<?php echo esc_attr(
                            get_option("paypal_hosted_button_id")
                        ); ?>" placeholder="GHL8CQKJRVR4U" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mostrar Monto Recaudado</th>
                        <td><input type="checkbox" name="show_amount_raised" value="1" <?php checked(
                            get_option("show_amount_raised"),
                            "1"
                        ); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mostrar Porcentaje de la Meta</th>
                        <td><input type="checkbox" name="show_percentage_of_goal" value="1" <?php checked(
                            get_option("show_percentage_of_goal"),
                            "1"
                        ); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mostrar Número de Donaciones</th>
                        <td><input type="checkbox" name="show_number_of_donations" value="1" <?php checked(
                            get_option("show_number_of_donations"),
                            "1"
                        ); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Mostrar Texto para Incentivar</th>
                        <td><input type="checkbox" name="show_cta_paragraph" value="1" <?php checked(
                            get_option("show_cta_paragraph"),
                            "1"
                        ); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Alinear Contenido</th>
                        <td>
                            <select name="content_alignment">
                                <option value="left" <?php selected(
                                    get_option("content_alignment"),
                                    "left"
                                ); ?>>Izquierda</option>
                                <option value="center" <?php selected(
                                    get_option("content_alignment"),
                                    "center"
                                ); ?>>Centro</option>
                                <option value="right" <?php selected(
                                    get_option("content_alignment"),
                                    "right"
                                ); ?>>Derecha</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Color de la barra de progreso</th>
                        <td><input type="color" name="progress_bar_color" value="<?php echo esc_attr(
                            get_option("progress_bar_color", "#00ff00")
                        ); ?>" placeholder="#00ff00" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Altura de la barra de progreso (px)</th>
                        <td><input type="number" name="progress_bar_height" value="<?php echo esc_attr(
                            get_option("progress_bar_height", 20)
                        ); ?>" placeholder="20" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Color del fondo de la barra de progreso</th>
                        <td><input type="color" name="progress_bar_well_color" value="<?php echo esc_attr(
                            get_option("progress_bar_well_color", "#eeeeee")
                        ); ?>" placeholder="#eeeeee" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Ancho del fondo de la barra de progreso (%)</th>
                        <td><input type="number" name="progress_bar_well_width" value="<?php echo esc_attr(
                            get_option("progress_bar_well_width", 100)
                        ); ?>" placeholder="100" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Esquinas Redondeadas de la Barra de Progreso (px)</th>
                        <td><input type="number" name="progress_bar_border_radius" value="<?php echo esc_attr(
                            get_option("progress_bar_border_radius", 0)
                        ); ?>" placeholder="0" /></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function donations_list_page()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "donations";
        $donations = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC"
        );
        ?>
        <div class="wrap">
            <h1>Lista de Donaciones</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cantidad</th>
                        <th>ID de Transacción</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation) { ?>
                        <tr>
                            <td><?php echo esc_html($donation->id); ?></td>
                            <td><?php echo esc_html(
                                number_format($donation->amount, 2)
                            ); ?></td>
                            <td><?php echo esc_html(
                                $donation->transaction_id
                            ); ?></td>
                            <td><?php echo esc_html(
                                $donation->created_at
                            ); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function donations_form_shortcode()
    {
        $goal = intval(get_option("donations_goal", 0));
        $current_total = self::get_current_donations_total();
        $donations_count = self::get_total_donations_count();
        $progress = $goal > 0 ? ($current_total / $goal) * 100 : 0;

        $progress_bar_color = get_option("progress_bar_color", "#00ff00");
        $progress_bar_height = get_option("progress_bar_height", 20);
        $progress_bar_well_color = get_option(
            "progress_bar_well_color",
            "#eeeeee"
        );
        $progress_bar_well_width = get_option("progress_bar_well_width", 100);
        $progress_bar_border_radius = get_option(
            "progress_bar_border_radius",
            0
        );

        $cta_paragraph = esc_textarea(
            get_option(
                "cta_paragraph",
                "¡Ayúdanos a alcanzar nuestra meta! Dona ahora a través de PayPal y marca la diferencia."
            )
        );
        $content_alignment = esc_attr(
            get_option("content_alignment", "center")
        );

        ob_start();
        ?>
        <div class="donations-module" style="text-align: <?php echo $content_alignment; ?>;">
            <?php if (get_option("show_cta_paragraph", "1")): ?>
                <p class="cta-paragraph"><?php echo $cta_paragraph; ?></p>
            <?php endif; ?>

            <div class="donation-stats">
                <?php if (get_option("show_amount_raised", "1")): ?>
                    <p><strong>Monto recaudado:</strong> $<?php echo number_format(
                        $current_total,
                        2
                    ); ?></p>
                <?php endif; ?>
                
                <?php if (get_option("show_percentage_of_goal", "1")): ?>
                    <p><strong>Porcentaje de la meta:</strong> <?php echo number_format(
                        $progress,
                        2
                    ); ?>%</p>
                <?php endif; ?>
                
                <?php if (get_option("show_number_of_donations", "1")): ?>
                    <p><strong>Número de donaciones:</strong> <?php echo intval(
                        $donations_count
                    ); ?></p>
                <?php endif; ?>
            </div>

            <form id="donations-form" class="donations-form alignwide" onsubmit="return false;" aria-labelledby="donations-form-heading">
                <input type="hidden" name="button_id" value="<?php echo esc_attr(
                    get_option("paypal_hosted_button_id")
                ); ?>">
                <input type="hidden" name="donation_nonce" value="<?php echo wp_create_nonce(
                    "save_donation"
                ); ?>">
                <div id="paypal-button-container"></div>
                <div id="form-feedback" role="alert" style="display:none; color:red;"></div>
            </form>
        </div>
        <div id="donation-progress" style="background-color: <?php echo esc_attr(
            $progress_bar_well_color
        ); ?>; width: <?php echo esc_attr(
    $progress_bar_well_width
); ?>%; height: <?php echo esc_attr(
    $progress_bar_height
); ?>px; max-width: 100%; border-radius: <?php echo esc_attr(
    $progress_bar_border_radius
); ?>px;" role="progressbar" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
            <div id="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo esc_attr(
    $progress_bar_color
); ?>; height: 100%; <?php echo $progress >= 100
    ? "border-radius: " . esc_attr($progress_bar_border_radius) . "px;"
    : "border-radius: " .
        esc_attr($progress_bar_border_radius) .
        "px 0 0 " .
        esc_attr($progress_bar_border_radius) .
        "px;"; ?>">
                <?php echo number_format($progress, 2); ?>%
            </div>
        </div>
        <p id="donation-summary"><?php echo "$" .
            intval($current_total) .
            " de $" .
            $goal; ?></p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
    PayPal.Donation.Button({
        env: 'sandbox', // Change to 'production' when going live
        hosted_button_id: '<?php echo esc_js(
            get_option("paypal_hosted_button_id")
        ); ?>',
        onComplete: function(data) {
            var donationData = {
                action: 'save_donation',
                donation_nonce: '<?php echo wp_create_nonce(
                    "save_donation"
                ); ?>',
                transaction_id: data.tx,
                donation_amount: data.amt
            };

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(donationData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Donation saved successfully.');
                } else {
                    console.error('Error saving donation:', data.message);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
        }
    }).render('#paypal-button-container');
});
        </script>
        <style>
            :root {
                --donation-form-bg: #ffffff;
                --donation-form-color: #333333;
            }

            .donations-module {
                padding: 20px;
                background-color: inherit;
                color: inherit;
            }
            
            .donations-module .cta-paragraph {
                font-size: 1.2em;
                margin-bottom: 20px;
            }

            .donations-module .donation-stats {
                margin-bottom: 20px;
            }

            .donations-module .donation-stats p {
                margin: 5px 0;
                font-weight: bold;
            }

            .donations-module .donations-form {
                max-width: 100%;
                padding: 10px;
                box-sizing: border-box;
                background-color: var(--donation-form-bg);
                color: var(--donation-form-color);
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            #paypal-button-container {
                max-width: 100%;
                margin-bottom: 20px;
            }

            #donation-progress {
                width: 100%;
                max-width: 400px;
                background-color: <?php echo esc_attr(
                    $progress_bar_well_color
                ); ?>;
                height: <?php echo esc_attr($progress_bar_height); ?>px;
                border-radius: <?php echo esc_attr(
                    $progress_bar_border_radius
                ); ?>px;
                overflow: hidden;
            }

            #progress-bar {
                width: <?php echo $progress; ?>%;
                background-color: <?php echo esc_attr($progress_bar_color); ?>;
                height: 100%;
                transition: width 0.5s ease-in-out;
                text-align: center;
                color: #fff;
                font-weight: bold;
                line-height: <?php echo esc_attr($progress_bar_height); ?>px;
                border-radius: <?php echo $progress >= 100
                    ? esc_attr($progress_bar_border_radius) . "px"
                    : esc_attr($progress_bar_border_radius) .
                        "px 0 0 " .
                        esc_attr($progress_bar_border_radius) .
                        "px"; ?>;
            }

            #donation-summary {
                font-size: 1.2em;
                font-weight: bold;
            }

            .alignleft {
                text-align: left;
            }

            .alignright {
                text-align: right;
            }

            .aligncenter {
                text-align: center;
            }
        </style>
        <?php return ob_get_clean();
    }

    private static function get_current_donations_total()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "donations";
        $result = $wpdb->get_var("SELECT SUM(amount) FROM $table_name");
        return $result ? floatval($result) : 0;
    }

    private static function get_total_donations_count()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "donations";
        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        return $result ? intval($result) : 0;
    }

    public static function save_donation()
    {
        // Log function trigger to confirm it's being called
        error_log("save_donation function triggered");

        // Check and verify nonce
        if (
            !isset($_POST["donation_nonce"]) ||
            !wp_verify_nonce($_POST["donation_nonce"], "save_donation")
        ) {
            error_log("Nonce verification failed");
            wp_send_json([
                "success" => false,
                "message" => "Invalid security token.",
            ]);
            return;
        }

        // Sanitize and validate input
        $transaction_id = sanitize_text_field($_POST["transaction_id"]);
        $amount = isset($_POST["donation_amount"])
            ? floatval($_POST["donation_amount"])
            : 0;

        if (empty($transaction_id) || $amount <= 0) {
            error_log("Invalid transaction data");
            wp_send_json([
                "success" => false,
                "message" => "Invalid donation data.",
            ]);
            return;
        }

        // Insert data into the database
        global $wpdb;
        $table_name = $wpdb->prefix . "donations";

        $inserted = $wpdb->insert($table_name, [
            "transaction_id" => $transaction_id,
            "amount" => $amount,
            "created_at" => current_time("mysql"),
        ]);

        // Check if insertion was successful
        if ($inserted === false) {
            error_log("Database insertion failed: " . $wpdb->last_error);
            wp_send_json([
                "success" => false,
                "message" => "Failed to save donation.",
            ]);
            return;
        }

        // Update donation metrics
        update_option(
            "total_amount_raised",
            self::get_current_donations_total()
        );
        update_option("number_of_donations", self::get_total_donations_count());

        // Send success response
        wp_send_json([
            "success" => true,
            "current_total" => self::get_current_donations_total(),
        ]);
    }
}

new Donations_Module();

register_activation_hook(__FILE__, ["Donations_Module", "activate"]);
register_deactivation_hook(__FILE__, ["Donations_Module", "deactivate"]);
