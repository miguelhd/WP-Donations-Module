<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Donations_Module {

    // Constructor method
    public function __construct() {
        // Initialization code
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('donations_form', [$this, 'donations_form_shortcode']);
        add_action('wp_ajax_save_donation', [$this, 'save_donation']);
        add_action('wp_ajax_nopriv_save_donation', [$this, 'save_donation']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_paypal_sdk']);
    }

    // Method to run on plugin activation
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(255) NOT NULL,
            amount decimal(10, 2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // Method to run on plugin deactivation
    public static function deactivate() {
        // Placeholder for deactivation logic, if needed
    }

    // Method to initialize the plugin
    public static function init() {
        // Instantiate the class
        $instance = new self();
    }

    // Method to enqueue PayPal SDK script
    public function enqueue_paypal_sdk() {
        wp_enqueue_script(
            'paypal-donate-sdk',
            'https://www.paypalobjects.com/donate/sdk/donate-sdk.js',
            [],
            null,
            true
        );
    }

    // Method to add an admin menu
    public function add_admin_menu() {
        add_menu_page(
            'Configuración del módulo de donaciones',
            'Módulo de donaciones',
            'manage_options',
            'donations-module',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'donations-module',
            'Configuración',
            'Configuración',
            'manage_options',
            'donations-module',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'donations-module',
            'Donaciones',
            'Donaciones',
            'manage_options',
            'donations-module-donations-list',
            [$this, 'donations_list_page']
        );
    }

    // Method to register plugin settings
    public function register_settings() {
        register_setting('donations_module', 'donations_goal', [
            'sanitize_callback' => 'intval',
        ]);
        register_setting('donations_module', 'paypal_hosted_button_id');
        register_setting('donations_module', 'cta_paragraph', 'sanitize_textarea_field');
        register_setting('donations_module', 'number_of_donations', [
            'default' => 0,
        ]);
        register_setting('donations_module', 'total_amount_raised', [
            'default' => 0,
        ]);
        register_setting('donations_module', 'show_amount_raised', [
            'default' => '1',
        ]);
        register_setting('donations_module', 'show_percentage_of_goal', [
            'default' => '1',
        ]);
        register_setting('donations_module', 'show_number_of_donations', [
            'default' => '1',
        ]);
        register_setting('donations_module', 'show_cta_paragraph', [
            'default' => '1',
        ]);
        register_setting('donations_module', 'content_alignment', [
            'default' => 'center',
        ]);
        register_setting('donations_module', 'progress_bar_color');
        register_setting('donations_module', 'progress_bar_height');
        register_setting('donations_module', 'progress_bar_well_color');
        register_setting('donations_module', 'progress_bar_well_width');
        register_setting('donations_module', 'progress_bar_border_radius');
        register_setting('donations_module', 'donations_text_color', [
            'default' => '#333333', // Default text color (dark gray)
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
    }

    // Method to display the settings page
    public function settings_page() {
        // Check if settings were saved and display a success message
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            add_settings_error(
                'donations_module_messages',
                'donations_module_message',
                __('Configuración guardada correctamente.', 'donations-module'),
                'updated'
            );
        }

        // Display any error messages that occurred
        settings_errors('donations_module_messages');
        
        ?>
        <div class="wrap">
            <h1>Configuración del módulo de donaciones</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('donations_module');
                do_settings_sections('donations_module');
                ?>

                <!-- General Settings Section -->
                <h2 class="section-title">Configuración General</h2>
                <hr class="section-separator" />
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="donations_goal">Meta de Donaciones</label></th>
                        <td><input type="number" id="donations_goal" name="donations_goal" value="<?php echo esc_attr(intval(get_option('donations_goal'))); ?>" placeholder="1000" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="paypal_hosted_button_id">PayPal Button ID</label></th>
                        <td><input type="text" id="paypal_hosted_button_id" name="paypal_hosted_button_id" value="<?php echo esc_attr(get_option('paypal_hosted_button_id')); ?>" placeholder="ABC1DEFGHIJ2K" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="cta_paragraph">Párrafo Incentivo</label></th>
                        <td><textarea id="cta_paragraph" name="cta_paragraph" rows="5" cols="50" placeholder="¡Ayúdanos a alcanzar nuestra meta! Dona ahora a través de PayPal y marca la diferencia." class="large-text"><?php echo esc_textarea(get_option('cta_paragraph')); ?></textarea></td>
                    </tr>
                </table>

                <!-- Display Settings Section -->
                <h2 class="section-title">Configuración de Pantalla</h2>
                <hr class="section-separator" />
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="content_alignment">Alinear Contenido</label></th>
                        <td>
                            <select id="content_alignment" name="content_alignment" class="regular-text">
                                <option value="left" <?php selected(get_option('content_alignment'), 'left'); ?>>Izquierda</option>
                                <option value="center" <?php selected(get_option('content_alignment'), 'center'); ?>>Centro</option>
                                <option value="right" <?php selected(get_option('content_alignment'), 'right'); ?>>Derecha</option>
                            </select>
                        </td>
                    </tr>
                        <tr valign="top">
                        <th scope="row"><label for="show_amount_raised">Mostrar Monto Recaudado</label></th>
                        <td><input type="checkbox" id="show_amount_raised" name="show_amount_raised" value="1" <?php checked(get_option('show_amount_raised'), '1'); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="show_percentage_of_goal">Mostrar Porcentaje de la Meta</label></th>
                        <td><input type="checkbox" id="show_percentage_of_goal" name="show_percentage_of_goal" value="1" <?php checked(get_option('show_percentage_of_goal'), '1'); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="show_number_of_donations">Mostrar Número de Donaciones</label></th>
                        <td><input type="checkbox" id="show_number_of_donations" name="show_number_of_donations" value="1" <?php checked(get_option('show_number_of_donations'), '1'); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="show_cta_paragraph">Mostrar Párrafo Incentivo</label></th>
                        <td><input type="checkbox" id="show_cta_paragraph" name="show_cta_paragraph" value="1" <?php checked(get_option('show_cta_paragraph'), '1'); ?> /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="donations_text_color">Color del Texto</label></th>
                        <td><input type="color" id="donations_text_color" name="donations_text_color" value="<?php echo esc_attr(get_option('donations_text_color', '#333333')); ?>" class="large-color-picker" /></td>
                    </tr>
                </table>

                <!-- Progress Bar Customization Section -->
                <h2 class="section-title">Personalización de la Barra de Progreso</h2>
                <hr class="section-separator" />
                <table class="form-table">
                    <!-- Color Adjustments -->
                    <tr valign="top">
                        <th scope="row"><label for="progress_bar_color">Color de la Barra de Progreso</label></th>
                        <td><input type="color" id="progress_bar_color" name="progress_bar_color" value="<?php echo esc_attr(get_option('progress_bar_color', '#00ff00')); ?>" placeholder="#00ff00" class="large-color-picker" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="progress_bar_well_color">Color del Fondo de la Barra de Progreso</label></th>
                        <td><input type="color" id="progress_bar_well_color" name="progress_bar_well_color" value="<?php echo esc_attr(get_option('progress_bar_well_color', '#eeeeee')); ?>" placeholder="#eeeeee" class="large-color-picker" /></td>
                    </tr>

                    <!-- Sizing Adjustments -->
                    <tr valign="top">
                        <th scope="row"><label for="progress_bar_height">Altura de la Barra de Progreso (px)</label></th>
                        <td><input type="number" id="progress_bar_height" name="progress_bar_height" value="<?php echo esc_attr(get_option('progress_bar_height', 20)); ?>" placeholder="20" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="progress_bar_well_width">Ancho del Fondo de la Barra de Progreso (%)</label></th>
                        <td><input type="number" id="progress_bar_well_width" name="progress_bar_well_width" value="<?php echo esc_attr(get_option('progress_bar_well_width', 100)); ?>" placeholder="100" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="progress_bar_border_radius">Esquinas Redondeadas de la Barra de Progreso (px)</label></th>
                        <td><input type="number" id="progress_bar_border_radius" name="progress_bar_border_radius" value="<?php echo esc_attr(get_option('progress_bar_border_radius', 0)); ?>" placeholder="0" class="small-text" /></td>
                    </tr>
                </table>

                <?php submit_button('Guardar Cambios', 'primary large'); ?>
            </form>
        </div>
        <style>
            .form-table th {
                font-weight: normal;
                width: 240px;
                padding-bottom: 5px;
                padding-top: 5px;
            }
            .form-table td {
                padding-bottom: 5px;
                padding-top: 5px;
            }
            .section-title {
                margin-top: 40px; /* Increased space between sections */
            }
            .section-separator {
                margin-top: 10px; /* Small space between title and separator */
                margin-bottom: 20px; /* Consistent space between separator and section content */
            }
            .form-table td input[type="text"], 
            .form-table td input[type="number"], 
            .form-table td textarea, 
            .form-table td select {
                width: 100%;
                max-width: 400px;
            }
            .large-color-picker {
                width: 70px;
                height: 30px;
                padding: 0;
                border: 1px solid #ccc;
                border-radius: 3px;
            }
        </style>
        <?php
    }

    // Method to display the donations list page
    public function donations_list_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';

        // Fetching data
        $total_collected = $this->get_current_donations_total();
        $goal = intval(get_option('donations_goal', 0));
        $donations_count = $this->get_total_donations_count();
        $percentage_of_goal = $goal > 0 ? ($total_collected / $goal) * 100 : 0;

        // Fetching the donations list
        $donations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        ?>
        <div class="wrap">
            <h1>Donaciones</h1>

            <!-- Metrics Dashboard -->
            <div class="donations-dashboard">
                <div class="metrics-card">
                    <div class="metric-value"><?php echo '$' . number_format($total_collected, 2); ?></div>
                    <div class="metric-label">Total Recaudado</div>
                </div>
                <div class="metrics-card">
                    <div class="metric-value"><?php echo number_format($percentage_of_goal, 2) . '%'; ?></div>
                    <div class="metric-label">Meta Alcanzada</div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo $percentage_of_goal; ?>%;"></div>
                    </div>
                </div>
                <div class="metrics-card">
                    <div class="metric-value"><?php echo intval($donations_count); ?></div>
                    <div class="metric-label">Número de Donaciones</div>
                </div>
            </div>

            <!-- Donations Table -->
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
                            <td><?php echo esc_html(number_format($donation->amount, 2)); ?></td>
                            <td><?php echo esc_html($donation->transaction_id); ?></td>
                            <td><?php echo esc_html($donation->created_at); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <style>
            .donations-dashboard {
                display: flex;
                justify-content: space-between;
                margin-bottom: 30px; /* Space between dashboard and table */
                margin-top: 20px; /* Increased space between title and metrics cards */
            }

            .metrics-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef; /* Consistent border with table */
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                flex: 1;
                margin: 0 10px;
            }

            .metrics-card .metric-value {
                font-size: 2em;
                font-weight: bold;
                color: #333;
            }

            .metrics-card .metric-label {
                font-size: 1em;
                color: #666;
                margin-top: 10px;
            }

            .progress {
                margin-top: 15px;
                background-color: #e9ecef;
                border-radius: 8px;
                overflow: hidden;
                height: 10px;
                width: 100%;
            }

            .progress-bar {
                height: 100%;
                background-color: #28a745;
                transition: width 0.4s ease;
            }

            .wp-list-table {
                border: 1px solid #e9ecef; /* Matching border with metrics cards */
                border-radius: 8px;
                width: 100%;
                margin-top: 20px;
            }

            .wp-list-table th, .wp-list-table td {
                border: 1px solid #e9ecef; /* Consistent cell borders */
            }

            .form-table td input[type="text"], 
            .form-table td input[type="number"], 
            .form-table td textarea, 
            .form-table td select {
                border: 1px solid #e9ecef; /* Consistent border with other elements */
                border-radius: 4px;
                padding: 5px;
                width: 100%;
                max-width: 400px;
            }
        </style>
        <?php
    }

    // Method to handle the donation form shortcode
    public function donations_form_shortcode() {
        $goal = intval(get_option('donations_goal', 0));
        $current_total = $this->get_current_donations_total();
        $donations_count = $this->get_total_donations_count();
        $progress = $goal > 0 ? ($current_total / $goal) * 100 : 0;

        $progress_bar_color = get_option('progress_bar_color', '#00ff00');
        $progress_bar_height = get_option('progress_bar_height', 20);
        $progress_bar_well_color = get_option('progress_bar_well_color', '#eeeeee');
        $progress_bar_well_width = get_option('progress_bar_well_width', 100);
        $progress_bar_border_radius = get_option('progress_bar_border_radius', 0);

        $cta_paragraph = esc_textarea(get_option('cta_paragraph', '¡Ayúdanos a alcanzar nuestra meta! Dona ahora a través de PayPal y marca la diferencia.'));
        $content_alignment = esc_attr(get_option('content_alignment', 'center'));
        $text_color = esc_attr(get_option('donations_text_color', '#333333'));

        ob_start();
        ?>
        <div class="donations-module" style="color: <?php echo $text_color; ?>; text-align: <?php echo $content_alignment; ?>;">
            <?php if (get_option('show_cta_paragraph', '1')): ?>
                <p class="cta-paragraph"><?php echo $cta_paragraph; ?></p>
            <?php endif; ?>

            <div class="donation-stats">
                <?php if (get_option('show_amount_raised', '1')): ?>
                    <p><strong>Monto recaudado:</strong> $<?php echo number_format($current_total, 2); ?></p>
                <?php endif; ?>
                
                <?php if (get_option('show_percentage_of_goal', '1')): ?>
                    <p><strong>Porcentaje de la meta:</strong> <?php echo number_format($progress, 2); ?>%</p>
                <?php endif; ?>
                
                <?php if (get_option('show_number_of_donations', '1')): ?>
                    <p><strong>Número de donaciones:</strong> <?php echo intval($donations_count); ?></p>
                <?php endif; ?>
            </div>

            <form id="donations-form" class="donations-form alignwide" onsubmit="return false;" aria-labelledby="donations-form-heading">
                <input type="hidden" name="button_id" value="<?php echo esc_attr(get_option('paypal_hosted_button_id')); ?>">
                <input type="hidden" name="donation_nonce" value="<?php echo wp_create_nonce('save_donation'); ?>">
                <div id="paypal-button-container"></div>
                <div id="form-feedback" role="alert" style="display:none; color:red;"></div>
            </form>
        </div>
        <div id="donation-progress" style="background-color: <?php echo esc_attr($progress_bar_well_color); ?>; width: <?php echo esc_attr($progress_bar_well_width); ?>%; height: <?php echo esc_attr($progress_bar_height); ?>px; max-width: 100%; border-radius: <?php echo esc_attr($progress_bar_border_radius); ?>px;" role="progressbar" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
            <div id="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo esc_attr($progress_bar_color); ?>; height: 100%; <?php echo $progress >= 100 ? 'border-radius: ' . esc_attr($progress_bar_border_radius) . 'px;' : 'border-radius: ' . esc_attr($progress_bar_border_radius) . 'px 0 0 ' . esc_attr($progress_bar_border_radius) . 'px;'; ?>">
                <?php echo number_format($progress, 2); ?>%
            </div>
        </div>
        <p id="donation-summary"><?php echo '$' . intval($current_total) . ' de $' . $goal; ?></p>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                PayPal.Donation.Button({
                    env: 'sandbox', // Change to 'production' when going live
                    hosted_button_id: '<?php echo esc_js(get_option('paypal_hosted_button_id')); ?>',
                    onComplete: function(data) {
                        var donationData = {
                            action: 'save_donation',
                            donation_nonce: '<?php echo wp_create_nonce('save_donation'); ?>',
                            transaction_id: data.tx,
                            donation_amount: data.amt
                        };
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
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
                                location.reload(); // Refresh the page to update values
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
                background-color: <?php echo esc_attr($progress_bar_well_color); ?>;
                height: <?php echo esc_attr($progress_bar_height); ?>px;
                border-radius: <?php echo esc_attr($progress_bar_border_radius); ?>px;
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
                    ? esc_attr($progress_bar_border_radius) . 'px'
                    : esc_attr($progress_bar_border_radius) .
                        'px 0 0 ' .
                        esc_attr($progress_bar_border_radius) .
                        'px'; ?>;
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

    // Method to get the current total donations
    private function get_current_donations_total() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $result = $wpdb->get_var("SELECT SUM(amount) FROM $table_name");
        return $result ? floatval($result) : 0;
    }

    // Method to get the total number of donations
    private function get_total_donations_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $result = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        return $result ? intval($result) : 0;
    }

    // Method to save a donation via AJAX
    public function save_donation() {
        // Log function trigger to confirm it's being called
        error_log('save_donation function triggered');

        // Check and verify nonce
        if (
            !isset($_POST['donation_nonce']) ||
            !wp_verify_nonce($_POST['donation_nonce'], 'save_donation')
        ) {
            error_log('Nonce verification failed');
            wp_send_json([
                'success' => false,
                'message' => 'Invalid security token.',
            ]);
            return;
        }

        // Sanitize and validate input
        $transaction_id = sanitize_text_field($_POST['transaction_id']);
        $amount = isset($_POST['donation_amount']) ? floatval($_POST['donation_amount']) : 0;

        if (empty($transaction_id) || $amount <= 0) {
            error_log('Invalid transaction data');
            wp_send_json([
                'success' => false,
                'message' => 'Invalid donation data.',
            ]);
            return;
        }

        // Insert data into the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';

        $inserted = $wpdb->insert($table_name, [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'created_at' => current_time('mysql'),
        ]);

        // Check if insertion was successful
        if ($inserted === false) {
            error_log('Database insertion failed: ' . $wpdb->last_error);
            wp_send_json([
                'success' => false,
                'message' => 'Failed to save donation.',
            ]);
            return;
        }

        // Update donation metrics
        update_option('total_amount_raised', $this->get_current_donations_total());
        update_option('number_of_donations', $this->get_total_donations_count());

        // Send success response
        wp_send_json([
            'success' => true,
            'current_total' => $this->get_current_donations_total(),
        ]);
    }
}