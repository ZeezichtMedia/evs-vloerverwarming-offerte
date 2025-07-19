<?php

namespace EVS\Mail;

use EVS\Contracts\MailerInterface;
use EVS\Models\Quote;
use EVS\Models\Invoice;

/**
 * Mailer service for quote and invoice emails
 */
class QuoteMailer implements MailerInterface
{
    private string $fromEmail = 'info@evs-vloerverwarmingen.nl';
    private string $fromName = 'EVS Vloerverwarmingen';

    /**
     * Send an email
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$this->fromName} <{$this->fromEmail}>",
        ];

        if (isset($options['reply_to'])) {
            $headers[] = "Reply-To: {$options['reply_to']}";
        }

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Send email using template
     */
    public function sendTemplate(string $template, string $to, array $data = []): bool
    {
        $templatePath = $this->getTemplatePath($template);
        
        if (!file_exists($templatePath)) {
            error_log("Email template not found: {$template}");
            return false;
        }

        // Extract data for template
        extract($data);
        
        // Capture template output
        ob_start();
        include $templatePath;
        $body = ob_get_clean();

        $subject = $data['subject'] ?? 'EVS Vloerverwarmingen';

        return $this->send($to, $subject, $body, $data['options'] ?? []);
    }

    /**
     * Queue email for later sending
     */
    public function queue(string $to, string $subject, string $body, array $options = []): bool
    {
        // For now, just send immediately
        // In future versions, this could use a proper queue system
        return $this->send($to, $subject, $body, $options);
    }

    /**
     * Send quote confirmation to customer
     */
    public function sendConfirmationToCustomer(Quote $quote): bool
    {

        $data = [
            'subject' => "Bevestiging van uw aanvraag - {$quote->customer_name}",
            'quote' => $quote,
            'customer_name' => $quote->customer_name,
            'quote_id' => $quote->id,
            'floor_level' => $this->getFloorLevelLabel($quote->floor_level),
            'floor_type' => $this->getFloorTypeLabel($quote->floor_type),
            'area_m2' => $quote->area,
            'strekkende_meter' => $quote->strekkende_meter,
            'heat_source' => $this->getHeatSourceLabel($quote->heat_source),
            'distributor' => $quote->distributor === 'ja' ? 'Ja' : 'Nee',
            'sealing' => $quote->sealing === 'ja' ? 'Ja' : 'Nee',
            'installation_date' => $quote->installation_date === 'weet_ik_niet' ? 'Nog niet bekend' : date('d-m-Y', strtotime($quote->installation_date)),
            'show_prices' => false, // CONFIRMATION EMAIL - NO PRICES
            'created_date' => date('d-m-Y', strtotime($quote->created_at)),
        ];


        return $this->sendTemplate('customer-html', $quote->customer_email, $data);
    }

    /**
     * Send quote notification to admin
     */
    public function sendQuoteToAdmin(Quote $quote): bool
    {
        $data = [
            'subject' => "Nieuwe offerte aanvraag van {$quote->customer_name}",
            'quote' => $quote,
            'customer_name' => $quote->customer_name,
            'customer_email' => $quote->customer_email,
            'customer_phone' => $quote->customer_phone,
            'customer_address' => $quote->customer_address,
            'quote_id' => $quote->id,
            'floor_level' => $this->getFloorLevelLabel($quote->floor_level),
            'floor_type' => $this->getFloorTypeLabel($quote->floor_type),
            'area_m2' => $quote->area, // Fixed: Use area instead of area_m2
            'strekkende_meter' => $quote->strekkende_meter,
            'heat_source' => $this->getHeatSourceLabel($quote->heat_source),
            'distributor' => $quote->distributor === 'ja' ? 'Ja' : 'Nee', // Fixed: Use distributor
            'sealing' => $quote->sealing === 'ja' ? 'Ja' : 'Nee', // Fixed: Use sealing
            'installation_date' => $quote->installation_date === 'weet_ik_niet' ? 'Nog niet bekend' : date('d-m-Y', strtotime($quote->installation_date)),
            'total_price' => $quote->getFormattedPrice(),
            'created_date' => date('d-m-Y H:i', strtotime($quote->created_at)),
            'admin_url' => admin_url("admin.php?page=evs-edit-offer&offer_id={$quote->id}"),
            'options' => [
                'reply_to' => $quote->customer_email
            ]
        ];

        return $this->sendTemplate('admin-html', $this->fromEmail, $data);
    }

