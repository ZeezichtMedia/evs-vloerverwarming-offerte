<?php
/**
 * Customer Invoice Email Template
 */
if (!defined('ABSPATH')) {
    exit;
}

// Load email header
$email_service->get_email_header($email_heading);

?>
<p>Beste <?php echo esc_html($invoice_data['customer_name']); ?>,</p>

<p>Hierbij ontvangt u de factuur voor de door ons uitgevoerde werkzaamheden. Gelieve het bedrag binnen de gestelde termijn te voldoen.</p>

<h3>Factuurgegevens</h3>
<table class="invoice-details-table">
    <tr>
        <th>Factuurnummer</th>
        <td><?php echo esc_html($invoice_data['invoice_number']); ?></td>
    </tr>
    <tr>
        <th>Factuurdatum</th>
        <td><?php echo esc_html(date('d-m-Y', strtotime($invoice_data['invoice_date']))); ?></td>
    </tr>
    <tr>
        <th>Vervaldatum</th>
        <td><?php echo esc_html(date('d-m-Y', strtotime($invoice_data['due_date']))); ?></td>
    </tr>
    <tr>
        <th>Adres</th>
        <td><?php echo esc_html($invoice_data['customer_address']); ?></td>
    </tr>
</table>

<h3>Prijsspecificatie</h3>
<table class="invoice-details-table">
    <thead>
        <tr>
            <th style="text-align:left;">Omschrijving</th>
            <th style="text-align:right;">Bedrag</th>
        </tr>
    </thead>
    <tbody>
        <tr style="font-weight:bold;">
            <td>Subtotaal</td>
            <td style="text-align:right;">€ <?php echo number_format($invoice_data['subtotal'], 2, ',', '.'); ?></td>
        </tr>
        <tr>
            <td>BTW (21%)</td>
            <td style="text-align:right;">€ <?php echo number_format($invoice_data['vat_amount'], 2, ',', '.'); ?></td>
        </tr>
        <tr style="font-weight:bold; background-color:#f9f9f9;">
            <td>Totaal te voldoen</td>
            <td style="text-align:right;">€ <?php echo number_format($invoice_data['total_amount'], 2, ',', '.'); ?></td>
        </tr>
    </tbody>
</table>

<h3>Betalingsgegevens</h3>
<p>Gelieve het bovenstaande bedrag over te maken naar:</p>
<ul>
    <li><strong>Rekeningnummer:</strong> NL12 ABCD 0123 4567 89</li>
    <li><strong>Ten name van:</strong> EVS Vloerverwarmingen</li>
    <li><strong>Onder vermelding van:</strong> <?php echo esc_html($invoice_data['invoice_number']); ?></li>
</ul>

<h3>Betalingsvoorwaarden</h3>
<ul>
    <li>Betaling dient te geschieden binnen 30 dagen na factuurdatum.</li>
    <li>Bij te late betaling worden wettelijke rente en incassokosten in rekening gebracht.</li>
    <li>Voor vragen over deze factuur kunt u contact met ons opnemen.</li>
</ul>

<p>Bedankt voor uw vertrouwen in EVS Vloerverwarmingen.</p>

<p>Met vriendelijke groet,<br>Het team van EVS Vloerverwarming</p>

<?php

// Load email footer
$email_service->get_email_footer();
