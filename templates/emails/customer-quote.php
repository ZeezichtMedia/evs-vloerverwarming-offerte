<?php
/**
 * Customer Quote Email Template
 */
if (!defined('ABSPATH')) {
    exit;
}

// Load email header
$this->get_email_header($email_heading);

?>
<p>Beste <?php echo esc_html($quote_data['naam']); ?>,</p>

<p>Hierbij ontvangt u de officiële offerte voor de door u aangevraagde werkzaamheden. Hieronder vindt u een gedetailleerde specificatie van de kosten.</p>

<h3>Projectdetails</h3>
<table class="quote-details-table">
    <tr>
        <th>Adres</th>
        <td><?php echo esc_html(($quote_data['adres'] ?? '') . ', ' . ($quote_data['postcode'] ?? '') . ' ' . ($quote_data['plaats'] ?? '')); ?></td>
    </tr>
    <tr>
        <th>Type Vloer</th>
        <td><?php echo esc_html($this->format_field_value('type_vloer', $quote_data['type_vloer'])); ?></td>
    </tr>
    <tr>
        <th>Oppervlakte</th>
        <td><?php echo esc_html($quote_data['area_m2']); ?> m²</td>
    </tr>
</table>

<?php
// Calculate missing values at the beginning of the price table.
$total_price = (float) $quote_data['total_price'];
$sub_total   = $total_price / EVS_Admin_Manager::VAT_RATE; // Using VAT rate constant
$btw_amount  = $total_price - $sub_total;
?>
<h3>Prijsspecificatie</h3>
<table class="quote-details-table">
    <thead>
        <tr>
            <th style="text-align:left;">Omschrijving</th>
            <th style="text-align:right;">Bedrag</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Infrezen vloerverwarming (<?php echo esc_html($quote_data['area_m2']); ?> m²)</td>
            <td style="text-align:right;">€ <?php echo number_format($quote_data['drilling_price'], 2, ',', '.'); ?></td>
        </tr>
        <?php if (isset($quote_data['verdeler_price']) && $quote_data['verdeler_price'] > 0) : ?>
        <tr>
            <td>Aansluiten verdeler op warmtebron</td>
            <td style="text-align:right;">€ <?php echo number_format($quote_data['verdeler_price'], 2, ',', '.'); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (isset($quote_data['sealing_price']) && $quote_data['sealing_price'] > 0) : ?>
        <tr>
            <td>Vloer dichtsmeren</td>
            <td style="text-align:right;">€ <?php echo number_format($quote_data['sealing_price'], 2, ',', '.'); ?></td>
        </tr>
        <?php endif; ?>
        <tr style="font-weight:bold;">
            <td>Subtotaal</td>
            <td style="text-align:right;">€ <?php echo number_format($sub_total, 2, ',', '.'); ?></td>
        </tr>
        <tr>
            <td>BTW (21%)</td>
            <td style="text-align:right;">€ <?php echo number_format($btw_amount, 2, ',', '.'); ?></td>
        </tr>
        <tr style="font-weight:bold; background-color:#f9f9f9;">
            <td>Totaal te voldoen</td>
            <td style="text-align:right;">€ <?php echo number_format($total_price, 2, ',', '.'); ?></td>
        </tr>
    </tbody>
</table>

<h3>Voorwaarden</h3>
<ul>
    <li>Deze offerte is 30 dagen geldig.</li>
    <li>Alle prijzen zijn inclusief 21% BTW.</li>
    <li>Betaling dient te geschieden binnen 14 dagen na factuurdatum.</li>
</ul>

<p>Indien u akkoord gaat met deze offerte, kunt u reageren op deze e-mail om de werkzaamheden in te plannen.</p>

<p>Met vriendelijke groet,<br>Het team van EVS Vloerverwarming</p>

<?php

// Load email footer
$this->get_email_footer();
