<?php

namespace EVS\Services;

use EVS\Contracts\ServiceInterface;
use EVS\Repositories\QuoteRepository;
use EVS\Models\Quote;
use EVS\Validators\QuoteValidator;

/**
 * Service for handling quote business logic
 */
class QuoteService implements ServiceInterface
{
    private QuoteRepository $quoteRepository;
    private QuoteValidator $validator;
    private PricingService $pricingService;

    public function __construct(
        QuoteRepository $quoteRepository,
        QuoteValidator $validator,
        PricingService $pricingService
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->validator = $validator;
        $this->pricingService = $pricingService;
    }

    /**
     * Validate quote data
     */
    public function validate(array $data): array
    {
        return $this->validator->validate($data);
    }

    /**
     * Process quote creation
     */
    public function process(array $data): array
    {
        // Validate input
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            // Calculate pricing
            $pricing = $this->pricingService->calculateQuotePricing($data);
            
            // Merge pricing with form data
            $quoteData = array_merge($data, $pricing);
            
            // Create quote model
            $quote = Quote::fromArray($quoteData);
            
            // Save to database
            $quoteId = $this->quoteRepository->create($quote->toArray());
            
            return [
                'success' => true,
                'quote_id' => $quoteId,
                'quote' => $quote
            ];
            
        } catch (\Exception $e) {
            error_log('Quote creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Er is een fout opgetreden bij het opslaan van de offerte.']
            ];
        }
    }

    /**
     * Get quote by ID
     */
    public function getQuote(int $id): ?Quote
    {
        $data = $this->quoteRepository->find($id);
        return $data ? Quote::fromArray($data) : null;
    }

    /**
     * Update quote
     */
    public function updateQuote(int $id, array $data): array
    {
        // Validate input
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            // Recalculate pricing if needed
            if ($this->needsPricingRecalculation($data)) {
                $pricing = $this->pricingService->calculateQuotePricing($data);
                $data = array_merge($data, $pricing);
            }

            // Update in database
            $success = $this->quoteRepository->update($id, $data);
            
            if ($success) {
                $quote = $this->getQuote($id);
                return ['success' => true, 'quote' => $quote];
            } else {
                return ['success' => false, 'errors' => ['Offerte kon niet worden bijgewerkt.']];
            }
            
        } catch (\Exception $e) {
            error_log('Quote update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Er is een fout opgetreden bij het bijwerken van de offerte.']
            ];
        }
    }

    /**
     * Delete quote
     */
    public function deleteQuote(int $id): bool
    {
        try {
            return $this->quoteRepository->delete($id);
        } catch (\Exception $e) {
            error_log('Quote deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get quotes by status
     */
    public function getQuotesByStatus(string $status): array
    {
        $data = $this->quoteRepository->findByStatus($status);
        return array_map(fn($item) => Quote::fromArray($item), $data);
    }

    /**
     * Search quotes
     */
    public function searchQuotes(array $criteria): array
    {
        $data = $this->quoteRepository->search($criteria);
        return array_map(fn($item) => Quote::fromArray($item), $data);
    }

    /**
     * Get quote statistics
     */
    public function getStatistics(): array
    {
        return $this->quoteRepository->getStatistics();
    }

    /**
     * Update quote status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $validStatuses = ['pending', 'sent', 'accepted', 'rejected', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        try {
            return $this->quoteRepository->update($id, [
                'status' => $status,
                'updated_at' => current_time('mysql')
            ]);
        } catch (\Exception $e) {
            error_log('Quote status update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if pricing needs recalculation
     */
    private function needsPricingRecalculation(array $data): bool
    {
        $pricingFields = [
            'area_m2', 'floor_type', 'connect_distributor', 
            'seal_floor', 'needs_grinding'
        ];

        foreach ($pricingFields as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare quote for email sending
     */
    public function prepareForEmail(Quote $quote): array
    {
        return [
            'quote_id' => $quote->id,
            'customer_name' => $quote->customer_name,
            'customer_email' => $quote->customer_email,
            'floor_type' => $quote->floor_type,
            'area_m2' => $quote->area_m2,
            'strekkende_meter' => $quote->strekkende_meter,
            'total_price' => $quote->getFormattedPrice(),
            'status' => $quote->getStatusLabel(),
            'created_at' => date('d-m-Y', strtotime($quote->created_at))
        ];
    }
}
