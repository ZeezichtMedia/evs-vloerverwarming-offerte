<?php
/**
 * Admin View: Edit Offer Page.
 *
 * @package EVS_Vloerverwarming_Offerte
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Maak de nonce URL's aan
$send_nonce_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=send_quote&offer_id=' . $offer_id), 'evs_send_quote_' . $offer_id, 'evs_send_quote_nonce');
$invoice_nonce_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=create_invoice&offer_id=' . $offer_id), 'evs_create_invoice_' . $offer_id, 'evs_create_invoice_nonce');

?>
<div class="wrap">
    <h1><?php printf(esc_html__('Offerte #%d Bewerken', 'evs-vloerverwarming'), $offer_id); ?></h1>

    <?php do_action('admin_notices'); ?>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <form method="post" action="">
                    <?php wp_nonce_field('update_offer_' . $offer_id); ?>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Klantgegevens', 'evs-vloerverwarming'); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="customer_name"><?php esc_html_e('Naam klant', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($offer['customer_name'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="customer_email"><?php esc_html_e('E-mail klant', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($offer['customer_email'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="customer_phone"><?php esc_html_e('Telefoon', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="customer_phone" name="customer_phone" value="<?php echo esc_attr($offer['customer_phone'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="customer_address"><?php esc_html_e('Adres', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="customer_address" name="customer_address" value="<?php echo esc_attr($offer['customer_address'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Projectgegevens', 'evs-vloerverwarming'); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="floor_level"><?php esc_html_e('Verdieping', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="floor_level" name="floor_level" class="regular-text">
                                                <option value="begaande_grond" <?php selected($offer['floor_level'], 'begaande_grond'); ?>>Begaande grond</option>
                                                <option value="eerste_verdieping" <?php selected($offer['floor_level'], 'eerste_verdieping'); ?>>Eerste verdieping</option>
                                                <option value="zolder" <?php selected($offer['floor_level'], 'zolder'); ?>>Zolder</option>
                                                <option value="anders" <?php selected($offer['floor_level'], 'anders'); ?>>Anders</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="floor_type"><?php esc_html_e('Type vloer', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="floor_type" name="floor_type" class="regular-text">
                                                <option value="cement_dekvloer" <?php selected($offer['floor_type'], 'cement_dekvloer'); ?>>Cement dekvloer</option>
                                                <option value="tegelvloer" <?php selected($offer['floor_type'], 'tegelvloer'); ?>>Tegelvloer</option>
                                                <option value="betonvloer" <?php selected($offer['floor_type'], 'betonvloer'); ?>>Betonvloer</option>
                                                <option value="fermacelvloer" <?php selected($offer['floor_type'], 'fermacelvloer'); ?>>Fermacelvloer</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="area"><?php esc_html_e('Oppervlakte (m²)', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="number" id="area" name="area" value="<?php echo esc_attr($offer['area'] ?? ''); ?>" class="small-text" min="1" step="1" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="heat_source"><?php esc_html_e('Warmtebron', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="heat_source" name="heat_source" class="regular-text">
                                                <option value="cv_ketel" <?php selected($offer['heat_source'], 'cv_ketel'); ?>>CV ketel</option>
                                                <option value="hybride_warmtepomp" <?php selected($offer['heat_source'], 'hybride_warmtepomp'); ?>>Hybride warmtepomp</option>
                                                <option value="volledige_warmtepomp" <?php selected($offer['heat_source'], 'volledige_warmtepomp'); ?>>Volledige warmtepomp</option>
                                                <option value="stadsverwarming" <?php selected($offer['heat_source'], 'stadsverwarming'); ?>>Stadsverwarming</option>
                                                <option value="toekomstige_warmtepomp" <?php selected($offer['heat_source'], 'toekomstige_warmtepomp'); ?>>Toekomstige warmtepomp</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="sealing"><?php esc_html_e('Dichtsmeren', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="sealing" name="sealing" class="regular-text">
                                                <option value="ja" <?php selected($offer['sealing'], 'ja'); ?>>Ja</option>
                                                <option value="nee" <?php selected($offer['sealing'], 'nee'); ?>>Nee</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Prijzen', 'evs-vloerverwarming'); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="drilling_price"><?php esc_html_e('Prijs infrezen (€)', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="number" id="drilling_price" name="drilling_price" value="<?php echo esc_attr($offer['drilling_price'] ?? ''); ?>" class="small-text" min="0" step="0.01" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="sealing_price"><?php esc_html_e('Prijs dichtsmeren (€)', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="number" id="sealing_price" name="sealing_price" value="<?php echo esc_attr($offer['sealing_price'] ?? ''); ?>" class="small-text" min="0" step="0.01" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="verdeler_price"><?php esc_html_e('Prijs verdeler (€)', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="number" id="verdeler_price" name="verdeler_price" value="<?php echo esc_attr($offer['verdeler_price'] ?? ''); ?>" class="small-text" min="0" step="0.01" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><strong><?php esc_html_e('Totaalprijs (€)', 'evs-vloerverwarming'); ?></strong></th>
                                        <td><strong>€ <?php echo number_format(($offer['drilling_price'] ?? 0) + ($offer['sealing_price'] ?? 0) + ($offer['verdeler_price'] ?? 0), 2, ',', '.'); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Overige gegevens', 'evs-vloerverwarming'); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="installation_date"><?php esc_html_e('Montagedatum', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="installation_date" name="installation_date" value="<?php echo esc_attr($offer['installation_date'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="notes"><?php esc_html_e('Opmerkingen', 'evs-vloerverwarming'); ?></label></th>
                                        <td><textarea id="notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($offer['notes'] ?? ''); ?></textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Aanmaakdatum', 'evs-vloerverwarming'); ?></th>
                                        <td><?php echo esc_html($offer['created_at'] ?? ''); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>

            <div id="postbox-container-1" class="postbox-container">
                <div class="postbox">
                    <h2 class="hndle"><span><?php esc_html_e('Acties', 'evs-vloerverwarming'); ?></span></h2>
                    <div class="inside">
                        <div class="misc-pub-section">
                            <input type="submit" name="submit" class="button button-primary button-large" value="<?php esc_attr_e('Offerte Opslaan', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center; margin-bottom:10px;">
                        </div>
                        
                        <p>
                            <label for="status"><?php esc_html_e('Status', 'evs-vloerverwarming'); ?></label>
                            <select id="status" name="status" class="widefat">
                                <?php
                                $statuses = ['new' => 'Nieuw', 'sent' => 'Verzonden', 'accepted' => 'Geaccepteerd', 'invoiced' => 'Gefactureerd', 'cancelled' => 'Geannuleerd'];
                                foreach ($statuses as $key => $label) {
                                    echo '<option value="' . esc_attr($key) . '" ' . selected($offer['status'], $key, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </p>
                        <hr>
                        </form>
                        <form method="post" action="">
                            <?php wp_nonce_field('evs_send_offer_' . $offer_id, 'evs_send_offer_nonce'); ?>
                            <input type="submit" name="send_offer" class="button button-large button-primary" value="<?php esc_attr_e('Offerte Verzenden', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center;">
                        </form>
                        <br>
                        <form method="post" action="">
                            <?php wp_nonce_field('evs_create_invoice_' . $offer_id, 'evs_create_invoice_nonce'); ?>
                            <input type="submit" name="create_invoice" class="button button-large" value="<?php esc_attr_e('Factuur Genereren', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center;">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
