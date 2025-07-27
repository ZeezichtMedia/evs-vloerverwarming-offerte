<?php
/**
 * EVS Admin Manager
 * 
 * Handles all admin panel functionality for the plugin
 * Separates admin logic from main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVS_Admin_Manager {
    const VAT_RATE = 1.21;
    
    private $database_manager;
    private $email_service;
    private $pricing_calculator;
    
    /**
     * Constructor
     */
    public function __construct($database_manager, $email_service, $pricing_calculator) {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_init', array($this, 'handle_admin_submissions'));
        $this->database_manager = $database_manager;
        $this->email_service = $email_service;
        $this->pricing_calculator = $pricing_calculator;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'EVS Offertes',
            'EVS Offertes',
            'manage_options',
            'evs-offertes',
            array($this, 'display_quotes_page'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'evs-offertes',
            'Alle Offertes',
            'Alle Offertes',
            'manage_options',
            'evs-offertes',
            array($this, 'display_quotes_page')
        );
        
        add_submenu_page(
            'evs-offertes',
            'Facturen',
            'Facturen',
            'manage_options',
            'evs-invoices',
            array($this, 'display_invoices_page')
        );
        
        add_submenu_page(
            'evs-offertes', // Parent slug
            'Offerte Bewerken', // Page Title
            null, // Menu Title (set to null to hide from menu)
            'manage_options',
            'evs-edit-quote',
            array($this, 'display_edit_quote_page')
        );
        
        add_submenu_page(
            'evs-offertes',
            'Instellingen',
            'Instellingen',
            'manage_options',
            'evs-settings',
            array($this, 'display_settings_page')
        );
        
    }

    public function enqueue_admin_assets($hook) {


        // Enqueue styles for the main quotes list page
        if ('toplevel_page_evs-offertes' === $hook) {
            wp_enqueue_style(
                'evs-admin-quotes-list',
                EVS_IMPROVED_URL . 'assets/css/evs-admin-quotes-list.css',
                array(),
                EVS_PLUGIN_VERSION
            );
        }

        // Enqueue styles and scripts for the edit quote page
        if ('evs-offertes_page_evs-edit-quote' === $hook) {
            wp_enqueue_style(
                'evs-admin-edit-quote',
                EVS_IMPROVED_URL . 'assets/css/evs-admin-edit-quote.css',
                array(),
                EVS_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'evs-admin-edit-quote',
                EVS_IMPROVED_URL . 'assets/js/evs-admin-edit-quote.js',
                array('jquery'),
                EVS_PLUGIN_VERSION,
                true
            );

            // Pass data to the script for AJAX price calculation.
            wp_localize_script(
                'evs-admin-edit-quote',
                'evs_admin_quote_data',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('evs_calculate_price_nonce')
                )
            );
        }
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (
            isset($_GET['page']) &&
            $_GET['page'] === 'evs-offertes' &&
            isset($_GET['admin_action']) &&
            $_GET['admin_action'] === 'edit' &&
            isset($_GET['quote_id'])
        ) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'evs_admin_action_edit_' . intval($_GET['quote_id']))) {
                wp_die(__('Security check failed.', 'evs-vloerverwarming'));
            }

            $quote_id = intval($_GET['quote_id']);
            $redirect_url = admin_url('admin.php?page=evs-edit-quote&quote_id=' . $quote_id);

            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Display quotes page
     */
    public function display_quotes_page() {
        $current_status = sanitize_key($_GET['status'] ?? 'all');
        $quotes = $this->database_manager->get_quotes(
            $current_status !== 'all' ? array('status' => $current_status) : array()
        );
        
        // Fetch status counts for different statuses
        $status_counts = array(
            'all' => $this->database_manager->get_quotes_count(),
            'pending' => $this->database_manager->get_quotes_count('pending'),
            'sent' => $this->database_manager->get_quotes_count('sent'),
            'accepted' => $this->database_manager->get_quotes_count('accepted'),
            'completed' => $this->database_manager->get_quotes_count('completed')
        );

        // Load the template file
        include EVS_IMPROVED_PATH . 'templates/admin/quotes-list.php';
    }
    
    /**
     * Display invoices page
     */
    public function display_invoices_page() {
        ?>
        <div class="wrap">
            <h1>Facturen</h1>
            <p>Factuuroverzicht - functionaliteit wordt binnenkort toegevoegd.</p>
        </div>
        <?php
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        // Prepare data for the view
        $settings = [
            'admin_email'        => get_option('evs_admin_email', get_option('admin_email')),
            'company_name'       => get_option('evs_company_name', 'EVS Vloerverwarmingen'),
            'company_address'    => get_option('evs_company_address', ''),
            'vat_number'         => get_option('evs_vat_number', ''),
            'auto_send_quotes'   => get_option('evs_auto_send_quotes', 0),
            'privacy_policy_url' => get_option('evs_privacy_policy_url', ''),
        ];

        // Handle success messages from redirect
        $message_type = '';
        $message_text = '';
        if (isset($_GET['message']) && $_GET['message'] === 'saved') {
            $message_type = 'success';
            $message_text = 'Instellingen succesvol opgeslagen.';
        }

        // Load the template
        include EVS_IMPROVED_PATH . 'templates/admin/settings-page.php';
    }

    /**
     * Handle settings page form submission
     */
    /**
     * Handle admin form submissions from the settings and edit quote pages.
     */
    public function handle_admin_submissions() {
        // Check if the edit offer form was submitted
        if (isset($_POST['offer_id'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'evs_offer_actions_' . intval($_POST['offer_id']))) {
            $this->process_edit_offer_form(intval($_POST['offer_id']));
        }

        // Check if the settings form was submitted
        if (isset($_POST['evs_settings_nonce']) && wp_verify_nonce($_POST['evs_settings_nonce'], 'evs_settings_nonce')) {
            $this->handle_settings_update();
        }
    }

    private function handle_settings_update() {
        // Nonce is already verified in handle_admin_submissions

        // Sanitize and update options
        update_option('evs_admin_email', sanitize_email($_POST['evs_admin_email']));
        update_option('evs_company_name', sanitize_text_field($_POST['evs_company_name']));
        update_option('evs_company_address', sanitize_textarea_field($_POST['evs_company_address']));
        update_option('evs_vat_number', sanitize_text_field($_POST['evs_vat_number']));
        update_option('evs_auto_send_quotes', isset($_POST['evs_auto_send_quotes']) ? 1 : 0);
        update_option('evs_privacy_policy_url', esc_url_raw($_POST['evs_privacy_policy_url'] ?? ''));

        // Redirect with a success message
        wp_redirect(add_query_arg(['page' => 'evs-settings', 'message' => 'saved'], admin_url('admin.php')));
        exit;
    }
    

    
    /**
     * Display edit quote page
     */
    public function display_edit_quote_page() {
        if (!isset($_GET['quote_id'])) {
            wp_die('No quote ID provided.');
        }

        $quote_id = intval($_GET['quote_id']);
        $offer = $this->database_manager->get_quote($quote_id); // Using 'quote' to match DB manager

        if (!$offer) {
            wp_die('Quote not found.');
        }

        // Handle feedback messages from redirects
        if (isset($_GET['message'])) {
            add_action('admin_notices', function() {
                $message_type = 'error';
                $message_text = '';
                switch ($_GET['message']) {
                    case 'saved':
                        $message_type = 'success';
                        $message_text = 'Offerte succesvol opgeslagen.';
                        break;
                    case 'sent':
                        $message_type = 'success';
                        $message_text = 'Offerte succesvol verzonden naar de klant.';
                        break;
                    case 'invoice_created':
                        $message_type = 'success';
                        $message_text = 'Factuur succesvol aangemaakt.';
                        break;
                    case 'save_error':
                        $message_text = 'Fout: De offerte kon niet worden opgeslagen.';
                        break;
                    case 'send_error':
                        $message_text = 'Fout: De offerte is opgeslagen, maar kon niet worden verzonden.';
                        break;
                    case 'invoice_error':
                        $message_text = 'Fout: De offerte is opgeslagen, maar de factuur kon niet worden aangemaakt.';
                        break;
                    case 'calc_error':
                        $message_text = isset($_GET['error']) ? sanitize_text_field(urldecode($_GET['error'])) : 'Fout in de prijsberekening.';
                        break;
                }
                if ($message_text) {
                    printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($message_type), esc_html($message_text));
                }
            });
        }

        // Load the template
        include EVS_IMPROVED_PATH . 'admin/views/edit-offer-page.php';
    }

    private function process_edit_offer_form($quote_id) {
        // 1. Sanitize and prepare all data from the form - map form fields to database columns.
        $quote_data = [
            'naam'                  => sanitize_text_field($_POST['customer_name']),
            'email'                 => sanitize_email($_POST['customer_email']),
            'telefoon'              => sanitize_text_field($_POST['customer_phone']),
            'adres'                 => sanitize_text_field($_POST['customer_address']),
            'verdieping'            => sanitize_text_field($_POST['floor_level']),
            'type_vloer'            => sanitize_text_field($_POST['floor_type']),
            'area_m2'               => floatval($_POST['area']),
            'strekkende_meter'      => floatval($_POST['area']) * 8.5, // Calculate strekkende meter
            'warmtebron'            => sanitize_text_field($_POST['heat_source']),
            'verdeler_aansluiten'   => intval($_POST['verdeler_aansluiten']), // Convert to 1/0
            'vloer_dichtsmeren'     => intval($_POST['sealing']), // Convert to 1/0
            'montagedatum'          => sanitize_text_field($_POST['installation_date']),
            'opmerkingen'           => sanitize_textarea_field($_POST['notes']),
            'status'                => sanitize_key($_POST['status']),
        ];

        // 2. Recalculate prices based on submitted data to ensure accuracy.
        $price_data = $this->pricing_calculator->calculate_price($quote_data);

        if (isset($price_data['error'])) {
            $error_message = urlencode($price_data['error']);
            wp_redirect(add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => $quote_id, 'message' => 'calc_error', 'error' => $error_message], admin_url('admin.php')));
            exit;
        }

        // Get the original quote to compare prices and detect manual overrides.
        $original_quote = $this->database_manager->get_quote($quote_id);

        // An array to hold the final prices.
        $final_prices = [];

        // List of price fields to check.
        $price_fields = ['drilling_price', 'sealing_price', 'verdeler_price'];

        foreach ($price_fields as $field) {
            $submitted_price = isset($_POST[$field]) ? floatval($_POST[$field]) : 0;
            $original_price = isset($original_quote[$field]) ? floatval($original_quote[$field]) : 0;
            $calculated_price = isset($price_data[$field]) ? floatval($price_data[$field]) : 0;

            // If the submitted price is different from the original, the user made a manual edit.
            if (round($submitted_price, 2) !== round($original_price, 2)) {
                $final_prices[$field] = $submitted_price; // Use manual price.
            } else {
                $final_prices[$field] = $calculated_price; // Use newly calculated price.
            }
        }

        // Always recalculate the total price based on the final component prices.
        $final_prices['total_price'] = $final_prices['drilling_price'] + $final_prices['sealing_price'] + $final_prices['verdeler_price'];

        // Merge the final prices and other calculated data back into the main quote data array.
        $quote_data = array_merge($quote_data, $final_prices);
        $quote_data['strekkende_meter'] = $price_data['strekkende_meter'];

        // 3. Always save the latest data.
        $save_result = $this->database_manager->update_quote($quote_id, $quote_data);
        $message = $save_result ? 'saved' : 'save_error';

        // 4. Perform the specific action based on which button was pressed.
        if ($save_result) {
            if (isset($_POST['send_offer'])) {
                // Action: Save and Send Offer
                $quote = $this->database_manager->get_quote($quote_id);
                $email_success = $this->email_service->send_quote_to_customer($quote, $quote_id);
                $message = $email_success ? 'sent' : 'send_error';
                if ($email_success) {
                    $this->database_manager->update_quote($quote_id, ['status' => 'sent']);
                }
            } elseif (isset($_POST['create_invoice'])) {
                // Action: Save and Create Invoice
                $invoice_id = $this->database_manager->create_invoice_from_quote($quote_id);
                $message = $invoice_id ? 'invoice_created' : 'invoice_error';
                if ($invoice_id) {
                    $this->database_manager->update_quote($quote_id, ['status' => 'invoiced']);
                }
            } elseif (isset($_POST['save_offer'])) {
                // Action: Just Save (default)
                $message = 'saved';
            }
        }

        // 5. Redirect back to the edit page with a feedback message.
        wp_redirect(add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => $quote_id, 'message' => $message], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Format floor level for display
     */
    private function format_floor_level($level) {
        switch ($level) {
            case 'begaande_grond':
                return 'Begaande grond';
            case 'eerste_verdieping':
                return 'Eerste verdieping';
            case 'zolder':
                return 'Zolder';
            case 'anders':
                return 'Anders';
            default:
                return ucfirst(str_replace('_', ' ', $level));
        }
    }
    
    /**
     * Format floor type for display
     */
    private function format_floor_type($type) {
        $types = array(
            'cement_dekvloer' => 'Cement dekvloer',
            'tegelvloer' => 'Tegelvloer',
            'betonvloer' => 'Betonvloer',
            'fermacelvloer' => 'Fermacelvloer'
        );
        
        return $types[$type] ?? $type;
    }
    
    /**
     * Format status for display
     */
    private function format_status($status) {
        $statuses = array(
            'pending' => 'In behandeling',
            'sent' => 'Verzonden',
            'accepted' => 'Geaccepteerd',
            'completed' => 'Voltooid'
        );
        
        return $statuses[$status] ?? $status;
    }
}

