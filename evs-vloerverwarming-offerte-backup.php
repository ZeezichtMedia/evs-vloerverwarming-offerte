<?php
/**
 * Plugin Name: EVS Vloerverwarming Offerte
 * Plugin URI: 
 * Description: Multi-step offerteformulier voor vloerverwarming installaties
 * Version: 3.5.3
 * Author: 
 * Author URI: 
 * Text Domain: evs-vloerverwarming
 */

// Voorkom direct toegang tot dit bestand
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include the Composer autoloader safely
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Laad de Offer class
if (file_exists(plugin_dir_path(__FILE__) . 'src/Models/Offer.php')) {
    require_once plugin_dir_path(__FILE__) . 'src/Models/Offer.php';
}

final class EVS_Vloerverwarming_Offerte {
    
    /**
     * Versie van de plugin voor cache busting
     */
    private static $instance = null;
    private $version = '3.5.3';
    
    /**
     * Of foutlogboek ingeschakeld is
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
     * Constructor
     */
    private function __construct() {
        add_shortcode('evs_offerte_formulier', array($this, 'render_form_shortcode'));
        add_action('wp_ajax_evs_vloerverwarming_offerte_submit', array($this, 'process_form'));
        add_action('wp_ajax_nopriv_evs_vloerverwarming_offerte_submit', array($this, 'process_form'));
        add_action('wp_ajax_log_evs_form_error', array($this, 'log_js_error'));
        add_action('wp_ajax_nopriv_log_evs_form_error', array($this, 'log_js_error'));
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
     * Verwerk het formulier via AJAX
     */
    public function process_form() {
        try {
            check_ajax_referer('evs_form_nonce', 'nonce');
            
            $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : array();
            $form_data = $this->sanitize_form_data($form_data);
            
            $validation_errors = $this->validate_form_data($form_data);
            if (!empty($validation_errors)) {
                wp_send_json_error(array(
                    'message' => __('Er zijn fouten gevonden in het formulier.', 'evs-vloerverwarming'),
                    'errors' => $validation_errors
                ));
            }
            
            $quote = $this->calculate_quote($form_data);
            
            $quote_id = $this->save_quote_to_database($form_data, $quote);
            if ($quote_id) {
                $form_data['id'] = $quote_id;
                $quote['id'] = $quote_id;
            }
            
            $customer_email_sent = $this->send_customer_email($form_data, $quote);
            $admin_email_sent = $this->send_admin_email($form_data, $quote);
            
            if (!$customer_email_sent || !$admin_email_sent) {
                $this->log_error('E-mail verzenden mislukt', array(
                    'customer_email_sent' => $customer_email_sent,
                    'admin_email_sent' => $admin_email_sent,
                    'form_data' => $form_data
                ));
            }
            
            wp_send_json_success(array(
                'message' => __('Uw offerte is succesvol verzonden. U ontvangt binnen 8 uur een prijsindicatie.', 'evs-vloerverwarming'),
                'quote' => $quote,
                'quote_id' => $quote_id
            ));
        } catch (Exception $e) {
            $this->log_error(__('Fout bij verwerken formulier', 'evs-vloerverwarming'), array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            wp_send_json_error(array(
                'message' => __('Er is een fout opgetreden bij het verwerken van uw aanvraag. Probeer het later opnieuw of neem contact met ons op.', 'evs-vloerverwarming')
            ));
        }
    }
    
    /**
     * Valideer formuliergegevens
     */
    private function validate_form_data($form_data) {
        $errors = array();
        
        $required_fields = array(
            'soort_vloerverwarming' => __('Soort vloerverwarming is verplicht', 'evs-vloerverwarming'),
            'type_woning' => __('Type woning is verplicht', 'evs-vloerverwarming'),
            'type_vloer' => __('Type vloer is verplicht', 'evs-vloerverwarming'),
            'vierkante_meters' => __('Oppervlakte is verplicht', 'evs-vloerverwarming'),
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
        
        if (!empty($form_data['email']) && !is_email($form_data['email'])) {
            $errors['email'] = __('Een geldig e-mailadres is verplicht.', 'evs-vloerverwarming');
        }
        
        if (!empty($form_data['vierkante_meters']) && (!is_numeric($form_data['vierkante_meters']) || $form_data['vierkante_meters'] <= 0)) {
            $errors['vierkante_meters'] = __('Oppervlakte is verplicht en moet een getal zijn.', 'evs-vloerverwarming');
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
                case 'vierkante_meters':
                case 'vierkante_meters_1':
                case 'vierkante_meters_2':
                    $sanitized[$key] = intval($value);
                    break;
                case 'toelichting':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                case 'privacy_akkoord':
                    $sanitized[$key] = ($value === 'on' || $value === '1' || $value === 'yes') ? 'ja' : 'nee';
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
        $prices = $this->get_price_options();
        $total_area = intval($form_data['vierkante_meters'] ?? 0);

        // Zoek de juiste prijs per meter in de prijstabel
        $price_per_meter = 0;
        krsort($prices['price_table']); // Sorteer van hoog naar laag
        foreach ($prices['price_table'] as $area_limit => $price) {
            if ($total_area >= $area_limit) {
                $price_per_meter = $price;
                break;
            }
        }

        $drilling_price = $price_per_meter;
        $sealing_price = ($form_data['vloer_dichtsmeren'] === 'ja') ? $prices['sealing_price_per_meter'] * $total_area : 0;

        return array(
            'drilling_price' => $drilling_price,
            'sealing_price' => $sealing_price,
            'total_price' => $drilling_price + $sealing_price,
            'square_meters' => $total_area,
            'vat_included' => true
        );
    }
    
    /**
     * Haal prijsopties op
     */
    private function get_price_options() {
        $options = get_option('evs_price_options');

        $defaults = array(
            'sealing_price_per_meter' => '15.00',
            'price_table' => array(
                0 => 750, 20 => 700, 30 => 650, 40 => 600, 50 => 550,
                60 => 500, 70 => 475, 80 => 450, 90 => 425, 100 => 400,
                110 => 375, 120 => 350, 130 => 325
            )
        );

        $settings = wp_parse_args($options, $defaults);

        return array(
            'sealing_price_per_meter' => floatval($settings['sealing_price_per_meter']),
            'price_table' => array_map('floatval', $settings['price_table'])
        );
    }
    
    /**
     * Sla offerte op in de WordPress database.
     */
    private function save_quote_to_database($form_data, $quote) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        try {
            $customer_name = trim(sprintf('%s %s', $form_data['voornaam'], $form_data['achternaam']));

            $data_to_insert = array(
                'created_at'        => current_time('mysql'),
                'customer_name'     => $customer_name,
                'customer_email'    => $form_data['email'],
                'floor_level'       => $form_data['type_woning'], // Mapping 'type_woning' to 'floor_level'
                'floor_level_other' => '', // This field seems deprecated by the new form
                'floor_type'        => $form_data['type_vloer'],
                'area'              => $quote['square_meters'],
                'heat_source'       => $form_data['warmtebron'],
                'distributor'       => 'ja', // Assuming distributor is always included now
                'sealing'           => $form_data['vloer_dichtsmeren'],
                'floor_clean'       => 'nee', // Defaulting as it's not in the form
                'installation_date' => $form_data['montagedatum'] ?? 'Onbekend',
                'drilling_price'    => $quote['drilling_price'],
                'sealing_price'     => $quote['sealing_price'],
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
        $subject = __('Uw offerte voor vloerverwarming - EVS Vloerverwarmingen', 'evs-vloerverwarming');

        $labels = [
            'subject' => __('Uw offerte voor vloerverwarming', 'evs-vloerverwarming'),
            'greeting' => sprintf(__('Beste %s,', 'evs-vloerverwarming'), esc_html($form_data['voornaam'])),
            'intro' => __('Bedankt voor uw offerteaanvraag voor vloerverwarming. Op basis van de door u verstrekte gegevens hebben wij een prijsindicatie opgesteld.', 'evs-vloerverwarming'),
            'your_data' => __('Uw gegevens:', 'evs-vloerverwarming'),
            'heating_type' => __('Soort vloerverwarming:', 'evs-vloerverwarming'),
            'housing_type' => __('Type woning:', 'evs-vloerverwarming'),
            'floor_type' => __('Type vloer:', 'evs-vloerverwarming'),
            'area' => __('Oppervlakte:', 'evs-vloerverwarming'),
            'heat_source' => __('Warmtebron:', 'evs-vloerverwarming'),
            'sealing' => __('Vloer dichtsmeren:', 'evs-vloerverwarming'),
            'quote_details' => __('Details van uw prijsindicatie:', 'evs-vloerverwarming'),
            'drilling_price' => __('Prijs infrezen (incl. verdeler):', 'evs-vloerverwarming'),
            'sealing_price' => __('Prijs dichtsmeren:', 'evs-vloerverwarming'),
            'total_price' => __('Totaalbedrag (incl. BTW):', 'evs-vloerverwarming'),
            'next_steps' => __('Vervolgstappen', 'evs-vloerverwarming'),
            'next_steps_body' => __('Wij nemen zo spoedig mogelijk contact met u op om de offerte en eventuele vragen te bespreken. Deze prijsindicatie is onder voorbehoud van eventuele bijzonderheden die tijdens een inspectie aan het licht komen.', 'evs-vloerverwarming'),
            'regards' => __('Met vriendelijke groet,', 'evs-vloerverwarming'),
            'team' => __('Het team van EVS Vloerverwarmingen', 'evs-vloerverwarming'),
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
            '{{heating_type_value}}' => $this->get_readable_value('soort_vloerverwarming', $form_data['soort_vloerverwarming'] ?? '', $form_data),
            '{{housing_type_label}}' => $labels['housing_type'],
            '{{housing_type_value}}' => $this->get_readable_value('type_woning', $form_data['type_woning'] ?? '', $form_data),
            '{{floor_type_label}}' => $labels['floor_type'],
            '{{floor_type_value}}' => $this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '', $form_data),
            '{{area_label}}' => $labels['area'],
            '{{area_value}}' => sprintf(__('%d m²', 'evs-vloerverwarming'), $quote['square_meters']),
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
        $subject = sprintf(__('Nieuwe offerteaanvraag (#%d) van %s', 'evs-vloerverwarming'), $quote['id'], $form_data['voornaam'] . ' ' . $form_data['achternaam']);

        $labels = [
            'subject' => __('Nieuwe offerteaanvraag', 'evs-vloerverwarming'),
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
            'quote_details' => __('Prijsindicatie:', 'evs-vloerverwarming'),
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
            '{{heating_type_value}}' => $this->get_readable_value('soort_vloerverwarming', $form_data['soort_vloerverwarming'] ?? '', $form_data),
            '{{housing_type_label}}' => $labels['housing_type'],
            '{{housing_type_value}}' => $this->get_readable_value('type_woning', $form_data['type_woning'] ?? '', $form_data),
            '{{floor_type_label}}' => $labels['floor_type'],
            '{{floor_type_value}}' => $this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '', $form_data),
            '{{area_label}}' => $labels['area'],
            '{{area_value}}' => sprintf(__('%d m²', 'evs-vloerverwarming'), $quote['square_meters']),
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
     * Verwerkt admin-acties zoals het verzenden van offertes en aanmaken van facturen.
     */
    public function handle_admin_actions() {
        // Alleen doorgaan als er een actie en offerte ID is
        if (isset($_GET['action']) && isset($_GET['offer_id'])) {
            $action = sanitize_key($_GET['action']);
            $offer_id = intval($_GET['offer_id']);

            // Nonce-naam is afhankelijk van de actie
            $nonce_name = '';
            $nonce_action = '';
            if ($action === 'send_quote') {
                $nonce_name = 'evs_send_quote_nonce';
                $nonce_action = 'evs_send_quote_' . $offer_id;
            } elseif ($action === 'create_invoice') {
                $nonce_name = 'evs_create_invoice_nonce';
                $nonce_action = 'evs_create_invoice_' . $offer_id;
            }

            // Verifieer de nonce voor de veiligheid
            if (empty($nonce_name) || !isset($_GET[$nonce_name]) || !wp_verify_nonce($_GET[$nonce_name], $nonce_action)) {
                // Nonce is niet geldig, stop de uitvoering
                wp_die(__('Security check failed', 'evs-vloerverwarming'));
            }

            // Laad de PDF generator alleen als we een actie uitvoeren die het nodig heeft.
            if (in_array($action, ['send_quote'])) {
                if (file_exists(plugin_dir_path(__FILE__) . 'admin/class-evs-pdf-generator.php')) {
                    require_once plugin_dir_path(__FILE__) . 'admin/class-evs-pdf-generator.php';
                }
            }

            if ($action === 'send_quote') {
                // Check if PDF generator class is available
                if (class_exists('EVS_PDF_Generator')) {
                    $this->handle_send_quote($offer_id);
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('PDF generator is niet beschikbaar. Controleer of alle plugin bestanden correct zijn geïnstalleerd.', 'evs-vloerverwarming') . '</p></div>';
                    });
                }
            } elseif ($action === 'create_invoice') {
                $this->handle_create_invoice($offer_id);
            }

            // Redirect om te voorkomen dat de actie opnieuw wordt uitgevoerd bij het vernieuwen
            wp_redirect(admin_url('admin.php?page=evs-offertes'));
            exit;
        }
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

        $update_data = ['status' => sanitize_text_field($data['status'])];
        $update_format = ['%s'];

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

        // Bereid data voor voor de e-mail template
        $name_parts = explode(' ', $offer['customer_name'], 2);
        $form_data = [
            'email' => $offer['customer_email'],
            'voornaam' => $name_parts[0],
            'achternaam' => $name_parts[1] ?? '',
            'soort_vloerverwarming' => 'infrezen', // Aanname, kan uit offerte gehaald worden indien opgeslagen
            'type_woning' => $offer['floor_level'],
            'type_vloer' => $offer['floor_type'],
            'warmtebron' => $offer['heat_source'],
            'vloer_dichtsmeren' => $offer['sealing'],
        ];

        $quote = [
            'square_meters' => $offer['area'],
            'drilling_price' => $offer['drilling_price'],
            'sealing_price' => $offer['sealing_price'],
            'total_price' => floatval($offer['drilling_price']) + floatval($offer['sealing_price']),
        ];

        // Verzend de e-mail via de centrale functie
        if ($this->send_customer_email($form_data, $quote, $attachments)) {
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
        global $wpdb;
        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $invoices_table = $wpdb->prefix . 'evs_invoices';
        $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quotes_table WHERE id = %d", $offer_id), ARRAY_A);
        if (!$offer) {
            $this->log_error('Kan factuur niet aanmaken: offerte niet gevonden.', ['offer_id' => $offer_id]);
            return;
        }
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $invoices_table WHERE quote_id = %d", $offer_id))) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Er bestaat al een factuur voor deze offerte.', 'evs-vloerverwarming') . '</p></div>';
            });
            return;
        }
        $year = date('Y');
        $last_invoice = $wpdb->get_var("SELECT MAX(invoice_number) FROM $invoices_table WHERE invoice_number LIKE '{$year}-%'");
        $next_id = $last_invoice ? intval(substr($last_invoice, 5)) + 1 : 1;
        $invoice_number = sprintf('%s-%04d', $year, $next_id);
        $total_amount = $offer['drilling_price'] + $offer['sealing_price'];
        if ($wpdb->insert($invoices_table, [
            'quote_id' => $offer_id,
            'invoice_number' => $invoice_number,
            'created_at' => current_time('mysql'),
            'due_date' => date('Y-m-d H:i:s', strtotime('+14 days')),
            'customer_name' => $offer['customer_name'],
            'customer_email' => $offer['customer_email'],
            'total_amount' => $total_amount,
            'status' => 'unpaid'
        ])) {
            $wpdb->update($quotes_table, ['status' => 'invoiced'], ['id' => $offer_id]);
            add_action('admin_notices', function() use ($invoice_number) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Factuur %s succesvol aangemaakt.', 'evs-vloerverwarming'), esc_html($invoice_number)) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het aanmaken van de factuur.', 'evs-vloerverwarming') . '</p></div>';
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

        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $sql_quotes = "CREATE TABLE $quotes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            floor_level varchar(55) DEFAULT '' NOT NULL,
            floor_level_other varchar(255) DEFAULT '' NOT NULL,
            floor_type varchar(55) DEFAULT '' NOT NULL,
            area float NOT NULL,
            heat_source varchar(55) DEFAULT '' NOT NULL,
            distributor varchar(10) DEFAULT '' NOT NULL,
            sealing varchar(10) DEFAULT '' NOT NULL,
            floor_clean varchar(10) DEFAULT '' NOT NULL,
            installation_date varchar(55) DEFAULT '' NOT NULL,
            drilling_price decimal(10, 2) NOT NULL,
            sealing_price decimal(10, 2) NOT NULL,
            status varchar(55) DEFAULT 'new' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_quotes);

        $invoices_table = $wpdb->prefix . 'evs_invoices';
        $sql_invoices = "CREATE TABLE $invoices_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            invoice_number varchar(255) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            due_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            total_amount decimal(10, 2) NOT NULL,
            status varchar(55) DEFAULT 'unpaid' NOT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charset_collate;";
        dbDelta($sql_invoices);
    }
}
    
    /**
     * Versie van de plugin voor cache busting
     */
    private $version = '1.0.2';
    
    /**
     * Of foutlogboek ingeschakeld is
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
        // Controleer nonce voor veiligheid
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
     * Constructor
     */
    public function __construct() {
        // Shortcode toevoegen. De scripts worden nu vanuit de shortcode-functie geladen.
        add_shortcode('evs_offerte_formulier', array($this, 'render_form_shortcode'));
        
        // AJAX handlers voor form processing
        add_action('wp_ajax_evs_vloerverwarming_offerte_submit', array($this, 'process_form'));
        add_action('wp_ajax_nopriv_evs_vloerverwarming_offerte_submit', array($this, 'process_form'));
        
        // AJAX handler voor foutlogboek
        add_action('wp_ajax_log_evs_form_error', array($this, 'log_js_error'));
        add_action('wp_ajax_nopriv_log_evs_form_error', array($this, 'log_js_error'));

        // Admin menu toevoegen
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Instellingen registreren
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Laad scripts en styles
     */
    public function enqueue_scripts() {
        // Laad CSS met versienummer voor cache busting
        wp_enqueue_style(
            'evs-form-styles', 
            plugin_dir_url(__FILE__) . 'assets/css/evs-form.css',
            array(),
            $this->version
        );
        
        // Laad JavaScript met versienummer voor cache busting
        wp_enqueue_script(
            'evs-form-script',
            plugin_dir_url(__FILE__) . 'assets/js/evs-form.js',
            array('jquery'),
            $this->version, // Use the dynamic version for cache busting
            true
        );
        
        wp_localize_script(
            'evs-form-script',
            'evs_offerte_ajax_object', // Moet overeenkomen met het object in JS
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('evs_form_nonce')
            )
        );
    }
    
    /**
     * Render het formulier via shortcode en laad de benodigde scripts.
     */
    public function render_form_shortcode($atts) {
        // Scripts en styles alleen laden wanneer de shortcode wordt gebruikt.
        $this->enqueue_scripts();

        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/form-template.php';
        return ob_get_clean();
    }
    
    /**
     * Verwerk het formulier via AJAX
     */
    public function process_form() {
        try {
            // Controleer nonce voor veiligheid
            check_ajax_referer('evs_form_nonce', 'nonce');
            
            // Haal formuliergegevens op en saniteer deze
            $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : array();
            $form_data = $this->sanitize_form_data($form_data);
            
            // Valideer essentiële velden
            $validation_errors = $this->validate_form_data($form_data);
            if (!empty($validation_errors)) {
                wp_send_json_error(array(
                    'message' => 'Er zijn fouten gevonden in het formulier.',
                    'errors' => $validation_errors
                ));
                wp_die();
            }
            
            // Bereken offerte op basis van ingevoerde gegevens
            $quote = $this->calculate_quote($form_data);
            
            // Sla offertegegevens op in de database (optioneel)
            $quote_id = $this->save_quote_to_database($form_data, $quote);
            
            // Stuur e-mail naar klant
            $customer_email_sent = $this->send_customer_email($form_data, $quote);
            
            // Stuur e-mail naar admin
            $admin_email_sent = $this->send_admin_email($form_data, $quote);
            
            // Controleer of e-mails zijn verzonden
            if (!$customer_email_sent || !$admin_email_sent) {
                $this->log_error('E-mail verzenden mislukt', array(
                    'customer_email_sent' => $customer_email_sent,
                    'admin_email_sent' => $admin_email_sent,
                    'form_data' => $form_data
                ));
            }
            
            wp_send_json_success(array(
                'message' => __('Uw offerte is succesvol verzonden. U ontvangt binnen 8 uur een prijsindicatie.', 'evs-vloerverwarming'),
                'quote' => $quote,
                'quote_id' => $quote_id ?? null
            ));
        } catch (Exception $e) {
            $this->log_error(__('Fout bij verwerken formulier', 'evs-vloerverwarming'), array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            wp_send_json_error(array(
                'message' => __('Er is een fout opgetreden bij het verwerken van uw aanvraag. Probeer het later opnieuw of neem contact met ons op.', 'evs-vloerverwarming')
            ));
        }
    }
    
    /**
     * Valideer formuliergegevens
     */
    private function validate_form_data($form_data) {
        $errors = array();
        
        // Controleer verplichte velden
        $required_fields = array(
            'soort_vloerverwarming' => __('Soort vloerverwarming is verplicht', 'evs-vloerverwarming'),
            'type_woning' => __('Type woning is verplicht', 'evs-vloerverwarming'),
            'type_vloer' => __('Type vloer is verplicht', 'evs-vloerverwarming'),
            'vierkante_meters' => __('Oppervlakte is verplicht', 'evs-vloerverwarming'),
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
        
        // Controleer e-mailadres
        if (!empty($form_data['email']) && !is_email($form_data['email'])) {
            $errors['email'] = __('Een geldig e-mailadres is verplicht.', 'evs-vloerverwarming');
        }
        
        // Controleer vierkante meters (moet een positief getal zijn)
        if (!empty($form_data['vierkante_meters']) && (!is_numeric($form_data['vierkante_meters']) || $form_data['vierkante_meters'] <= 0)) {
            $errors['vierkante_meters'] = __('Oppervlakte is verplicht en moet een getal zijn.', 'evs-vloerverwarming');
        }
        
        return $errors;
    }
    
    /**
     * Saniteer formuliergegevens
     */
    private function sanitize_form_data($form_data) {
        $sanitized = array();
        
        foreach ($form_data as $key => $value) {
            // Verschillende sanitatie per veldtype
            switch ($key) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                    
                case 'vierkante_meters':
                case 'vierkante_meters_1':
                case 'vierkante_meters_2':
                    $sanitized[$key] = intval($value);
                    break;
                    
                case 'toelichting':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                    
                case 'privacy_akkoord':
                    $sanitized[$key] = ($value === 'on' || $value === '1' || $value === 'yes') ? 'ja' : 'nee';
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Bereken offerte op basis van formuliergegevens
     */
    private function calculate_quote($form_data) {
        try {
            // Haal prijzen uit opties (kan later via admin panel worden ingesteld)
            $prices = $this->get_price_options();
            
            // Basisprijs instellen
            $base_price = 0;
            
            // Soort vloerverwarming
            $heating_type = $form_data['soort_vloerverwarming'] ?? '';
            $base_price += $prices['heating_types'][$heating_type] ?? $prices['heating_types']['default'];
            
            // Type woning factor
            $housing_type = $form_data['type_woning'] ?? '';
            $housing_factor = $prices['housing_factors'][$housing_type] ?? $prices['housing_factors']['default'];
            
            // Vloertype factor
            $floor_type = $form_data['type_vloer'] ?? '';
            $floor_factor = $prices['floor_factors'][$floor_type] ?? $prices['floor_factors']['default'];
            
            // Vierkante meters
            $square_meters = intval($form_data['vierkante_meters'] ?? 0);
            
            // Extra verdiepingen
            $total_square_meters = $square_meters;
            for ($i = 1; $i <= 2; $i++) {
                $extra_floor_key = "vierkante_meters_{$i}";
                if (isset($form_data[$extra_floor_key]) && intval($form_data[$extra_floor_key]) > 0) {
                    $total_square_meters += intval($form_data[$extra_floor_key]);
                }
            }
            
            // Warmtebron factor
            $heat_source = $form_data['warmtebron'] ?? '';
            $heat_source_factor = $prices['heat_source_factors'][$heat_source] ?? $prices['heat_source_factors']['default'];
            
            // Vloer dichtsmeren
            $floor_sealing = $form_data['vloer_dichtsmeren'] ?? '';
            $floor_sealing_price = ($floor_sealing === 'ja') ? $prices['floor_sealing_price'] : 0;
            
            // Totale prijs berekenen
            $total_price = ($base_price * $total_square_meters * $housing_factor * $floor_factor * $heat_source_factor) + ($floor_sealing_price * $total_square_meters);
            
            // Afronden naar boven op hele euro's
            $total_price = ceil($total_price);
            
            return array(
                'base_price_per_m2' => $base_price,
                'square_meters' => $total_square_meters,
                'housing_factor' => $housing_factor,
                'floor_factor' => $floor_factor,
                'heat_source_factor' => $heat_source_factor,
                'floor_sealing_price' => $floor_sealing_price,
                'total_price' => $total_price,
                'vat_included' => true
            );
        } catch (Exception $e) {
            $this->log_error(__('Fout bij berekenen offerte', 'evs-vloerverwarming'), array(
                'error' => $e->getMessage(),
                'form_data' => $form_data
            ));
            
            // Fallback prijs
            return array(
                'error' => true,
                'total_price' => 0,
                'vat_included' => true
            );
        }
    }
    
    /**
     * Haal prijsopties op (kan later via admin panel komen)
     */
    private function get_price_options() {
        $options = get_option('evs_price_options');

        // Standaardwaarden instellen als de opties niet zijn opgeslagen
        $defaults = array(
            'price_per_meter' => '25.00', // Prijs per m² voor infrezen
            'sealing_price_per_meter' => '15.00', // Prijs per m² voor dichtsmeren
            'distributor_price' => '150.00', // Vaste prijs voor de verdeler
        );

        $prices = wp_parse_args($options, $defaults);

        // Deze prijzen kunnen later uit een admin-instellingenpagina komen
        return array(
            'price_per_meter' => floatval($prices['price_per_meter']),
            'sealing_price_per_meter' => floatval($prices['sealing_price_per_meter']),
            'distributor_price' => floatval($prices['distributor_price']),
            'price_table' => array(
                0 => 750,
                20 => 700,
                30 => 650,
                40 => 600,
                50 => 550,
                60 => 500,
                70 => 475,
                80 => 450,
                90 => 425,
                100 => 400,
                110 => 375,
                120 => 350,
                130 => 325,
            )
        );
    }
    
    /**
     * Sla offerte op in de WordPress database.
     */
    private function save_quote_to_database($form_data, $quote) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        try {
            $data_to_insert = array(
                'created_at'        => current_time('mysql'),
                'customer_name'     => $form_data['naam'],
                'customer_email'    => $form_data['email'],
                'floor_level'       => $form_data['soort_verdieping'],
                'floor_level_other' => $form_data['andere_verdieping'] ?? '',
                'floor_type'        => $form_data['type_vloer'],
                'area'              => $form_data['oppervlakte'],
                'heat_source'       => $form_data['warmtebron'],
                'distributor'       => $form_data['verdeler_aansluiten'],
                'sealing'           => $form_data['vloer_dichtsmeren'],
                'floor_clean'       => $form_data['vloer_schoon'] ?? 'nee',
                'installation_date' => $form_data['montagedatum'] ?? 'Onbekend',
                'drilling_price'    => $quote['drilling_price'],
                'sealing_price'     => $quote['sealing_price'],
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
            
            return $quote_id;
        }
        */
        
        return null;
    }
    
    /**
     * Stuur e-mail naar klant
     * 
     * @param array $form_data Formuliergegevens
     * @param array $quote Offertegegevens
     * @return bool Succes of mislukking van e-mail verzenden
     */
    private function send_customer_email($form_data, $quote) {
        try {
            $to = sanitize_email($form_data['email'] ?? '');
            if (empty($to)) {
                $this->log_error(__('Klant e-mail verzenden mislukt: geen geldig e-mailadres', 'evs-vloerverwarming'));
                return false;
            }
            
            $subject = __('Uw offerte voor vloerverwarming - EVS Vloerverwarmingen', 'evs-vloerverwarming');
            
            // Inline CSS voor betere webmail-compatibiliteit
            $css = <<<CSS
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.5; }
            h2 { color: #0a3b2e; margin-top: 20px; margin-bottom: 15px; }
            h3 { color: #00a651; margin-top: 20px; margin-bottom: 10px; }
            p { margin-bottom: 15px; }
            ul { margin-bottom: 15px; }
            li { margin-bottom: 5px; }
            .price { font-weight: bold; color: #0a3b2e; font-size: 16px; }
            .footer { margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
            CSS;
            
            // HTML-versie van de e-mail met tabellen voor betere webmail-compatibiliteit
            $html_message = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title><?php _e('Uw offerte voor vloerverwarming', 'evs-vloerverwarming'); ?></title>
                <style type="text/css">{$css}</style>
            </head>
            <body>
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td>
                            <h2><?php _e('Bedankt voor uw offerteaanvraag', 'evs-vloerverwarming'); ?></h2>
                            <p><?php echo sprintf(esc_html__('Beste %s,', 'evs-vloerverwarming'), esc_html($form_data['voornaam'])); ?></p>
                            <p><?php _e('Bedankt voor uw offerteaanvraag voor vloerverwarming. Op basis van de door u verstrekte gegevens hebben wij een prijsindicatie opgesteld.', 'evs-vloerverwarming'); ?></p>
                            
                            <h3><?php _e('Uw gegevens:', 'evs-vloerverwarming'); ?></h3>
                            <table width="100%" cellpadding="5" cellspacing="0" border="0">
                                <tr>
                                    <td><strong><?php _e('Soort vloerverwarming:', 'evs-vloerverwarming'); ?></strong></td>
                                    <td>{$this->get_readable_value('soort_vloerverwarming', $form_data['soort_vloerverwarming'] ?? '')}</td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Type woning:', 'evs-vloerverwarming'); ?></strong></td>
                                    <td>{$this->get_readable_value('type_woning', $form_data['type_woning'] ?? '')}</td>
{{ ... }}
                
                <h3><?php _e('Installatieadres:', 'evs-vloerverwarming'); ?></h3>
                <table>
                    <tr>
                        <th width="30%"><?php _e('Adres', 'evs-vloerverwarming'); ?></th>
                        <td>{$adres}</td>
                    </tr>
                    <tr>
                        <th><?php _e('Postcode', 'evs-vloerverwarming'); ?></th>
                        <td>{$postcode}</td>
                    </tr>
{{ ... }}
                
                <h3><?php _e('Projectgegevens:', 'evs-vloerverwarming'); ?></h3>
                <table>
                    <tr>
                        <th width="30%"><?php _e('Soort vloerverwarming', 'evs-vloerverwarming'); ?></th>
                        <td>{$soort_vloerverwarming}</td>
                    </tr>
                    <tr>
                        <th><?php _e('Type woning', 'evs-vloerverwarming'); ?></th>
                        <td>{$type_woning}</td>
                    </tr>
{{ ... }}
            </body>
            </html>
            HTML;
            
            // Platte tekst versie van de e-mail voor betere compatibiliteit
            $text_message  = __('Nieuwe offerteaanvraag', 'evs-vloerverwarming') . "\n=========================\n\n";
            $text_message .= __('Klantgegevens:', 'evs-vloerverwarming') . "\n";
            $text_message .= __('Naam:', 'evs-vloerverwarming') . " {$klantnaam}\n";
            $text_message .= __('E-mail:', 'evs-vloerverwarming') . " {$email}\n";
            $text_message .= __('Telefoon:', 'evs-vloerverwarming') . " {$telefoon}\n\n";
            $text_message .= __('Installatieadres:', 'evs-vloerverwarming') . "\n";
            $text_message .= __('Adres:', 'evs-vloerverwarming') . " {$adres}\n";
            $text_message .= __('Postcode:', 'evs-vloerverwarming') . " {$postcode}\n";
            $text_message .= __('Plaats:', 'evs-vloerverwarming') . " {$plaats}\n\n";
            $text_message .= __('Projectgegevens:', 'evs-vloerverwarming') . "\n";
            $text_message .= __('Soort vloerverwarming:', 'evs-vloerverwarming') . " {$soort_vloerverwarming}\n";
            $text_message .= __('Type woning:', 'evs-vloerverwarming') . " {$type_woning}\n";
            $text_message .= __('Type vloer:', 'evs-vloerverwarming') . " {$type_vloer}\n";
            $text_message .= __('Oppervlakte:', 'evs-vloerverwarming') . " {$quote['square_meters']} m²\n";
            $text_message .= __('Warmtebron:', 'evs-vloerverwarming') . " {$warmtebron}\n";
            $text_message .= __('Vloer dichtsmeren:', 'evs-vloerverwarming') . " {$vloer_dichtsmeren}\n";
            $text_message .= __('Gewenste montagedatum:', 'evs-vloerverwarming') . " {$montagedatum}\n\n";
            $text_message .= __('Toelichting:', 'evs-vloerverwarming') . "\n{$toelichting}\n\n";
            $text_message .= __('Prijsindicatie:', 'evs-vloerverwarming') . "\n";
            $text_message .= __('Totaalbedrag (incl. BTW):', 'evs-vloerverwarming') . " €{$this->format_price($quote['total_price'])}\n\n";
            $text_message .= sprintf(__('Deze offerte is opgeslagen in het systeem met ID: %d', 'evs-vloerverwarming'), $quote['id']) . "\n";
            $text_message .= strip_tags($toelichting) . "\n";
            
            // Stuur multipart e-mail (zowel HTML als platte tekst)
            return $this->send_email($to, $subject, $html_message, $text_message);
        } catch (Exception $e) {
{{ ... }}
            $this->log_error(__('Admin e-mail verzenden mislukt', 'evs-vloerverwarming'), array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Krijg leesbare waarde voor alle formuliervelden
     * 
     * @param string $field Veldnaam
     * @param string $value Veldwaarde
     * @return string Leesbare waarde
     */
    private function get_readable_value($field, $value, $form_data = array()) {
        // Specifieke behandeling voor prijsvelden
        if (in_array($field, ['drilling_price', 'sealing_price', 'total_amount'])) {
            return __('€ ', 'evs-vloerverwarming') . $this->format_price($value);
        }

        // Specifieke behandeling voor 'floor_level'
        if ($field === 'floor_level' && $value === 'Anders' && !empty($form_data['floor_level_other'])) {
            return __('Anders: ', 'evs-vloerverwarming') . esc_html($form_data['floor_level_other']);
        }

        // Mapping van slugs naar leesbare tekst
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
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        // Fallback voor onbekende waarden
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
            'price_per_meter',
            __('Prijs per m² (Infrezen)', 'evs-vloerverwarming'),
            array($this, 'render_price_field'),
            'evs-settings',
            'evs_pricing_section',
            ['name' => 'price_per_meter', 'default' => '25.00']
        );

        add_settings_field(
            'sealing_price_per_meter',
            __('Prijs per m² (Dichtsmeren)', 'evs-vloerverwarming'),
            array($this, 'render_price_field'),
            'evs-settings',
            'evs_pricing_section',
            ['name' => 'sealing_price_per_meter', 'default' => '15.00']
        );

        add_settings_field(
            'distributor_price',
            __('Prijs verdeler', 'evs-vloerverwarming'),
            array($this, 'render_price_field'),
            'evs-settings',
            'evs_pricing_section',
            ['name' => 'distributor_price', 'default' => '150.00']
        );
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

        $update_data = ['status' => sanitize_text_field($data['status'])];
        $update_format = ['%s'];

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
        require_once plugin_dir_path(__FILE__) . 'admin/class-evs-pdf-generator.php';
        $upload_dir = wp_upload_dir();
        $temp_pdf_dir = $upload_dir['basedir'] . '/evs-temp-pdfs';
        if (!file_exists($temp_pdf_dir)) {
            wp_mkdir_p($temp_pdf_dir);
        }
        $pdf_drilling = new EVS_PDF_Generator();
        $pdf_drilling->generate_quote_pdf($offer, 'drilling');
        $drilling_filename = $temp_pdf_dir . '/offerte-' . $offer_id . '-infrezen.pdf';
        $pdf_drilling->Output('F', $drilling_filename);
        $attachments = [$drilling_filename];
        if ($offer['sealing'] === 'ja' && $offer['sealing_price'] > 0) {
            $pdf_sealing = new EVS_PDF_Generator();
            $pdf_sealing->generate_quote_pdf($offer, 'sealing');
            $sealing_filename = $temp_pdf_dir . '/offerte-' . $offer_id . '-dichtsmeren.pdf';
            $pdf_sealing->Output('F', $sealing_filename);
            $attachments[] = $sealing_filename;
        }
        $to = $offer['customer_email'];
        $subject = __('Uw offerte van EVS Vloerverwarmingen', 'evs-vloerverwarming');
        $body = sprintf(
            "%s %s,\n\n%s\n\n%s\n%s",
            __('Beste', 'evs-vloerverwarming'),
            $offer['customer_name'],
            __('In de bijlage vindt u de offerte(s) voor de vloerverwarming.', 'evs-vloerverwarming'),
            __('Met vriendelijke groet,', 'evs-vloerverwarming'),
            __('Het team van EVS Vloerverwarmingen', 'evs-vloerverwarming')
        );
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if (wp_mail($to, $subject, $body, $headers, $attachments)) {
            $wpdb->update($quotes_table, ['status' => 'sent'], ['id' => $offer_id]);
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Offerte succesvol verzonden.', 'evs-vloerverwarming') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij verzenden van de offerte.', 'evs-vloerverwarming') . '</p></div>';
            });
        }
        foreach ($attachments as $file) {
            unlink($file);
        }
    }

    /**
     * Verwerkt het aanmaken van een factuur vanuit een offerte.
     */
    private function handle_create_invoice($offer_id) {
        global $wpdb;
        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $invoices_table = $wpdb->prefix . 'evs_invoices';
        $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quotes_table WHERE id = %d", $offer_id), ARRAY_A);
        if (!$offer) {
            $this->log_error('Kan factuur niet aanmaken: offerte niet gevonden.', ['offer_id' => $offer_id]);
            return;
        }
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $invoices_table WHERE quote_id = %d", $offer_id))) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Er bestaat al een factuur voor deze offerte.', 'evs-vloerverwarming') . '</p></div>';
            });
            return;
        }
        $year = date('Y');
        $last_invoice = $wpdb->get_var("SELECT MAX(invoice_number) FROM $invoices_table WHERE invoice_number LIKE '{$year}-%'");
        $next_id = $last_invoice ? intval(substr($last_invoice, 5)) + 1 : 1;
        $invoice_number = sprintf('%s-%04d', $year, $next_id);
        $total_amount = $offer['drilling_price'] + $offer['sealing_price'];
        if ($wpdb->insert($invoices_table, [
            'quote_id' => $offer_id,
            'invoice_number' => $invoice_number,
            'created_at' => current_time('mysql'),
            'due_date' => date('Y-m-d H:i:s', strtotime('+14 days')),
            'customer_name' => $offer['customer_name'],
            'customer_email' => $offer['customer_email'],
            'total_amount' => $total_amount,
            'status' => 'unpaid'
        ])) {
            $wpdb->update($quotes_table, ['status' => 'invoiced'], ['id' => $offer_id]);
            add_action('admin_notices', function() use ($invoice_number) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Factuur %s succesvol aangemaakt.', 'evs-vloerverwarming'), esc_html($invoice_number)) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Fout bij het aanmaken van de factuur.', 'evs-vloerverwarming') . '</p></div>';
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

        $quotes_table = $wpdb->prefix . 'evs_offertes';
        $sql_quotes = "CREATE TABLE $quotes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            floor_level varchar(55) DEFAULT '' NOT NULL,
            floor_level_other varchar(255) DEFAULT '' NOT NULL,
            floor_type varchar(55) DEFAULT '' NOT NULL,
            area float NOT NULL,
            heat_source varchar(55) DEFAULT '' NOT NULL,
            distributor varchar(10) DEFAULT '' NOT NULL,
            sealing varchar(10) DEFAULT '' NOT NULL,
            floor_clean varchar(10) DEFAULT '' NOT NULL,
            installation_date varchar(55) DEFAULT '' NOT NULL,
            drilling_price decimal(10, 2) NOT NULL,
            sealing_price decimal(10, 2) NOT NULL,
            status varchar(55) DEFAULT 'new' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_quotes);

        $invoices_table = $wpdb->prefix . 'evs_invoices';
        $sql_invoices = "CREATE TABLE $invoices_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            invoice_number varchar(255) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            due_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            customer_name tinytext NOT NULL,
            customer_email varchar(100) NOT NULL,
            total_amount decimal(10, 2) NOT NULL,
            status varchar(55) DEFAULT 'unpaid' NOT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) $charset_collate;";
        dbDelta($sql_invoices);
    }
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
add_action('plugins_loaded', 'evs_vloerverwarming_offerte_init');

// Registreer de activatie hook om de database tabel aan te maken
register_activation_hook(__FILE__, array('EVS_Vloerverwarming_Offerte', 'on_activation'));
