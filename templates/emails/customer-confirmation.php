<?php
/**
 * Customer Confirmation Email Template
 */
if (!defined('ABSPATH')) {
    exit;
}

// Load email header
$email_service->get_email_header($email_heading);

?>
<p>Beste <?php echo esc_html($quote_data['naam']); ?>,</p>

<p>Hartelijk dank voor uw interesse in onze vloerverwarmingsdiensten. We hebben uw aanvraag met referentienummer <strong>#<?php echo esc_html($quote_id); ?></strong> ontvangen en zullen deze zo spoedig mogelijk verwerken.</p>

<h3>Wat gebeurt er nu?</h3>
<ol>
    <li>We bekijken uw aanvraag zorgvuldig.</li>
    <li>U ontvangt binnen enkele werkdagen een gedetailleerde offerte per e-mail.</li>
    <li>Bij akkoord plannen we een afspraak voor de montage.</li>
</ol>

<p>Heeft u in de tussentijd vragen? Neem gerust contact met ons op en vermeld uw referentienummer.</p>

<p>Met vriendelijke groet,<br>Het team van EVS Vloerverwarming</p>

<?php

// Load email footer
$email_service->get_email_footer();
