<?php

namespace EVS\Controllers;

use EVS\Services\QuoteService;
use EVS\Services\InvoiceService;

/**
 * Controller for handling admin pages and functionality
 */
class AdminController
{
    private QuoteService $quoteService;
    private InvoiceService $invoiceService;

    public function __construct(QuoteService $quoteService, InvoiceService $invoiceService)
    {
        $this->quoteService = $quoteService;
        $this->invoiceService = $invoiceService;
    }

    /**
     * Display quotes list page
     */
    public function displayQuotesPage(): void
    {
        // Handle messages
        $this->displayAdminMessages();

        echo '<div class="wrap">';
        echo '<h1>EVS Offertes</h1>';

        // Get quotes
        $quotes = $this->quoteService->searchQuotes([]);
        $stats = $this->quoteService->getStatistics();

        // Display statistics
        echo '<div class="evs-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        echo '<div class="evs-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0a3b2e;">Totaal Offertes</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a651;">' . $stats['total_quotes'] . '</p>';
        echo '</div>';

        echo '<div class="evs-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0a3b2e;">Totale Waarde</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a651;">€ ' . number_format($stats['total_value'], 2, ',', '.') . '</p>';
        echo '</div>';

        echo '<div class="evs-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0a3b2e;">Gemiddelde Waarde</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a651;">€ ' . number_format($stats['average_value'], 2, ',', '.') . '</p>';
        echo '</div>';

        echo '</div>';

        // Display quotes table
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Klant</th>';
        echo '<th>E-mail</th>';
        echo '<th>Vloertype</th>';
        echo '<th>Oppervlakte</th>';
        echo '<th>Totaalprijs</th>';
        echo '<th>Status</th>';
        echo '<th>Datum</th>';
        echo '<th>Acties</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($quotes as $quote) {
            echo '<tr>';
            echo '<td>' . $quote->id . '</td>';
            echo '<td>' . esc_html($quote->customer_name) . '</td>';
            echo '<td>' . esc_html($quote->customer_email) . '</td>';
            echo '<td>' . esc_html($this->getFloorTypeLabel($quote->floor_type)) . '</td>';
            echo '<td>' . $quote->area_m2 . ' m²</td>';
            echo '<td>' . $quote->getFormattedPrice() . '</td>';
            echo '<td>' . esc_html($quote->getStatusLabel()) . '</td>';
            echo '<td>' . date('d-m-Y', strtotime($quote->created_at)) . '</td>';
            echo '<td>';
            
            // Action buttons
            $editUrl = admin_url("admin.php?page=evs-edit-offer&offer_id={$quote->id}");
            echo '<a href="' . esc_url($editUrl) . '" class="button button-small">Bewerken</a> ';
            
            if ($quote->status === 'pending') {
                $sendUrl = wp_nonce_url(
                    admin_url("admin.php?page=evs-offertes&action=send_quote&offer_id={$quote->id}"),
                    'evs_admin_action'
                );
                echo '<a href="' . esc_url($sendUrl) . '" class="button button-small">Verzenden</a> ';
            }
            
            if ($quote->canCreateInvoice()) {
                $invoiceUrl = wp_nonce_url(
                    admin_url("admin.php?page=evs-offertes&action=create_invoice&offer_id={$quote->id}"),
                    'evs_admin_action'
                );
                echo '<a href="' . esc_url($invoiceUrl) . '" class="button button-small">Factuur</a> ';
            }
            
            $deleteUrl = wp_nonce_url(
                admin_url("admin.php?page=evs-offertes&action=delete&offer_id={$quote->id}"),
                'evs_admin_action'
            );
            echo '<a href="' . esc_url($deleteUrl) . '" class="button button-small" onclick="return confirm(\'Weet je zeker dat je deze offerte wilt verwijderen?\')">Verwijderen</a>';
            
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Display invoices list page
     */
    public function displayInvoicesPage(): void
    {
        // Handle messages
        $this->displayAdminMessages();

        echo '<div class="wrap">';
        echo '<h1>EVS Facturen</h1>';

        // Get invoices
        $invoices = $this->invoiceService->searchInvoices([]);
        $stats = $this->invoiceService->getStatistics();

        // Display statistics
        echo '<div class="evs-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
        
        echo '<div class="evs-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0a3b2e;">Totaal Facturen</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a651;">' . $stats['total_invoices'] . '</p>';
        echo '</div>';

        echo '<div class="evs-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0a3b2e;">Totale Omzet</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a651;">€ ' . number_format($stats['total_revenue'], 2, ',', '.') . '</p>';
        echo '</div>';

        echo '<div class="evs-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #0a3b2e;">Uitstaand</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: #ff6b35;">€ ' . number_format($stats['outstanding_amount'], 2, ',', '.') . '</p>';
        echo '</div>';

        echo '</div>';

        // Display invoices table
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Factuurnummer</th>';
        echo '<th>Klant</th>';
        echo '<th>Bedrag</th>';
        echo '<th>Status</th>';
        echo '<th>Factuurdatum</th>';
        echo '<th>Vervaldatum</th>';
        echo '<th>Acties</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($invoices as $invoice) {
            echo '<tr>';
            echo '<td>' . esc_html($invoice->invoice_number) . '</td>';
            echo '<td>' . esc_html($invoice->customer_name) . '</td>';
            echo '<td>' . $invoice->getFormattedAmount() . '</td>';
            echo '<td>';
            
            // Status with color coding
            $statusClass = '';
            switch ($invoice->status) {
                case 'paid':
                    $statusClass = 'style="color: #00a651; font-weight: bold;"';
                    break;
                case 'overdue':
                    $statusClass = 'style="color: #ff6b35; font-weight: bold;"';
                    break;
                case 'sent':
                    $statusClass = 'style="color: #0073aa; font-weight: bold;"';
                    break;
            }
            
            echo '<span ' . $statusClass . '>' . esc_html($invoice->getStatusLabel()) . '</span>';
            echo '</td>';
            echo '<td>' . date('d-m-Y', strtotime($invoice->invoice_date)) . '</td>';
            echo '<td>' . date('d-m-Y', strtotime($invoice->due_date)) . '</td>';
            echo '<td>';
            
            // Action buttons
            $editUrl = admin_url("admin.php?page=evs-edit-invoice&invoice_id={$invoice->id}");
            echo '<a href="' . esc_url($editUrl) . '" class="button button-small">Bewerken</a> ';
            
            if ($invoice->status !== 'paid') {
                $sendUrl = wp_nonce_url(
                    admin_url("admin.php?page=evs-facturen&action=send_invoice&invoice_id={$invoice->id}"),
                    'evs_admin_action'
                );
                echo '<a href="' . esc_url($sendUrl) . '" class="button button-small">Verzenden</a> ';
                
                $paidUrl = wp_nonce_url(
                    admin_url("admin.php?page=evs-facturen&action=mark_paid&invoice_id={$invoice->id}"),
                    'evs_admin_action'
                );
                echo '<a href="' . esc_url($paidUrl) . '" class="button button-small">Betaald</a> ';
            }
            
            $deleteUrl = wp_nonce_url(
                admin_url("admin.php?page=evs-facturen&action=delete&invoice_id={$invoice->id}"),
                'evs_admin_action'
            );
            echo '<a href="' . esc_url($deleteUrl) . '" class="button button-small" onclick="return confirm(\'Weet je zeker dat je deze factuur wilt verwijderen?\')">Verwijderen</a>';
            
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Display edit quote page
     */
    public function displayEditQuotePage(int $quoteId): void
    {
        $quote = $this->quoteService->getQuote($quoteId);
        
        if (!$quote) {
            echo '<div class="wrap"><h1>Fout</h1><p>Offerte niet gevonden.</p></div>';
            return;
        }

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('update_offer_' . $quoteId)) {
            $result = $this->quoteService->updateQuote($quoteId, $_POST);
            
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>Offerte succesvol bijgewerkt!</p></div>';
                $quote = $result['quote'];
            } else {
                echo '<div class="notice notice-error"><p>Fouten: ' . implode(', ', $result['errors']) . '</p></div>';
            }
        }

        // Include the edit template
        include EVS_PLUGIN_PATH . 'admin/views/edit-offer-page.php';
    }

