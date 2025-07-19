<?php

namespace EVS\Validators;

/**
 * Validator for invoice data
 */
class InvoiceValidator extends BaseValidator
{
    /**
     * Validate invoice data
     */
    public function validate(array $data): array
    {
        $this->resetErrors();

        // Invoice specific fields
        $this->required('invoice_number', $data['invoice_number'] ?? '', 'Factuurnummer is verplicht');
        $this->maxLength('invoice_number', $data['invoice_number'] ?? '', 50, 'Factuurnummer mag maximaal 50 karakters bevatten');

        // Customer information
        $this->required('customer_name', $data['customer_name'] ?? '', 'Klantnaam is verplicht');
        $this->maxLength('customer_name', $data['customer_name'] ?? '', 100, 'Klantnaam mag maximaal 100 karakters bevatten');

        $this->required('customer_email', $data['customer_email'] ?? '', 'E-mailadres is verplicht');
        $this->email('customer_email', $data['customer_email'] ?? '');

        $this->required('customer_address', $data['customer_address'] ?? '', 'Adres is verplicht');
        $this->maxLength('customer_address', $data['customer_address'] ?? '', 255, 'Adres mag maximaal 255 karakters bevatten');

        // Optional customer fields
        if (isset($data['customer_phone'])) {
            $this->phone('customer_phone', $data['customer_phone']);
        }

        if (isset($data['customer_city'])) {
            $this->maxLength('customer_city', $data['customer_city'], 100, 'Plaats mag maximaal 100 karakters bevatten');
        }

        if (isset($data['customer_postal_code'])) {
            $this->validatePostalCode('customer_postal_code', $data['customer_postal_code']);
        }

        // Service and pricing
        $this->required('service_description', $data['service_description'] ?? '', 'Servicebeschrijving is verplicht');
        $this->maxLength('service_description', $data['service_description'] ?? '', 2000, 'Servicebeschrijving mag maximaal 2000 karakters bevatten');

        $this->required('service_amount', $data['service_amount'] ?? '', 'Servicebedrag is verplicht');
        $this->numeric('service_amount', $data['service_amount'] ?? '');
        $this->min('service_amount', $data['service_amount'] ?? '', 0.01, 'Servicebedrag moet minimaal €0,01 zijn');
        $this->max('service_amount', $data['service_amount'] ?? '', 999999.99, 'Servicebedrag mag maximaal €999.999,99 zijn');

        $this->required('btw_rate', $data['btw_rate'] ?? '', 'BTW-percentage is verplicht');
        $this->numeric('btw_rate', $data['btw_rate'] ?? '');
        $this->min('btw_rate', $data['btw_rate'] ?? '', 0, 'BTW-percentage kan niet negatief zijn');
        $this->max('btw_rate', $data['btw_rate'] ?? '', 100, 'BTW-percentage mag maximaal 100% zijn');

        // Dates
        $this->required('invoice_date', $data['invoice_date'] ?? '', 'Factuurdatum is verplicht');
        $this->date('invoice_date', $data['invoice_date'] ?? '', 'Y-m-d', 'Ongeldige factuurdatum');

        $this->required('due_date', $data['due_date'] ?? '', 'Vervaldatum is verplicht');
        $this->date('due_date', $data['due_date'] ?? '', 'Y-m-d', 'Ongeldige vervaldatum');

        // Validate due date is after invoice date
        if (!empty($data['invoice_date']) && !empty($data['due_date'])) {
            if (strtotime($data['due_date']) <= strtotime($data['invoice_date'])) {
                $this->addError('due_date', 'Vervaldatum moet na de factuurdatum liggen');
            }
        }

        // Status validation
        if (isset($data['status'])) {
            $this->in('status', $data['status'], [
                'concept', 'sent', 'paid', 'overdue', 'cancelled'
            ], 'Ongeldige factuurstatus');
        }

        // Payment method validation
        if (isset($data['payment_method']) && !empty($data['payment_method'])) {
            $this->in('payment_method', $data['payment_method'], [
                'bank_transfer', 'ideal', 'cash', 'card', 'other'
            ], 'Ongeldige betaalmethode');
        }

        // Payment reference validation
        if (isset($data['payment_reference'])) {
            $this->maxLength('payment_reference', $data['payment_reference'], 100, 'Betalingsreferentie mag maximaal 100 karakters bevatten');
        }

        // Paid date validation
        if (isset($data['paid_date']) && !empty($data['paid_date'])) {
            $this->date('paid_date', $data['paid_date'], 'Y-m-d H:i:s', 'Ongeldige betaaldatum');
        }

        // Notes validation
        if (isset($data['notes'])) {
            $this->maxLength('notes', $data['notes'], 2000, 'Opmerkingen mogen maximaal 2000 karakters bevatten');
        }

        return $this->getFlatErrors();
    }

