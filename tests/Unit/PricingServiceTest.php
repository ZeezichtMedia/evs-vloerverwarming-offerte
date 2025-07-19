<?php

namespace EVS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use EVS\Services\PricingService;

/**
 * Unit tests for PricingService
 */
class PricingServiceTest extends TestCase
{
    private PricingService $pricingService;

    protected function setUp(): void
    {
        $this->pricingService = new PricingService();
    }

    public function testCalculateQuotePricingWithCementFloor(): void
    {
        $data = [
            'area_m2' => 100,
            'floor_type' => 'cement_dekvloer',
            'connect_distributor' => true,
            'seal_floor' => true,
            'needs_grinding' => false,
        ];

        $result = $this->pricingService->calculateQuotePricing($data);

        // Expected calculations:
        // strekkende_meter = 100 * 8.5 = 850
        // floor_price = 850 * 1.57 (tier 500-750) = 1334.5
        // distributor_price = 185
        // sealing_price = 850 * 12.75 = 10837.5
        // grinding_price = 0
        // total = 1334.5 + 185 + 10837.5 = 12357

        $this->assertEquals(850, $result['strekkende_meter']);
        $this->assertEquals(1334.5, $result['floor_price']);
        $this->assertEquals(185, $result['distributor_price']);
        $this->assertEquals(10837.5, $result['sealing_price']);
        $this->assertEquals(0, $result['grinding_price']);
        $this->assertEquals(12357, $result['total_price']);
    }

    public function testCalculateQuotePricingWithTileFloor(): void
    {
        $data = [
            'area_m2' => 50,
            'floor_type' => 'tegelvloer',
            'connect_distributor' => false,
            'seal_floor' => false,
            'needs_grinding' => true,
        ];

        $result = $this->pricingService->calculateQuotePricing($data);

        // Expected calculations:
        // strekkende_meter = 50 * 8.5 = 425
        // floor_price = 425 * 2.25 = 956.25
        // distributor_price = 0
        // sealing_price = 0
        // grinding_price = 50 * 7 = 350
        // total = 956.25 + 350 = 1306.25

        $this->assertEquals(425, $result['strekkende_meter']);
        $this->assertEquals(956.25, $result['floor_price']);
        $this->assertEquals(0, $result['distributor_price']);
        $this->assertEquals(0, $result['sealing_price']);
        $this->assertEquals(350, $result['grinding_price']);
        $this->assertEquals(1306.25, $result['total_price']);
    }

    public function testTieredPricingForCementFloor(): void
    {
        // Test different tiers
        $testCases = [
            ['area' => 20, 'expected_rate' => 1.77], // 170m = tier 1
            ['area' => 35, 'expected_rate' => 1.67], // 297.5m = tier 2
            ['area' => 70, 'expected_rate' => 1.57], // 595m = tier 3
            ['area' => 100, 'expected_rate' => 1.47], // 850m = tier 4
        ];

        foreach ($testCases as $case) {
            $data = [
                'area_m2' => $case['area'],
                'floor_type' => 'cement_dekvloer',
                'connect_distributor' => false,
                'seal_floor' => false,
                'needs_grinding' => false,
            ];

            $result = $this->pricingService->calculateQuotePricing($data);
            $strekkende_meter = $case['area'] * 8.5;
            $expected_price = $strekkende_meter * $case['expected_rate'];

            $this->assertEquals($expected_price, $result['floor_price'], 
                "Failed for area {$case['area']}m² (rate {$case['expected_rate']})");
        }
    }

    public function testGetPriceBreakdown(): void
    {
        $data = [
            'area_m2' => 100,
            'floor_type' => 'cement_dekvloer',
            'connect_distributor' => true,
            'seal_floor' => true,
            'needs_grinding' => true,
        ];

        $breakdown = $this->pricingService->getPriceBreakdown($data);

        $this->assertCount(4, $breakdown);
        $this->assertStringContains('Vloerverwarming cement_dekvloer', $breakdown[0]['description']);
        $this->assertEquals('Verdeler aansluiten op warmtebron', $breakdown[1]['description']);
        $this->assertStringContains('Vloer dichtsmeren', $breakdown[2]['description']);
        $this->assertStringContains('Vloer schuren', $breakdown[3]['description']);
    }

    public function testCalculateInvoicePricing(): void
    {
        $quoteData = ['total_price' => 1000];
        $result = $this->pricingService->calculateInvoicePricing($quoteData, 21.0);

        $this->assertEquals(1000, $result['service_amount']);
        $this->assertEquals(21.0, $result['btw_rate']);
        $this->assertEquals(210, $result['btw_amount']);
        $this->assertEquals(1210, $result['total_amount']);
    }

    public function testGetPricingConstants(): void
    {
        $constants = $this->pricingService->getPricingConstants();

        $this->assertArrayHasKey('distributor_price', $constants);
        $this->assertArrayHasKey('sealing_price_per_meter', $constants);
        $this->assertArrayHasKey('grinding_price_per_m2', $constants);
        $this->assertArrayHasKey('multiplier', $constants);
        $this->assertArrayHasKey('floor_pricing', $constants);

        $this->assertEquals(185, $constants['distributor_price']);
        $this->assertEquals(12.75, $constants['sealing_price_per_meter']);
        $this->assertEquals(7, $constants['grinding_price_per_m2']);
        $this->assertEquals(8.5, $constants['multiplier']);
    }
}
