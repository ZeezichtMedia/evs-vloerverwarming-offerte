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
            'edit_posts',
            'evs-offertes',
            array($this, 'display_quotes_page'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'evs-offertes',
            'Alle Offertes',
            'Alle Offertes',
            'edit_posts',
            'evs-offertes',
            array($this, 'display_quotes_page')
        );
        
        add_submenu_page(
            'evs-offertes',
            'Facturen',
            'Facturen',
            'edit_posts',
            'evs-invoices',
            array($this, 'display_invoices_page')
        );
        
        add_submenu_page(
            'evs-offertes', // Parent slug
            'Offerte Bewerken', // Page Title
            null, // Menu Title (set to null to hide from menu)
            'edit_posts',
            'evs-edit-quote',
            array($this, 'display_edit_quote_page')
        );
        
        add_submenu_page(
            'evs-offertes',
            'Instellingen',
            'Instellingen',
            'edit_posts',
            'evs-settings',
            array($this, 'display_settings_page')
        );
        
    }

    public function enqueue_admin_assets($hook) {
        // Debug hook name
        error_log('EVS Admin Hook: ' . $hook);

        // Enqueue styles for the main quotes list page - try multiple hook patterns
        if ('toplevel_page_evs-offertes' === $hook || strpos($hook, 'evs-offertes') !== false) {
            error_log('EVS: Loading CSS for hook: ' . $hook);
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
                    'nonce'    => wp_create_nonce('evs_form_nonce')
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
            wp_redirect(add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => intval($_GET['quote_id'])], admin_url('admin.php')));
            exit;
        }
        
        // Handle invoice PDF download
        if (
            isset($_GET['page']) &&
            $_GET['page'] === 'evs-invoices' &&
            isset($_GET['action']) &&
            $_GET['action'] === 'download_pdf' &&
            isset($_GET['invoice_id'])
        ) {
            $this->generate_invoice_pdf(intval($_GET['invoice_id']));
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
        // Get all invoices
        $invoices = $this->database_manager->get_invoices();
        
        ?>
        <div class="wrap">
            <h1>Facturen</h1>
            
            <?php if (empty($invoices)): ?>
                <div class="notice notice-info">
                    <p>Er zijn nog geen facturen aangemaakt.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Factuurnummer</th>
                            <th>Klant</th>
                            <th>Offerte ID</th>
                            <th>Bedrag</th>
                            <th>Status</th>
                            <th>Factuurdatum</th>
                            <th>Vervaldatum</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($invoice['invoice_number']); ?></strong></td>
                                <td>
                                    <?php echo esc_html($invoice['customer_name']); ?><br>
                                    <small><?php echo esc_html($invoice['customer_email']); ?></small>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => $invoice['quote_id']], admin_url('admin.php'))); ?>">
                                        #<?php echo esc_html($invoice['quote_id']); ?>
                                    </a>
                                </td>
                                <td>€ <?php echo esc_html(number_format($invoice['total_amount'], 2, ',', '.')); ?></td>
                                <td>
                                    <span class="invoice-status status-<?php echo esc_attr($invoice['status']); ?>">
                                        <?php echo esc_html($this->format_invoice_status($invoice['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('d-m-Y', strtotime($invoice['invoice_date']))); ?></td>
                                <td><?php echo esc_html(date('d-m-Y', strtotime($invoice['due_date']))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'evs-invoices', 'action' => 'download_pdf', 'invoice_id' => $invoice['id']], admin_url('admin.php'))); ?>" class="button button-small">PDF</a>
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'evs-edit-invoice', 'invoice_id' => $invoice['id']], admin_url('admin.php'))); ?>" class="button button-small">Bewerken</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
        .invoice-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-unpaid {
            background-color: #ffeaa7;
            color: #d63031;
        }
        .status-paid {
            background-color: #55a3ff;
            color: white;
        }
        .status-overdue {
            background-color: #ff6b6b;
            color: white;
        }
        </style>
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
                    case 'invoice_created_and_sent':
                        $message_type = 'success';
                        $message_text = 'Factuur succesvol aangemaakt en verzonden naar de klant.';
                        break;
                    case 'invoice_created_not_sent':
                        $message_type = 'warning';
                        $message_text = 'Factuur succesvol aangemaakt, maar kon niet worden verzonden naar de klant.';
                        break;
                    case 'save_error':
                        $db_error = isset($_GET['db_error']) ? urldecode($_GET['db_error']) : '';
                        $message_text = 'Fout: De offerte kon niet worden opgeslagen.';
                        if (!empty($db_error)) {
                            $message_text .= ' Database fout: ' . esc_html($db_error);
                        }
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
    // --- Robust Validation ---
    $validation_errors = [];
    if (empty($_POST['customer_name'])) {
        $validation_errors[] = 'Klantnaam is een verplicht veld.';
    }
    if (!is_email($_POST['customer_email'] ?? '')) {
        $validation_errors[] = 'Voer een geldig e-mailadres in.';
    }
    if (floatval($_POST['area_m2'] ?? 0) <= 0) {
        $validation_errors[] = 'Oppervlakte moet groter zijn dan 0.';
    }

    if (!empty($validation_errors)) {
        $error_message = urlencode(implode('<br>', $validation_errors));
        wp_redirect(add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => $quote_id, 'message' => 'validation_error', 'error' => $error_message], admin_url('admin.php')));
        exit;
    }

        // 1. Sanitize and prepare all data from the form - map form fields to database columns.
        $quote_data = [
            'naam'                  => sanitize_text_field($_POST['customer_name'] ?? ''),
            'email'                 => sanitize_email($_POST['customer_email'] ?? ''),
            'telefoon'              => sanitize_text_field($_POST['customer_phone'] ?? ''),
            'adres'                 => sanitize_text_field($_POST['customer_address'] ?? ''),
            'verdieping'            => sanitize_text_field($_POST['floor_level'] ?? ''),
            'type_vloer'            => sanitize_text_field($_POST['type_vloer'] ?? ''),
            'area_m2'               => floatval($_POST['area_m2'] ?? 0),
            'warmtebron'            => sanitize_text_field($_POST['heat_source'] ?? ''),
            'verdeler_aansluiten'   => intval($_POST['verdeler_aansluiten'] ?? 0),
            'vloer_dichtsmeren'     => intval($_POST['vloer_dichtsmeren'] ?? 0),
            'vloer_schuren'         => intval($_POST['vloer_schuren'] ?? 0),
            'montagedatum'          => sanitize_text_field($_POST['installation_date'] ?? ''),
            'opmerkingen'           => sanitize_textarea_field($_POST['notes'] ?? ''),
            'status'                => sanitize_key($_POST['status'] ?? 'pending'),
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
        // Map form field names to database column names
        $price_field_map = [
            'drilling_price' => 'drilling_price',
            'sealing_price' => 'sealing_price',
            'verdeler_price' => 'verdeler_price',
            'sanding_price' => 'sanding_price' 
        ];

        foreach ($price_field_map as $form_field => $db_field) {
            $submitted_price = isset($_POST[$form_field]) ? floatval(str_replace(',', '.', $_POST[$form_field])) : 0;
            $original_price = isset($original_quote[$db_field]) ? floatval($original_quote[$db_field]) : 0;
            $calculated_price = isset($price_data[$db_field]) ? floatval($price_data[$db_field]) : 0;

            // If the submitted price is different from the original, the user made a manual edit.
            if (round($submitted_price, 2) !== round($original_price, 2)) {
                $final_prices[$db_field] = $submitted_price; // Use manual price.
            } else {
                $final_prices[$db_field] = $calculated_price; // Use newly calculated price.
            }
        }

        // Always recalculate the total price based on the final component prices.
        $final_prices['total_price'] = $final_prices['drilling_price'] + $final_prices['sealing_price'] + $final_prices['verdeler_price'] + $final_prices['sanding_price'];

        // Merge the final prices and other calculated data back into the main quote data array.
        $quote_data = array_merge($quote_data, $final_prices);
        $quote_data['strekkende_meter'] = $price_data['strekkende_meter'];

        // 3. Always save the latest data.
        $save_result = $this->database_manager->update_quote($quote_id, $quote_data);
        
        // 4. Check for save errors and redirect immediately if there's a problem
        if ($save_result !== true) {
            // If save_result is not true, it contains the error message
            $error_message = urlencode($save_result);
            wp_redirect(add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => $quote_id, 'message' => 'save_error', 'db_error' => $error_message], admin_url('admin.php')));
            exit;
        }

        $message = 'saved'; // Default success message
        
        // 5. Perform the specific action based on which button was pressed.
            if (isset($_POST['send_offer'])) {
                if (!current_user_can('edit_posts')) {
                    wp_die(__('You do not have sufficient permissions to send quotes.', 'evs-vloerverwarming'));
                }
                // Action: Save and Send Offer
                $quote = $this->database_manager->get_quote($quote_id);
                
                // Debug logging
                error_log('EVS Plugin: Attempting to send quote #' . $quote_id);
                error_log('EVS Plugin: Quote data: ' . print_r($quote, true));
                
                $email_success = $this->email_service->send_quote_to_customer($quote, $quote_id);
                
                error_log('EVS Plugin: Email send result: ' . ($email_success ? 'SUCCESS' : 'FAILED'));
                
                $message = $email_success ? 'sent' : 'send_error';
                if ($email_success) {
                    $this->database_manager->update_quote($quote_id, ['status' => 'sent']);
                }
            } elseif (isset($_POST['create_invoice'])) {
                if (!current_user_can('edit_posts')) {
                    wp_die(__('You do not have sufficient permissions to create invoices.', 'evs-vloerverwarming'));
                }
                // Action: Save and Create Invoice
                $invoice_id = $this->database_manager->create_invoice_from_quote($quote_id);
                
                if ($invoice_id) {
                    // Get the created invoice data
                    $invoice_data = $this->database_manager->get_invoice($invoice_id);
                    
                    // Send invoice email to customer
                    $email_success = $this->email_service->send_invoice_to_customer($invoice_data, $invoice_id);
                    
                    // Update quote status
                    $this->database_manager->update_quote($quote_id, ['status' => 'invoiced']);
                    
                    $message = $email_success ? 'invoice_created_and_sent' : 'invoice_created_not_sent';
                } else {
                    $message = 'invoice_error';
                }
            } elseif (isset($_POST['save_offer'])) {
                // Action: Just Save (default)
                $message = 'saved';
            }

        // 6. Redirect back to the edit page with a feedback message.
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
            'declined' => 'Afgewezen',
            'invoiced' => 'Gefactureerd',
            'completed' => 'Voltooid',
            'new' => 'Nieuw'
        );
        
        return $statuses[$status] ?? $status;
    }
    
    /**
     * Format invoice status for display
     */
    private function format_invoice_status($status) {
        $statuses = array(
            'unpaid' => 'Onbetaald',
            'paid' => 'Betaald',
            'overdue' => 'Achterstallig',
            'cancelled' => 'Geannuleerd'
        );
        
        return $statuses[$status] ?? $status;
    }
    
    /**
     * Generate and download invoice PDF
     */
    private function generate_invoice_pdf($invoice_id) {
        $invoice = $this->database_manager->get_invoice($invoice_id);
        
        if (!$invoice) {
            wp_die('Factuur niet gevonden.');
        }
        
        // For now, generate a simple HTML version that can be printed as PDF
        // In the future, this could be enhanced with a proper PDF library
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="factuur-' . $invoice['invoice_number'] . '.html"');
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Factuur <?php echo esc_html($invoice['invoice_number']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .invoice-details { margin-bottom: 30px; }
                .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                .invoice-table th, .invoice-table td { padding: 10px; border: 1px solid #ddd; }
                .invoice-table th { background-color: #f5f5f5; }
                .total-row { font-weight: bold; background-color: #f9f9f9; }
                .payment-info { margin-top: 30px; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>EVS Vloerverwarmingen</h1>
                <h2>Factuur <?php echo esc_html($invoice['invoice_number']); ?></h2>
            </div>
            
            <div class="invoice-details">
                <p><strong>Factuurdatum:</strong> <?php echo esc_html(date('d-m-Y', strtotime($invoice['invoice_date']))); ?></p>
                <p><strong>Vervaldatum:</strong> <?php echo esc_html(date('d-m-Y', strtotime($invoice['due_date']))); ?></p>
                <p><strong>Klant:</strong> <?php echo esc_html($invoice['customer_name']); ?></p>
                <p><strong>Adres:</strong> <?php echo esc_html($invoice['customer_address']); ?></p>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Omschrijving</th>
                        <th style="text-align: right;">Bedrag</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Vloerverwarmingsinstallatie</td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice['subtotal'], 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>BTW (21%)</td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice['vat_amount'], 2, ',', '.'); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Totaal</td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice['total_amount'], 2, ',', '.'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="payment-info">
                <h3>Betalingsgegevens</h3>
                <p><strong>Rekeningnummer:</strong> NL12 ABCD 0123 4567 89</p>
                <p><strong>Ten name van:</strong> EVS Vloerverwarmingen</p>
                <p><strong>Onder vermelding van:</strong> <?php echo esc_html($invoice['invoice_number']); ?></p>
                <p><strong>Betalingstermijn:</strong> 30 dagen</p>
            </div>
            
            <script>
                // Auto-print when opened
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
        <?php
    }
}

