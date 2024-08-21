<?php
/*
Plugin Name: Donations Module
Plugin URI: https://miguelhd.com
Description: A plugin to accept donations via PayPal/Braintree for non-profits.
Version: 1.0.0
Author: Miguel Hernández Domenech
Author URI: https://miguelhd.com
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente
}

class Donations_Module {
    public function __construct() {
        add_action('plugins_loaded', array(__CLASS__, 'init'));
    }

    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            amount decimal(10, 2) NOT NULL,
            transaction_id varchar(255) NOT NULL,
            donor_name varchar(255),
            donor_email varchar(255),
            button_id varchar(255) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function deactivate() {
    }

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_shortcode('donations_form', array(__CLASS__, 'donations_form_shortcode'));
        add_action('wp_ajax_save_donation', array(__CLASS__, 'save_donation'));
        add_action('wp_ajax_nopriv_save_donation', array(__CLASS__, 'save_donation'));
    }

    public static function add_admin_menu() {
        add_menu_page(
            'Configuración del módulo de donaciones',
            'Módulo de donaciones',
            'manage_options',
            'donations-module',
            array(__CLASS__, 'settings_page')
        );

        add_submenu_page(
            'donations-module',
            'Configuración',
            'Configuración',
            'manage_options',
            'donations-module',
            array(__CLASS__, 'settings_page')
        );

        add_submenu_page(
            'donations-module',
            'Lista de Donaciones',
            'Lista de Donaciones',
            'manage_options',
            'donations-module-donations-list',
            array(__CLASS__, 'donations_list_page')
        );
    }

    public static function register_settings() {
        register_setting('donations_module', 'donations_goal', array('sanitize_callback' => 'intval'));
        register_setting('donations_module', 'progress_bar_color');
        register_setting('donations_module', 'progress_bar_height');
        register_setting('donations_module', 'progress_bar_well_color');
        register_setting('donations_module', 'progress_bar_well_width');
        register_setting('donations_module', 'progress_bar_border_radius');
        register_setting('donations_module', 'paypal_client_id');
        register_setting('donations_module', 'paypal_button_layout');
        register_setting('donations_module', 'paypal_button_color');
        register_setting('donations_module', 'paypal_button_shape');
        register_setting('donations_module', 'paypal_button_label');
        register_setting('donations_module', 'paypal_button_height');
        register_setting('donations_module', 'paypal_button_funding_sources', array('default' => array('paypal', 'credit')));
        register_setting('donations_module', 'paypal_button_id');
    }

    public static function settings_page() {
        ?>
        <div class="wrap">
            <h1>Configuración del módulo de donaciones</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=donations-module" class="nav-tab<?php if(!isset($_GET['tab']) || $_GET['tab'] == 'settings') echo ' nav-tab-active'; ?>">Configuración</a>
                <a href="?page=donations-module&tab=donations-list" class="nav-tab<?php if(isset($_GET['tab']) && $_GET['tab'] == 'donations-list') echo ' nav-tab-active'; ?>">Lista de Donaciones</a>
            </h2>
            <?php
            if(isset($_GET['tab']) && $_GET['tab'] == 'donations-list') {
                self::donations_list_page();
            } else {
                self::settings_tab_page();
            }
            ?>
            <div style="margin-top: 20px;">
                <h3>Status de PayPal: <span style="color: <?php echo self::check_paypal_sdk_status() ? 'green' : 'red'; ?>;"><?php echo self::check_paypal_sdk_status() ? 'Up' : 'Down'; ?></span></h3>
            </div>
        </div>
        <?php
    }

    public static function settings_tab_page() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('donations_module');
            do_settings_sections('donations_module');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Meta de donaciones</th>
                    <td><input type="number" name="donations_goal" value="<?php echo esc_attr(intval(get_option('donations_goal'))); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Color de la barra de progreso</th>
                    <td><input type="color" name="progress_bar_color" value="<?php echo esc_attr(get_option('progress_bar_color', '#00ff00')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Altura de la barra de progreso (px)</th>
                    <td><input type="number" name="progress_bar_height" value="<?php echo esc_attr(get_option('progress_bar_height', 20)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Color del fondo de la barra de progreso</th>
                    <td><input type="color" name="progress_bar_well_color" value="<?php echo esc_attr(get_option('progress_bar_well_color', '#eeeeee')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Ancho del fondo de la barra de progreso (%)</th>
                    <td><input type="number" name="progress_bar_well_width" value="<?php echo esc_attr(get_option('progress_bar_well_width', 100)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Esquinas Redondeadas de la Barra de Progreso (px)</th>
                    <td><input type="number" name="progress_bar_border_radius" value="<?php echo esc_attr(get_option('progress_bar_border_radius', 0)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">ID del cliente de PayPal</th>
                    <td><input type="text" name="paypal_client_id" value="<?php echo esc_attr(get_option('paypal_client_id')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">ID del botón de PayPal</th>
                    <td><input type="text" name="paypal_button_id" value="<?php echo esc_attr(get_option('paypal_button_id')); ?>" /></td>
                </tr>
            </table>
            
            <h2>Configuración del botón de PayPal</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Diseño del botón</th>
                    <td>
                        <select name="paypal_button_layout">
                            <option value="vertical" <?php selected(get_option('paypal_button_layout'), 'vertical'); ?>>Vertical</option>
                            <option value="horizontal" <?php selected(get_option('paypal_button_layout'), 'horizontal'); ?>>Horizontal</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Color del botón</th>
                    <td>
                        <select name="paypal_button_color">
                            <option value="gold" <?php selected(get_option('paypal_button_color'), 'gold'); ?>>Gold</option>
                            <option value="blue" <?php selected(get_option('paypal_button_color'), 'blue'); ?>>Blue</option>
                            <option value="silver" <?php selected(get_option('paypal_button_color'), 'silver'); ?>>Silver</option>
                            <option value="white" <?php selected(get_option('paypal_button_color'), 'white'); ?>>White</option>
                            <option value="black" <?php selected(get_option('paypal_button_color'), 'black'); ?>>Black</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Forma del botón</th>
                    <td>
                        <select name="paypal_button_shape">
                            <option value="pill" <?php selected(get_option('paypal_button_shape'), 'pill'); ?>>Pill</option>
                            <option value="rect" <?php selected(get_option('paypal_button_shape'), 'rect'); ?>>Rect</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Etiqueta del botón</th>
                    <td>
                        <select name="paypal_button_label">
                            <option value="paypal" <?php selected(get_option('paypal_button_label'), 'paypal'); ?>>PayPal</option>
                            <option value="checkout" <?php selected(get_option('paypal_button_label'), 'checkout'); ?>>Checkout</option>
                            <option value="buynow" <?php selected(get_option('paypal_button_label'), 'buynow'); ?>>Buy Now</option>
                            <option value="pay" <?php selected(get_option('paypal_button_label'), 'pay'); ?>>Pay</option>
                            <option value="installment" <?php selected(get_option('paypal_button_label'), 'installment'); ?>>Installment</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Altura del botón (px)</th>
                    <td><input type="number" name="paypal_button_height" value="<?php echo esc_attr(get_option('paypal_button_height', 40)); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Botones a mostrar</th>
                    <td>
                        <label><input type="checkbox" name="paypal_button_funding_sources[]" value="paypal" <?php checked(in_array('paypal', (array) get_option('paypal_button_funding_sources', []))); ?>> PayPal</label><br>
                        <label><input type="checkbox" name="paypal_button_funding_sources[]" value="credit" <?php checked(in_array('credit', (array) get_option('paypal_button_funding_sources', []))); ?>> Credit Card</label><br>
                        <label><input type="checkbox" name="paypal_button_funding_sources[]" value="paylater" <?php checked(in_array('paylater', (array) get_option('paypal_button_funding_sources', []))); ?>> Pay Later</label><br>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    public static function donations_list_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $donations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1>Lista de Donaciones</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cantidad</th>
                        <th>ID de Transacción</th>
                        <th>Nombre del Donante</th>
                        <th>Email del Donante</th>
                        <th>Fecha</th>
                        <th>ID del Botón</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation) { ?>
                        <tr>
                            <td><?php echo esc_html($donation->id); ?></td>
                            <td><?php echo esc_html(number_format($donation->amount, 2)); ?></td>
                            <td><?php echo esc_html($donation->transaction_id); ?></td>
                            <td><?php echo esc_html($donation->donor_name); ?></td>
                            <td><?php echo esc_html($donation->donor_email); ?></td>
                            <td><?php echo esc_html($donation->created_at); ?></td>
                            <td><?php echo esc_html($donation->button_id); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function donations_form_shortcode() {
        $goal = intval(get_option('donations_goal', 0)); // Ensuring goal is an integer
        $current_total = self::get_current_donations_total();
        $progress = $goal > 0 ? ($current_total / $goal) * 100 : 0;

        $progress_bar_color = get_option('progress_bar_color', '#00ff00');
        $progress_bar_height = get_option('progress_bar_height', 20);
        $progress_bar_well_color = get_option('progress_bar_well_color', '#eeeeee');
        $progress_bar_well_width = get_option('progress_bar_well_width', 100);
        $progress_bar_border_radius = get_option('progress_bar_border_radius', 0); // New option for rounded corners
        $paypal_client_id = get_option('paypal_client_id');
        $paypal_button_id = get_option('paypal_button_id');
        
        $paypal_button_layout = get_option('paypal_button_layout', 'vertical');
        $paypal_button_color = get_option('paypal_button_color', 'gold');
        $paypal_button_shape = get_option('paypal_button_shape', 'pill');
        $paypal_button_label = get_option('paypal_button_label', 'paypal');
        $paypal_button_height = get_option('paypal_button_height', 40);
        $paypal_button_funding_sources = get_option('paypal_button_funding_sources', ['paypal', 'credit']);

        ob_start();
        ?>
        <form id="donations-form" class="donations-form" onsubmit="return false;" aria-labelledby="donations-form-heading">
            <h2 id="donations-form-heading">Formulario de donaciones</h2>
            <label for="donation-amount">Total de la donación:</label>
            <input type="text" name="donation_amount" id="donation-amount" required aria-required="true" aria-label="Donation amount" class="donation-amount">
            <input type="hidden" name="button_id" value="<?php echo esc_attr($paypal_button_id); ?>">
            <div id="paypal-button-container"></div>
            <div id="form-feedback" role="alert" style="display:none; color:red;"></div> <!-- Feedback container -->
        </form>
        <div id="donation-progress" style="background-color: <?php echo esc_attr($progress_bar_well_color); ?>; width: <?php echo esc_attr($progress_bar_well_width); ?>%; height: <?php echo esc_attr($progress_bar_height); ?>px; max-width: 100%; border-radius: <?php echo esc_attr($progress_bar_border_radius); ?>px;" role="progressbar" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
            <div id="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo esc_attr($progress_bar_color); ?>; height: 100%; <?php echo ($progress >= 100) ? 'border-radius: ' . esc_attr($progress_bar_border_radius) . 'px;' : 'border-radius: ' . esc_attr($progress_bar_border_radius) . 'px 0 0 ' . esc_attr($progress_bar_border_radius) . 'px;'; ?>"></div>
        </div>
        <p id="donation-summary"><?php echo "$" . intval($current_total) . " de $" . $goal; ?></p>
        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo esc_attr($paypal_client_id); ?>&currency=USD&components=buttons,funding-eligibility"></script>
        <script>
            const fundingSources = <?php echo json_encode($paypal_button_funding_sources); ?>;
            const formFeedback = document.getElementById('form-feedback');
            
            fundingSources.forEach(fundingSource => {
                paypal.Buttons({
                    fundingSource: fundingSource,
                    style: {
                        layout: '<?php echo esc_js($paypal_button_layout); ?>',
                        color: '<?php echo esc_js($paypal_button_color); ?>',
                        shape: '<?php echo esc_js($paypal_button_shape); ?>',
                        label: '<?php echo esc_js($paypal_button_label); ?>',
                        height: <?php echo esc_js($paypal_button_height); ?>
                    },
                    createOrder: function(data, actions) {
                        var amount = document.getElementById('donation-amount').value;
                        if (isNaN(amount) || amount <= 0) {
                            formFeedback.style.display = 'block';
                            formFeedback.textContent = 'Por favor, introduzca una cantidad válida mayor que cero.';
                            return false; // Prevent form submission
                        }
                        formFeedback.style.display = 'none'; // Hide feedback if valid
                        return actions.order.create({
                            purchase_units: [{
                                amount: {
                                    value: amount
                                }
                            }]
                        });
                    },
                    onApprove: function(data, actions) {
                        return actions.order.capture().then(function(details) {
                            var amount = parseFloat(details.purchase_units[0].amount.value);
                            saveDonation(amount, details.id, details.payer.name.given_name, details.payer.email_address);
                        });
                    }
                }).render('#paypal-button-container').catch(function(err) {
                    document.getElementById('paypal-button-container').innerHTML = '<p>PayPal is currently unavailable. Please try again later.</p>';
                    console.error('PayPal Button Render Error:', err);
                });
            });

            function saveDonation(amount, transaction_id, donor_name, donor_email) {
                var formData = new FormData();
                formData.append('action', 'save_donation');
                formData.append('donation_amount', amount);
                formData.append('transaction_id', transaction_id);
                formData.append('donor_name', donor_name);
                formData.append('donor_email', donor_email);
                formData.append('button_id', '<?php echo esc_js($paypal_button_id); ?>');

                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        var progressBar = document.getElementById('progress-bar');
                        var currentTotal = data.current_total;
                        var goal = <?php echo $goal; ?>;
                        var progress = (currentTotal / goal) * 100;
                        progressBar.style.width = progress + '%';
                        document.getElementById('donation-summary').textContent = '$' + currentTotal + ' de $' + goal;
                    } else {
                        alert('Error al guardar la donación.');
                    }
                }).catch(error => console.error('Error:', error));
            }
        </script>
        <style>
            .donations-form {
                max-width: 100%;
                padding: 10px;
                box-sizing: border-box;
            }

            .donations-form input, .donations-form button {
                width: 100%;
                max-width: 100%;
                padding: 10px;
                margin-bottom: 10px;
                font-size: 1.2em;
            }

            #paypal-button-container {
                max-width: 100%;
                text-align: center;
            }

            #progress-bar {
                transition: width 0.5s ease-in-out;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    private static function get_current_donations_total() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $result = $wpdb->get_var("SELECT SUM(amount) FROM $table_name");
        return $result ? intval($result) : 0; // Ensure the result is an integer
    }

    public static function save_donation() {
        if (!isset($_POST['donation_amount']) || empty($_POST['donation_amount'])) {
            wp_send_json(array('success' => false, 'message' => 'Cantidad de la donación no proporcionada.'));
        }
        if (!isset($_POST['transaction_id']) || empty($_POST['transaction_id'])) {
            wp_send_json(array('success' => false, 'message' => 'ID de la transacción no proporcionada.'));
        }
        if (!is_numeric($_POST['donation_amount']) || $_POST['donation_amount'] <= 0) {
            wp_send_json(array('success' => false, 'message' => 'Cantidad de la donación inválida.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'donations';
        $amount = sanitize_text_field($_POST['donation_amount']);
        $transaction_id = sanitize_text_field($_POST['transaction_id']);
        $donor_name = sanitize_text_field($_POST['donor_name']);
        $donor_email = sanitize_text_field($_POST['donor_email']);
        $button_id = sanitize_text_field($_POST['button_id']);

        $wpdb->insert($table_name, array(
            'amount' => $amount,
            'transaction_id' => $transaction_id,
            'donor_name' => $donor_name,
            'donor_email' => $donor_email,
            'button_id' => $button_id
        ));

        $current_total = self::get_current_donations_total();
        wp_send_json(array('success' => true, 'current_total' => $current_total));
    }

    public static function check_paypal_sdk_status() {
        $response = wp_remote_get('https://www.paypal.com/sdk/js?client-id=test');

        if (is_wp_error($response)) {
            return false; // PayPal SDK check failed, treat it as down
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return $status_code === 200; // PayPal SDK is up if we get a 200 response
    }
}

// Instanciar la clase
new Donations_Module();

// Registrar hooks de activación y desactivación
register_activation_hook(__FILE__, array('Donations_Module', 'activate'));
register_deactivation_hook(__FILE__, array('Donations_Module', 'deactivate'));
