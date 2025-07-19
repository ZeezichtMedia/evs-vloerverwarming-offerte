<?php

namespace EVS\Services;

/**
 * Service for handling pricing calculations
 */
class PricingService
{
    // Pricing constants
    private const DISTRIBUTOR_CONNECTION_PRICE = 185.00;
    private const SEALING_PRICE_PER_METER = 12.75;
    private const GRINDING_PRICE_PER_M2 = 7.00;
    private const M2_TO_STREKKENDE_METER_MULTIPLIER = 8.5;

    // Floor type pricing (tiered for cement/fermacell)
    private const FLOOR_PRICING = [
        'cement_dekvloer' => [
            ['min' => 0, 'max' => 250, 'price' => 1.77],
            ['min' => 250, 'max' => 500, 'price' => 1.67],
            ['min' => 500, 'max' => 750, 'price' => 1.57],
            ['min' => 750, 'max' => PHP_INT_MAX, 'price' => 1.47],
        ],
        'fermacelvloer' => [
            ['min' => 0, 'max' => 250, 'price' => 1.77],
            ['min' => 250, 'max' => 500, 'price' => 1.67],
            ['min' => 500, 'max' => 750, 'price' => 1.57],
            ['min' => 750, 'max' => PHP_INT_MAX, 'price' => 1.47],
        ],
        'tegelvloer' => 2.25,
        'betonvloer' => 4.00,
    ];

    /**
     * Calculate complete quote pricing
     */
    public function calculateQuotePricing(array $data): array
    {
        // Use new field names that match database schema
        $area_m2 = (float) ($data['area'] ?? 0);  // Fixed: use 'area' instead of 'area_m2'
        $floor_type = $data['floor_type'] ?? '';
        $connect_distributor = ($data['distributor'] ?? 'nee') === 'ja';  // Fixed: convert string to boolean
        $seal_floor = ($data['sealing'] ?? 'nee') === 'ja';  // Fixed: convert string to boolean
        $needs_grinding = (bool) ($data['needs_grinding'] ?? false);

        // Calculate strekkende meter
        $strekkende_meter = $area_m2 * self::M2_TO_STREKKENDE_METER_MULTIPLIER;

        // Calculate floor price (drilling price)
        $drilling_price = $this->calculateFloorPrice($floor_type, $strekkende_meter);

        // Calculate distributor price (verdeler price)
        $verdeler_price = $connect_distributor ? self::DISTRIBUTOR_CONNECTION_PRICE : 0;

        // Calculate sealing price
        $sealing_price = $seal_floor ? ($strekkende_meter * self::SEALING_PRICE_PER_METER) : 0;

        // Calculate grinding price
        $grinding_price = $needs_grinding ? ($area_m2 * self::GRINDING_PRICE_PER_M2) : 0;

        // Calculate total (drilling + verdeler + sealing + grinding)
        $total_price = $drilling_price + $verdeler_price + $sealing_price + $grinding_price;

        // Return with database field names
        return [
            'strekkende_meter' => round($strekkende_meter, 2),
            'drilling_price' => round($drilling_price, 2),  // Fixed: use drilling_price
            'verdeler_price' => round($verdeler_price, 2),  // Fixed: use verdeler_price
            'sealing_price' => round($sealing_price, 2),
            'total_price' => round($total_price, 2),
        ];
    }

    /**
     * Calculate floor price based on type and strekkende meter
     */
    private function calculateFloorPrice(string $floor_type, float $strekkende_meter): float
    {
        if (!isset(self::FLOOR_PRICING[$floor_type])) {
            return 0;
        }

        $pricing = self::FLOOR_PRICING[$floor_type];

        // Handle tiered pricing (cement/fermacell)
        if (is_array($pricing)) {
            foreach ($pricing as $tier) {
                if ($strekkende_meter >= $tier['min'] && $strekkende_meter < $tier['max']) {
                    return $strekkende_meter * $tier['price'];
                }
            }
            // Fallback to highest tier
            return $strekkende_meter * end($pricing)['price'];
        }

        // Handle flat rate pricing (tegel/beton)
        return $strekkende_meter * $pricing;
    }

    /**
     * Get price breakdown for display
     */
    public function getPriceBreakdown(array $data): array
    {
        $pricing = $this->calculateQuotePricing($data);
        $area_m2 = (float) ($data['area_m2'] ?? 0);
        $floor_type = $data['floor_type'] ?? '';

        $breakdown = [];

        // Floor work
        if ($pricing['floor_price'] > 0) {
            $rate = $this->getFloorRate($floor_type, $pricing['strekkende_meter']);
            $breakdown[] = [
                'description' => "Vloerverwarming {$floor_type} ({$pricing['strekkende_meter']}m à €{$rate})",
                'amount' => $pricing['floor_price']
            ];
        }

        // Distributor connection
        if ($pricing['distributor_price'] > 0) {
            $breakdown[] = [
                'description' => 'Verdeler aansluiten op warmtebron',
                'amount' => $pricing['distributor_price']
            ];
        }

        // Floor sealing
        if ($pricing['sealing_price'] > 0) {
            $breakdown[] = [
                'description' => "Vloer dichtsmeren ({$pricing['strekkende_meter']}m à €" . self::SEALING_PRICE_PER_METER . ")",
                'amount' => $pricing['sealing_price']
            ];
        }

        // Grinding
        if ($pricing['grinding_price'] > 0) {
            $breakdown[] = [
                'description' => "Vloer schuren ({$area_m2}m² à €" . self::GRINDING_PRICE_PER_M2 . ")",
                'amount' => $pricing['grinding_price']
            ];
        }

        return $breakdown;
    }

    /**
     * Get the rate used for floor pricing
     */
    private function getFloorRate(string $floor_type, float $strekkende_meter): float
    {
        if (!isset(self::FLOOR_PRICING[$floor_type])) {
            return 0;
        }

        $pricing = self::FLOOR_PRICING[$floor_type];

        // Handle tiered pricing
        if (is_array($pricing)) {
            foreach ($pricing as $tier) {
                if ($strekkende_meter >= $tier['min'] && $strekkende_meter < $tier['max']) {
                    return $tier['price'];
                }
            }
            return end($pricing)['price'];
        }

        // Handle flat rate
        return $pricing;
    }

    /**
     * Calculate invoice pricing from quote
     */
    public function calculateInvoicePricing(array $quoteData, float $btwRate = 21.0): array
    {
        $serviceAmount = (float) ($quoteData['total_price'] ?? 0);
        $btwAmount = $serviceAmount * ($btwRate / 100);
        $totalAmount = $serviceAmount + $btwAmount;

        return [
            'service_amount' => round($serviceAmount, 2),
            'btw_rate' => $btwRate,
            'btw_amount' => round($btwAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * Get pricing constants for frontend
     */
    public function getPricingConstants(): array
    {
        return [
            'distributor_price' => self::DISTRIBUTOR_CONNECTION_PRICE,
            'sealing_price_per_meter' => self::SEALING_PRICE_PER_METER,
            'grinding_price_per_m2' => self::GRINDING_PRICE_PER_M2,
            'multiplier' => self::M2_TO_STREKKENDE_METER_MULTIPLIER,
            'floor_pricing' => self::FLOOR_PRICING,
        ];
    }
}
