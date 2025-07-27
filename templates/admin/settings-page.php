<?php
/**
 * Admin Settings Page Template
 *
 * @package EVS_Vloerverwarming_Offerte
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wrap">
    <h1>EVS Instellingen</h1>

    <?php if (!empty($message_text)) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message_text); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('evs_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="evs_admin_email">Admin E-mailadres</label></th>
                <td>
                    <input type="email" id="evs_admin_email" name="evs_admin_email" 
                           value="<?php echo esc_attr($settings['admin_email']); ?>" class="regular-text" required>
                    <p class="description">E-mailadres waar nieuwe offertes naartoe worden gestuurd.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="evs_company_name">Bedrijfsnaam</label></th>
                <td>
                    <input type="text" id="evs_company_name" name="evs_company_name" 
                           value="<?php echo esc_attr($settings['company_name']); ?>" class="regular-text" required>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="evs_company_address">Bedrijfsadres</label></th>
                <td>
                    <textarea id="evs_company_address" name="evs_company_address" 
                              rows="4" class="large-text"><?php echo esc_textarea($settings['company_address']); ?></textarea>
                    <p class="description">Volledig adres voor op facturen en offertes.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="evs_vat_number">BTW-nummer</label></th>
                <td>
                    <input type="text" id="evs_vat_number" name="evs_vat_number" 
                           value="<?php echo esc_attr($settings['vat_number']); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row">Automatisch versturen</th>
                <td>
                    <label>
                        <input type="checkbox" name="evs_auto_send_quotes" value="1" 
                               <?php checked($settings['auto_send_quotes'], 1); ?>>
                        Offertes automatisch versturen naar klanten
                    </label>
                    <p class="description">Als dit is ingeschakeld, worden offertes automatisch naar klanten verzonden na bevestiging.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="evs_privacy_policy_url">Privacy Policy URL</label></th>
                <td>
                    <input type="url" id="evs_privacy_policy_url" name="evs_privacy_policy_url" 
                           value="<?php echo esc_attr($settings['privacy_policy_url']); ?>" class="regular-text">
                    <p class="description">Link naar de privacy policy pagina. Wordt getoond onder het offerteformulier.</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Instellingen opslaan'); ?>
    </form>
</div>
