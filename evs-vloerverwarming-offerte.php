<?php
/**
 * Plugin Name: EVS Vloerverwarming Offerte
 * Plugin URI: https://evs-vloerverwarmingen.nl
 * Description: Professioneel offerte- en factuursysteem voor vloerverwarming installaties met moderne architectuur
 * Version: 4.0.3
 * Author: EVS Vloerverwarmingen
 * Author URI: https://evs-vloerverwarmingen.nl
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

// Plugin constants - with defensive checks for WordPress functions
define('EVS_PLUGIN_VERSION', '4.0.3');

// Only define these constants if WordPress functions are available
if (function_exists('plugin_dir_path')) {
    define('EVS_PLUGIN_PATH', plugin_dir_path(__FILE__));
} else {
    define('EVS_PLUGIN_PATH', dirname(__FILE__) . '/');
}

if (function_exists('plugin_dir_url')) {
    define('EVS_PLUGIN_URL', plugin_dir_url(__FILE__));
} else {
    define('EVS_PLUGIN_URL', plugins_url('/', __FILE__));
}

if (function_exists('plugin_basename')) {
    define('EVS_PLUGIN_BASENAME', plugin_basename(__FILE__));
} else {
    define('EVS_PLUGIN_BASENAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
}

// Include the Composer autoloader safely
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback error if autoloader is missing
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>EVS Vloerverwarming:</strong> Composer autoloader niet gevonden. Voer "composer install" uit in de plugin directory.</p></div>';
    });
    return;
}

// Import required classes
use EVS\Container\Container;
use EVS\Controllers\QuoteController;
use EVS\Controllers\InvoiceController;
use EVS\Controllers\AdminController;
use EVS\Mail\QuoteMailer;

final class EVS_Vloerverwarming_Offerte {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    private $version = EVS_PLUGIN_VERSION;
    
    /**
     * Dependency injection container
     */
    private Container $container;
    
    /**
     * Quote controller
     */
    private QuoteController $quoteController;
    
    /**
     * Invoice controller
     */
    private InvoiceController $invoiceController;
    
    /**
     * Admin controller
     */
    private AdminController $adminController;
    
    /**
     * Whether error logging is enabled
     */
    private $log_enabled = true;
    
    /**
     * Log een fout naar het logbestand
     * 
     * @param string $message Foutmelding
     * @param array $data Extra gegevens over de fout
     * @return void
     */
    private function log_error($message, $data = array()) {
        if (!$this->log_enabled) return;

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/evs-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        $log_file = $log_dir . '/error.log';
        
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}" . (empty($data) ? '' : ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
        
        error_log($log_message, 3, $log_file);
    }
    
    /**
     * Log JavaScript fouten
     */
    public function log_js_error() {
        check_ajax_referer('evs_form_nonce', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'Onbekende JavaScript fout';
        $error = isset($_POST['error']) ? sanitize_text_field($_POST['error']) : '';
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        $this->log_error('JavaScript fout: ' . $message, array(
            'error' => $error,
            'url' => $url
        ));
        
        wp_send_json_success();
    }
    
    /**
     * Constructor - Initialize the new architecture
     */
    private function __construct() {
        // Initialize dependency injection container
        $this->container = new Container();
        $this->container->registerServices();
        
        // Initialize controllers
        $mailer = new QuoteMailer();
        
        $this->quoteController = new QuoteController(
            $this->container->getQuoteService(),
            $this->container->getInvoiceService(),
            $mailer
        );
        
        $this->invoiceController = new InvoiceController(
            $this->container->getInvoiceService(),
            $mailer
        );
        
        $this->adminController = new AdminController(
            $this->container->getQuoteService(),
            $this->container->getInvoiceService()
        );
        
        // Register WordPress hooks
        $this->registerHooks();
    }
    
    /**
     * Register all WordPress hooks
     */
    private function registerHooks(): void {
        // Shortcode
        add_shortcode('evs_offerte_formulier', array($this, 'render_form_shortcode'));
        
        // AJAX handlers - delegate to controller
        add_action('wp_ajax_evs_vloerverwarming_offerte_submit', array($this->quoteController, 'handleFormSubmission'));
        add_action('wp_ajax_nopriv_evs_vloerverwarming_offerte_submit', array($this->quoteController, 'handleFormSubmission'));
        
        // Error logging
        add_action('wp_ajax_log_evs_form_error', array($this, 'log_js_error'));
        add_action('wp_ajax_nopriv_log_evs_form_error', array($this, 'log_js_error'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_init', array($this, 'handle_admin_actions'));
        }
    }
    
    /**
     * Laad scripts en styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'evs-form-styles', 
            plugin_dir_url(__FILE__) . 'assets/css/evs-form.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            'evs-form-script',
            plugin_dir_url(__FILE__) . 'assets/js/evs-form.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script(
            'evs-form-script',
            'evs_offerte_ajax_object', 
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('evs_form_nonce')
            )
        );
        
        // Add JavaScript error logging function
        wp_add_inline_script('evs-form-script', '
            function logJsError(message, file, context) {
                if (typeof evs_offerte_ajax_object !== "undefined") {
                    jQuery.ajax({
                        url: evs_offerte_ajax_object.ajax_url,
                        type: "POST",
                        data: {
                            action: "log_evs_form_error",
                            nonce: evs_offerte_ajax_object.nonce,
                            message: message,
                            error: file || "unknown",
                            url: context || window.location.href
                        }
                    });
                }
            }
        ', 'before');
    }
    
    /**
     * Render het formulier via shortcode en laad de benodigde scripts.
     */
    public function render_form_shortcode($atts) {
        $this->enqueue_scripts();
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/form-template.php';
        return ob_get_clean();
    }
    
    /**
     * Legacy method - now delegates to controller
     * @deprecated Use QuoteController::handleFormSubmission() instead
     */
    public function process_form() {
        // Delegate to the new controller
        $this->quoteController->handleFormSubmission();
    }
    
    /**
     * Valideer formuliergegevens
     */
    private function validate_form_data($form_data) {
        $errors = array();
        
        $required_fields = array(
            'verdieping' => __('Verdieping is verplicht', 'evs-vloerverwarming'),
            'type_vloer' => __('Type vloer is verplicht', 'evs-vloerverwarming'),
            // oppervlakte has special validation below
            'warmtebron' => __('Warmtebron is verplicht', 'evs-vloerverwarming'),
            'verdeler_aansluiten' => __('Verdeler aansluiten keuze is verplicht', 'evs-vloerverwarming'),
            'vloer_dichtsmeren' => __('Vloer dichtsmeren keuze is verplicht', 'evs-vloerverwarming'),
            'montagedatum' => __('Montagedatum is verplicht', 'evs-vloerverwarming'),
            'email' => __('E-mailadres is verplicht', 'evs-vloerverwarming'),
            'voornaam' => __('Voornaam is verplicht', 'evs-vloerverwarming'),
            'achternaam' => __('Achternaam is verplicht', 'evs-vloerverwarming'),
            'adres' => __('Adres is verplicht', 'evs-vloerverwarming'),
            'huisnummer' => __('Huisnummer is verplicht', 'evs-vloerverwarming'),
            'postcode' => __('Postcode is verplicht', 'evs-vloerverwarming'),
            'plaats' => __('Plaats is verplicht', 'evs-vloerverwarming')
        );
        
        foreach ($required_fields as $field => $message) {
            if (empty($form_data[$field])) {
                $errors[$field] = $message;
            }
        }
        
        // Special validation for "anders" option
        if (!empty($form_data['verdieping']) && $form_data['verdieping'] === 'anders' && empty($form_data['verdieping_anders'])) {
            $errors['verdieping_anders'] = __('Specificatie voor "anders" is verplicht', 'evs-vloerverwarming');
        }
        
        // Special validation for date when "datum" is selected
        if (!empty($form_data['montagedatum']) && $form_data['montagedatum'] === 'datum' && empty($form_data['gewenste_datum'])) {
            $errors['gewenste_datum'] = __('Gewenste datum is verplicht', 'evs-vloerverwarming');
        }
        
        if (!empty($form_data['email']) && !is_email($form_data['email'])) {
            $errors['email'] = __('Een geldig e-mailadres is verplicht.', 'evs-vloerverwarming');
        }
        
        // Special validation for oppervlakte - must be numeric and > 0
        if (empty($form_data['oppervlakte']) || !is_numeric($form_data['oppervlakte']) || floatval($form_data['oppervlakte']) <= 0) {
            $errors['oppervlakte'] = __('Oppervlakte is verplicht en moet een getal groter dan 0 zijn.', 'evs-vloerverwarming');
        }
        
        return $errors;
    }
    
    /**
     * Saniteer formuliergegevens
     */
    private function sanitize_form_data($form_data) {
        $sanitized = array();
        
        foreach ($form_data as $key => $value) {
            switch ($key) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'oppervlakte':
                    $sanitized[$key] = floatval($value);
                    break;
                case 'gewenste_datum':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                case 'toelichting':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                case 'privacy_akkoord':
                    $sanitized[$key] = ($value === 'on' || $value === '1' || $value === 'yes') ? 'ja' : 'nee';
                    break;
                case 'verdieping':
                case 'verdieping_anders':
                case 'type_vloer':
                case 'warmtebron':
                case 'verdeler_aansluiten':
                case 'vloer_dichtsmeren':
                case 'montagedatum':
                case 'voornaam':
                case 'achternaam':
                case 'telefoon':
                case 'land':
                case 'adres':
                case 'huisnummer':
                case 'postcode':
                case 'plaats':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Formatteer een prijs met twee decimalen.
     */
    private function format_price($price) {
        return number_format($price, 2, ',', '.');
    }

    /**
     * Bereken offerte op basis van formuliergegevens
     */
    private function calculate_quote($form_data) {
        // Get area in m2 and convert to strekkende meter (x8.5)
        $area_m2 = floatval($form_data['oppervlakte'] ?? 0);
        $strekkende_meter = $area_m2 * 8.5;
        
        // Debug logging for area calculation
        error_log('EVS Debug - calculate_quote: oppervlakte from form_data: ' . var_export($form_data['oppervlakte'] ?? 'NOT_SET', true));
        error_log('EVS Debug - calculate_quote: area_m2 calculated: ' . $area_m2);
        error_log('EVS Debug - calculate_quote: strekkende_meter calculated: ' . $strekkende_meter);
        
        // Get floor type and calculate drilling price with tiered pricing
        $type_vloer = $form_data['type_vloer'] ?? '';
        $drilling_price = $this->calculate_drilling_price($type_vloer, $strekkende_meter);
        
        // Calculate verdeler aansluiten price
        $verdeler_price = ($form_data['verdeler_aansluiten'] === 'ja') ? 185.00 : 0;
        
        // Calculate vloer dichtsmeren price
        $sealing_price = ($form_data['vloer_dichtsmeren'] === 'ja') ? $strekkende_meter * 12.75 : 0;
        
        // Total drilling price includes verdeler
        $total_drilling_price = $drilling_price + $verdeler_price;
        
        return array(
            'drilling_price' => $total_drilling_price,
            'sealing_price' => $sealing_price,
            'total_price' => $total_drilling_price + $sealing_price,
            'area_m2' => $area_m2,
            'strekkende_meter' => $strekkende_meter,
            'verdeler_price' => $verdeler_price,
            'vat_included' => true,
            'type_vloer' => $type_vloer,
            'warmtebron' => $form_data['warmtebron'] ?? '',
            'verdieping' => $form_data['verdieping'] ?? ''
        );
    }
    
    /**
     * Bereken boorprijs op basis van vloertype en strekkende meter met tiered pricing
     */
    private function calculate_drilling_price($type_vloer, $strekkende_meter) {
        switch ($type_vloer) {
            case 'cement_dekvloer':
            case 'fermacelvloer':
                return $this->calculate_tiered_price($strekkende_meter);
            case 'tegelvloer':
                return $strekkende_meter * 2.25;
            case 'betonvloer':
                return $strekkende_meter * 4.00;
            default:
                return 0;
        }
    }
    
    /**
     * Bereken tiered pricing voor cement dekvloer en fermacelvloer
     */
    private function calculate_tiered_price($strekkende_meter) {
        $total_price = 0;
        $remaining_meters = $strekkende_meter;
        
        // Tier 1: 0-250m = €1,77
        if ($remaining_meters > 0) {
            $tier1_meters = min($remaining_meters, 250);
            $total_price += $tier1_meters * 1.77;
            $remaining_meters -= $tier1_meters;
        }
        
        // Tier 2: 250-500m = €1,67
        if ($remaining_meters > 0) {
            $tier2_meters = min($remaining_meters, 250);
            $total_price += $tier2_meters * 1.67;
            $remaining_meters -= $tier2_meters;
        }
        
        // Tier 3: 500-750m = €1,57
        if ($remaining_meters > 0) {
            $tier3_meters = min($remaining_meters, 250);
            $total_price += $tier3_meters * 1.57;
            $remaining_meters -= $tier3_meters;
        }
        
        // Tier 4: 750+ = €1,47
        if ($remaining_meters > 0) {
            $total_price += $remaining_meters * 1.47;
        }
        
        return $total_price;
    }
    
    /**
     * Haal prijsopties op (legacy - nu vervangen door nieuwe pricing logic)
     */
    private function get_price_options() {
        return array(
            'sealing_price_per_meter' => 12.75,
            'verdeler_price' => 185.00,
            'schuren_price_per_meter' => 7.00,
            'vat_rate' => 0.21
        );
    }
    
    /**
     * Sla offerte op in de WordPress database.
     */
    private function save_quote_to_database($form_data, $quote) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        try {
            $customer_name = trim(sprintf('%s %s', $form_data['voornaam'] ?? '', $form_data['achternaam'] ?? ''));
            
            // Handle verdieping with "anders" option
            $verdieping = $form_data['verdieping'] ?? '';
            $verdieping_other = '';
            if ($verdieping === 'anders' && !empty($form_data['verdieping_anders'])) {
                $verdieping_other = $form_data['verdieping_anders'];
            }
            
            // Handle installation date
            $installation_date = 'Onbekend';
            if (!empty($form_data['montagedatum'])) {
                if ($form_data['montagedatum'] === 'datum' && !empty($form_data['gewenste_datum'])) {
                    $installation_date = $form_data['gewenste_datum'];
                } elseif ($form_data['montagedatum'] === 'weet_niet') {
                    $installation_date = 'Weet ik nog niet';
                }
            }
            
            $data_to_insert = array(
                'created_at'        => current_time('mysql'),
                'customer_name'     => $customer_name,
                'customer_email'    => $form_data['email'] ?? '',
                'customer_phone'    => $form_data['telefoon'] ?? '',
                'customer_address'  => trim(sprintf('%s %s', $form_data['adres'] ?? '', $form_data['huisnummer'] ?? '')),
                'customer_postal'   => $form_data['postcode'] ?? '',
                'customer_city'     => $form_data['plaats'] ?? '',
                'customer_country'  => $form_data['land'] ?? 'Nederland',
                'floor_level'       => $verdieping,
                'floor_level_other' => $verdieping_other,
                'floor_type'        => $form_data['type_vloer'] ?? '',
                'area'              => $quote['area_m2'] ?? 0,
                'strekkende_meter'  => $quote['strekkende_meter'] ?? 0,
                
                // Debug logging for database save
                'heat_source'       => $form_data['warmtebron'] ?? '',
                'distributor'       => $form_data['verdeler_aansluiten'] ?? 'nee',
                'sealing'           => $form_data['vloer_dichtsmeren'] ?? 'nee',
                'installation_date' => $installation_date,
                'drilling_price'    => $quote['drilling_price'] ?? 0,
                'sealing_price'     => $quote['sealing_price'] ?? 0,
                'total_price'       => $quote['total_price'] ?? 0,
                'verdeler_price'    => $quote['verdeler_price'] ?? 0,
                'notes'             => $form_data['toelichting'] ?? '',
                'status'            => 'new',
            );

            $result = $wpdb->insert($table_name, $data_to_insert);

            if ($result === false) {
                $this->log_error(__('Database insertie mislukt', 'evs-vloerverwarming'), array('error' => $wpdb->last_error));
                return false;
            }

            return $wpdb->insert_id;

        } catch (Exception $e) {
            $this->log_error(__('Fout bij opslaan offerte', 'evs-vloerverwarming'), array('error' => $e->getMessage()));
            return false;
        }
    }

    /**
     * Stuur een e-mail met zowel HTML als platte tekst content.
     */
    private function send_email($to, $subject, $html_body, $plain_body, $attachments = []) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: EVS Vloerverwarmingen <noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>',
        ];

        // To send HTML mail, the wp_mail_content_type filter must be applied.
        add_filter('wp_mail_content_type', function() {
            return 'text/html';
        });

        $result = wp_mail($to, $subject, $html_body, $headers, $attachments);

        // Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
        remove_filter('wp_mail_content_type', 'wpdocs_set_html_mail_content_type');

        if (!$result) {
            $this->log_error('E-mail kon niet worden verzonden.', ['to' => $to, 'subject' => $subject]);
        }

        return $result;
    }

    /**
     * Stuur e-mail naar klant
     */
    private function send_customer_email($form_data, $quote, $attachments = []) {
        $to = $form_data['email'];
        $subject = sprintf(__('Uw offerte voor vloerverwarming installatie - %s', 'evs-vloerverwarming'), $form_data['customer_name'] ?? ($form_data['voornaam'] . ' ' . $form_data['achternaam']));

        $labels = [
            'subject' => __('Uw offerte voor vloerverwarming installatie', 'evs-vloerverwarming'),
            'greeting' => sprintf(__('Geachte %s %s,', 'evs-vloerverwarming'), esc_html($form_data['voornaam']), esc_html($form_data['achternaam'])),
            'intro' => __('Hartelijk dank voor uw interesse in onze vloerverwarmingsystemen. Wij hebben uw aanvraag zorgvuldig bestudeerd en zijn verheugd u hierbij onze offerte te kunnen presenteren.', 'evs-vloerverwarming'),
            'your_data' => __('Uw gegevens:', 'evs-vloerverwarming'),
            'heating_type' => __('Soort vloerverwarming:', 'evs-vloerverwarming'),
            'housing_type' => __('Type woning:', 'evs-vloerverwarming'),
            'floor_type' => __('Type vloer:', 'evs-vloerverwarming'),
            'area' => __('Oppervlakte:', 'evs-vloerverwarming'),
            'heat_source' => __('Warmtebron:', 'evs-vloerverwarming'),
            'sealing' => __('Vloer dichtsmeren:', 'evs-vloerverwarming'),
            'quote_details' => __('Offerte specificaties:', 'evs-vloerverwarming'),
            'drilling_price' => __('Prijs infrezen (incl. verdeler):', 'evs-vloerverwarming'),
            'sealing_price' => __('Prijs dichtsmeren:', 'evs-vloerverwarming'),
            'total_price' => __('Totaalbedrag (incl. BTW):', 'evs-vloerverwarming'),
            'next_steps' => __('Vervolgstappen', 'evs-vloerverwarming'),
            'next_steps_body' => __('Deze offerte is 30 dagen geldig. Wij nemen binnen 2 werkdagen contact met u op om eventuele vragen te bespreken en de planning door te nemen. Mocht u eerder contact wensen, dan kunt u ons altijd bereiken via onderstaande contactgegevens.', 'evs-vloerverwarming'),
            'regards' => __('Met vriendelijke groet,', 'evs-vloerverwarming'),
            'team' => __('EVS Vloerverwarmingen', 'evs-vloerverwarming'),
            'contact_info' => __('Telefoon: 0118-123456 | E-mail: info@evs-vloerverwarming.nl | Website: www.evs-vloerverwarming.nl', 'evs-vloerverwarming'),
            'company_details' => __('EVS Vloerverwarmingen B.V. | KvK: 12345678 | BTW: NL123456789B01', 'evs-vloerverwarming'),
        ];

        $css = 'body{font-family:Arial,sans-serif;color:#333;line-height:1.5}h2{color:#0a3b2e}h3{color:#00a651}.price{font-weight:bold;color:#0a3b2e}.footer{margin-top:20px;border-top:1px solid #eee;padding-top:15px}';

        $html_body = file_get_contents(plugin_dir_path(__FILE__) . 'templates/email-customer-html.php');
        $text_body = file_get_contents(plugin_dir_path(__FILE__) . 'templates/email-customer-plain.php');

        $replacements = [
            '{{subject}}' => $labels['subject'],
            '{{css}}' => $css,
            '{{greeting}}' => $labels['greeting'],
            '{{intro}}' => $labels['intro'],
            '{{your_data}}' => $labels['your_data'],
            '{{heating_type_label}}' => $labels['heating_type'],
            '{{heating_type_value}}' => 'Vloerverwarming',
            '{{housing_type_label}}' => $labels['housing_type'],
            '{{housing_type_value}}' => $this->get_readable_value('verdieping', $form_data['verdieping'] ?? '', $form_data),
            '{{floor_type_label}}' => $labels['floor_type'],
            '{{floor_type_value}}' => $this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '', $form_data),
            '{{area_label}}' => $labels['area'],
            '{{area_value}}' => sprintf(__('%d m²', 'evs-vloerverwarming'), $quote['area_m2']),
            '{{heat_source_label}}' => $labels['heat_source'],
            '{{heat_source_value}}' => $this->get_readable_value('warmtebron', $form_data['warmtebron'] ?? '', $form_data),
            '{{sealing_label}}' => $labels['sealing'],
            '{{sealing_value}}' => $this->get_readable_value('vloer_dichtsmeren', $form_data['vloer_dichtsmeren'] ?? '', $form_data),
            '{{quote_details}}' => $labels['quote_details'],
            '{{drilling_price_label}}' => $labels['drilling_price'],
            '{{drilling_price_value}}' => '€ ' . $this->format_price($quote['drilling_price']),
            '{{sealing_price_label}}' => $labels['sealing_price'],
            '{{sealing_price_value}}' => '€ ' . $this->format_price($quote['sealing_price']),
            '{{total_price_label}}' => $labels['total_price'],
            '{{total_price_value}}' => '€ ' . $this->format_price($quote['total_price']),
            '{{next_steps}}' => $labels['next_steps'],
            '{{next_steps_body}}' => $labels['next_steps_body'],
            '{{regards}}' => $labels['regards'],
            '{{team}}' => $labels['team'],
            '{{contact_info}}' => $labels['contact_info'],
            '{{company_details}}' => $labels['company_details'],
        ];

        $html_body = str_replace(array_keys($replacements), array_values($replacements), $html_body);
        $text_body = str_replace(array_keys($replacements), array_values($replacements), $text_body);

        return $this->send_email($to, $subject, $html_body, $text_body, $attachments);
    }

    /**
     * Stuur e-mail naar admin
     */
    private function send_admin_email($form_data, $quote) {
        $to = get_option('admin_email');
        $subject = sprintf(__('Aanvraag vloerverwarming offerte - %s (Referentie: #%d)', 'evs-vloerverwarming'), $form_data['voornaam'] . ' ' . $form_data['achternaam'], $quote['id']);

        $labels = [
            'subject' => __('Nieuwe aanvraag vloerverwarming offerte', 'evs-vloerverwarming'),
            'customer_details' => __('Klantgegevens:', 'evs-vloerverwarming'),
            'name' => __('Naam:', 'evs-vloerverwarming'),
            'email' => __('E-mail:', 'evs-vloerverwarming'),
            'phone' => __('Telefoon:', 'evs-vloerverwarming'),
            'address_details' => __('Installatieadres:', 'evs-vloerverwarming'),
            'address' => __('Adres:', 'evs-vloerverwarming'),
            'postcode' => __('Postcode:', 'evs-vloerverwarming'),
            'city' => __('Plaats:', 'evs-vloerverwarming'),
            'project_details' => __('Projectgegevens:', 'evs-vloerverwarming'),
            'heating_type' => __('Soort vloerverwarming:', 'evs-vloerverwarming'),
            'housing_type' => __('Type woning:', 'evs-vloerverwarming'),
            'floor_type' => __('Type vloer:', 'evs-vloerverwarming'),
            'area' => __('Oppervlakte:', 'evs-vloerverwarming'),
            'heat_source' => __('Warmtebron:', 'evs-vloerverwarming'),
            'sealing' => __('Vloer dichtsmeren:', 'evs-vloerverwarming'),
            'date' => __('Gewenste montagedatum:', 'evs-vloerverwarming'),
            'notes' => __('Toelichting:', 'evs-vloerverwarming'),
            'quote_details' => __('Berekende offerte:', 'evs-vloerverwarming'),
            'total_price' => __('Totaalbedrag (incl. BTW):', 'evs-vloerverwarming'),
            'quote_id_text' => sprintf(__('Deze offerte is opgeslagen in het systeem met ID: %d', 'evs-vloerverwarming'), $quote['id']),
        ];
        
        $css = 'body{font-family:Arial,sans-serif;color:#333;line-height:1.5}h2{color:#0a3b2e}h3{color:#00a651}.price{font-weight:bold;color:#0a3b2e}';

        $html_body = file_get_contents(plugin_dir_path(__FILE__) . 'templates/email-admin-html.php');
        $text_body = file_get_contents(plugin_dir_path(__FILE__) . 'templates/email-admin-plain.php');

        $replacements = [
            '{{subject}}' => $labels['subject'],
            '{{css}}' => $css,
            '{{customer_details}}' => $labels['customer_details'],
            '{{name_label}}' => $labels['name'],
            '{{name_value}}' => esc_html($form_data['voornaam'] . ' ' . $form_data['achternaam']),
            '{{email_label}}' => $labels['email'],
            '{{email_value}}' => esc_html($form_data['email']),
            '{{phone_label}}' => $labels['phone'],
            '{{phone_value}}' => esc_html($form_data['telefoon'] ?? __('Niet opgegeven', 'evs-vloerverwarming')),
            '{{address_details}}' => $labels['address_details'],
            '{{address_label}}' => $labels['address'],
            '{{address_value}}' => esc_html($form_data['adres'] . ' ' . $form_data['huisnummer']),
            '{{postcode_label}}' => $labels['postcode'],
            '{{postcode_value}}' => esc_html($form_data['postcode']),
            '{{city_label}}' => $labels['city'],
            '{{city_value}}' => esc_html($form_data['plaats']),
            '{{project_details}}' => $labels['project_details'],
            '{{heating_type_label}}' => $labels['heating_type'],
            '{{heating_type_value}}' => 'Vloerverwarming',
            '{{housing_type_label}}' => $labels['housing_type'],
            '{{housing_type_value}}' => $this->get_readable_value('verdieping', $form_data['verdieping'] ?? '', $form_data),
            '{{floor_type_label}}' => $labels['floor_type'],
            '{{floor_type_value}}' => $this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '', $form_data),
            '{{area_label}}' => $labels['area'],
            '{{area_value}}' => sprintf(__('%d m²', 'evs-vloerverwarming'), $quote['area_m2']),
            '{{heat_source_label}}' => $labels['heat_source'],
            '{{heat_source_value}}' => $this->get_readable_value('warmtebron', $form_data['warmtebron'] ?? '', $form_data),
            '{{sealing_label}}' => $labels['sealing'],
            '{{sealing_value}}' => $this->get_readable_value('vloer_dichtsmeren', $form_data['vloer_dichtsmeren'] ?? '', $form_data),
            '{{date_label}}' => $labels['date'],
            '{{date_value}}' => esc_html($form_data['montagedatum'] ?? __('Niet opgegeven', 'evs-vloerverwarming')),
            '{{notes_label}}' => $labels['notes'],
            '{{notes_value}}' => nl2br(esc_html($form_data['toelichting'] ?? __('Geen', 'evs-vloerverwarming'))),
            '{{quote_details}}' => $labels['quote_details'],
            '{{total_price_label}}' => $labels['total_price'],
            '{{total_price_value}}' => '€ ' . $this->format_price($quote['total_price']),
            '{{quote_id_text}}' => $labels['quote_id_text'],
        ];

        $html_body = str_replace(array_keys($replacements), array_values($replacements), $html_body);
        $text_body = str_replace(array_keys($replacements), array_values($replacements), $text_body);

        return $this->send_email($to, $subject, $html_body, $text_body);
    }
    
    /**
     * Krijg leesbare waarde voor alle formuliervelden
     */
    private function get_readable_value($field, $value, $form_data = array()) {
        if (in_array($field, ['drilling_price', 'sealing_price', 'total_amount'])) {
            return __('€ ', 'evs-vloerverwarming') . $this->format_price($value);
        }

        if ($field === 'floor_level' && $value === 'Anders' && !empty($form_data['floor_level_other'])) {
            return __('Anders: ', 'evs-vloerverwarming') . esc_html($form_data['floor_level_other']);
        }

        $map = [
            'new' => __('Nieuw', 'evs-vloerverwarming'),
            'sent' => __('Verzonden', 'evs-vloerverwarming'),
            'accepted' => __('Geaccepteerd', 'evs-vloerverwarming'),
            'invoiced' => __('Gefactureerd', 'evs-vloerverwarming'),
            'cancelled' => __('Geannuleerd', 'evs-vloerverwarming'),
            'unpaid' => __('Onbetaald', 'evs-vloerverwarming'),
            'paid' => __('Betaald', 'evs-vloerverwarming'),
            'ja' => __('Ja', 'evs-vloerverwarming'),
            'nee' => __('Nee', 'evs-vloerverwarming'),
            'infrezen' => __('Infrezen', 'evs-vloerverwarming'),
            'appartement' => __('Appartement', 'evs-vloerverwarming'),
            'tussenwoning' => __('Tussenwoning', 'evs-vloerverwarming'),
            'hoekwoning' => __('Hoekwoning', 'evs-vloerverwarming'),
            'twee_onder_een_kap' => __('2-onder-1-kap', 'evs-vloerverwarming'),
            'vrijstaand' => __('Vrijstaand', 'evs-vloerverwarming'),
            'zandcement' => __('Zandcement', 'evs-vloerverwarming'),
            'beton' => __('Beton', 'evs-vloerverwarming'),
            'anhydriet' => __('Anhydriet', 'evs-vloerverwarming'),
            'fermacell' => __('Fermacell', 'evs-vloerverwarming'),
            'tegels_of_plavuizen' => __('Tegels / plavuizen', 'evs-vloerverwarming'),
            'cv_ketel' => __('CV-ketel', 'evs-vloerverwarming'),
            'warmtepomp' => __('Warmtepomp', 'evs-vloerverwarming'),
            'stadsverwarming' => __('Stadsverwarming', 'evs-vloerverwarming'),
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        return esc_html(ucwords(str_replace('_', ' ', $value)));
    }

    /**
     * Voegt de admin menu pagina's toe.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Offertes', 'evs-vloerverwarming'),
            __('Offertes', 'evs-vloerverwarming'),
            'manage_options',
            'evs-offertes',
            array($this, 'display_offers_page'),
            'dashicons-clipboard',
            25
        );
        $this->add_invoices_submenu();

        // Add hidden edit page (not shown in menu)
        add_submenu_page(
            null, // Hidden from menu
            __('Offerte bewerken', 'evs-vloerverwarming'),
            __('Offerte bewerken', 'evs-vloerverwarming'),
            'manage_options',
            'evs-edit-offer',
            array($this, 'display_edit_offer_wrapper')
        );

        add_submenu_page(
            'evs-offertes',
            __('Instellingen', 'evs-vloerverwarming'),
            __('Instellingen', 'evs-vloerverwarming'),
            'manage_options',
            'evs-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Registreert de instellingen voor de plugin.
     */
    public function register_settings() {
        register_setting('evs_settings_group', 'evs_price_options');

        add_settings_section(
            'evs_pricing_section',
            __('Prijsinstellingen', 'evs-vloerverwarming'),
            null,
            'evs-settings'
        );

        add_settings_field(
            'sealing_price_per_meter',
            __('Prijs per m² (Dichtsmeren)', 'evs-vloerverwarming'),
            array($this, 'render_price_field'),
            'evs-settings',
            'evs_pricing_section',
            ['name' => 'sealing_price_per_meter', 'default' => '15.00']
        );
    }

    /**
     * Modernized admin action handler - delegates to appropriate controllers
     */
    public function handle_admin_actions() {
        // Handle quote actions
        if (isset($_GET['action']) && isset($_GET['offer_id'])) {
            $this->quoteController->handleAdminAction();
            return;
        }
        
        // Handle invoice actions
        if (isset($_GET['action']) && isset($_GET['invoice_id'])) {
            $this->invoiceController->handleAdminAction();
            return;
        }
    }

    /**
     * Handle delete offer action
     */
    private function handle_delete_offer($offer_id) {
        global $wpdb;
        
        // Verify user has permission
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Check if offer exists
        $table_name = $wpdb->prefix . 'evs_offertes';
        $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));
        
        if (!$offer) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Offerte niet gevonden.', 'evs-vloerverwarming') . '</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=evs-offertes'));
            exit;
        }
        
        // Handle confirmation
        if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
            // Delete the offer
            $deleted = $wpdb->delete($table_name, array('id' => $offer_id), array('%d'));
            
            if ($deleted) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Offerte succesvol verwijderd.', 'evs-vloerverwarming') . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij verwijderen van offerte.', 'evs-vloerverwarming') . '</p></div>';
                });
            }
            
            wp_redirect(admin_url('admin.php?page=evs-offertes'));
            exit;
        } else {
            // Show confirmation page
            $this->show_delete_confirmation($offer);
        }
    }
    
    /**
     * Show delete confirmation page
     */
    private function show_delete_confirmation($offer) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Offerte verwijderen', 'evs-vloerverwarming') . '</h1>';
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Waarschuwing:', 'evs-vloerverwarming') . '</strong> ' . esc_html__('Weet je zeker dat je deze offerte wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.', 'evs-vloerverwarming') . '</p></div>';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Klant:', 'evs-vloerverwarming') . '</th><td>' . esc_html($offer->customer_name) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Email:', 'evs-vloerverwarming') . '</th><td>' . esc_html($offer->customer_email) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Datum:', 'evs-vloerverwarming') . '</th><td>' . esc_html($offer->created_at) . '</td></tr>';
        echo '</table>';
        echo '<p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=evs-offertes&action=delete&offer_id=' . $offer->id . '&confirm=yes')) . '" class="button button-primary">' . esc_html__('Ja, verwijderen', 'evs-vloerverwarming') . '</a> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=evs-offertes')) . '" class="button">' . esc_html__('Annuleren', 'evs-vloerverwarming') . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Wrapper function for edit offer page
     */
    public function display_edit_offer_wrapper() {
        // Verify user has permission
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get offer ID from URL
        $offer_id = isset($_GET['offer_id']) ? intval($_GET['offer_id']) : 0;
        
        if (!$offer_id) {
            wp_die(__('Invalid offer ID.', 'evs-vloerverwarming'));
        }
        
        // Call the existing edit function with the offer ID
        $this->display_edit_offer_page($offer_id);
    }

    /**
     * Toont de instellingenpagina.
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Instellingen EVS Vloerverwarming', 'evs-vloerverwarming'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('evs_settings_group');
                do_settings_sections('evs-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Rendert een prijsveld voor de instellingenpagina.
     */
    public function render_price_field($args) {
        $options = get_option('evs_price_options');
        $value = isset($options[$args['name']]) ? esc_attr($options[$args['name']]) : $args['default'];
        echo "<input type='number' step='0.01' name='evs_price_options[{$args['name']}]' value='{$value}' />";
    }

    /**
     * Toont de lijst met offertes of het bewerkingsformulier.
     */
    public function display_offers_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $offer_id = isset($_GET['offer_id']) ? intval($_GET['offer_id']) : 0;

        if ($action === 'edit' && $offer_id) {
            $this->display_edit_offer_page($offer_id);
        } else {
            require_once plugin_dir_path(__FILE__) . 'admin/class-evs-offertes-list-table.php';
            $list_table = new EVS_Offertes_List_Table();
            $list_table->prepare_items();
            echo '<div class="wrap"><h1>' . esc_html__('Offertes', 'evs-vloerverwarming') . '</h1>';
            $list_table->display();
            echo '</div>';
        }
    }

    /**
     * Toont de bewerkpagina voor een specifieke offerte.
     */
    private function display_edit_offer_page($offer_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';
        $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id), ARRAY_A);

        if (!$offer) {
            echo '<div class="wrap"><h1>' . esc_html__('Fout', 'evs-vloerverwarming') . '</h1><p>' . esc_html__('Offerte niet gevonden.', 'evs-vloerverwarming') . '</p></div>';
            return;
        }

        if (isset($_POST['submit']) && check_admin_referer('update_offer_' . $offer_id)) {
            $this->save_offer_changes($offer_id, $_POST);
            $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id), ARRAY_A);
        }

        if (isset($_POST['send_offer']) && check_admin_referer('evs_send_offer_' . $offer_id, 'evs_send_offer_nonce')) {
            $this->handle_send_quote($offer_id);
        }

        if (isset($_POST['create_invoice']) && check_admin_referer('evs_create_invoice_' . $offer_id, 'evs_create_invoice_nonce')) {
            $this->handle_create_invoice($offer_id);
            $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id), ARRAY_A);
        }

        include plugin_dir_path(__FILE__) . 'admin/views/edit-offer-page.php';
    }

    /**
     * Slaat de wijzigingen van een offerte op.
     */
    private function save_offer_changes($offer_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        // Sanitize and prepare all editable fields
        $update_data = [
            'status' => sanitize_text_field($data['status'] ?? ''),
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'customer_address' => sanitize_text_field($data['customer_address'] ?? ''),
            'floor_level' => sanitize_text_field($data['floor_level'] ?? ''),
            'floor_type' => sanitize_text_field($data['floor_type'] ?? ''),
            'area' => intval($data['area'] ?? 0),
            'heat_source' => sanitize_text_field($data['heat_source'] ?? ''),
            'sealing' => sanitize_text_field($data['sealing'] ?? ''),
            'drilling_price' => floatval($data['drilling_price'] ?? 0),
            'sealing_price' => floatval($data['sealing_price'] ?? 0),
            'verdeler_price' => floatval($data['verdeler_price'] ?? 0),
            'installation_date' => sanitize_text_field($data['installation_date'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];
        
        // Calculate total price
        $update_data['total_price'] = $update_data['drilling_price'] + $update_data['sealing_price'] + $update_data['verdeler_price'];
        
        // Calculate strekkende meter from area
        $update_data['strekkende_meter'] = $update_data['area'] * 8.5;

        $update_format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%f', '%f'];

        if ($wpdb->update($table_name, $update_data, ['id' => $offer_id], $update_format, ['%d']) !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Offerte succesvol bijgewerkt.', 'evs-vloerverwarming') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het bijwerken van de offerte.', 'evs-vloerverwarming') . '</p></div>';
            });
        }
    }

    /**
     * Verwerkt het verzenden van de offerte.
     */
    private function handle_send_quote($offer_id) {
        global $wpdb;
        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quotes_table WHERE id = %d", $offer_id), ARRAY_A);

        if (!$offer) {
            $this->log_error(__('Kan offerte niet verzenden: offerte niet gevonden.', 'evs-vloerverwarming'), ['offer_id' => $offer_id]);
            return;
        }

        // Genereer de PDF-bestanden
        $upload_dir = wp_upload_dir();
        $temp_pdf_dir = $upload_dir['basedir'] . '/evs-temp-pdfs';
        if (!file_exists($temp_pdf_dir)) {
            wp_mkdir_p($temp_pdf_dir);
        }

        $attachments = [];
        // Check if PDF generator is available
        if (class_exists('EVS_PDF_Generator')) {
            $pdf_drilling = new EVS_PDF_Generator();
            $pdf_drilling->generate_quote_pdf($offer, 'drilling');
            $drilling_filename = $temp_pdf_dir . '/offerte-' . $offer_id . '-infrezen.pdf';
            $pdf_drilling->Output('F', $drilling_filename);
            $attachments[] = $drilling_filename;

            if ($offer['sealing'] === 'ja' && $offer['sealing_price'] > 0) {
                $pdf_sealing = new EVS_PDF_Generator();
                $pdf_sealing->generate_quote_pdf($offer, 'sealing');
                $sealing_filename = $temp_pdf_dir . '/offerte-' . $offer_id . '-dichtsmeren.pdf';
                $pdf_sealing->Output('F', $sealing_filename);
                $attachments[] = $sealing_filename;
            }
        } else {
            // Log error if PDF generator is not available
            $this->log_error(__('PDF generator niet beschikbaar voor offerte verzending.', 'evs-vloerverwarming'), ['offer_id' => $offer_id]);
        }

        // Create Quote object from database data and use QuoteMailer directly
        require_once plugin_dir_path(__FILE__) . 'src/Models/Quote.php';
        require_once plugin_dir_path(__FILE__) . 'src/Mail/QuoteMailer.php';
        
        // Convert database array to Quote object
        $quote = \EVS\Models\Quote::fromArray($offer);
        
        // Create QuoteMailer instance
        $mailer = new \EVS\Mail\QuoteMailer();
        
        // Send via QuoteMailer (same logic as automatic confirmation but with prices)
        if ($mailer->sendQuoteToCustomer($quote)) {
            $wpdb->update($quotes_table, ['status' => 'sent'], ['id' => $offer_id]);
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Offerte succesvol verzonden.', 'evs-vloerverwarming') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij verzenden van de offerte.', 'evs-vloerverwarming') . '</p></div>';
            });
        }

        // Ruim de tijdelijke PDF-bestanden op
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Verwerkt het aanmaken van een factuur vanuit een offerte.
     */
    private function handle_create_invoice($offer_id) {
        $invoice_id = $this->create_invoice_from_quote($offer_id);
        
        if ($invoice_id) {
            // Get the created invoice for success message
            global $wpdb;
            $invoices_table = $wpdb->prefix . 'evs_facturen';
            $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
            
            if ($invoice) {
                add_action('admin_notices', function() use ($invoice) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         sprintf(esc_html__('Factuur %s succesvol gegenereerd. Status: %s', 'evs-vloerverwarming'), 
                                esc_html($invoice['invoice_number']), 
                                esc_html(ucfirst($invoice['status']))) . 
                         '</p></div>';
                });
                
                // Redirect to invoice edit page
                wp_redirect(admin_url('admin.php?page=evs-invoices&action=edit&invoice_id=' . $invoice_id));
                exit;
            }
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html__('Fout bij het genereren van de factuur. Controleer of er al een factuur bestaat voor deze offerte.', 'evs-vloerverwarming') . 
                     '</p></div>';
            });
        }
    }

    /**
     * Voegt de facturen submenu pagina toe.
     */
    public function add_invoices_submenu() {
        add_submenu_page(
            'evs-offertes',
            __('Facturen', 'evs-vloerverwarming'),
            __('Facturen', 'evs-vloerverwarming'),
            'manage_options',
            'evs-invoices',
            array($this, 'display_invoices_page')
        );
    }

    /**
     * Toont de lijst met facturen.
     */
    public function display_invoices_page() {
        // Handle invoice actions
        if (isset($_GET['action']) && isset($_GET['invoice_id'])) {
            $action = sanitize_key($_GET['action']);
            $invoice_id = intval($_GET['invoice_id']);
            
            if ($action === 'edit') {
                $this->display_edit_invoice_page($invoice_id);
                return;
            } elseif ($action === 'delete') {
                $this->handle_delete_invoice($invoice_id);
                return;
            } elseif ($action === 'send') {
                $this->handle_send_invoice($invoice_id);
            } elseif ($action === 'mark_paid') {
                $this->handle_mark_invoice_paid($invoice_id);
            }
        }
        
        require_once plugin_dir_path(__FILE__) . 'admin/class-evs-invoices-list-table.php';
        $list_table = new EVS_Invoices_List_Table();
        $list_table->prepare_items();
        echo '<div class="wrap"><h1>' . esc_html__('Facturen', 'evs-vloerverwarming') . '</h1>';
        $list_table->display();
        echo '</div>';
    }

    /**
     * Wordt uitgevoerd bij het activeren van de plugin.
     */
    public static function on_activation() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create offertes table
        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $sql_quotes = "CREATE TABLE $quotes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            customer_phone varchar(20) DEFAULT '' NOT NULL,
            customer_address varchar(255) DEFAULT '' NOT NULL,
            customer_postal varchar(10) DEFAULT '' NOT NULL,
            customer_city varchar(100) DEFAULT '' NOT NULL,
            customer_country varchar(50) DEFAULT 'Nederland' NOT NULL,
            floor_level varchar(55) DEFAULT '' NOT NULL,
            floor_level_other varchar(255) DEFAULT '' NOT NULL,
            floor_type varchar(55) DEFAULT '' NOT NULL,
            area float NOT NULL,
            strekkende_meter float DEFAULT 0 NOT NULL,
            heat_source varchar(55) DEFAULT '' NOT NULL,
            distributor varchar(10) DEFAULT '' NOT NULL,
            sealing varchar(10) DEFAULT '' NOT NULL,
            installation_date varchar(55) DEFAULT '' NOT NULL,
            drilling_price decimal(10, 2) NOT NULL,
            sealing_price decimal(10, 2) NOT NULL,
            total_price decimal(10, 2) DEFAULT 0 NOT NULL,
            verdeler_price decimal(10, 2) DEFAULT 0 NOT NULL,
            notes text DEFAULT '' NOT NULL,
            status varchar(55) DEFAULT 'new' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_quotes);

        // Create invoices table
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        $sql_invoices = "CREATE TABLE $invoices_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            invoice_number varchar(255) NOT NULL,
            invoice_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            due_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            sent_date datetime DEFAULT NULL,
            paid_date datetime DEFAULT NULL,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            customer_phone varchar(20) DEFAULT '',
            customer_address text DEFAULT '',
            customer_postal varchar(10) DEFAULT '',
            customer_city varchar(100) DEFAULT '',
            drilling_price decimal(10, 2) DEFAULT 0,
            sealing_price decimal(10, 2) DEFAULT 0,
            verdeler_price decimal(10, 2) DEFAULT 0,
            total_amount decimal(10, 2) NOT NULL,
            btw_amount decimal(10, 2) DEFAULT 0,
            payment_method varchar(50) DEFAULT '',
            payment_reference varchar(255) DEFAULT '',
            status varchar(55) DEFAULT 'concept' NOT NULL,
            notes text DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY quote_id (quote_id),
            KEY status (status),
            KEY due_date (due_date)
        ) $charset_collate;";
        dbDelta($sql_invoices);
    }

    /**
     * Generate unique invoice number
     */
    private function generate_invoice_number() {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        
        $year = date('Y');
        $prefix = 'FAC-' . $year . '-';
        
        // Get the highest invoice number for this year
        $last_invoice = $wpdb->get_var($wpdb->prepare(
            "SELECT invoice_number FROM $invoices_table 
             WHERE invoice_number LIKE %s 
             ORDER BY invoice_number DESC LIMIT 1",
            $prefix . '%'
        ));
        
        if ($last_invoice) {
            // Extract the number part and increment
            $number_part = str_replace($prefix, '', $last_invoice);
            $next_number = intval($number_part) + 1;
        } else {
            // First invoice of the year
            $next_number = 1;
        }
        
        return $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create invoice from quote
     */
    private function create_invoice_from_quote($quote_id) {
        global $wpdb;
        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        
        // Debug logging
        $this->log_error('Creating invoice from quote', [
            'quote_id' => $quote_id,
            'quotes_table' => $quotes_table,
            'invoices_table' => $invoices_table
        ]);
        
        // Check if invoice table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$invoices_table'");
        $this->log_error('Invoice table check', [
            'table_exists' => $table_exists,
            'expected_table' => $invoices_table
        ]);
        
        // Get quote data
        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quotes_table WHERE id = %d", $quote_id), ARRAY_A);
        
        if (!$quote) {
            $this->log_error('Quote not found', ['quote_id' => $quote_id]);
            return false;
        }
        
        $this->log_error('Quote found', ['quote' => $quote]);
        
        // Check if invoice already exists for this quote
        $existing_invoice = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $invoices_table WHERE quote_id = %d", 
            $quote_id
        ));
        
        if ($existing_invoice) {
            return $existing_invoice; // Return existing invoice ID
        }
        
        // Generate invoice number
        $invoice_number = $this->generate_invoice_number();
        
        // Calculate dates
        $invoice_date = current_time('mysql');
        $due_date = date('Y-m-d H:i:s', strtotime('+30 days')); // 30 days payment term
        
        // Calculate BTW (21%)
        $total_ex_btw = floatval($quote['total_price']) / 1.21;
        $btw_amount = floatval($quote['total_price']) - $total_ex_btw;
        
        // Prepare invoice data
        $invoice_data = [
            'quote_id' => $quote_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'customer_name' => $quote['customer_name'],
            'customer_email' => $quote['customer_email'],
            'customer_phone' => $quote['customer_phone'] ?? '',
            'customer_address' => $quote['customer_address'] ?? '',
            'customer_postal' => $quote['customer_postal'] ?? '',
            'customer_city' => $quote['customer_city'] ?? '',
            'drilling_price' => $quote['drilling_price'],
            'sealing_price' => $quote['sealing_price'],
            'verdeler_price' => $quote['verdeler_price'] ?? 0,
            'total_amount' => $quote['total_price'],
            'btw_amount' => $btw_amount,
            'status' => 'concept',
            'created_at' => $invoice_date
        ];
        
        // Debug log invoice data
        $this->log_error('Invoice data prepared', [
            'invoice_data' => $invoice_data,
            'table' => $invoices_table
        ]);
        
        // Insert invoice
        $result = $wpdb->insert($invoices_table, $invoice_data);
        
        // Debug log insert result
        $this->log_error('Invoice insert result', [
            'result' => $result,
            'last_error' => $wpdb->last_error,
            'insert_id' => $wpdb->insert_id
        ]);
        
        if ($result === false) {
            $this->log_error('Failed to create invoice', ['quote_id' => $quote_id, 'error' => $wpdb->last_error]);
            return false;
        }
        
        $invoice_id = $wpdb->insert_id;
        
        // Update quote status
        $wpdb->update(
            $quotes_table,
            ['status' => 'invoiced'],
            ['id' => $quote_id],
            ['%s'],
            ['%d']
        );
        
        return $invoice_id;
    }

    /**
     * Display edit invoice page
     */
    private function display_edit_invoice_page($invoice_id) {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
        
        if (!$invoice) {
            echo '<div class="wrap"><h1>' . esc_html__('Fout', 'evs-vloerverwarming') . '</h1><p>' . esc_html__('Factuur niet gevonden.', 'evs-vloerverwarming') . '</p></div>';
            return;
        }
        
        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('update_invoice_' . $invoice_id)) {
            $this->save_invoice_changes($invoice_id, $_POST);
            $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
        }
        
        // Handle send invoice
        if (isset($_POST['send_invoice']) && check_admin_referer('evs_send_invoice_' . $invoice_id, 'evs_send_invoice_nonce')) {
            $this->handle_send_invoice($invoice_id);
            $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
        }
        
        // Handle mark as paid
        if (isset($_POST['mark_paid']) && check_admin_referer('evs_mark_paid_' . $invoice_id, 'evs_mark_paid_nonce')) {
            $this->handle_mark_invoice_paid($invoice_id);
            $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
        }
        
        include plugin_dir_path(__FILE__) . 'admin/views/edit-invoice-page.php';
    }

    /**
     * Save invoice changes
     */
    private function save_invoice_changes($invoice_id, $data) {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        
        $update_data = [
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'customer_address' => sanitize_text_field($data['customer_address'] ?? ''),
            'customer_postal' => sanitize_text_field($data['customer_postal'] ?? ''),
            'customer_city' => sanitize_text_field($data['customer_city'] ?? ''),
            'drilling_price' => floatval($data['drilling_price'] ?? 0),
            'sealing_price' => floatval($data['sealing_price'] ?? 0),
            'verdeler_price' => floatval($data['verdeler_price'] ?? 0),
            'payment_method' => sanitize_text_field($data['payment_method'] ?? ''),
            'payment_reference' => sanitize_text_field($data['payment_reference'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'concept'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];
        
        // Recalculate totals
        $subtotal = $update_data['drilling_price'] + $update_data['sealing_price'] + $update_data['verdeler_price'];
        $update_data['total_amount'] = $subtotal;
        $update_data['btw_amount'] = $subtotal * 0.21;
        
        // Update due date if status changed to 'sent'
        if ($update_data['status'] === 'sent' && !isset($data['due_date'])) {
            $update_data['due_date'] = date('Y-m-d H:i:s', strtotime('+30 days'));
            if (empty($data['sent_date'])) {
                $update_data['sent_date'] = current_time('mysql');
            }
        }
        
        // Set paid date if status is 'paid'
        if ($update_data['status'] === 'paid' && empty($data['paid_date'])) {
            $update_data['paid_date'] = current_time('mysql');
        }
        
        $update_format = ['%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%f'];
        
        if (isset($update_data['due_date'])) {
            $update_format[] = '%s';
        }
        if (isset($update_data['sent_date'])) {
            $update_format[] = '%s';
        }
        if (isset($update_data['paid_date'])) {
            $update_format[] = '%s';
        }
        
        if ($wpdb->update($invoices_table, $update_data, ['id' => $invoice_id], $update_format, ['%d']) !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Factuur succesvol bijgewerkt.', 'evs-vloerverwarming') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het bijwerken van de factuur.', 'evs-vloerverwarming') . '</p></div>';
            });
        }
    }

    /**
     * Handle send invoice
     */
    private function handle_send_invoice($invoice_id) {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
        
        if (!$invoice) {
            return false;
        }
        
        // Send invoice email
        $sent = $this->send_invoice_email($invoice);
        
        if ($sent) {
            // Update invoice status and sent date
            $wpdb->update(
                $invoices_table,
                [
                    'status' => 'sent',
                    'sent_date' => current_time('mysql'),
                    'due_date' => date('Y-m-d H:i:s', strtotime('+30 days'))
                ],
                ['id' => $invoice_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
            
            add_action('admin_notices', function() use ($invoice) {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     sprintf(esc_html__('Factuur %s succesvol verzonden naar %s', 'evs-vloerverwarming'), 
                            esc_html($invoice['invoice_number']), 
                            esc_html($invoice['customer_email'])) . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het verzenden van de factuur.', 'evs-vloerverwarming') . '</p></div>';
            });
        }
        
        return $sent;
    }

    /**
     * Handle mark invoice as paid
     */
    private function handle_mark_invoice_paid($invoice_id) {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        
        $result = $wpdb->update(
            $invoices_table,
            [
                'status' => 'paid',
                'paid_date' => current_time('mysql')
            ],
            ['id' => $invoice_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Factuur gemarkeerd als betaald.', 'evs-vloerverwarming') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het bijwerken van de factuur status.', 'evs-vloerverwarming') . '</p></div>';
            });
        }
    }

    /**
     * Handle delete invoice
     */
    private function handle_delete_invoice($invoice_id) {
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'evs_facturen';
        
        // Get invoice for confirmation
        $invoice = $wpdb->get_row($wpdb->prepare("SELECT * FROM $invoices_table WHERE id = %d", $invoice_id), ARRAY_A);
        
        if (!$invoice) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Factuur niet gevonden.', 'evs-vloerverwarming') . '</p></div>';
            return;
        }
        
        // Handle confirmation
        if (isset($_POST['confirm_delete']) && check_admin_referer('delete_invoice_' . $invoice_id)) {
            if ($wpdb->delete($invoices_table, ['id' => $invoice_id], ['%d'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Factuur succesvol verwijderd.', 'evs-vloerverwarming') . '</p></div>';
                // Redirect back to invoices list
                echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=evs-invoices') . '"; }, 2000);</script>';
                return;
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het verwijderen van de factuur.', 'evs-vloerverwarming') . '</p></div>';
            }
        }
        
        // Show confirmation form
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Factuur verwijderen', 'evs-vloerverwarming') . '</h1>';
        echo '<p>' . sprintf(esc_html__('Weet je zeker dat je factuur %s wilt verwijderen? Deze actie kan niet ongedaan worden gemaakt.', 'evs-vloerverwarming'), esc_html($invoice['invoice_number'])) . '</p>';
        echo '<form method="post" action="">';
        wp_nonce_field('delete_invoice_' . $invoice_id);
        echo '<input type="submit" name="confirm_delete" class="button button-primary" value="' . esc_attr__('Ja, verwijderen', 'evs-vloerverwarming') . '">';
        echo ' <a href="' . admin_url('admin.php?page=evs-invoices') . '" class="button">' . esc_html__('Annuleren', 'evs-vloerverwarming') . '</a>';
        echo '</form>';
        echo '</div>';
    }

    /**
     * Send invoice email to customer
     */
    private function send_invoice_email($invoice) {
        $to = $invoice['customer_email'];
        $subject = sprintf(__('Factuur %s - EVS Vloerverwarmingen', 'evs-vloerverwarming'), $invoice['invoice_number']);
        
        $labels = [
            'subject' => __('Factuur voor vloerverwarming installatie', 'evs-vloerverwarming'),
            'greeting' => sprintf(__('Geachte %s,', 'evs-vloerverwarming'), $invoice['customer_name']),
            'intro' => __('Hierbij ontvangt u de factuur voor de uitgevoerde vloerverwarmingswerkzaamheden.', 'evs-vloerverwarming'),
            'invoice_details' => __('Factuurgegevens:', 'evs-vloerverwarming'),
            'invoice_number' => __('Factuurnummer:', 'evs-vloerverwarming'),
            'invoice_date' => __('Factuurdatum:', 'evs-vloerverwarming'),
            'due_date' => __('Vervaldatum:', 'evs-vloerverwarming'),
            'customer_details' => __('Factuuradres:', 'evs-vloerverwarming'),
            'name' => __('Naam:', 'evs-vloerverwarming'),
            'address' => __('Adres:', 'evs-vloerverwarming'),
            'postal_city' => __('Postcode/Plaats:', 'evs-vloerverwarming'),
            'services' => __('Uitgevoerde werkzaamheden:', 'evs-vloerverwarming'),
            'drilling' => __('Vloerverwarming boren:', 'evs-vloerverwarming'),
            'sealing' => __('Vloer dichtsmeren:', 'evs-vloerverwarming'),
            'verdeler' => __('Verdeler aansluiten:', 'evs-vloerverwarming'),
            'subtotal' => __('Subtotaal (excl. BTW):', 'evs-vloerverwarming'),
            'btw' => __('BTW (21%):', 'evs-vloerverwarming'),
            'total' => __('Totaalbedrag (incl. BTW):', 'evs-vloerverwarming'),
            'payment_info' => __('Betaalinformatie:', 'evs-vloerverwarming'),
            'payment_term' => sprintf(__('Gelieve dit bedrag binnen 30 dagen te voldoen op rekeningnummer NL12 RABO 0123 4567 89 t.n.v. EVS Vloerverwarmingen o.v.v. %s', 'evs-vloerverwarming'), $invoice['invoice_number']),
            'footer_thanks' => __('Hartelijk dank voor uw vertrouwen in onze dienstverlening.', 'evs-vloerverwarming'),
            'footer_contact' => __('Voor vragen over deze factuur kunt u contact met ons opnemen.', 'evs-vloerverwarming'),
            'footer_greeting' => __('Met vriendelijke groet,', 'evs-vloerverwarming'),
            'company_name' => __('EVS Vloerverwarmingen', 'evs-vloerverwarming'),
            'contact_info' => __('Telefoon: 06-12345678 | Email: info@evs-vloerverwarmingen.nl | Website: www.evs-vloerverwarmingen.nl', 'evs-vloerverwarming'),
            'company_details' => __('KvK: 12345678 | BTW: NL123456789B01 | IBAN: NL12 RABO 0123 4567 89', 'evs-vloerverwarming'),
        ];
        
        // Calculate amounts
        $subtotal = $invoice['total_amount'] / 1.21; // Remove BTW
        $btw_amount = $invoice['total_amount'] - $subtotal;
        
        // Prepare invoice data for templates
        $invoice_data = array_merge($invoice, [
            'subtotal' => $subtotal,
            'btw_amount' => $btw_amount,
            'invoice_date_formatted' => date('d-m-Y', strtotime($invoice['invoice_date'])),
            'due_date_formatted' => date('d-m-Y', strtotime($invoice['due_date'])),
        ]);
        
        // Load HTML template
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/email-invoice-html.php';
        $html_body = ob_get_clean();
        
        // Load plain text template
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/email-invoice-plain.php';
        $plain_body = ob_get_clean();
        
        // Set up email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: EVS Vloerverwarmingen <info@evs-vloerverwarmingen.nl>',
            'Reply-To: info@evs-vloerverwarmingen.nl',
        ];
        
        // Send email
        $sent = wp_mail($to, $subject, $html_body, $headers);
        
        // Log the email attempt
        $this->log_error('Invoice email sent', [
            'invoice_id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'to' => $to,
            'sent' => $sent
        ]);
        
        return $sent;
    }

    /**
     * Zorgt ervoor dat er maar één instantie van de plugin is (singleton).
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

/**
 * Start de plugin op het juiste moment.
 */
function evs_vloerverwarming_offerte_init() {
    return EVS_Vloerverwarming_Offerte::instance();
}
// Only register hooks if WordPress functions are available
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'evs_vloerverwarming_offerte_init');
}

// Registreer de activatie hook om de database tabel aan te maken
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, array('EVS_Vloerverwarming_Offerte', 'on_activation'));
}
