<?php

namespace EVS\Models;

class Offer
{
    // Properties to match Supabase table columns
    public $id;
    public $customer_name;
    public $customer_email;
    public $floor_level;
    public $floor_level_other; // For "Anders, namelijk..."
    public $floor_type;
    public $area;
    public $heat_source;
    public $distributor;
    public $sealing;
    public $floor_clean; // New property for sealing condition ('yes' or 'no')
    public $installation_date;
    public $drilling_price;
    public $sealing_price;
    public $distributor_price;
    public $status;
    public $created_at;

    public function __construct(array $data = [])
    {
        // Assign all properties from the data array
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Calculates the drilling and sealing prices based on the offer's properties.
     */
    public function calculatePrices(): void
    {
        // SOLUTION: Validate the input before calculating
        if (!isset($this->area) || !is_numeric($this->area) || $this->area <= 0) {
            throw new \InvalidArgumentException('Oppervlakte (area) is ongeldig of ontbreekt.');
        }
        if (empty($this->floor_type)) {
            throw new \InvalidArgumentException('Vloertype (floor_type) ontbreekt.');
        }

        // Effective area for drilling is the provided m2 * 8.5
        $effectiveArea = (float)$this->area * 8.5;

        // 1. Calculate Drilling Price (Infrezen)
        $drillingTotal = 0;
        switch ($this->floor_type) {
            case 'cement':
            case 'fermacel':
                // Tiered pricing for cement and fermacel floors
                $tempArea = $effectiveArea;
                if ($tempArea > 750) {
                    $drillingTotal += ($tempArea - 750) * 1.47;
                    $tempArea = 750;
                }
                if ($tempArea > 500) {
                    $drillingTotal += ($tempArea - 500) * 1.57;
                    $tempArea = 500;
                }
                if ($tempArea > 250) {
                    $drillingTotal += ($tempArea - 250) * 1.67;
                    $tempArea = 250;
                }
                $drillingTotal += $tempArea * 1.77;
                break;
            case 'tile':
                $drillingTotal = $effectiveArea * 2.25;
                break;
            case 'concrete':
                $drillingTotal = $effectiveArea * 4.00;
                break;
        }
        $this->drilling_price = round($drillingTotal, 2);

        // SOLUTION: Calculate the distributor price separately
        $this->distributor_price = 0;
        if ($this->distributor === 'yes') {
            $this->distributor_price = 185;
        }

        // 2. Calculate Sealing Price (Dichtsmeren)
        $this->sealing_price = 0; // Default to 0
        if ($this->sealing === 'yes') {
            // Base price for sealing is per m2
            $this->sealing_price = (float)$this->area * 12.75;

            // Add extra sanding cost if floor is not clean
            if ($this->floor_clean === 'no') {
                $this->sealing_price += (float)$this->area * 7.00;
            }
        }
    }

    /**
     * Returns the object's properties as an array for Supabase.
     */
    public function toArray(): array
    {
        $vars = get_object_vars($this);
        // Ensure prices are formatted correctly for database
        $vars['drilling_price']    = round((float)$this->drilling_price, 2);
        $vars['sealing_price']     = round((float)$this->sealing_price, 2);
        $vars['distributor_price'] = round((float)$this->distributor_price, 2); // Toevoegen voor consistentie
        return $vars;
    }
}
