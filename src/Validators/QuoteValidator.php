<?php

namespace EVS\Validators;

/**
 * Validator for quote data
 */
class QuoteValidator extends BaseValidator
{
    /**
     * Validate quote data
     */
    public function validate(array $data): array
    {
        $this->resetErrors();

        // Customer information
        $this->required('customer_name', $data['customer_name'] ?? '', 'Naam is verplicht');
        $this->maxLength('customer_name', $data['customer_name'] ?? '', 100, 'Naam mag maximaal 100 karakters bevatten');

        $this->required('customer_email', $data['customer_email'] ?? '', 'E-mailadres is verplicht');
        $this->email('customer_email', $data['customer_email'] ?? '');

        // Phone is now required
        $this->required('customer_phone', $data['customer_phone'] ?? '', 'Telefoonnummer is verplicht');
        if (!empty($data['customer_phone'])) {
            $this->phone('customer_phone', $data['customer_phone'], 'Ongeldig telefoonnummer');
        }

        $this->required('customer_address', $data['customer_address'] ?? '', 'Adres is verplicht');
        $this->maxLength('customer_address', $data['customer_address'] ?? '', 255, 'Adres mag maximaal 255 karakters bevatten');

        // Project details
        $this->required('floor_level', $data['floor_level'] ?? '', 'Verdieping is verplicht');
        $this->in('floor_level', $data['floor_level'] ?? '', [
            'begaande_grond', 'eerste_verdieping', 'zolder', 'anders'
        ], 'Ongeldige verdieping geselecteerd');

        $this->required('floor_type', $data['floor_type'] ?? '', 'Vloertype is verplicht');
        $this->in('floor_type', $data['floor_type'] ?? '', [
            'cement_dekvloer', 'tegelvloer', 'betonvloer', 'fermacelvloer'
        ], 'Ongeldig vloertype geselecteerd');

        $this->required('area', $data['area'] ?? '', 'Oppervlakte is verplicht');
        $this->numeric('area', $data['area'] ?? '');
        $this->min('area', $data['area'] ?? '', 1, 'Oppervlakte moet minimaal 1 m² zijn');
        $this->max('area', $data['area'] ?? '', 10000, 'Oppervlakte mag maximaal 10.000 m² zijn');

        $this->required('heat_source', $data['heat_source'] ?? '', 'Warmtebron is verplicht');
        $this->in('heat_source', $data['heat_source'] ?? '', [
            'cv_ketel', 'hybride_warmtepomp', 'volledige_warmtepomp', 'stadsverwarming', 'toekomstige_warmtepomp'
        ], 'Ongeldige warmtebron geselecteerd');

        // Optional fields validation
        if (isset($data['installation_date']) && !empty($data['installation_date'])) {
            if ($data['installation_date'] !== 'weet_ik_niet') {
                $this->date('installation_date', $data['installation_date'], 'Y-m-d', 'Ongeldige installatiedatum');
                
                // Check if date is not in the past
                if (strtotime($data['installation_date']) < strtotime('today')) {
                    $this->addError('installation_date', 'Installatiedatum kan niet in het verleden liggen');
                }
            }
        }

        // Notes validation
        if (isset($data['notes'])) {
            $this->maxLength('notes', $data['notes'], 1000, 'Opmerkingen mogen maximaal 1000 karakters bevatten');
        }

        // Pricing validation (if provided)
        if (isset($data['total_price'])) {
            $this->numeric('total_price', $data['total_price']);
            $this->min('total_price', $data['total_price'], 0, 'Totaalprijs kan niet negatief zijn');
        }

        return $this->getFlatErrors();
    }

    /**
     * Validate quote update data (less strict)
     */
    public function validateUpdate(array $data): array
    {
        $this->resetErrors();

        // Only validate fields that are present
        if (isset($data['customer_name'])) {
            $this->required('customer_name', $data['customer_name'], 'Naam is verplicht');
            $this->maxLength('customer_name', $data['customer_name'], 100);
        }

        if (isset($data['customer_email'])) {
            $this->required('customer_email', $data['customer_email'], 'E-mailadres is verplicht');
            $this->email('customer_email', $data['customer_email']);
        }

        if (isset($data['customer_phone'])) {
            $this->required('customer_phone', $data['customer_phone'], 'Telefoonnummer is verplicht');
            $this->phone('customer_phone', $data['customer_phone']);
        }

        if (isset($data['customer_address'])) {
            $this->maxLength('customer_address', $data['customer_address'], 255);
        }

        if (isset($data['floor_level'])) {
            $this->in('floor_level', $data['floor_level'], [
                'begaande_grond', 'eerste_verdieping', 'zolder', 'anders'
            ]);
        }

        if (isset($data['floor_type'])) {
            $this->in('floor_type', $data['floor_type'], [
                'cement_dekvloer', 'tegelvloer', 'betonvloer', 'fermacelvloer'
            ]);
        }

        if (isset($data['area_m2'])) {
            $this->numeric('area_m2', $data['area_m2']);
            $this->min('area_m2', $data['area_m2'], 1);
            $this->max('area_m2', $data['area_m2'], 10000);
        }

        if (isset($data['heat_source'])) {
            $this->in('heat_source', $data['heat_source'], [
                'cv_ketel', 'hybride_warmtepomp', 'volledige_warmtepomp', 'stadsverwarming', 'toekomstige_warmtepomp'
            ]);
        }

        if (isset($data['status'])) {
            $this->in('status', $data['status'], [
                'pending', 'sent', 'accepted', 'rejected', 'completed', 'cancelled'
            ]);
        }

        if (isset($data['installation_date']) && !empty($data['installation_date'])) {
            if ($data['installation_date'] !== 'weet_ik_niet') {
                $this->date('installation_date', $data['installation_date']);
            }
        }

        if (isset($data['notes'])) {
            $this->maxLength('notes', $data['notes'], 1000);
        }

        if (isset($data['total_price'])) {
            $this->numeric('total_price', $data['total_price']);
            $this->min('total_price', $data['total_price'], 0);
        }

        return $this->getFlatErrors();
    }
}
