<?php
/**
 * EVS Pricing Calculator
 * 
 * Handles all pricing calculations for floor heating installations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVS_Pricing_Calculator {

    // Pricing Constants
    private const PIPE_PER_M2 = 8.5;
    private const VERDELER_PRICE = 185.00;
    private const DICHTSMEREN_PRICE_PER_M2 = 12.75;
    private const SCHUREN_PRICE_PER_M2 = 7.00;

    // Drilling prices per strekkende meter
    private const TEGELVLOER_PRICE_PER_METER = 2.25;
    private const BETONVLOER_PRICE_PER_METER = 4.00;

    // Tiered pricing for cement/fermacel floors
    private const CEMENT_TIER_1_METERS = 250;
    private const CEMENT_TIER_1_PRICE = 1.77;
    private const CEMENT_TIER_2_METERS = 250;
    private const CEMENT_TIER_2_PRICE = 1.67;
    private const CEMENT_TIER_3_METERS = 250;
    private const CEMENT_TIER_3_PRICE = 1.57;
    private const CEMENT_TIER_4_PRICE = 1.47;
    
    /**
     * The single, authoritative method for calculating all quote prices.
     * This is used by the admin AJAX endpoint and other server-side calculations.
     * 
     * @param array $form_data User input data (e.g., from $_POST).
     * @return array Calculated pricing data.
     */
    public function calculate_price($form_data) {
        // --- SOLUTION: ADD VALIDATION ---
        $area_m2 = floatval($form_data['area_m2'] ?? 0);

        // Validate if the area is a positive number.
        if ($area_m2 <= 0) {
            return [
                'success' => false,
                'error'   => 'Oppervlakte moet groter zijn dan 0.',
            ];
        }
        
        // Validate for a realistic upper limit (e.g., 10,000 m²).
        if ($area_m2 > 10000) {
            return [
                'success' => false,
                'error'   => 'Oppervlakte is onrealistisch groot.',
            ];
        }

        $strekkende_meter = $area_m2 * self::PIPE_PER_M2;
        $type_vloer = $form_data['type_vloer'] ?? 'cement';

        // Calculate individual prices
        $drilling_price = $this->calculate_drilling_price($type_vloer, $strekkende_meter);
        $verdeler_price = !empty($form_data['verdeler_aansluiten']) ? self::VERDELER_PRICE : 0;
        $sealing_price = !empty($form_data['vloer_dichtsmeren']) ? ($area_m2 * self::DICHTSMEREN_PRICE_PER_M2) : 0;
        $sanding_price = !empty($form_data['vloer_schuren']) ? ($area_m2 * self::SCHUREN_PRICE_PER_M2) : 0;

        // Calculate total price
        $total_price = $drilling_price + $verdeler_price + $sealing_price + $sanding_price;

        // The return array remains consistent
        return [
            'success'          => true, // Important to include this now
            'drilling_price'   => $drilling_price,
            'verdeler_price'   => $verdeler_price,
            'sealing_price'    => $sealing_price,
            'sanding_price'    => $sanding_price,
            'total_price'      => $total_price,
            'area_m2'          => $area_m2,
            'strekkende_meter' => $strekkende_meter
        ];
    }
    
    /**
     * Calculate drilling price based on floor type and strekkende meter.
     * 
     * @param string $type_vloer The type of floor.
     * @param float $strekkende_meter The total length of pipes to be laid.
     * @return float The calculated drilling price.
     */
    private function calculate_drilling_price($type_vloer, $strekkende_meter) {
        switch ($type_vloer) {
            case 'cement_dekvloer':
            case 'cement':
            case 'fermacelvloer':
            case 'fermacel':
                return $this->calculate_cement_tiered_price($strekkende_meter);
            case 'tegelvloer':
            case 'tegel':
                return $strekkende_meter * self::TEGELVLOER_PRICE_PER_METER;
            case 'betonvloer':
            case 'beton':
                return $strekkende_meter * self::BETONVLOER_PRICE_PER_METER;
            default:
                return 0;
        }
    }
    
    /**
     * Calculate tiered pricing for cement and fermacel floors.
     * 
     * @param float $strekkende_meter The total length of pipes.
     * @return float The total tiered price.
     */
    private function calculate_cement_tiered_price($strekkende_meter) {
        $total_price = 0;
        $remaining_meters = $strekkende_meter;
        
        if ($remaining_meters > 0) {
            $tier1_meters = min($remaining_meters, self::CEMENT_TIER_1_METERS);
            $total_price += $tier1_meters * self::CEMENT_TIER_1_PRICE;
            $remaining_meters -= $tier1_meters;
        }
        
        if ($remaining_meters > 0) {
            $tier2_meters = min($remaining_meters, self::CEMENT_TIER_2_METERS);
            $total_price += $tier2_meters * self::CEMENT_TIER_2_PRICE;
            $remaining_meters -= $tier2_meters;
        }
        
        if ($remaining_meters > 0) {
            $tier3_meters = min($remaining_meters, self::CEMENT_TIER_3_METERS);
            $total_price += $tier3_meters * self::CEMENT_TIER_3_PRICE;
            $remaining_meters -= $tier3_meters;
        }
        
        if ($remaining_meters > 0) {
            $total_price += $remaining_meters * self::CEMENT_TIER_4_PRICE;
        }
        
        return $total_price;
    }
}
