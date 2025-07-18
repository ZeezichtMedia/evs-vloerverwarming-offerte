<?php
/**
 * Simple test handler for EVS form submission
 * This allows testing the form without full WordPress environment
 */

// Mock WordPress functions for testing
if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim(strip_tags($text));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($text) {
        return trim(strip_tags($text));
    }
}

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'evs_vloerverwarming_offerte_submit') {
    
    // Get form data
    $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : array();
    
    // Simple validation
    $errors = array();
    $required_fields = array(
        'verdieping' => 'Verdieping is verplicht',
        'type_vloer' => 'Type vloer is verplicht',
        'oppervlakte' => 'Oppervlakte is verplicht',
        'warmtebron' => 'Warmtebron is verplicht',
        'verdeler_aansluiten' => 'Verdeler aansluiten keuze is verplicht',
        'vloer_dichtsmeren' => 'Vloer dichtsmeren keuze is verplicht',
        'montagedatum' => 'Montagedatum is verplicht',
        'email' => 'E-mailadres is verplicht',
        'voornaam' => 'Voornaam is verplicht',
        'achternaam' => 'Achternaam is verplicht'
    );
    
    foreach ($required_fields as $field => $message) {
        if (empty($form_data[$field])) {
            $errors[$field] = $message;
        }
    }
    
    // Validate email
    if (!empty($form_data['email']) && !is_email($form_data['email'])) {
        $errors['email'] = 'Een geldig e-mailadres is verplicht.';
    }
    
    // Validate oppervlakte
    if (!empty($form_data['oppervlakte']) && (!is_numeric($form_data['oppervlakte']) || $form_data['oppervlakte'] <= 0)) {
        $errors['oppervlakte'] = 'Oppervlakte is verplicht en moet een getal zijn.';
    }
    
    // Special validation for "anders" option
    if (!empty($form_data['verdieping']) && $form_data['verdieping'] === 'anders' && empty($form_data['verdieping_anders'])) {
        $errors['verdieping_anders'] = 'Specificatie voor "anders" is verplicht';
    }
    
    // Special validation for date when "datum" is selected
    if (!empty($form_data['montagedatum']) && $form_data['montagedatum'] === 'datum' && empty($form_data['gewenste_datum'])) {
        $errors['gewenste_datum'] = 'Gewenste datum is verplicht';
    }
    
    header('Content-Type: application/json');
    
    if (!empty($errors)) {
        echo json_encode(array(
            'success' => false,
            'data' => array(
                'message' => 'Er zijn fouten gevonden in het formulier.',
                'errors' => $errors
            )
        ));
    } else {
        // Calculate quote (simplified)
        $area_m2 = floatval($form_data['oppervlakte']);
        $strekkende_meter = $area_m2 * 8.5;
        
        // Simple pricing calculation
        $drilling_price = $strekkende_meter * 2.00; // Simplified pricing
        $verdeler_price = ($form_data['verdeler_aansluiten'] === 'ja') ? 185.00 : 0;
        $sealing_price = ($form_data['vloer_dichtsmeren'] === 'ja') ? $strekkende_meter * 12.75 : 0;
        $total_price = $drilling_price + $verdeler_price + $sealing_price;
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'message' => 'Uw offerte is succesvol verzonden! Dit is een test - in de echte plugin wordt dit opgeslagen in de database en worden e-mails verzonden.',
                'quote' => array(
                    'drilling_price' => $drilling_price,
                    'sealing_price' => $sealing_price,
                    'total_price' => $total_price,
                    'area_m2' => $area_m2,
                    'strekkende_meter' => $strekkende_meter
                )
            )
        ));
    }
    exit;
}

// If not a form submission, show error
header('Content-Type: application/json');
echo json_encode(array(
    'success' => false,
    'data' => array('message' => 'Invalid request')
));
?>
