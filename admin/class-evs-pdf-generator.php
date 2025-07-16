<?php

require_once plugin_dir_path(__DIR__) . 'vendor/fpdf/fpdf.php';

class EVS_PDF_Generator extends FPDF {

    private $company_details;

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4') {
        parent::__construct($orientation, $unit, $size);
        $this->company_details = [
            'name' => 'EVS Vloerverwarmingen',
            'address' => 'Straatnaam 123',
            'zip' => '1234 AB',
            'city' => 'Stad',
            'phone' => '06-12345678',
            'email' => 'info@evs-vloerverwarming.nl',
            'kvk' => '12345678',
            'btw' => 'NL123456789B01'
        ];
    }

    // Page header
    function Header() {
        // Logo - Placeholder
        // $this->Image('path/to/logo.png', 10, 6, 30);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'Offerte', 1, 0, 'C');
        $this->Ln(20);
    }

    // Page footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function generate_quote_pdf($offer, $type) {
        $this->AliasNbPages();
        $this->AddPage();
        $this->SetFont('Arial', '', 12);

        // Add content here...
        $this->Cell(0, 10, 'Offerte voor: ' . $offer['customer_name'], 0, 1);
        $this->Cell(0, 10, 'Datum: ' . date('d-m-Y'), 0, 1);
        $this->Ln(10);

        if ($type === 'drilling') {
            $this->Cell(0, 10, 'Betreft: Infrezen vloerverwarming', 0, 1);
            $this->Cell(0, 10, 'Prijs: ' . chr(128) . ' ' . number_format($offer['drilling_price'], 2, ',', '.'), 0, 1);
        } elseif ($type === 'sealing') {
            $this->Cell(0, 10, 'Betreft: Dichtsmeren vloerverwarming', 0, 1);
            $this->Cell(0, 10, 'Prijs: ' . chr(128) . ' ' . number_format($offer['sealing_price'], 2, ',', '.'), 0, 1);
        }
    }
}
