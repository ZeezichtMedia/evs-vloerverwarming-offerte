<?php

namespace EVS\Controllers;

use EVS\Services\QuoteService;
use EVS\Services\InvoiceService;
use EVS\Mail\QuoteMailer;

/**
 * Controller for handling quote-related requests
 */
class QuoteController
{
    private QuoteService $quoteService;
    private InvoiceService $invoiceService;
    private QuoteMailer $mailer;

    public function __construct(
        QuoteService $quoteService,
        InvoiceService $invoiceService,
        QuoteMailer $mailer
    ) {
        $this->quoteService = $quoteService;
        $this->invoiceService = $invoiceService;
        $this->mailer = $mailer;
    }

    /**
     * Handle AJAX form submission
     */
    public function handleFormSubmission(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'evs_form_nonce')) {
            wp_die('Security check failed');
        }

        // Process form data
        $formData = $this->sanitizeFormData($_POST);
        
        // Debug: Log sanitized form data
        error_log('EVS Debug - Sanitized form data: ' . print_r($formData, true));
        
        // Create quote
        $result = $this->quoteService->process($formData);
        
        if ($result['success']) {
            // Send emails
            $quote = $result['quote'];
            $this->mailer->sendQuoteToAdmin($quote);
            $this->mailer->sendConfirmationToCustomer($quote);
            
            wp_send_json_success([
                'message' => 'Uw aanvraag is succesvol verzonden!',
                'quote_id' => $result['quote_id']
            ]);
        } else {
            // Debug: Log validation errors
            error_log('EVS Debug - Validation errors: ' . print_r($result['errors'], true));
            
            wp_send_json_error([
                'message' => 'Er zijn fouten opgetreden:',
                'errors' => $result['errors'],
                'debug_data' => $formData // Add debug data to response
            ]);
        }
    }

    /**
     * Handle admin actions (send quote, create invoice, etc.)
     */
    public function handleAdminAction(): void
    {
        $action = sanitize_key($_GET['action'] ?? '');
        $quoteId = intval($_GET['offer_id'] ?? 0);
        
        if (!$quoteId) {
            wp_die('Invalid quote ID');
        }

        // Verify nonce for destructive actions
        if (in_array($action, ['send_quote', 'create_invoice', 'delete'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'evs_admin_action')) {
                wp_die('Security check failed');
            }
        }

        switch ($action) {
            case 'send_quote':
                $this->sendQuote($quoteId);
                break;
                
            case 'create_invoice':
                $this->createInvoice($quoteId);
                break;
                
            case 'edit':
                $this->redirectToEdit($quoteId);
                break;
                
            case 'delete':
                $this->deleteQuote($quoteId);
                break;
                
            default:
                wp_die('Invalid action');
        }
    }

    /**
     * Send quote email
     */
    private function sendQuote(int $quoteId): void
    {
        $quote = $this->quoteService->getQuote($quoteId);
        
        if (!$quote) {
            wp_die('Quote not found');
        }

        $success = $this->mailer->sendQuoteToCustomer($quote);
        
        if ($success) {
            $this->quoteService->updateStatus($quoteId, 'sent');
            $this->redirectWithMessage('Offerte succesvol verzonden!', 'success');
        } else {
            $this->redirectWithMessage('Fout bij het verzenden van de offerte.', 'error');
        }
    }

    /**
     * Create invoice from quote
     */
    private function createInvoice(int $quoteId): void
    {
        $result = $this->invoiceService->createFromQuote($quoteId);
        
        if ($result['success']) {
            $invoiceId = $result['invoice_id'];
            wp_redirect(admin_url("admin.php?page=evs-edit-invoice&invoice_id={$invoiceId}"));
            exit;
        } else {
            $errors = implode(', ', $result['errors']);
            $this->redirectWithMessage("Fout bij het genereren van de factuur: {$errors}", 'error');
        }
    }

    /**
     * Redirect to edit page
     */
    private function redirectToEdit(int $quoteId): void
    {
        wp_redirect(admin_url("admin.php?page=evs-edit-offer&offer_id={$quoteId}"));
        exit;
    }

    /**
     * Delete quote
     */
    private function deleteQuote(int $quoteId): void
    {
        if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
            // Show confirmation
            $quote = $this->quoteService->getQuote($quoteId);
            $customerName = $quote ? $quote->customer_name : 'Onbekend';
            
            $confirmUrl = add_query_arg([
                'action' => 'delete',
                'offer_id' => $quoteId,
                'confirm' => '1',
                '_wpnonce' => wp_create_nonce('evs_admin_action')
            ], admin_url('admin.php?page=evs-offertes'));
            
            $cancelUrl = admin_url('admin.php?page=evs-offertes');
            
            echo '<div class="wrap">';
            echo '<h1>Offerte verwijderen</h1>';
            echo '<p>Weet je zeker dat je de offerte voor <strong>' . esc_html($customerName) . '</strong> wilt verwijderen?</p>';
            echo '<p><strong>Deze actie kan niet ongedaan worden gemaakt.</strong></p>';
            echo '<p>';
            echo '<a href="' . esc_url($confirmUrl) . '" class="button button-primary">Ja, verwijderen</a> ';
            echo '<a href="' . esc_url($cancelUrl) . '" class="button">Annuleren</a>';
            echo '</p>';
            echo '</div>';
            return;
        }

        // Perform deletion
        $success = $this->quoteService->deleteQuote($quoteId);
        
        if ($success) {
            $this->redirectWithMessage('Offerte succesvol verwijderd.', 'success');
        } else {
            $this->redirectWithMessage('Fout bij het verwijderen van de offerte.', 'error');
        }
    }

    /**
     * Sanitize form data
     */
    private function sanitizeFormData(array $data): array
    {
        // Extract form_data if it exists (AJAX submission)
        $formData = $data['form_data'] ?? $data;
        
        // Combine name fields
        $fullName = trim(($formData['voornaam'] ?? '') . ' ' . ($formData['achternaam'] ?? ''));
        
        // Combine address fields
        $fullAddress = trim(($formData['adres'] ?? '') . ' ' . ($formData['huisnummer'] ?? ''));
        if (!empty($formData['postcode']) || !empty($formData['plaats'])) {
            $fullAddress .= ', ' . trim(($formData['postcode'] ?? '') . ' ' . ($formData['plaats'] ?? ''));
        }
        if (!empty($formData['land'])) {
            $fullAddress .= ', ' . $formData['land'];
        }
        
        // Fix installation date mapping
        $installationDate = $formData['montagedatum'] ?? '';
        if ($installationDate === 'weet_niet') {
            $installationDate = 'weet_ik_niet';
        }
        
        return [
            'created_at' => current_time('mysql'),
            'customer_name' => sanitize_text_field($fullName ?: ($formData['naam'] ?? '')),
            'customer_email' => sanitize_email($formData['email'] ?? ''),
            'customer_phone' => sanitize_text_field($formData['telefoon'] ?? ''),
            'customer_address' => sanitize_textarea_field($fullAddress ?: ($formData['adres'] ?? '')),
            'customer_postal' => sanitize_text_field($formData['postcode'] ?? ''),
            'customer_city' => sanitize_text_field($formData['plaats'] ?? ''),
            'customer_country' => sanitize_text_field($formData['land'] ?? 'Nederland'),
            'floor_level' => sanitize_text_field($formData['verdieping'] ?? ''),
            'floor_level_other' => sanitize_text_field($formData['verdieping_anders'] ?? ''),
            'floor_type' => sanitize_text_field($formData['type_vloer'] ?? ''),
            'area' => floatval($formData['oppervlakte'] ?? 0),
            'strekkende_meter' => floatval($formData['oppervlakte'] ?? 0) * 8.5, // Calculate strekkende meter
            'heat_source' => sanitize_text_field($formData['warmtebron'] ?? ''),
            'distributor' => ($formData['verdeler_aansluiten'] ?? '') === 'ja' ? 'ja' : 'nee',
            'sealing' => ($formData['vloer_dichtsmeren'] ?? '') === 'ja' ? 'ja' : 'nee',
            'installation_date' => sanitize_text_field($installationDate),
            'drilling_price' => 0.00, // Will be calculated by pricing service
            'sealing_price' => 0.00, // Will be calculated by pricing service
            'total_price' => 0.00, // Will be calculated by pricing service
            'verdeler_price' => 0.00, // Will be calculated by pricing service
            'notes' => sanitize_textarea_field($formData['opmerkingen'] ?? ''),
            'status' => 'new',
        ];
    }

    /**
     * Redirect with admin message
     */
    private function redirectWithMessage(string $message, string $type = 'info'): void
    {
        $url = add_query_arg([
            'message' => urlencode($message),
            'type' => $type
        ], admin_url('admin.php?page=evs-offertes'));
        
        wp_redirect($url);
        exit;
    }
}
