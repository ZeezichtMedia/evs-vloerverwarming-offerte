<?php
/**
 * EVS Form Handler
 * 
 * Handles form rendering, validation, and processing
 * Separates form logic from main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVS_Form_Handler {
    
    private $pricing_calculator;
    private $database_manager;
    private $email_service;
    
    /**
     * Constructor
     */
    public function __construct($pricing_calculator, $database_manager, $email_service) {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        $this->pricing_calculator = $pricing_calculator;
        $this->database_manager = $database_manager;
        $this->email_service = $email_service;
    }
    
    /**
     * Render the form HTML
     */
    public function render_form($atts) {
        ob_start();
        
        // Pass attributes and the form handler instance to the template
        // The $atts and $this variables will be available in the included file.
        include(EVS_IMPROVED_PATH . 'templates/forms/quote-form.php');
        
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles for the form
     */
    public function enqueue_scripts() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'evs_offerte_formulier')) {
            wp_enqueue_style(
                'evs-form-style',
                EVS_IMPROVED_URL . 'assets/css/evs-form.css',
                array(),
                EVS_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'evs-form-script',
                EVS_IMPROVED_URL . 'assets/js/evs-form.js',
                array('jquery'),
                EVS_PLUGIN_VERSION,
                true
            );

            wp_localize_script('evs-form-script', 'evs_offerte_ajax_object', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('evs_form_nonce')
            ));
        }
    }
    
    /**
     * Process form submission
     */
    public function process_form() {
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'evs_form_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Get form data from nested structure or direct POST
        $raw_form_data = $_POST['form_data'] ?? $_POST;
        
        // Sanitize form data
        $form_data = $this->sanitize_form_data($raw_form_data);
        
        // Validate form data
        $validation_errors = $this->validate_form_data($form_data);
        if (!empty($validation_errors)) {
            wp_send_json_error(array(
                'message' => 'Validatiefouten gevonden',
                'errors' => $validation_errors
            ));
            return;
        }
        
        try {
            // Calculate pricing
            $quote_data = $this->pricing_calculator->calculate_price($form_data);

            // Check if the calculation was successful
            if (empty($quote_data['success'])) {
                wp_send_json_error(array('message' => $quote_data['error'] ?? 'Er is een fout opgetreden bij de prijsberekening.'));
                return;
            }
            
            // Merge form data with calculated pricing
            $complete_data = array_merge($form_data, $quote_data);
            
            // Save to database
            $quote_id = $this->database_manager->save_quote($complete_data);
            
            if ($quote_id) {
                // The quote was saved successfully. Send the success response to the user now.
                wp_send_json_success(array(
                    'message' => 'Uw aanvraag is succesvol verzonden!',
                    'quote_id' => $quote_id
                ));

                // Now, attempt to send the emails. Failures here will not affect the user's success message.
                try {
                    $this->email_service->send_admin_notification($complete_data, $quote_id);
                } catch (Exception $e) {
                    // Log the error for the admin to know an email failed.
                    error_log('EVS Admin Email Error for quote #' . $quote_id . ': ' . $e->getMessage());
                }
                
                try {
                    $this->email_service->send_customer_confirmation($complete_data, $quote_id);
                } catch (Exception $e) {
                    // Log this error as well.
                    error_log('EVS Customer Email Error for quote #' . $quote_id . ': ' . $e->getMessage());
                }

            } else {
                wp_send_json_error(array('message' => 'Er is een fout opgetreden bij het opslaan van de offerte.'));
            }
            
        } catch (Exception $e) {
            error_log('EVS Form Processing Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
            error_log('EVS Form Data: ' . json_encode($form_data, JSON_UNESCAPED_UNICODE));
            wp_send_json_error(array('message' => 'Er is een onverwachte fout opgetreden tijdens de verwerking.'));
        }
    }
    
    /**
     * Sanitize form data
     */
    private function sanitize_form_data($data) {
        $sanitized = array();
        
        // Text fields
        $text_fields = array('voornaam', 'achternaam', 'email', 'telefoon', 'adres', 'huisnummer', 'postcode', 'plaats', 'land', 'toelichting', 'verdieping_anders');
        foreach ($text_fields as $field) {
            $sanitized[$field] = sanitize_text_field($data[$field] ?? '');
        }
        
        // Combine voornaam and achternaam into naam for backward compatibility
        $sanitized['naam'] = trim(($sanitized['voornaam'] ?? '') . ' ' . ($sanitized['achternaam'] ?? ''));
        
        // Email
        $sanitized['email'] = sanitize_email($data['email'] ?? '');
        
        // Radio/select fields
        $select_fields = array('verdieping', 'type_vloer', 'warmtebron', 'verdeler_aansluiten', 'vloer_dichtsmeren', 'extra_schuren', 'montagedatum_type');
        foreach ($select_fields as $field) {
            $sanitized[$field] = sanitize_key($data[$field] ?? '');
        }
        
        // Numeric fields - support both field names for compatibility
        $sanitized['area_m2'] = (float) ($data['area_m2'] ?? $data['oppervlakte'] ?? 0);
        
        // Date field
        $sanitized['montagedatum'] = sanitize_text_field($data['montagedatum'] ?? '');
        
        // Checkbox - handle 'on', true, 1, or any truthy value
        $sanitized['privacy_akkoord'] = (!empty($data['privacy_akkoord']) && ($data['privacy_akkoord'] === 'on' || $data['privacy_akkoord'])) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * Validate form data
     * 
     * @param array $data Form data to validate
     * @return array Associative array with field names as keys and error messages as values
     */
    private function validate_form_data($data) {
        $errors = array();
        
        // Required fields with field-specific error messages
        // Combine voornaam and achternaam into naam for backward compatibility
        $naam = trim(($data['voornaam'] ?? '') . ' ' . ($data['achternaam'] ?? ''));
        if (empty($naam)) {
            $errors['naam'] = 'Voor- en achternaam zijn verplicht';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'E-mailadres is verplicht';
        } elseif (!is_email($data['email'])) {
            $errors['email'] = 'Geldig e-mailadres is verplicht';
        }
        
        if (empty($data['verdieping'])) {
            $errors['verdieping'] = 'Verdieping selectie is verplicht';
        }
        
        if (empty($data['type_vloer'])) {
            $errors['type_vloer'] = 'Vloertype selectie is verplicht';
        }
        
        if (empty($data['area_m2']) || $data['area_m2'] <= 0) {
            $errors['area_m2'] = 'Oppervlakte moet groter zijn dan 0 m²';
        }
        
        if (empty($data['warmtebron'])) {
            $errors['warmtebron'] = 'Warmtebron selectie is verplicht';
        }
        
        if (empty($data['verdeler_aansluiten'])) {
            $errors['verdeler_aansluiten'] = 'Verdeler aansluiten selectie is verplicht';
        }
        
        if (empty($data['vloer_dichtsmeren'])) {
            $errors['vloer_dichtsmeren'] = 'Vloer dichtsmeren selectie is verplicht';
        }
        
        if (empty($data['montagedatum_type'])) {
            $errors['montagedatum_type'] = 'Montagedatum type selectie is verplicht';
        }
        
        // If specific date is chosen, validate the date field
        if ($data['montagedatum_type'] === 'datum' && empty($data['montagedatum'])) {
            $errors['montagedatum'] = 'Gewenste datum is verplicht';
        }
        
        // If "anders" is selected for verdieping, validate the text field
        if ($data['verdieping'] === 'anders' && empty($data['verdieping_anders'])) {
            $errors['verdieping_anders'] = 'Specificatie van verdieping is verplicht';
        }
        
        // Privacy checkbox can be 'on', true, or 1 when checked
        if (empty($data['privacy_akkoord']) || ($data['privacy_akkoord'] !== 'on' && !$data['privacy_akkoord'])) {
            $errors['privacy_akkoord'] = 'Akkoord met privacyverklaring is verplicht';
        }
        
        return $errors;
    }
}
