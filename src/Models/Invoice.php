<?php

namespace EVS\Models;

/**
 * Invoice model for type safety and data validation
 */
class Invoice
{
    public int $id;
    public int $quote_id;
    public string $invoice_number;
    public string $customer_name;
    public string $customer_email;
    public string $customer_phone;
    public string $customer_address;
    public string $customer_city;
    public string $customer_postal_code;
    public string $service_description;
    public float $service_amount;
    public float $btw_rate;
    public float $btw_amount;
    public float $total_amount;
    public string $invoice_date;
    public string $due_date;
    public string $status;
    public string $payment_method;
    public string $payment_reference;
    public string $paid_date;
    public string $notes;
    public string $created_at;
    public string $updated_at;

    /**
     * Create Invoice from array data
     */
    public static function fromArray(array $data): self
    {
        $invoice = new self();
        
        $invoice->id = (int) ($data['id'] ?? 0);
        $invoice->quote_id = (int) ($data['quote_id'] ?? 0);
        $invoice->invoice_number = $data['invoice_number'] ?? '';
        $invoice->customer_name = $data['customer_name'] ?? '';
        $invoice->customer_email = $data['customer_email'] ?? '';
        $invoice->customer_phone = $data['customer_phone'] ?? '';
        $invoice->customer_address = $data['customer_address'] ?? '';
        $invoice->customer_city = $data['customer_city'] ?? '';
        $invoice->customer_postal_code = $data['customer_postal_code'] ?? '';
        $invoice->service_description = $data['service_description'] ?? '';
        $invoice->service_amount = (float) ($data['service_amount'] ?? 0);
        $invoice->btw_rate = (float) ($data['btw_rate'] ?? 21.0);
        $invoice->btw_amount = (float) ($data['btw_amount'] ?? 0);
        $invoice->total_amount = (float) ($data['total_amount'] ?? 0);
        $invoice->invoice_date = $data['invoice_date'] ?? '';
        $invoice->due_date = $data['due_date'] ?? '';
        $invoice->status = $data['status'] ?? 'concept';
        $invoice->payment_method = $data['payment_method'] ?? '';
        $invoice->payment_reference = $data['payment_reference'] ?? '';
        $invoice->paid_date = $data['paid_date'] ?? '';
        $invoice->notes = $data['notes'] ?? '';
        $invoice->created_at = $data['created_at'] ?? '';
        $invoice->updated_at = $data['updated_at'] ?? '';
        
        return $invoice;
    }

    /**
     * Convert Invoice to array for database operations
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'quote_id' => $this->quote_id,
            'invoice_number' => $this->invoice_number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            'customer_city' => $this->customer_city,
            'customer_postal_code' => $this->customer_postal_code,
            'service_description' => $this->service_description,
            'service_amount' => $this->service_amount,
            'btw_rate' => $this->btw_rate,
            'btw_amount' => $this->btw_amount,
            'total_amount' => $this->total_amount,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'paid_date' => $this->paid_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedAmount(): string
    {
        return '€ ' . number_format($this->total_amount, 2, ',', '.');
    }

    /**
     * Get status label in Dutch
     */
    public function getStatusLabel(): string
    {
        $labels = [
            'concept' => 'Concept',
            'sent' => 'Verzonden',
            'paid' => 'Betaald',
            'overdue' => 'Vervallen',
            'cancelled' => 'Geannuleerd',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'paid' || empty($this->due_date)) {
            return false;
        }

        return strtotime($this->due_date) < time();
    }

    /**
     * Get days until due date (negative if overdue)
     */
    public function getDaysUntilDue(): int
    {
        if (empty($this->due_date)) {
            return 0;
        }

        $due_timestamp = strtotime($this->due_date);
        $today_timestamp = strtotime('today');
        
        return (int) (($due_timestamp - $today_timestamp) / (24 * 60 * 60));
    }

    /**
     * Calculate BTW amount based on service amount and rate
     */
    public function calculateBtw(): void
    {
        $this->btw_amount = $this->service_amount * ($this->btw_rate / 100);
        $this->total_amount = $this->service_amount + $this->btw_amount;
    }

    /**
     * Validate invoice data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->customer_name)) {
            $errors[] = 'Klantnaam is verplicht';
        }

        if (empty($this->customer_email) || !is_email($this->customer_email)) {
            $errors[] = 'Geldig e-mailadres is verplicht';
        }

        if (empty($this->invoice_number)) {
            $errors[] = 'Factuurnummer is verplicht';
        }

        if ($this->service_amount <= 0) {
            $errors[] = 'Servicebedrag moet groter zijn dan 0';
        }

        if ($this->btw_rate < 0 || $this->btw_rate > 100) {
            $errors[] = 'BTW-percentage moet tussen 0 en 100 liggen';
        }

        if (empty($this->invoice_date)) {
            $errors[] = 'Factuurdatum is verplicht';
        }

        if (empty($this->due_date)) {
            $errors[] = 'Vervaldatum is verplicht';
        }

        return $errors;
    }
}
