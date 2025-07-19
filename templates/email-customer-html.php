<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo esc_html($subject); ?></title>
    <style type="text/css">
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        table { border-collapse: collapse; }
        .price { text-align: right; font-weight: bold; color: #2c5aa0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        h2 { color: #2c5aa0; }
        h3 { color: #2c5aa0; margin-top: 25px; }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td>
                <h2>Geachte <?php echo esc_html($customer_name); ?>,</h2>
                <p>Hartelijk dank voor uw interesse in onze vloerverwarmingsystemen. Wij hebben uw aanvraag zorgvuldig bestudeerd en zijn verheugd u hierbij onze offerte te kunnen presenteren.</p>
                
                <h3>Uw gegevens:</h3>
                <table width="100%" cellpadding="5" cellspacing="0" border="0">
                    <tr><td><strong>Soort vloerverwarming:</strong></td><td>Vloerverwarming</td></tr>
                    <tr><td><strong>Verdieping:</strong></td><td><?php echo esc_html($floor_level ?? ''); ?></td></tr>
                    <tr><td><strong>Type vloer:</strong></td><td><?php echo esc_html($floor_type ?? ''); ?></td></tr>
                    <tr><td><strong>Oppervlakte:</strong></td><td><?php echo esc_html($area_m2 ?? '0'); ?> m²</td></tr>
                    <tr><td><strong>Warmtebron:</strong></td><td><?php echo esc_html($heat_source ?? ''); ?></td></tr>
                    <tr><td><strong>Vloer dichtsmeren:</strong></td><td><?php echo esc_html($sealing ?? 'Nee'); ?></td></tr>
                </table>

                <?php if (isset($show_prices) && $show_prices): ?>
                <h3>Offerte specificaties:</h3>
                <table width="100%" cellpadding="5" cellspacing="0" border="0">
                    <tr><td><strong>Prijs infrezen (incl. verdeler):</strong></td><td class="price">€ <?php echo esc_html($drilling_price ?? '0,00'); ?></td></tr>
                    <tr><td><strong>Prijs dichtsmeren:</strong></td><td class="price">€ <?php echo esc_html($sealing_price ?? '0,00'); ?></td></tr>
                    <tr><td><strong>Totaalbedrag (incl. BTW):</strong></td><td class="price">€ <?php echo esc_html($total_price ?? '0,00'); ?></td></tr>
                </table>

                <h3>Volgende stappen:</h3>
                <p>Wij hopen dat onze offerte aan uw verwachtingen voldoet. Mocht u vragen hebben of aanpassingen wensen, dan horen wij dat graag van u. Na uw akkoord kunnen wij de werkzaamheden inplannen.</p>
                <?php else: ?>
                <h3>Volgende stappen:</h3>
                <p>Bedankt voor uw aanvraag! Wij hebben uw gegevens ontvangen en zullen deze beoordelen. U ontvangt binnen 24 uur een persoonlijke offerte van ons team.</p>
                <p>Mocht u nog vragen hebben, dan kunt u altijd contact met ons opnemen.</p>
                <?php endif; ?>

                <div class="footer">
                    <p>Met vriendelijke groet,</p>
                    <p><strong>Team EVS Vloerverwarmingen</strong></p>
                    <p style="margin-top:15px; font-size:14px; color:#666;">
                        📧 info@evs-vloerverwarmingen.nl<br>
                        📞 +31 (0)6 12345678<br>
                        🌐 www.evs-vloerverwarmingen.nl
                    </p>
                    <p style="font-size:12px; color:#999;">
                        EVS Vloerverwarmingen | KvK: 12345678 | BTW: NL123456789B01<br>
                        Deze offerte is 30 dagen geldig vanaf de datum van verzending.
                    </p>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
