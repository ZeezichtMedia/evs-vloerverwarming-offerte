<?php
/**
 * Plugin Name: EVS Vloerverwarming Offerte
 * Plugin URI: 
 * Description: Multi-step offerteformulier voor vloerverwarming installaties
 * Version: 1.0.2
 * Author: 
 * Author URI: 
 * Text Domain: evs-vloerverwarming
 */

// Voorkom direct toegang tot dit bestand
if (!defined('ABSPATH')) {
    exit;
}

// Laad de Offer class
if (file_exists(plugin_dir_path(__FILE__) . 'src/Models/Offer.php')) {
    require_once plugin_dir_path(__FILE__) . 'src/Models/Offer.php';
}

class EVS_Vloerverwarming_Offerte {
    
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
        
        $log_file = plugin_dir_path(__FILE__) . 'error.log';
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
        wp_die();
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
                'message' => 'Uw offerte is succesvol verzonden. U ontvangt binnen 8 uur een prijsindicatie.',
                'quote' => $quote,
                'quote_id' => $quote_id ?? null
            ));
        } catch (Exception $e) {
            $this->log_error('Fout bij verwerken formulier', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            wp_send_json_error(array(
                'message' => 'Er is een fout opgetreden bij het verwerken van uw aanvraag. Probeer het later opnieuw of neem contact met ons op.'
            ));
        }
        
        wp_die();
    }
    
    /**
     * Valideer formuliergegevens
     */
    private function validate_form_data($form_data) {
        $errors = array();
        
        // Controleer verplichte velden
        $required_fields = array(
            'soort_vloerverwarming' => 'Soort vloerverwarming is verplicht',
            'type_woning' => 'Type woning is verplicht',
            'type_vloer' => 'Type vloer is verplicht',
            'vierkante_meters' => 'Vierkante meters is verplicht',
            'email' => 'E-mailadres is verplicht',
            'voornaam' => 'Voornaam is verplicht',
            'achternaam' => 'Achternaam is verplicht',
            'adres' => 'Adres is verplicht',
            'huisnummer' => 'Huisnummer is verplicht',
            'postcode' => 'Postcode is verplicht',
            'plaats' => 'Plaats is verplicht'
        );
        
        foreach ($required_fields as $field => $message) {
            if (empty($form_data[$field])) {
                $errors[$field] = $message;
            }
        }
        
        // Controleer e-mailadres
        if (!empty($form_data['email']) && !is_email($form_data['email'])) {
            $errors['email'] = 'Ongeldig e-mailadres';
        }
        
        // Controleer vierkante meters (moet een positief getal zijn)
        if (!empty($form_data['vierkante_meters']) && (!is_numeric($form_data['vierkante_meters']) || $form_data['vierkante_meters'] <= 0)) {
            $errors['vierkante_meters'] = 'Vierkante meters moet een positief getal zijn';
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
            $this->log_error('Fout bij berekenen offerte', array(
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
     * Haal prijsopties op (kan later uit admin panel komen)
     */
    private function get_price_options() {
        // Dit zou later uit een admin panel kunnen komen
        return array(
            'heating_types' => array(
                'laten_frezen' => 25,
                'op_krimpnetten' => 20,
                'default' => 22
            ),
            'housing_factors' => array(
                'bestaande_woning' => 1.0,
                'nieuwbouw_woning' => 0.95,
                'appartement' => 1.1,
                'default' => 1.0
            ),
            'floor_factors' => array(
                'cementdek_vloer' => 1.0,
                'beton_vloer' => 1.1,
                'fermacell_vloer' => 1.15,
                'weet_niet' => 1.05,
                'default' => 1.0
            ),
            'heat_source_factors' => array(
                'cv_ketel' => 1.0,
                'warmtepomp' => 1.05,
                'stadsverwarming' => 1.02,
                'anders' => 1.03,
                'default' => 1.0
            ),
            'floor_sealing_price' => 5 // prijs per m²
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
                $this->log_error('Database insertie mislukt', array('error' => $wpdb->last_error));
                return false;
            }

            return $wpdb->insert_id;

        } catch (Exception $e) {
            $this->log_error('Fout bij opslaan offerte', array('error' => $e->getMessage()));
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
                $this->log_error('Klant e-mail verzenden mislukt: geen geldig e-mailadres');
                return false;
            }
            
            $subject = 'Uw offerte voor vloerverwarming - EVS Vloerverwarmingen';
            
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
                <title>Uw offerte voor vloerverwarming</title>
                <style type="text/css">{$css}</style>
            </head>
            <body>
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td>
                            <h2>Bedankt voor uw offerteaanvraag</h2>
                            <p>Beste {$form_data['voornaam']},</p>
                            <p>Bedankt voor uw offerteaanvraag voor vloerverwarming. Op basis van de door u verstrekte gegevens hebben wij een prijsindicatie opgesteld.</p>
                            
                            <h3>Uw gegevens:</h3>
                            <table width="100%" cellpadding="5" cellspacing="0" border="0">
                                <tr>
                                    <td width="40%"><strong>Soort vloerverwarming:</strong></td>
                                    <td>{$this->get_readable_value('soort_vloerverwarming', $form_data['soort_vloerverwarming'] ?? '')}</td>
                                </tr>
                                <tr>
                                    <td><strong>Type woning:</strong></td>
                                    <td>{$this->get_readable_value('type_woning', $form_data['type_woning'] ?? '')}</td>
                                </tr>
                                <tr>
                                    <td><strong>Type vloer:</strong></td>
                                    <td>{$this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '')}</td>
                                </tr>
                                <tr>
                                    <td><strong>Oppervlakte:</strong></td>
                                    <td>{$quote['square_meters']} m²</td>
                                </tr>
                            </table>
                            
                            <h3>Prijsindicatie:</h3>
                            <p class="price">Totaalbedrag (incl. BTW): €{$this->format_price($quote['total_price'])}</p>
                            
                            <p>Let op: Dit is een indicatieve prijs. Een van onze medewerkers zal binnen 8 uur contact met u opnemen voor een definitieve offerte.</p>
                            
                            <div class="footer">
                                <p>Met vriendelijke groet,<br>EVS Vloerverwarmingen</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            HTML;
            
            // Platte tekst versie van de e-mail voor betere compatibiliteit
            $text_message = "Bedankt voor uw offerteaanvraag\n\n";
            $text_message .= "Beste {$form_data['voornaam']},\n\n";
            $text_message .= "Bedankt voor uw offerteaanvraag voor vloerverwarming. Op basis van de door u verstrekte gegevens hebben wij een prijsindicatie opgesteld.\n\n";
            $text_message .= "UW GEGEVENS:\n";
            $text_message .= "Soort vloerverwarming: {$this->get_readable_value('soort_vloerverwarming', $form_data['soort_vloerverwarming'] ?? '')}\n";
            $text_message .= "Type woning: {$this->get_readable_value('type_woning', $form_data['type_woning'] ?? '')}\n";
            $text_message .= "Type vloer: {$this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '')}\n";
            $text_message .= "Oppervlakte: {$quote['square_meters']} m²\n\n";
            $text_message .= "PRIJSINDICATIE:\n";
            $text_message .= "Totaalbedrag (incl. BTW): €{$this->format_price($quote['total_price'])}\n\n";
            $text_message .= "Let op: Dit is een indicatieve prijs. Een van onze medewerkers zal binnen 8 uur contact met u opnemen voor een definitieve offerte.\n\n";
            $text_message .= "Met vriendelijke groet,\nEVS Vloerverwarmingen";
            
            // Stuur multipart e-mail (zowel HTML als platte tekst)
            return $this->send_email($to, $subject, $html_message, $text_message);
        } catch (Exception $e) {
            $this->log_error('Klant e-mail verzenden mislukt', array(
                'error' => $e->getMessage(),
                'email' => $form_data['email'] ?? ''
            ));
            return false;
        }
    }
    
    /**
     * Format prijs met komma als decimaal scheidingsteken
     */
    private function format_price($price) {
        return number_format($price, 2, ',', '.');
    }
    
    /**
     * Stuur e-mail met zowel HTML als platte tekst voor betere compatibiliteit
     * 
     * @param string $to E-mailadres van ontvanger
     * @param string $subject Onderwerp van e-mail
     * @param string $html_message HTML-versie van het bericht
     * @param string $text_message Platte tekst versie van het bericht
     * @return bool Succes of mislukking van e-mail verzenden
     */
    private function send_email($to, $subject, $html_message, $text_message) {
        // Genereer unieke boundary
        $boundary = md5(time());
        
        // Headers instellen
        $headers = array(
            'MIME-Version: 1.0',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . get_option('admin_email'),
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
        );
        
        // Bouw multipart bericht
        $message = "\r\n\r\n--" . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $text_message . "\r\n\r\n";
        
        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $html_message . "\r\n\r\n";
        
        $message .= "--" . $boundary . "--";
        
        // Verstuur e-mail
        $result = wp_mail($to, $subject, $message, $headers);
        
        if (!$result) {
            $this->log_error('E-mail verzenden mislukt', array(
                'to' => $to,
                'subject' => $subject
            ));
        }
        
        return $result;
    }
    
    /**
     * Stuur e-mail naar admin
     * 
     * @param array $form_data Formuliergegevens
     * @param array $quote Offertegegevens
     * @return bool Succes of mislukking van e-mail verzenden
     */
    private function send_admin_email($form_data, $quote) {
        try {
            $to = get_option('admin_email');
            $subject = 'Nieuwe offerteaanvraag vloerverwarming';
            
            // Inline CSS voor betere webmail-compatibiliteit
            $css = <<<CSS
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.5; }
            h2 { color: #0a3b2e; margin-top: 20px; margin-bottom: 15px; }
            h3 { color: #00a651; margin-top: 20px; margin-bottom: 10px; }
            p { margin-bottom: 15px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th { background-color: #f2f2f2; text-align: left; padding: 8px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            .price { font-weight: bold; color: #0a3b2e; font-size: 16px; }
            CSS;
            
            // Klantgegevens voorbereiden
            $klantnaam = sanitize_text_field($form_data['voornaam'] ?? '') . ' ' . sanitize_text_field($form_data['achternaam'] ?? '');
            $email = sanitize_email($form_data['email'] ?? '');
            $telefoon = sanitize_text_field($form_data['telefoon'] ?? '');
            
            // Adresgegevens voorbereiden
            $adres = sanitize_text_field($form_data['adres'] ?? '') . ' ' . sanitize_text_field($form_data['huisnummer'] ?? '');
            $postcode = sanitize_text_field($form_data['postcode'] ?? '');
            $plaats = sanitize_text_field($form_data['plaats'] ?? '');
            
            // Projectgegevens voorbereiden
            $soort_vloerverwarming = $this->get_readable_value('soort_vloerverwarming', $form_data['soort_vloerverwarming'] ?? '');
            $type_woning = $this->get_readable_value('type_woning', $form_data['type_woning'] ?? '');
            $type_vloer = $this->get_readable_value('type_vloer', $form_data['type_vloer'] ?? '');
            $warmtebron = $this->get_readable_value('warmtebron', $form_data['warmtebron'] ?? '');
            $vloer_dichtsmeren = $this->get_readable_value('vloer_dichtsmeren', $form_data['vloer_dichtsmeren'] ?? '');
            $montagedatum = $this->get_readable_value('montagedatum', $form_data['montagedatum'] ?? '');
            
            // Toelichting
            $toelichting = !empty($form_data['toelichting']) ? nl2br(sanitize_textarea_field($form_data['toelichting'])) : 'Geen toelichting';
            
            // HTML-versie van de e-mail met tabellen voor betere webmail-compatibiliteit
            $html_message = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>Nieuwe offerteaanvraag vloerverwarming</title>
                <style type="text/css">{$css}</style>
            </head>
            <body>
                <h2>Nieuwe offerteaanvraag</h2>
                
                <h3>Klantgegevens:</h3>
                <table>
                    <tr>
                        <th width="30%">Naam</th>
                        <td>{$klantnaam}</td>
                    </tr>
                    <tr>
                        <th>E-mail</th>
                        <td>{$email}</td>
                    </tr>
                    <tr>
                        <th>Telefoon</th>
                        <td>{$telefoon}</td>
                    </tr>
                </table>
                
                <h3>Installatieadres:</h3>
                <table>
                    <tr>
                        <th width="30%">Adres</th>
                        <td>{$adres}</td>
                    </tr>
                    <tr>
                        <th>Postcode</th>
                        <td>{$postcode}</td>
                    </tr>
                    <tr>
                        <th>Plaats</th>
                        <td>{$plaats}</td>
                    </tr>
                </table>
                
                <h3>Projectgegevens:</h3>
                <table>
                    <tr>
                        <th width="30%">Soort vloerverwarming</th>
                        <td>{$soort_vloerverwarming}</td>
                    </tr>
                    <tr>
                        <th>Type woning</th>
                        <td>{$type_woning}</td>
                    </tr>
                    <tr>
                        <th>Type vloer</th>
                        <td>{$type_vloer}</td>
                    </tr>
                    <tr>
                        <th>Oppervlakte</th>
                        <td>{$quote['square_meters']} m²</td>
                    </tr>
                    <tr>
                        <th>Warmtebron</th>
                        <td>{$warmtebron}</td>
                    </tr>
                    <tr>
                        <th>Vloer dichtsmeren</th>
                        <td>{$vloer_dichtsmeren}</td>
                    </tr>
                    <tr>
                        <th>Montagedatum</th>
                        <td>{$montagedatum}</td>
                    </tr>
                </table>
                
                <h3>Prijsindicatie:</h3>
                <p class="price">Totaalbedrag (incl. BTW): €{$this->format_price($quote['total_price'])}</p>
                
                <h3>Toelichting van klant:</h3>
                <p>{$toelichting}</p>
            </body>
            </html>
            HTML;
            
            // Platte tekst versie van de e-mail voor betere compatibiliteit
            $text_message = "NIEUWE OFFERTEAANVRAAG\n\n";
            
            $text_message .= "KLANTGEGEVENS:\n";
            $text_message .= "Naam: {$klantnaam}\n";
            $text_message .= "E-mail: {$email}\n";
            $text_message .= "Telefoon: {$telefoon}\n\n";
            
            $text_message .= "INSTALLATIEADRES:\n";
            $text_message .= "Adres: {$adres}\n";
            $text_message .= "Postcode: {$postcode}\n";
            $text_message .= "Plaats: {$plaats}\n\n";
            
            $text_message .= "PROJECTGEGEVENS:\n";
            $text_message .= "Soort vloerverwarming: {$soort_vloerverwarming}\n";
            $text_message .= "Type woning: {$type_woning}\n";
            $text_message .= "Type vloer: {$type_vloer}\n";
            $text_message .= "Oppervlakte: {$quote['square_meters']} m²\n";
            $text_message .= "Warmtebron: {$warmtebron}\n";
            $text_message .= "Vloer dichtsmeren: {$vloer_dichtsmeren}\n";
            $text_message .= "Montagedatum: {$montagedatum}\n\n";
            
            $text_message .= "PRIJSINDICATIE:\n";
            $text_message .= "Totaalbedrag (incl. BTW): €{$this->format_price($quote['total_price'])}\n\n";
            
            $text_message .= "TOELICHTING VAN KLANT:\n";
            $text_message .= strip_tags($toelichting) . "\n";
            
            // Stuur multipart e-mail (zowel HTML als platte tekst)
            return $this->send_email($to, $subject, $html_message, $text_message);
        } catch (Exception $e) {
            $this->log_error('Admin e-mail verzenden mislukt', array(
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
    private function get_readable_value($field, $value) {
        $options = array(
            'soort_vloerverwarming' => array(
                'laten_frezen' => 'Laten frezen',
                'op_krimpnetten' => 'Op krimpnetten'
            ),
            'type_woning' => array(
                'bestaande_woning' => 'Bestaande woning',
                'nieuwbouw_woning' => 'Nieuwbouw woning',
                'appartement' => 'Appartement'
            ),
            'type_vloer' => array(
                'cementdek_vloer' => 'Cementdek vloer',
                'beton_vloer' => 'Beton vloer',
                'fermacell_vloer' => 'Fermacell vloer',
                'weet_niet' => 'Weet ik niet'
            ),
            'warmtebron' => array(
                'cv_ketel' => 'CV ketel',
                'warmtepomp' => 'Warmtepomp',
                'stadsverwarming' => 'Stadsverwarming',
                'anders' => 'Anders'
            ),
            'vloer_dichtsmeren' => array(
                'ja' => 'Ja',
                'nee' => 'Nee',
                'weet_niet' => 'Weet ik niet'
            ),
            'montagedatum' => array(
                'zo_snel_mogelijk' => 'Zo snel mogelijk',
                'binnen_1_maand' => 'Binnen 1 maand',
                'binnen_3_maanden' => 'Binnen 3 maanden',
                'later' => 'Later'
            )
        );
        
        if (isset($options[$field]) && isset($options[$field][$value])) {
            return $options[$field][$value];
        }
        
        return $value;
    }
    

}

    /**
     * Voegt de admin menu pagina's toe.
     */
    public function add_admin_menu() {
        add_menu_page(
            'EVS Offertes',                         // Paginatitel
            'Offertes',                             // Menutitel
            'manage_options',                       // Benodigde capability (voor nu admin)
            'evs-offertes',                         // Menu slug
            array($this, 'display_offers_page'),    // Callback functie voor de pagina-inhoud
            'dashicons-calculator',                 // Icoon
            25                                      // Positie in het menu
        );
    }

    /**
     * Toont de hoofd offerte overzichtspagina of de bewerkpagina.
     */
    public function display_offers_page() {
        require_once plugin_dir_path(__FILE__) . 'admin/class-evs-offertes-list-table.php';

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $offer_id = isset($_GET['offer']) ? intval($_GET['offer']) : 0;

        switch ($action) {
            case 'edit':
                if ($offer_id > 0) {
                    $this->display_edit_offer_page($offer_id);
                }
                break;
            default:
                echo '<div class="wrap">';
                echo '<h1>Offerte Aanvragen</h1>';
                $list_table = new EVS_Offertes_List_Table();
                $list_table->prepare_items();
                $list_table->display();
                echo '</div>';
                break;
        }
    }

    /**
     * Toont de bewerkpagina voor een specifieke offerte.
     */
    /**
     * Slaat de wijzigingen van een offerte op.
     */
    private function save_offer_changes($offer_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        $update_data = array(
            'customer_name'  => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'area'           => floatval($data['area']),
            'drilling_price' => floatval($data['drilling_price']),
            'sealing_price'  => floatval($data['sealing_price']),
            'status'         => sanitize_text_field($data['status'])
        );

        $where = array('id' => $offer_id);

        $wpdb->update($table_name, $update_data, $where);
    }

    public function display_edit_offer_page($offer_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        // Gegevens opslaan als het formulier is ingediend
        if (isset($_POST['evs_update_offer_nonce']) && wp_verify_nonce($_POST['evs_update_offer_nonce'], 'evs_update_offer_' . $offer_id)) {
            $this->save_offer_changes($offer_id, $_POST);
            echo '<div class="updated"><p>Offerte bijgewerkt!</p></div>';
        }

        $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id), ARRAY_A);

        if (!$offer) {
            echo '<div class="wrap"><h1>Fout</h1><p>Offerte niet gevonden.</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1>Offerte #<?php echo esc_html($offer_id); ?> Bewerken</h1>
            <form method="post">
                <input type="hidden" name="action" value="update_offer">
                <input type="hidden" name="offer_id" value="<?php echo esc_attr($offer_id); ?>">
                <?php wp_nonce_field('evs_update_offer_' . $offer_id, 'evs_update_offer_nonce'); ?>

                <h2>Klantgegevens</h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="customer_name">Klantnaam</label></th>
                            <td><input name="customer_name" type="text" id="customer_name" value="<?php echo esc_attr($offer['customer_name']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="customer_email">Email</label></th>
                            <td><input name="customer_email" type="email" id="customer_email" value="<?php echo esc_attr($offer['customer_email']); ?>" class="regular-text"></td>
                        </tr>
                    </tbody>
                </table>

                <h2>Offertedetails</h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="area">Oppervlakte (m²)</label></th>
                            <td><input name="area" type="number" step="0.01" id="area" value="<?php echo esc_attr($offer['area']); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="drilling_price">Prijs Infrezen (€)</label></th>
                            <td><input name="drilling_price" type="number" step="0.01" id="drilling_price" value="<?php echo esc_attr($offer['drilling_price']); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sealing_price">Prijs Dichtsmeren (€)</label></th>
                            <td><input name="sealing_price" type="number" step="0.01" id="sealing_price" value="<?php echo esc_attr($offer['sealing_price']); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="status">Status</label></th>
                            <td>
                                <select name="status" id="status">
                                    <option value="new" <?php selected($offer['status'], 'new'); ?>>Nieuw</option>
                                    <option value="sent" <?php selected($offer['status'], 'sent'); ?>>Verzonden</option>
                                    <option value="accepted" <?php selected($offer['status'], 'accepted'); ?>>Geaccepteerd</option>
                                    <option value="invoiced" <?php selected($offer['status'], 'invoiced'); ?>>Gefactureerd</option>
                                    <option value="cancelled" <?php selected($offer['status'], 'cancelled'); ?>>Geannuleerd</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Offerte Bijwerken'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Wordt uitgevoerd bij het activeren van de plugin.
     */
    public static function on_activation() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Start de plugin
$evs_vloerverwarming_offerte = new EVS_Vloerverwarming_Offerte();

// Registreer de activatie hook om de database tabel aan te maken
register_activation_hook(__FILE__, array('EVS_Vloerverwarming_Offerte', 'on_activation'));
