<?php

namespace EVS\Models;

/**
 * Quote model for type safety and data validation
 */
class Quote
{
    // Database schema matching properties with default values
    public int $id = 0;
    public string $created_at = '';
    public string $customer_name = '';
    public string $customer_email = '';
    public string $customer_phone = '';
    public string $customer_address = '';
    public string $customer_postal = '';
    public string $customer_city = '';
    public string $customer_country = 'Nederland';
    public string $floor_level = '';
    public string $floor_level_other = '';
    public string $floor_type = '';
    public float $area = 0.0;
    public float $strekkende_meter = 0.0;
    public string $heat_source = '';
    public string $distributor = 'nee';
    public string $sealing = 'nee';
    public string $installation_date = '';
    public float $drilling_price = 0.0;
    public float $sealing_price = 0.0;
    public float $total_price = 0.0;
    public float $verdeler_price = 0.0;
    public string $notes = '';
    public string $status = 'new';

    /**
     * Create Quote from array data
     */
    public static function fromArray(array $data): self
    {
        $quote = new self();
        
        $quote->id = (int) ($data['id'] ?? 0);
        $quote->created_at = $data['created_at'] ?? current_time('mysql');
        $quote->customer_name = $data['customer_name'] ?? '';
        $quote->customer_email = $data['customer_email'] ?? '';
        $quote->customer_phone = $data['customer_phone'] ?? '';
        $quote->customer_address = $data['customer_address'] ?? '';
        $quote->customer_postal = $data['customer_postal'] ?? '';
        $quote->customer_city = $data['customer_city'] ?? '';
        $quote->customer_country = $data['customer_country'] ?? 'Nederland';
        $quote->floor_level = $data['floor_level'] ?? '';
        $quote->floor_level_other = $data['floor_level_other'] ?? '';
        $quote->floor_type = $data['floor_type'] ?? '';
        $quote->area = (float) ($data['area'] ?? 0);
        $quote->strekkende_meter = (float) ($data['strekkende_meter'] ?? 0);
        $quote->heat_source = $data['heat_source'] ?? '';
        $quote->distributor = $data['distributor'] ?? 'nee';
        $quote->sealing = $data['sealing'] ?? 'nee';
        $quote->installation_date = $data['installation_date'] ?? '';
        $quote->drilling_price = (float) ($data['drilling_price'] ?? 0);
        $quote->sealing_price = (float) ($data['sealing_price'] ?? 0);
        $quote->total_price = (float) ($data['total_price'] ?? 0);
        $quote->verdeler_price = (float) ($data['verdeler_price'] ?? 0);
        $quote->notes = $data['notes'] ?? '';
        $quote->status = $data['status'] ?? 'new';
        
        return $quote;
    }

    /**
     * Convert Quote to array for database operations
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'customer_address' => $this->customer_address,
            'customer_postal' => $this->customer_postal,
            'customer_city' => $this->customer_city,
            'customer_country' => $this->customer_country,
            'floor_level' => $this->floor_level,
            'floor_level_other' => $this->floor_level_other,
            'floor_type' => $this->floor_type,
            'area' => $this->area,
            'strekkende_meter' => $this->strekkende_meter,
            'heat_source' => $this->heat_source,
            'distributor' => $this->distributor,
            'sealing' => $this->sealing,
            'installation_date' => $this->installation_date,
            'drilling_price' => $this->drilling_price,
            'sealing_price' => $this->sealing_price,
            'total_price' => $this->total_price,
            'verdeler_price' => $this->verdeler_price,
            'notes' => $this->notes,
            'status' => $this->status,
        ];
    }

    /**
     * Get formatted total price
     */
    public function getFormattedPrice(): string
    {
        return '€ ' . number_format($this->total_price, 2, ',', '.');
    }

    /**
     * Get status label in Dutch
     */
    public function getStatusLabel(): string
    {
        $labels = [
            'pending' => 'In behandeling',
            'sent' => 'Verzonden',
            'accepted' => 'Geaccepteerd',
            'rejected' => 'Afgewezen',
            'completed' => 'Voltooid',
            'cancelled' => 'Geannuleerd',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Check if quote can be converted to invoice
     */
    public function canCreateInvoice(): bool
    {
        return in_array($this->status, ['accepted', 'completed']);
    }

    /**
     * Validate quote data
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

        if ($this->area <= 0) {
            $errors[] = 'Oppervlakte moet groter zijn dan 0';
        }

        if (empty($this->floor_type)) {
            $errors[] = 'Vloertype is verplicht';
        }

        if ($this->total_price < 0) {
            $errors[] = 'Totaalprijs kan niet negatief zijn';
        }

        return $errors;
    }
}