    /**
     * Display edit invoice page
     */
    public function displayEditInvoicePage(int $invoiceId): void
    {
        $invoice = $this->invoiceService->getInvoice($invoiceId);
        
        if (!$invoice) {
            echo '<div class="wrap"><h1>Fout</h1><p>Factuur niet gevonden.</p></div>';
            return;
        }

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('update_invoice_' . $invoiceId)) {
            $result = $this->invoiceService->updateInvoice($invoiceId, $_POST);
            
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>Factuur succesvol bijgewerkt!</p></div>';
                $invoice = $result['invoice'];
            } else {
                echo '<div class="notice notice-error"><p>Fouten: ' . implode(', ', $result['errors']) . '</p></div>';
            }
        }

        // Include the edit template
        include EVS_PLUGIN_PATH . 'admin/views/edit-invoice-page.php';
    }

    /**
     * Display admin messages
     */
    private function displayAdminMessages(): void
    {
        if (isset($_GET['message']) && isset($_GET['type'])) {
            $message = sanitize_text_field($_GET['message']);
            $type = sanitize_text_field($_GET['type']);
            
            $class = 'notice-info';
            if ($type === 'success') {
                $class = 'notice-success';
            } elseif ($type === 'error') {
                $class = 'notice-error';
            }
            
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Get floor type label in Dutch
     */
    private function getFloorTypeLabel(string $floorType): string
    {
        $labels = [
            'cement_dekvloer' => 'Cement dekvloer',
            'tegelvloer' => 'Tegelvloer',
            'betonvloer' => 'Betonvloer',
            'fermacelvloer' => 'Fermacelvloer',
        ];

        return $labels[$floorType] ?? $floorType;
    }
}
