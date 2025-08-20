<?php
/**
 * Plugin Name: EVS Vloerverwarming Offerte - Improved
 * Plugin URI: https://evs-vloerverwarmingen.nl
 * Description: Professioneel offerte- en factuursysteem voor vloerverwarming installaties met verbeterde architectuur
 * Version: 4.8.9
 * Author: Zee-Zicht Media
 * Author URI: https://zee-zicht.nl
 * Text Domain: evs-vloerverwarming
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Voorkom direct toegang tot dit bestand
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Plugin constants
define('EVS_PLUGIN_VERSION', '4.8.9');
define('EVS_IMPROVED_PATH', plugin_dir_path(__FILE__));
define('EVS_IMPROVED_URL', plugin_dir_url(__FILE__));


// Load required classes
require_once EVS_IMPROVED_PATH . 'includes/class-evs-pricing-calculator.php';
require_once EVS_IMPROVED_PATH . 'includes/class-evs-form-handler.php';
require_once EVS_IMPROVED_PATH . 'includes/class-evs-email-service.php';
require_once EVS_IMPROVED_PATH . 'includes/class-evs-admin-manager.php';
require_once EVS_IMPROVED_PATH . 'includes/class-evs-database-manager.php';

final class EVS_Vloerverwarming_Offerte_Improved {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    private $version = EVS_PLUGIN_VERSION;
    
    /**
     * Service instances
     */
    private $pricing_calculator;
    private $form_handler;
    private $email_service;
    private $admin_manager;
    private $database_manager;
    
    /**
     * Whether error logging is enabled
     */
    private $log_enabled = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Enable logging if WP_DEBUG and WP_DEBUG_LOG are true
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $this->log_enabled = true;
        }

        $this->init_services();
        $this->init_hooks();
    }
    
    /**
     * Initialize service classes
     */
    private function init_services() {
        global $wpdb;
        $this->pricing_calculator = new EVS_Pricing_Calculator();
        $this->database_manager = new EVS_Database_Manager($wpdb);
        $this->email_service = new EVS_Email_Service();
        $this->form_handler = new EVS_Form_Handler($this->pricing_calculator, $this->database_manager, $this->email_service);
        $this->admin_manager = new EVS_Admin_Manager($this->database_manager, $this->email_service, $this->pricing_calculator);
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Shortcode
        add_shortcode('evs_offerte_formulier', array($this, 'render_form_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_evs_vloerverwarming_offerte_submit', array($this->form_handler, 'process_form'));
        add_action('wp_ajax_nopriv_evs_vloerverwarming_offerte_submit', array($this->form_handler, 'process_form'));
        add_action('wp_ajax_log_evs_form_error', array($this, 'log_js_error'));
        add_action('wp_ajax_nopriv_log_evs_form_error', array($this, 'log_js_error'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_evs_calculate_admin_price', array($this, 'calculate_admin_price'));
        
        // Quote acceptance handlers (public endpoints)
        add_action('init', array($this, 'handle_quote_actions'));
        
        // Admin hooks are now handled within the EVS_Admin_Manager class constructor.
    }
    
    /**
     * Render form shortcode
     */
    public function render_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Vraag een offerte aan',
            'show_title' => 'true'
        ), $atts);
        
        // Generate form HTML
        return $this->form_handler->render_form($atts);
    }
    

    
    /**
     * Calculate admin price via AJAX
     */
    public function calculate_admin_price() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'evs_form_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get form data
        parse_str($_POST['form_data'] ?? '', $form_data);
        
        // Sanitize input, handling both 1/0 numeric values and ja/nee text values
        $sanitized_data = array(
            'area_m2'             => floatval($form_data['area_m2'] ?? 0),
            'type_vloer'          => sanitize_text_field($form_data['type_vloer'] ?? ''),
            'verdeler_aansluiten' => intval($form_data['verdeler_aansluiten'] ?? 0),
            'vloer_dichtsmeren'   => intval($form_data['vloer_dichtsmeren'] ?? 0),
            'vloer_schuren'       => intval($form_data['vloer_schuren'] ?? 0),
        );
        
        // Perform calculation using the pricing calculator
        $pricing = $this->pricing_calculator->calculate_price($sanitized_data);
        
        // Send the appropriate JSON response based on calculation success or failure.
        if (!empty($pricing['success'])) {
            wp_send_json_success($pricing);
        } else {
            wp_send_json_error(array('message' => $pricing['error'] ?? 'An unknown error occurred.'));
        }
    }
    
    /**
     * Log JavaScript errors from the frontend
     */
    public function log_js_error() {
        if (isset($_POST['error_message'])) {
            error_log('EVS Frontend JS Error: ' . sanitize_text_field($_POST['error_message']));
        }
        wp_die();
    }
    
    /**
     * Handle quote acceptance/decline actions from email links
     */
    public function handle_quote_actions() {
        if (!isset($_GET['evs_action']) || !isset($_GET['quote_id']) || !isset($_GET['token'])) {
            return;
        }
        
        $action = sanitize_key($_GET['evs_action']);
        $quote_id = intval($_GET['quote_id']);
        $token = sanitize_text_field($_GET['token']);
        
        // Get quote data for validation
        $quote = $this->database_manager->get_quote($quote_id);
        if (!$quote) {
            wp_die('Offerte niet gevonden.');
        }
        
        // Verify token security
        $expected_token = '';
        if ($action === 'accept_quote') {
            $expected_token = wp_hash('accept_' . $quote_id . '_' . $quote['email']);
            $new_status = 'accepted';
            $message = 'Bedankt! Uw offerte is geaccepteerd. Wij nemen binnenkort contact met u op.';
            $admin_message = 'Offerte #' . $quote_id . ' is geaccepteerd door ' . $quote['naam'];
        } elseif ($action === 'decline_quote') {
            $expected_token = wp_hash('decline_' . $quote_id . '_' . $quote['email']);
            $new_status = 'declined';
            $message = 'Uw offerte is afgewezen. Bedankt voor uw interesse.';
            $admin_message = 'Offerte #' . $quote_id . ' is afgewezen door ' . $quote['naam'];
        } else {
            wp_die('Ongeldige actie.');
        }
        
        if (!hash_equals($expected_token, $token)) {
            wp_die('Ongeldig token.');
        }
        
        // Update quote status
        $result = $this->database_manager->update_quote($quote_id, ['status' => $new_status]);
        
        if ($result === true) {
            // Send notification to admin
            $admin_email = get_option('admin_email');
            wp_mail(
                $admin_email,
                'EVS Offerte Status Update - #' . $quote_id,
                $admin_message . "\n\nBekijk de offerte in het admin dashboard.",
                ['Content-Type: text/plain; charset=UTF-8']
            );
            
            // Show success page
            $this->show_quote_action_result($message, $new_status);
        } else {
            wp_die('Fout bij het bijwerken van de offerte status.');
        }
    }
    
    /**
     * Show result page after quote action
     */
    private function show_quote_action_result($message, $status) {
        $title = $status === 'accepted' ? 'Offerte Geaccepteerd' : 'Offerte Afgewezen';
        $color = $status === 'accepted' ? '#28a745' : '#dc3545';
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background-color: #f8f9fa; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .icon { font-size: 64px; margin-bottom: 20px; }
                .title { color: <?php echo $color; ?>; margin-bottom: 20px; }
                .message { font-size: 18px; line-height: 1.6; color: #333; }
                .footer { margin-top: 30px; font-size: 14px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon"><?php echo $status === 'accepted' ? '✅' : '❌'; ?></div>
                <h1 class="title"><?php echo esc_html($title); ?></h1>
                <p class="message"><?php echo esc_html($message); ?></p>
                <div class="footer">
                    <strong>EVS Vloerverwarmingen</strong><br>
                    Voor vragen kunt u contact met ons opnemen.
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Log an error to the log file
     */
    private function log_error($message, $data = array()) {
        if (!$this->log_enabled) return;

        $upload_dir = wp_upload_dir();
        if ( ! is_writable( $upload_dir['basedir'] ) ) {
            // Can't write to log, so use the default PHP error log as a fallback.
            error_log('EVS Plugin Logging Error: Upload directory is not writable.');
            return;
        }

        $log_dir = $upload_dir['basedir'] . '/evs-logs';
        if ( ! file_exists( $log_dir ) ) {
            if ( ! wp_mkdir_p( $log_dir ) ) {
                error_log('EVS Plugin Logging Error: Could not create log directory.');
                return;
            }
        }

        // Secure log directory
        $htaccess_file = $log_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            file_put_contents( $htaccess_file, 'Deny from all' );
        }

        $index_file = $log_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            file_put_contents( $index_file, '<?php // Silence is golden.' );
        }

        $log_file = $log_dir . '/error.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}" . (empty($data) ? '' : ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
        
        // The 3rd argument to error_log is 'destination', which is the file path.
        // It returns true on success, false on failure.
        if ( ! error_log($log_message, 3, $log_file) ) {
            // Fallback if writing to custom log fails
            error_log('EVS Plugin Logging Error: Could not write to custom log file: ' . $log_file);
        }
    }
    
    /**
     * Plugin activation hook
     */
    public static function on_activation() {
        global $wpdb;
        // Create database tables
        $database_manager = new EVS_Database_Manager($wpdb);
        $database_manager->create_tables();

        // Create and secure log directory
        $upload_dir = wp_upload_dir();
        if ( ! is_writable( $upload_dir['basedir'] ) ) {
            error_log('EVS Plugin Activation: Upload directory is not writable.');
            return;
        }

        $log_dir = $upload_dir['basedir'] . '/evs-logs';
        if ( ! file_exists( $log_dir ) ) {
            if ( ! wp_mkdir_p( $log_dir ) ) {
                error_log('EVS Plugin Activation: Could not create log directory.');
                return;
            }
        }

        // Secure log directory with .htaccess
        $htaccess_file = $log_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            if ( false === file_put_contents( $htaccess_file, 'Deny from all' ) ) {
                error_log('EVS Plugin Activation: Could not write .htaccess file.');
            }
        }

        // Add blank index.php to prevent directory listing
        $index_file = $log_dir . '/index.php';
        if ( ! file_exists( $index_file ) ) {
            if ( false === file_put_contents( $index_file, '<?php // Silence is golden.' ) ) {
                error_log('EVS Plugin Activation: Could not write index.php file.');
            }
        }
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function on_deactivation() {
        // Clean up if needed
    }
}

/**
 * Initialize the plugin
 */
function evs_vloerverwarming_offerte_improved_init() {
    EVS_Vloerverwarming_Offerte_Improved::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'evs_vloerverwarming_offerte_improved_init');

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('EVS_Vloerverwarming_Offerte_Improved', 'on_activation'));
register_deactivation_hook(__FILE__, array('EVS_Vloerverwarming_Offerte_Improved', 'on_deactivation'));