    /**
     * Send quote to customer
     */
    public function sendQuoteToCustomer(Quote $quote): bool
    {
        $data = [
            'subject' => "Uw offerte van EVS Vloerverwarmingen - {$quote->customer_name}",
            'quote' => $quote,
            'customer_name' => $quote->customer_name,
            'quote_id' => $quote->id,
            'floor_level' => $this->getFloorLevelLabel($quote->floor_level),
            'floor_type' => $this->getFloorTypeLabel($quote->floor_type),
            'area_m2' => $quote->area,
            'strekkende_meter' => $quote->strekkende_meter,
            'heat_source' => $this->getHeatSourceLabel($quote->heat_source),
            'distributor' => $quote->distributor === 'ja' ? 'Ja' : 'Nee',
            'sealing' => $quote->sealing === 'ja' ? 'Ja' : 'Nee',
            'installation_date' => $quote->installation_date === 'weet_ik_niet' ? 'Nog niet bekend' : date('d-m-Y', strtotime($quote->installation_date)),
            'show_prices' => true, // QUOTE EMAIL - WITH PRICES
            'drilling_price' => number_format($quote->drilling_price, 2, ',', '.'),
            'sealing_price' => number_format($quote->sealing_price, 2, ',', '.'),
            'verdeler_price' => number_format($quote->verdeler_price, 2, ',', '.'),
            'total_price' => number_format($quote->total_price, 2, ',', '.'),
            'created_date' => date('d-m-Y', strtotime($quote->created_at)),
        ];

        return $this->sendTemplate('customer-html', $quote->customer_email, $data);
    }

    /**
     * Send invoice to customer
     */
    public function sendInvoiceToCustomer(Invoice $invoice): bool
    {
        $data = [
            'subject' => "Factuur {$invoice->invoice_number} van EVS Vloerverwarmingen",
            'invoice' => $invoice,
            'customer_name' => $invoice->customer_name,
            'invoice_number' => $invoice->invoice_number,
            'service_description' => $invoice->service_description,
            'service_amount' => number_format($invoice->service_amount, 2, ',', '.'),
            'btw_amount' => number_format($invoice->btw_amount, 2, ',', '.'),
            'total_amount' => $invoice->getFormattedAmount(),
            'invoice_date' => date('d-m-Y', strtotime($invoice->invoice_date)),
            'due_date' => date('d-m-Y', strtotime($invoice->due_date)),
        ];

        return $this->sendTemplate('invoice-html', $invoice->customer_email, $data);
    }

    /**
     * Get template file path
     */
    private function getTemplatePath(string $template): string
    {
        $pluginDir = dirname(dirname(__DIR__));
        return $pluginDir . "/templates/email-{$template}.php";
    }

    /**
     * Get floor type label in Dutch
     */
    private function getFloorTypeLabel(string $floorType): string
    {
        $labels = [
            'cement_dekvloer' => 'Cement dekvloer',
            'tegelvloer' => 'Tegelvloer',
            'betonvloer' => 'Betonvloer',
            'fermacelvloer' => 'Fermacelvloer',
        ];

        return $labels[$floorType] ?? $floorType;
    }

    /**
     * Get floor level label in Dutch
     */
    private function getFloorLevelLabel(string $floorLevel): string
    {
        $labels = [
            'begaande_grond' => 'Begaande grond',
            'eerste_verdieping' => 'Eerste verdieping',
            'zolder' => 'Zolder',
            'anders' => 'Anders',
        ];

        return $labels[$floorLevel] ?? $floorLevel;
    }

    /**
     * Get heat source label in Dutch
     */
    private function getHeatSourceLabel(string $heatSource): string
    {
        $labels = [
            'cv_ketel' => 'CV ketel',
            'hybride_warmtepomp' => 'Hybride warmtepomp',
            'volledige_warmtepomp' => 'Volledige warmtepomp',
            'stadsverwarming' => 'Stadsverwarming',
            'toekomstige_warmtepomp' => 'Toekomstige warmtepomp',
        ];

        return $labels[$heatSource] ?? $heatSource;
    }
}
