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
$send_nonce_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=send_quote&offer_id=' . $quote_id), 'evs_send_quote_' . $quote_id, 'evs_send_quote_nonce');
$invoice_nonce_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=create_invoice&offer_id=' . $quote_id), 'evs_create_invoice_' . $quote_id, 'evs_create_invoice_nonce');

?>
<div class="wrap">
    <h1><?php printf(esc_html__('Offerte #%d Bewerken', 'evs-vloerverwarming'), $quote_id); ?></h1>

    <?php do_action('admin_notices'); ?>

    <form method="post" id="evs-edit-quote-form" action="">
        <?php wp_nonce_field('evs_offer_actions_' . $quote_id); // Single nonce for all actions ?>
        <input type="hidden" name="offer_id" value="<?php echo esc_attr($quote_id); ?>" />
        <input type="hidden" name="current_status" value="<?php echo esc_attr($offer['status'] ?? 'pending'); ?>" />
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Klantgegevens', 'evs-vloerverwarming'); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="customer_name"><?php esc_html_e('Naam klant', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($offer['naam'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="customer_email"><?php esc_html_e('E-mail klant', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($offer['email'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="customer_phone"><?php esc_html_e('Telefoon', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="customer_phone" name="customer_phone" value="<?php echo esc_attr($offer['telefoon'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="customer_address"><?php esc_html_e('Adres', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="text" id="customer_address" name="customer_address" value="<?php echo esc_attr($offer['adres'] ?? ''); ?>" class="regular-text" /></td>
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
                                                <option value="begaande_grond" <?php selected($offer['verdieping'], 'begaande_grond'); ?>>Begaande grond</option>
                                                <option value="eerste_verdieping" <?php selected($offer['verdieping'], 'eerste_verdieping'); ?>>Eerste verdieping</option>
                                                <option value="zolder" <?php selected($offer['verdieping'], 'zolder'); ?>>Zolder</option>
                                                <option value="anders" <?php selected($offer['verdieping'], 'anders'); ?>>Anders</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="type_vloer"><?php esc_html_e('Type vloer', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="type_vloer" name="type_vloer" class="regular-text">
                                                <option value="cement_dekvloer" <?php selected($offer['type_vloer'], 'cement_dekvloer'); ?>>Cement dekvloer</option>
                                                <option value="tegelvloer" <?php selected($offer['type_vloer'], 'tegelvloer'); ?>>Tegelvloer</option>
                                                <option value="betonvloer" <?php selected($offer['type_vloer'], 'betonvloer'); ?>>Betonvloer</option>
                                                <option value="fermacelvloer" <?php selected($offer['type_vloer'], 'fermacelvloer'); ?>>Fermacelvloer</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="area_m2"><?php esc_html_e('Oppervlakte (m²)', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="number" id="area_m2" name="area_m2" value="<?php echo esc_attr($offer['area_m2'] ?? '0'); ?>" class="small-text" min="1" step="1" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="heat_source"><?php esc_html_e('Warmtebron', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="heat_source" name="heat_source" class="regular-text">
                                                <option value="cv_ketel" <?php selected($offer['warmtebron'], 'cv_ketel'); ?>>CV ketel</option>
                                                <option value="hybride_warmtepomp" <?php selected($offer['warmtebron'], 'hybride_warmtepomp'); ?>>Hybride warmtepomp</option>
                                                <option value="volledige_warmtepomp" <?php selected($offer['warmtebron'], 'volledige_warmtepomp'); ?>>Volledige warmtepomp</option>
                                                <option value="stadsverwarming" <?php selected($offer['warmtebron'], 'stadsverwarming'); ?>>Stadsverwarming</option>
                                                <option value="toekomstige_warmtepomp" <?php selected($offer['warmtebron'], 'toekomstige_warmtepomp'); ?>>Toekomstige warmtepomp</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="verdeler_aansluiten"><?php esc_html_e('Verdeler aansluiten', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="verdeler_aansluiten" name="verdeler_aansluiten" class="regular-text">
                                                <option value="1" <?php selected($offer['verdeler_aansluiten'], '1'); ?>>Ja</option>
                                                <option value="0" <?php selected($offer['verdeler_aansluiten'], '0'); ?>>Nee</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="vloer_dichtsmeren"><?php esc_html_e('Dichtsmeren', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="vloer_dichtsmeren" name="vloer_dichtsmeren" class="regular-text">
                                                <option value="1" <?php selected($offer['vloer_dichtsmeren'], '1'); ?>>Ja</option>
                                                <option value="0" <?php selected($offer['vloer_dichtsmeren'], '0'); ?>>Nee</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="vloer_schuren"><?php esc_html_e('Vloer schuren', 'evs-vloerverwarming'); ?></label></th>
                                        <td>
                                            <select id="vloer_schuren" name="vloer_schuren" class="regular-text">
                                                <option value="1" <?php selected($offer['vloer_schuren'], '1'); ?>>Ja</option>
                                                <option value="0" <?php selected($offer['vloer_schuren'], '0'); ?>>Nee</option>
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
                                        <th scope="row"><label for="sanding_price"><?php esc_html_e('Prijs schuren (€)', 'evs-vloerverwarming'); ?></label></th>
                                        <td><input type="number" id="sanding_price" name="sanding_price" value="<?php echo esc_attr($offer['sanding_price'] ?? ''); ?>" class="small-text" min="0" step="0.01" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><strong><?php esc_html_e('Totaalprijs (€)', 'evs-vloerverwarming'); ?></strong></th>
                                        <td><strong>€ <?php echo number_format(($offer['total_price'] ?? 0), 2, ',', '.'); ?></strong></td>
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
                                        <td><input type="text" id="installation_date" name="installation_date" value="<?php echo esc_attr($offer['montagedatum'] ?? ''); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="notes"><?php esc_html_e('Opmerkingen', 'evs-vloerverwarming'); ?></label></th>
                                        <td><textarea id="notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea($offer['opmerkingen'] ?? ''); ?></textarea></td>
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
                    <div class="postbox price-sidebar">
                        <h2 class="hndle"><span>Prijsoverzicht</span></h2>
                        <div class="inside">
                            <div class="price-item">
                                <span class="price-label">Benodigde meters:</span>
                                <span class="price-value"><strong id="strekkende-meter"><?php echo esc_html(number_format($offer['strekkende_meter'] ?? 0, 2, ',', '.')); ?></strong> m</span>
                            </div>
                            <hr>
                            <div class="price-item">
                                <span class="price-label">Infrezen:</span>
                                <span class="price-value"><strong id="boren-price"><?php echo esc_html(number_format($offer['drilling_price'] ?? 0, 2, ',', '.')); ?></strong></span>
                            </div>
                            <div class="price-item">
                                <span class="price-label">Verdeler:</span>
                                <span class="price-value"><strong id="verdeler-price"><?php echo esc_html(number_format($offer['verdeler_price'] ?? 0, 2, ',', '.')); ?></strong></span>
                            </div>
                            <div class="price-item">
                                <span class="price-label">Dichtsmeren:</span>
                                <span class="price-value"><strong id="dichtsmeren-price"><?php echo esc_html(number_format($offer['sealing_price'] ?? 0, 2, ',', '.')); ?></strong></span>
                            </div>
                            <div class="price-item">
                                <span class="price-label">Schuren:</span>
                                <span class="price-value"><strong id="schuren-price"><?php echo esc_html(number_format($offer['sanding_price'] ?? 0, 2, ',', '.')); ?></strong></span>
                            </div>
                            <hr>
                            <div class="price-item total-price">
                                <span class="price-label">Totaal:</span>
                                <span class="price-value"><strong id="total-price"><?php echo esc_html(number_format($offer['total_price'] ?? 0, 2, ',', '.')); ?></strong></span>
                            </div>
                            <div class="submitbox" id="submitpost">
                                <div id="major-publishing-actions">
                                    <div id="publishing-action">
                                        <input type="submit" name="save_offer" id="save_offer" class="button button-primary button-large" value="Offerte Opslaan" />
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="postbox">
                        <h2 class="hndle"><span>Status & Acties</span></h2>
                        <div class="inside">
                            <p>
                                <label for="status" class="screen-reader-text"><?php esc_html_e('Status', 'evs-vloerverwarming'); ?></label>
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
                            <div class="submitbox">
                                <input type="submit" name="send_offer" class="button button-large" value="<?php esc_attr_e('Offerte Verzenden', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center; margin-bottom: 10px;">
                                <input type="submit" name="create_invoice" class="button button-large" value="<?php esc_attr_e('Factuur Genereren', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
