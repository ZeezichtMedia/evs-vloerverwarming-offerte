<?php
/**
 * Template for the admin edit quote page.
 *
 * @package EVS
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// The $quote variable is passed from the calling method.
?>
<div class="wrap">
    <div class="evs-edit-header">
        <a href="<?php echo admin_url('admin.php?page=evs-offertes'); ?>" class="evs-back-link">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            Terug naar Offertes
        </a>
        <h1 style="margin: 0;">Offerte Bewerken #<?php echo $quote['id']; ?></h1>
    </div>

    <?php
    // Use the generic and robust notification logic
    if (!empty($message_text)) : ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message_text); ?></p>
        </div>
    <?php endif; ?>

    <div class="evs-admin-layout">
        <div class="evs-admin-main">
            <form method="post" action="" id="evs-edit-quote-form">
                <?php wp_nonce_field('evs_edit_quote'); ?>
                <input type="hidden" name="action_type" id="action_type" value="save">

                <div class="evs-form-section">
                    <h2>Klantgegevens</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Naam</th>
                            <td><input type="text" name="naam" value="<?php echo esc_attr($quote['naam']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Email</th>
                            <td><input type="email" name="email" value="<?php echo esc_attr($quote['email']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Telefoon</th>
                            <td><input type="text" name="telefoon" value="<?php echo esc_attr($quote['telefoon']); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Adres</th>
                            <td><input type="text" name="adres" value="<?php echo esc_attr($quote['adres'] ?? ''); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                </div>

                <div class="evs-form-section">
                    <h2>Offerte Details</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Verdieping</th>
                            <td>
                                <select name="verdieping">
                                    <option value="begaande_grond" <?php selected($quote['verdieping'], 'begaande_grond'); ?>>Begaande grond</option>
                                    <option value="eerste_verdieping" <?php selected($quote['verdieping'], 'eerste_verdieping'); ?>>Eerste verdieping</option>
                                    <option value="zolder" <?php selected($quote['verdieping'], 'zolder'); ?>>Zolder</option>
                                    <option value="anders" <?php selected($quote['verdieping'], 'anders'); ?>>Anders</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Type Vloer</th>
                            <td>
                                <select name="type_vloer">
                                    <option value="cement" <?php selected($quote['type_vloer'], 'cement'); ?>>Cement dekvloer</option>
                                    <option value="tegel" <?php selected($quote['type_vloer'], 'tegel'); ?>>Tegelvloer</option>
                                    <option value="beton" <?php selected($quote['type_vloer'], 'beton'); ?>>Betonvloer</option>
                                    <option value="fermacel" <?php selected($quote['type_vloer'], 'fermacel'); ?>>Fermacelvloer</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Oppervlakte (m²)</th>
                            <td><input type="number" name="area_m2" value="<?php echo esc_attr($quote['area_m2']); ?>" step="0.1" class="small-text" /> m²</td>
                        </tr>
                        <tr>
                            <th scope="row">Warmtebron</th>
                            <td>
                                <select name="warmtebron">
                                    <option value="cv_ketel" <?php selected($quote['warmtebron'], 'cv_ketel'); ?>>CV ketel</option>
                                    <option value="hybride_warmtepomp" <?php selected($quote['warmtebron'], 'hybride_warmtepomp'); ?>>Hybride warmtepomp</option>
                                    <option value="volledige_warmtepomp" <?php selected($quote['warmtebron'], 'volledige_warmtepomp'); ?>>Volledige warmtepomp</option>
                                    <option value="stadsverwarming" <?php selected($quote['warmtebron'], 'stadsverwarming'); ?>>Stadsverwarming</option>
                                    <option value="toekomstige_warmtepomp" <?php selected($quote['warmtebron'], 'toekomstige_warmtepomp'); ?>>Toekomstige warmtepomp</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Verdeler Aansluiten</th>
                            <td><input type="checkbox" name="verdeler_aansluiten" value="1" <?php checked($quote['verdeler_aansluiten'], 1); ?> /> Ja</td>
                        </tr>
                        <tr>
                            <th scope="row">Vloer Dichtsmeren</th>
                            <td><input type="checkbox" name="vloer_dichtsmeren" value="1" <?php checked($quote['vloer_dichtsmeren'], 1); ?> /> Ja</td>
                        </tr>
                        <tr>
                            <th scope="row">Vloer Schuren</th>
                            <td><input type="checkbox" name="vloer_schuren" value="1" <?php checked($quote['vloer_schuren'], 1); ?> /> Ja</td>
                        </tr>
                        <tr>
                            <th scope="row">Montagedatum</th>
                            <td><input type="date" name="montagedatum" value="<?php echo esc_attr($quote['montagedatum']); ?>" /></td>
                        </tr>
                    </table>
                </div>

                <div class="evs-form-section">
                    <h2>Opmerkingen</h2>
                    <textarea name="opmerkingen" rows="5" class="large-text"><?php echo esc_textarea($quote['opmerkingen']); ?></textarea>
                </div>
            </form>
        </div>

        <div class="evs-admin-sidebar">
            <div class="evs-price-calculator-box">
                <h2>Prijsberekening</h2>
                <div id="price-breakdown">
                    <p><strong>Boren:</strong> <span id="boren-price">€0.00</span></p>
                    <p><strong>Verdeler:</strong> <span id="verdeler-price">€0.00</span></p>
                    <p><strong>Dichtsmeren:</strong> <span id="dichtsmeren-price">€0.00</span></p>
                    <p><strong>Schuren:</strong> <span id="schuren-price">€0.00</span></p>
                </div>
                <hr>
                <p class="total-price"><strong>Totaal:</strong> <span id="total-price">€0.00</span></p>
                <p class="strekkende-meter">Strekkende meter: <span id="strekkende-meter">0.0</span></p>
                <div class="evs-sidebar-tip">
                    <span class="dashicons dashicons-info"></span>
                    <p>Prijzen worden live bijgewerkt terwijl u het formulier aanpast.</p>
                </div>
            </div>
            <div class="evs-actions-box">
                <h2>Acties</h2>
                <button type="button" id="save-quote-btn" class="button button-primary evs-action-btn">Offerte Opslaan</button>
                <button type="button" id="send-quote-btn" class="button evs-action-btn">Offerte Versturen</button>
                <button type="button" id="create-invoice-btn" class="button evs-action-btn">Factuur Aanmaken</button>
            </div>
        </div>
    </div>
</div>
