<?php

namespace EVS\Controllers;

use EVS\Services\InvoiceService;
use EVS\Mail\QuoteMailer;

/**
 * Controller for handling invoice-related requests
 */
class InvoiceController
{
    private InvoiceService $invoiceService;
    private QuoteMailer $mailer;

    public function __construct(InvoiceService $invoiceService, QuoteMailer $mailer)
    {
        $this->invoiceService = $invoiceService;
        $this->mailer = $mailer;
    }

    /**
     * Handle admin invoice actions
     */
    public function handleAdminAction(): void
    {
        $action = sanitize_key($_GET['action'] ?? '');
        $invoiceId = intval($_GET['invoice_id'] ?? 0);
        
        if (!$invoiceId) {
            wp_die('Invalid invoice ID');
        }

        // Verify nonce for destructive actions
        if (in_array($action, ['send_invoice', 'mark_paid', 'delete'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'evs_admin_action')) {
                wp_die('Security check failed');
            }
        }

        switch ($action) {
            case 'send_invoice':
                $this->sendInvoice($invoiceId);
                break;
                
            case 'mark_paid':
                $this->markAsPaid($invoiceId);
                break;
                
            case 'edit':
                $this->redirectToEdit($invoiceId);
                break;
                
            case 'delete':
                $this->deleteInvoice($invoiceId);
                break;
                
            default:
                wp_die('Invalid action');
        }
    }

    /**
     * Send invoice email
     */
    private function sendInvoice(int $invoiceId): void
    {
        $invoice = $this->invoiceService->getInvoice($invoiceId);
        
        if (!$invoice) {
            wp_die('Invoice not found');
        }

        $success = $this->mailer->sendInvoiceToCustomer($invoice);
        
        if ($success) {
            $this->invoiceService->updateStatus($invoiceId, 'sent');
            $this->redirectWithMessage('Factuur succesvol verzonden!', 'success');
        } else {
            $this->redirectWithMessage('Fout bij het verzenden van de factuur.', 'error');
        }
    }

    /**
     * Mark invoice as paid
     */
    private function markAsPaid(int $invoiceId): void
    {
        $success = $this->invoiceService->updateStatus($invoiceId, 'paid');
        
        if ($success) {
            $this->redirectWithMessage('Factuur gemarkeerd als betaald!', 'success');
        } else {
            $this->redirectWithMessage('Fout bij het bijwerken van de factuurstatus.', 'error');
        }
    }

    /**
     * Redirect to edit page
     */
    private function redirectToEdit(int $invoiceId): void
    {
        wp_redirect(admin_url("admin.php?page=evs-edit-invoice&invoice_id={$invoiceId}"));
        exit;
    }

    /**
     * Delete invoice
     */
    private function deleteInvoice(int $invoiceId): void
    {
        if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
            // Show confirmation
            $invoice = $this->invoiceService->getInvoice($invoiceId);
            $invoiceNumber = $invoice ? $invoice->invoice_number : 'Onbekend';
            
            $confirmUrl = add_query_arg([
                'action' => 'delete',
                'invoice_id' => $invoiceId,
                'confirm' => '1',
                '_wpnonce' => wp_create_nonce('evs_admin_action')
            ], admin_url('admin.php?page=evs-facturen'));
            
            $cancelUrl = admin_url('admin.php?page=evs-facturen');
            
            echo '<div class="wrap">';
            echo '<h1>Factuur verwijderen</h1>';
            echo '<p>Weet je zeker dat je factuur <strong>' . esc_html($invoiceNumber) . '</strong> wilt verwijderen?</p>';
            echo '<p><strong>Deze actie kan niet ongedaan worden gemaakt.</strong></p>';
            echo '<p>';
            echo '<a href="' . esc_url($confirmUrl) . '" class="button button-primary">Ja, verwijderen</a> ';
            echo '<a href="' . esc_url($cancelUrl) . '" class="button">Annuleren</a>';
            echo '</p>';
            echo '</div>';
            return;
        }

        // Perform deletion
        $success = $this->invoiceService->deleteInvoice($invoiceId);
        
        if ($success) {
            $this->redirectWithMessage('Factuur succesvol verwijderd.', 'success');
        } else {
            $this->redirectWithMessage('Fout bij het verwijderen van de factuur.', 'error');
        }
    }

    /**
     * Save invoice changes from edit form
     */
    public function saveInvoiceChanges(int $invoiceId, array $data): array
    {
        // Sanitize data
        $sanitizedData = [
            'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
            'customer_email' => sanitize_email($data['customer_email'] ?? ''),
            'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
            'customer_address' => sanitize_textarea_field($data['customer_address'] ?? ''),
            'customer_city' => sanitize_text_field($data['customer_city'] ?? ''),
            'customer_postal_code' => sanitize_text_field($data['customer_postal_code'] ?? ''),
            'service_description' => sanitize_textarea_field($data['service_description'] ?? ''),
            'service_amount' => floatval($data['service_amount'] ?? 0),
            'btw_rate' => floatval($data['btw_rate'] ?? 21),
            'invoice_date' => sanitize_text_field($data['invoice_date'] ?? ''),
            'due_date' => sanitize_text_field($data['due_date'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? ''),
            'payment_method' => sanitize_text_field($data['payment_method'] ?? ''),
            'payment_reference' => sanitize_text_field($data['payment_reference'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
        ];

        return $this->invoiceService->updateInvoice($invoiceId, $sanitizedData);
    }

    /**
     * Redirect with admin message
     */
    private function redirectWithMessage(string $message, string $type = 'info'): void
    {
        $url = add_query_arg([
            'message' => urlencode($message),
            'type' => $type
        ], admin_url('admin.php?page=evs-facturen'));
        
        wp_redirect($url);
        exit;
    }
}
