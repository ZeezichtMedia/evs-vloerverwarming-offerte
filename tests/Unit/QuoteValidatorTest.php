<?php

namespace EVS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use EVS\Validators\QuoteValidator;

/**
 * Unit tests for QuoteValidator
 */
class QuoteValidatorTest extends TestCase
{
    private QuoteValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new QuoteValidator();
    }

    public function testValidQuoteData(): void
    {
        $validData = [
            'customer_name' => 'Jan Jansen',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '0612345678',
            'customer_address' => 'Teststraat 123, 1234 AB Amsterdam',
            'floor_level' => 'begaande_grond',
            'floor_type' => 'cement_dekvloer',
            'area_m2' => 100,
            'heat_source' => 'cv_ketel',
            'installation_date' => '2024-12-01',
        ];

        $errors = $this->validator->validate($validData);
        $this->assertEmpty($errors);
    }

    public function testRequiredFieldsValidation(): void
    {
        $invalidData = [];

        $errors = $this->validator->validate($invalidData);

        $this->assertContains('Naam is verplicht', $errors);
        $this->assertContains('E-mailadres is verplicht', $errors);
        $this->assertContains('Telefoonnummer is verplicht', $errors);
        $this->assertContains('Adres is verplicht', $errors);
        $this->assertContains('Verdieping is verplicht', $errors);
        $this->assertContains('Vloertype is verplicht', $errors);
        $this->assertContains('Oppervlakte is verplicht', $errors);
        $this->assertContains('Warmtebron is verplicht', $errors);
    }

    public function testEmailValidation(): void
    {
        $data = [
            'customer_name' => 'Test',
            'customer_email' => 'invalid-email',
            'customer_phone' => '0612345678',
            'customer_address' => 'Test',
            'floor_level' => 'begaande_grond',
            'floor_type' => 'cement_dekvloer',
            'area_m2' => 100,
            'heat_source' => 'cv_ketel',
        ];

        $errors = $this->validator->validate($data);
        $this->assertContains('Ongeldig e-mailadres', $errors);
    }

    public function testAreaValidation(): void
    {
        // Test negative area
        $data = $this->getValidBaseData();
        $data['area_m2'] = -10;

        $errors = $this->validator->validate($data);
        $this->assertContains('Oppervlakte moet minimaal 1 m² zijn', $errors);

        // Test zero area
        $data['area_m2'] = 0;
        $errors = $this->validator->validate($data);
        $this->assertContains('Oppervlakte moet minimaal 1 m² zijn', $errors);

        // Test too large area
        $data['area_m2'] = 20000;
        $errors = $this->validator->validate($data);
        $this->assertContains('Oppervlakte mag maximaal 10.000 m² zijn', $errors);
    }

    public function testFloorLevelValidation(): void
    {
        $data = $this->getValidBaseData();
        $data['floor_level'] = 'invalid_level';

        $errors = $this->validator->validate($data);
        $this->assertContains('Ongeldige verdieping geselecteerd', $errors);
    }

    public function testFloorTypeValidation(): void
    {
        $data = $this->getValidBaseData();
        $data['floor_type'] = 'invalid_type';

        $errors = $this->validator->validate($data);
        $this->assertContains('Ongeldig vloertype geselecteerd', $errors);
    }

    public function testHeatSourceValidation(): void
    {
        $data = $this->getValidBaseData();
        $data['heat_source'] = 'invalid_source';

        $errors = $this->validator->validate($data);
        $this->assertContains('Ongeldige warmtebron geselecteerd', $errors);
    }

    public function testInstallationDateValidation(): void
    {
        $data = $this->getValidBaseData();
        
        // Test invalid date format
        $data['installation_date'] = 'invalid-date';
        $errors = $this->validator->validate($data);
        $this->assertContains('Ongeldige installatiedatum', $errors);

        // Test past date
        $data['installation_date'] = '2020-01-01';
        $errors = $this->validator->validate($data);
        $this->assertContains('Installatiedatum kan niet in het verleden liggen', $errors);

        // Test "weet_ik_niet" option
        $data['installation_date'] = 'weet_ik_niet';
        $errors = $this->validator->validate($data);
        $this->assertNotContains('Ongeldige installatiedatum', $errors);
    }

    public function testPhoneValidation(): void
    {
        $data = $this->getValidBaseData();
        
        // Test invalid phone
        $data['customer_phone'] = '123';
        $errors = $this->validator->validate($data);
        $this->assertContains('Ongeldig telefoonnummer', $errors);

        // Test valid Dutch phone numbers
        $validPhones = ['0612345678', '+31612345678', '0031612345678', '020-1234567'];
        
        foreach ($validPhones as $phone) {
            $data['customer_phone'] = $phone;
            $errors = $this->validator->validate($data);
            $this->assertNotContains('Ongeldig telefoonnummer', $errors, "Failed for phone: $phone");
        }
    }

    public function testMaxLengthValidation(): void
    {
        $data = $this->getValidBaseData();
        
        // Test customer name too long
        $data['customer_name'] = str_repeat('a', 101);
        $errors = $this->validator->validate($data);
        $this->assertContains('Naam mag maximaal 100 karakters bevatten', $errors);

        // Test address too long
        $data = $this->getValidBaseData();
        $data['customer_address'] = str_repeat('a', 256);
        $errors = $this->validator->validate($data);
        $this->assertContains('Adres mag maximaal 255 karakters bevatten', $errors);

        // Test notes too long
        $data = $this->getValidBaseData();
        $data['notes'] = str_repeat('a', 1001);
        $errors = $this->validator->validate($data);
        $this->assertContains('Opmerkingen mogen maximaal 1000 karakters bevatten', $errors);
    }

    private function getValidBaseData(): array
    {
        return [
            'customer_name' => 'Jan Jansen',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '0612345678',
            'customer_address' => 'Teststraat 123, 1234 AB Amsterdam',
            'floor_level' => 'begaande_grond',
            'floor_type' => 'cement_dekvloer',
            'area_m2' => 100,
            'heat_source' => 'cv_ketel',
        ];
    }
}
