<?php

namespace EVS\Services;

use EVS\Contracts\ServiceInterface;
use EVS\Repositories\InvoiceRepository;
use EVS\Repositories\QuoteRepository;
use EVS\Models\Invoice;
use EVS\Models\Quote;
use EVS\Validators\InvoiceValidator;

/**
 * Service for handling invoice business logic
 */
class InvoiceService implements ServiceInterface
{
    private InvoiceRepository $invoiceRepository;
    private QuoteRepository $quoteRepository;
    private InvoiceValidator $validator;
    private PricingService $pricingService;

    public function __construct(
        InvoiceRepository $invoiceRepository,
        QuoteRepository $quoteRepository,
        InvoiceValidator $validator,
        PricingService $pricingService
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->quoteRepository = $quoteRepository;
        $this->validator = $validator;
        $this->pricingService = $pricingService;
    }

    /**
     * Validate invoice data
     */
    public function validate(array $data): array
    {
        return $this->validator->validate($data);
    }

    /**
     * Process invoice creation from quote
     */
    public function process(array $data): array
    {
        // This method is for creating invoices from quotes
        $quoteId = (int) ($data['quote_id'] ?? 0);
        
        if (!$quoteId) {
            return ['success' => false, 'errors' => ['Quote ID is verplicht']];
        }

        return $this->createFromQuote($quoteId);
    }

