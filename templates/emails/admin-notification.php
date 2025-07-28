<?php
/**
 * Admin Notification Email Template
 */
if (!defined('ABSPATH')) {
    exit;
}

// Load email header
do_action('evs_email_header', $email_heading);

?>
<p>Er is een nieuwe offerteaanvraag ingediend op de website. Hieronder vindt u de details.</p>

<table class="quote-details-table">
    <tr>
        <th>Offerte ID</th>
        <td>#<?php echo esc_html($quote_id); ?></td>
    </tr>
    <tr>
        <th>Naam</th>
        <td><?php echo esc_html($quote_data['naam']); ?></td>
    </tr>
    <tr>
        <th>E-mailadres</th>
        <td><a href="mailto:<?php echo esc_attr($quote_data['email']); ?>"><?php echo esc_html($quote_data['email']); ?></a></td>
    </tr>
    <tr>
        <th>Telefoonnummer</th>
        <td><?php echo esc_html($quote_data['telefoon'] ?? 'Niet opgegeven'); ?></td>
    </tr>
    <tr>
        <th>Adres</th>
        <td><?php echo nl2br(esc_html($quote_data['adres'] ?? 'Niet opgegeven')); ?></td>
    </tr>
    <tr>
        <td colspan="2" style="padding:0;"><hr style="border:none;border-top:1px solid #e0e0e0;"></td>
    </tr>
    <tr>
        <th>Type Vloer</th>
        <td><?php echo esc_html($this->format_field_value('type_vloer', $quote_data['type_vloer'])); ?></td>
    </tr>
    <tr>
        <th>Oppervlakte</th>
        <td><?php echo esc_html($quote_data['area_m2']); ?> m²</td>
    </tr>
    <tr>
        <th>Verdieping</th>
        <td><?php echo esc_html($this->format_field_value('verdieping', $quote_data['verdieping'], $quote_data['verdieping_anders'] ?? null)); ?></td>
    </tr>
    <tr>
        <th>Warmtebron</th>
        <td><?php echo esc_html($this->format_field_value('warmtebron', $quote_data['warmtebron'])); ?></td>
    </tr>
    <tr>
        <th>Verdeler Aansluiten</th>
        <td><?php echo esc_html($this->format_field_value('verdeler_aansluiten', $quote_data['verdeler_aansluiten'])); ?></td>
    </tr>
    <tr>
        <th>Vloer Dichtsmeren</th>
        <td><?php echo esc_html($this->format_field_value('vloer_dichtsmeren', $quote_data['vloer_dichtsmeren'])); ?></td>
    </tr>
    <tr>
        <th>Gewenste Montagedatum</th>
        <td><?php echo esc_html($this->format_field_value('montagedatum_type', $quote_data['montagedatum_type'], $quote_data['montagedatum'] ?? null)); ?></td>
    </tr>
    <tr>
        <th>Opmerkingen Klant</th>
        <td><?php echo !empty($quote_data['opmerkingen']) ? nl2br(esc_html($quote_data['opmerkingen'])) : 'Geen opmerkingen'; ?></td>
    </tr>
</table>

<p style="text-align:center; margin-top:30px;">
    <?php
    $edit_url = add_query_arg([
        'action'   => 'edit',
        'page'     => 'evs-offertes',
        'quote_id' => $quote_id
    ], admin_url('admin.php'));
    ?>
    <a href="<?php echo esc_url(wp_nonce_url($edit_url, 'evs_admin_action')); ?>" style="background-color:#E53E3E; color:#ffffff; padding:15px 30px; text-decoration:none; border-radius:5px; font-weight:bold; display:inline-block;">
        Bekijk en bewerk offerte
    </a>
</p>

<?php

// Load email footer
do_action('evs_email_footer');