    /**
     * Validate invoice update data (less strict)
     */
    public function validateUpdate(array $data): array
    {
        $this->resetErrors();

        // Only validate fields that are present
        if (isset($data['customer_name'])) {
            $this->required('customer_name', $data['customer_name'], 'Klantnaam is verplicht');
            $this->maxLength('customer_name', $data['customer_name'], 100);
        }

        if (isset($data['customer_email'])) {
            $this->required('customer_email', $data['customer_email'], 'E-mailadres is verplicht');
            $this->email('customer_email', $data['customer_email']);
        }

        if (isset($data['customer_phone'])) {
            $this->phone('customer_phone', $data['customer_phone']);
        }

        if (isset($data['customer_address'])) {
            $this->maxLength('customer_address', $data['customer_address'], 255);
        }

        if (isset($data['customer_city'])) {
            $this->maxLength('customer_city', $data['customer_city'], 100);
        }

        if (isset($data['customer_postal_code'])) {
            $this->validatePostalCode('customer_postal_code', $data['customer_postal_code']);
        }

        if (isset($data['service_description'])) {
            $this->maxLength('service_description', $data['service_description'], 2000);
        }

        if (isset($data['service_amount'])) {
            $this->numeric('service_amount', $data['service_amount']);
            $this->min('service_amount', $data['service_amount'], 0.01);
            $this->max('service_amount', $data['service_amount'], 999999.99);
        }

        if (isset($data['btw_rate'])) {
            $this->numeric('btw_rate', $data['btw_rate']);
            $this->min('btw_rate', $data['btw_rate'], 0);
            $this->max('btw_rate', $data['btw_rate'], 100);
        }

        if (isset($data['invoice_date'])) {
            $this->date('invoice_date', $data['invoice_date']);
        }

        if (isset($data['due_date'])) {
            $this->date('due_date', $data['due_date']);
        }

        if (isset($data['status'])) {
            $this->in('status', $data['status'], [
                'concept', 'sent', 'paid', 'overdue', 'cancelled'
            ]);
        }

        if (isset($data['payment_method']) && !empty($data['payment_method'])) {
            $this->in('payment_method', $data['payment_method'], [
                'bank_transfer', 'ideal', 'cash', 'card', 'other'
            ]);
        }

        if (isset($data['payment_reference'])) {
            $this->maxLength('payment_reference', $data['payment_reference'], 100);
        }

        if (isset($data['paid_date']) && !empty($data['paid_date'])) {
            $this->date('paid_date', $data['paid_date'], 'Y-m-d H:i:s');
        }

        if (isset($data['notes'])) {
            $this->maxLength('notes', $data['notes'], 2000);
        }

        return $this->getFlatErrors();
    }

    /**
     * Validate Dutch postal code format
     */
    private function validatePostalCode(string $field, $value): bool
    {
        if (!empty($value)) {
            // Dutch postal code format: 1234 AB or 1234AB
            if (!preg_match('/^[1-9][0-9]{3}\s?[A-Z]{2}$/i', $value)) {
                $this->addError($field, 'Ongeldige postcode (gebruik formaat: 1234 AB)');
                return false;
            }
        }
        return true;
    }
}
