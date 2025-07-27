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
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'evs_offerte_form')) {
            wp_enqueue_style(
                'evs-offerte-style',
                EVS_IMPROVED_URL . 'assets/css/evs-offerte-style.css',
                array(),
                EVS_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'evs-offerte-script',
                EVS_IMPROVED_URL . 'assets/js/evs-offerte-script.js',
                array('jquery'),
                EVS_PLUGIN_VERSION,
                true
            );

            wp_localize_script('evs-offerte-script', 'evs_ajax_obj', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('evs_form_nonce')
            ));
        }
    }
    
    /**
     * Process form submission
     */
    public function process_form() {
        // Verify nonce - check both possible nonce field names for compatibility
        $nonce = $_POST['evs_nonce'] ?? $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'evs_form_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Sanitize form data
        $form_data = $this->sanitize_form_data($_POST);
        
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
        $text_fields = array('naam', 'email', 'telefoon', 'adres', 'postcode', 'plaats', 'opmerkingen', 'verdieping_anders');
        foreach ($text_fields as $field) {
            $sanitized[$field] = sanitize_text_field($data[$field] ?? '');
        }
        
        // Email
        $sanitized['email'] = sanitize_email($data['email'] ?? '');
        
        // Radio/select fields
        $select_fields = array('verdieping', 'type_vloer', 'warmtebron', 'verdeler_aansluiten', 'vloer_dichtsmeren', 'extra_schuren', 'montagedatum_type');
        foreach ($select_fields as $field) {
            $sanitized[$field] = sanitize_key($data[$field] ?? '');
        }
        
        // Numeric fields
        $sanitized['area_m2'] = (float) ($data['area_m2'] ?? 0);
        
        // Date field
        $sanitized['montagedatum'] = sanitize_text_field($data['montagedatum'] ?? '');
        
        // Checkbox
        $sanitized['privacy_akkoord'] = isset($data['privacy_akkoord']) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * Validate form data
     */
    private function validate_form_data($data) {
        $errors = array();
        
        // Required fields
        if (empty($data['naam'])) {
            $errors[] = 'Naam is verplicht';
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = 'Geldig e-mailadres is verplicht';
        }
        
        if (empty($data['verdieping'])) {
            $errors[] = 'Verdieping selectie is verplicht';
        }
        
        if (empty($data['type_vloer'])) {
            $errors[] = 'Vloertype selectie is verplicht';
        }
        
        if (empty($data['warmtebron'])) {
            $errors[] = 'Warmtebron selectie is verplicht';
        }
        
        if (empty($data['verdeler_aansluiten'])) {
            $errors[] = 'Verdeler aansluiten selectie is verplicht';
        }
        
        if (empty($data['vloer_dichtsmeren'])) {
            $errors[] = 'Vloer dichtsmeren selectie is verplicht';
        }
        
        if (empty($data['montagedatum_type'])) {
            $errors[] = 'Montagedatum type selectie is verplicht';
        }
        
        if (!$data['privacy_akkoord']) {
            $errors[] = 'Akkoord met privacyverklaring is verplicht';
        }
        
        return $errors;
    }
}