    /**
     * Create invoice from quote
     */
    public function createFromQuote(int $quoteId): array
    {
        try {
            // Check if invoice already exists for this quote
            $existingInvoice = $this->invoiceRepository->findByQuoteId($quoteId);
            if ($existingInvoice) {
                return [
                    'success' => false,
                    'errors' => ['Er bestaat al een factuur voor deze offerte.']
                ];
            }

            // Get quote data
            $quoteData = $this->quoteRepository->find($quoteId);
            if (!$quoteData) {
                return [
                    'success' => false,
                    'errors' => ['Offerte niet gevonden.']
                ];
            }

            $quote = Quote::fromArray($quoteData);

            // Check if quote can be converted to invoice
            if (!$quote->canCreateInvoice()) {
                return [
                    'success' => false,
                    'errors' => ['Deze offerte kan nog niet worden omgezet naar een factuur.']
                ];
            }

            // Generate invoice number
            $invoiceNumber = $this->invoiceRepository->generateInvoiceNumber();

            // Calculate pricing with BTW
            $pricing = $this->pricingService->calculateInvoicePricing($quoteData);

            // Create invoice data
            $invoiceData = [
                'quote_id' => $quoteId,
                'invoice_number' => $invoiceNumber,
                'customer_name' => $quote->customer_name,
                'customer_email' => $quote->customer_email,
                'customer_phone' => $quote->customer_phone,
                'customer_address' => $quote->customer_address,
                'customer_city' => '', // Extract from address if needed
                'customer_postal_code' => '', // Extract from address if needed
                'service_description' => $this->generateServiceDescription($quote),
                'service_amount' => $pricing['service_amount'],
                'btw_rate' => $pricing['btw_rate'],
                'btw_amount' => $pricing['btw_amount'],
                'total_amount' => $pricing['total_amount'],
                'invoice_date' => current_time('mysql'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'concept',
                'payment_method' => '',
                'payment_reference' => '',
                'paid_date' => '',
                'notes' => '',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];

            // Validate invoice data
            $invoice = Invoice::fromArray($invoiceData);
            $errors = $invoice->validate();
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            // Save invoice
            $invoiceId = $this->invoiceRepository->create($invoiceData);

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice' => $invoice
            ];

        } catch (\Exception $e) {
            error_log('Invoice creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Er is een fout opgetreden bij het aanmaken van de factuur.']
            ];
        }
    }

    /**
     * Get invoice by ID
     */
    public function getInvoice(int $id): ?Invoice
    {
        $data = $this->invoiceRepository->find($id);
        return $data ? Invoice::fromArray($data) : null;
    }

    /**
     * Update invoice
     */
    public function updateInvoice(int $id, array $data): array
    {
        try {
            // Get current invoice
            $currentInvoice = $this->getInvoice($id);
            if (!$currentInvoice) {
                return ['success' => false, 'errors' => ['Factuur niet gevonden.']];
            }

            // Merge with current data
            $updatedData = array_merge($currentInvoice->toArray(), $data);
            $updatedData['updated_at'] = current_time('mysql');

            // Recalculate BTW if service amount changed
            if (isset($data['service_amount']) || isset($data['btw_rate'])) {
                $invoice = Invoice::fromArray($updatedData);
                $invoice->calculateBtw();
                $updatedData = $invoice->toArray();
            }

            // Validate
            $invoice = Invoice::fromArray($updatedData);
            $errors = $invoice->validate();
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            // Update in database
            $success = $this->invoiceRepository->update($id, $updatedData);
            
            if ($success) {
                return ['success' => true, 'invoice' => $invoice];
            } else {
                return ['success' => false, 'errors' => ['Factuur kon niet worden bijgewerkt.']];
            }

        } catch (\Exception $e) {
            error_log('Invoice update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Er is een fout opgetreden bij het bijwerken van de factuur.']
            ];
        }
    }

    /**
     * Delete invoice
     */
    public function deleteInvoice(int $id): bool
    {
        try {
            return $this->invoiceRepository->delete($id);
        } catch (\Exception $e) {
            error_log('Invoice deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update invoice status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $validStatuses = ['concept', 'sent', 'paid', 'overdue', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        try {
            $updateData = [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ];

            // Set paid date if marking as paid
            if ($status === 'paid') {
                $updateData['paid_date'] = current_time('mysql');
            }

            return $this->invoiceRepository->update($id, $updateData);

        } catch (\Exception $e) {
            error_log('Invoice status update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get invoices by status
     */
    public function getInvoicesByStatus(string $status): array
    {
        $data = $this->invoiceRepository->findByStatus($status);
        return array_map(fn($item) => Invoice::fromArray($item), $data);
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(): array
    {
        $data = $this->invoiceRepository->findOverdue();
        return array_map(fn($item) => Invoice::fromArray($item), $data);
    }

    /**
     * Search invoices
     */
    public function searchInvoices(array $criteria): array
    {
        $data = $this->invoiceRepository->search($criteria);
        return array_map(fn($item) => Invoice::fromArray($item), $data);
    }

    /**
     * Get invoice statistics
     */
    public function getStatistics(): array
    {
        return $this->invoiceRepository->getStatistics();
    }

    /**
     * Get monthly revenue data
     */
    public function getMonthlyRevenue(int $year): array
    {
        return $this->invoiceRepository->getMonthlyRevenue($year);
    }

    /**
     * Generate service description from quote
     */
    private function generateServiceDescription(Quote $quote): string
    {
        $description = "Vloerverwarming installatie:\n";
        $description .= "- Vloertype: {$quote->floor_type}\n";
        $description .= "- Oppervlakte: {$quote->area_m2} m²\n";
        $description .= "- Strekkende meter: {$quote->strekkende_meter} m\n";
        
        if ($quote->connect_distributor) {
            $description .= "- Verdeler aansluiten op warmtebron\n";
        }
        
        if ($quote->seal_floor) {
            $description .= "- Vloer dichtsmeren\n";
        }
        
        if ($quote->needs_grinding) {
            $description .= "- Vloer schuren\n";
        }

        return $description;
    }

    /**
     * Prepare invoice for email sending
     */
    public function prepareForEmail(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'service_description' => $invoice->service_description,
            'service_amount' => number_format($invoice->service_amount, 2, ',', '.'),
            'btw_amount' => number_format($invoice->btw_amount, 2, ',', '.'),
            'total_amount' => $invoice->getFormattedAmount(),
            'invoice_date' => date('d-m-Y', strtotime($invoice->invoice_date)),
            'due_date' => date('d-m-Y', strtotime($invoice->due_date)),
            'status' => $invoice->getStatusLabel(),
        ];
    }
}
