<?php
/**
 * Template for the EVS Quote Form.
 *
 * This template can be overridden by copying it to yourtheme/evs-vloerverwarming/forms/quote-form.php.
 *
 * @var EVS_Form_Handler $this The form handler instance.
 * @var array $atts Shortcode attributes.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Get an instance of the pricing calculator to access dynamic values
$pricing_calculator = new EVS_Pricing_Calculator();
$privacy_policy_url = get_option('evs_privacy_policy_url', '#'); // Fallback to #

?>
<form id="evs-offerte-form" class="evs-form">
    <?php wp_nonce_field('evs_offerte_nonce', 'evs_nonce'); ?>

    <div class="evs-form-step" data-step="1">
        <h3 class="evs-step-title">Stap 1: Uw Vloer</h3>
        
        <div class="evs-form-group">
            <label class="evs-label">Op welke verdieping komt de vloerverwarming?</label>
            <div class="evs-options-container">
                <label class="evs-option-card">
                    <input type="radio" name="floor_level" value="begane-grond" class="evs-radio-input" checked>
                    <div class="evs-card-content">Begane grond</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="floor_level" value="eerste-verdieping" class="evs-radio-input">
                    <div class="evs-card-content">Eerste verdieping</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="floor_level" value="zolder" class="evs-radio-input">
                    <div class="evs-card-content">Zolder</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="floor_level" value="anders" class="evs-radio-input">
                    <div class="evs-card-content">Anders, namelijk...</div>
                </label>
            </div>
            <input type="text" name="floor_level_other" id="floor_level_other" class="evs-input evs-conditional-field" placeholder="Omschrijving andere verdieping">
        </div>

        <div class="evs-form-group">
            <label class="evs-label">Wat voor type vloer heeft u?</label>
            <div class="evs-options-container evs-options-container-cards">
                <label class="evs-option-card">
                    <input type="radio" name="floor_type" value="cement" class="evs-radio-input" checked>
                    <div class="evs-card-content">
                        <h4>Cement dekvloer</h4>
                        <p><?php echo '€' . number_format(EVS_Pricing_Calculator::CEMENT_TIER_4_PRICE, 2, ',', '.') . ' - €' . number_format(EVS_Pricing_Calculator::CEMENT_TIER_1_PRICE, 2, ',', '.'); ?> per meter</p>
                    </div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="floor_type" value="tile" class="evs-radio-input">
                    <div class="evs-card-content">
                        <h4>Tegelvloer</h4>
                        <p>+€<?php echo number_format(EVS_Pricing_Calculator::TEGELVLOER_PRICE_PER_METER, 2, ',', '.'); ?> per meter</p>
                    </div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="floor_type" value="concrete" class="evs-radio-input">
                    <div class="evs-card-content">
                        <h4>Betonvloer</h4>
                        <p>+€<?php echo number_format(EVS_Pricing_Calculator::BETONVLOER_PRICE_PER_METER, 2, ',', '.'); ?> per meter</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="evs-form-group">
            <label for="area_m2" class="evs-label">Hoeveel m²?</label>
            <input type="number" name="area_m2" id="area_m2" class="evs-input" placeholder="bv. 50" required min="1">
        </div>

        <button type="button" class="evs-btn evs-btn-next">Volgende</button>
    </div>

    <div class="evs-form-step" data-step="2" style="display: none;">
        <h3 class="evs-step-title">Stap 2: Installatie Opties</h3>

        <div class="evs-form-group">
            <label class="evs-label">Wat is de warmtebron?</label>
            <div class="evs-options-container">
                <label class="evs-option-card">
                    <input type="radio" name="heat_source" value="cv-ketel" class="evs-radio-input" checked>
                    <div class="evs-card-content">CV Ketel</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="heat_source" value="hybride-warmtepomp" class="evs-radio-input">
                    <div class="evs-card-content">Hybride warmtepomp</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="heat_source" value="volledige-warmtepomp" class="evs-radio-input">
                    <div class="evs-card-content">Volledige warmtepomp</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="heat_source" value="stadsverwarming" class="evs-radio-input">
                    <div class="evs-card-content">Stadsverwarming</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="heat_source" value="toekomstige-warmtepomp" class="evs-radio-input">
                    <div class="evs-card-content">Toekomstige warmtepomp</div>
                </label>
            </div>
        </div>

        <div class="evs-form-group">
            <label class="evs-label">Wilt u dat wij de verdeler aansluiten op de warmtebron? <span class="evs-price-indicator">+€<?php echo number_format(EVS_Pricing_Calculator::VERDELER_PRICE, 2, ',', '.'); ?></span></label>
            <div class="evs-options-container">
                <label class="evs-option-card">
                    <input type="radio" name="distributor" value="yes" class="evs-radio-input" checked>
                    <div class="evs-card-content">Ja</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="distributor" value="no" class="evs-radio-input">
                    <div class="evs-card-content">Nee</div>
                </label>
            </div>
        </div>

        <div class="evs-form-group">
            <label class="evs-label">Wilt u dat wij de sleuven dichtsmeren? <span class="evs-price-indicator">+€<?php echo number_format(EVS_Pricing_Calculator::DICHTSMEREN_PRICE_PER_M2, 2, ',', '.'); ?> per m²</span></label>
            <div class="evs-options-container">
                <label class="evs-option-card">
                    <input type="radio" name="sealing" value="yes" class="evs-radio-input" checked>
                    <div class="evs-card-content">Ja</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="sealing" value="no" class="evs-radio-input">
                    <div class="evs-card-content">Nee</div>
                </label>
            </div>
        </div>

        <div class="evs-checkbox-group evs-conditional-input" id="extra-schuren-group">
            <input type="checkbox" name="extra_schuren" id="extra_schuren" value="ja">
            <label for="extra_schuren">Mijn vloer is niet schoon/geëgaliseerd en moet extra geschuurd worden.</label>
            <small class="evs-price-info">+€<?php echo number_format(EVS_Pricing_Calculator::EXTRA_SCHUREN_PRICE_PER_M2, 2, ',', '.'); ?> per m²</small>
        </div>

        <button type="button" class="evs-btn evs-btn-prev">Vorige</button>
        <button type="button" class="evs-btn evs-btn-next">Volgende</button>
    </div>

    <div class="evs-form-step" data-step="3" style="display: none;">
        <h3 class="evs-step-title">Stap 3: Uw Gegevens</h3>

        <div class="evs-form-group">
            <label for="naam" class="evs-label">Naam</label>
            <input type="text" name="naam" id="naam" class="evs-input" required>
        </div>

        <div class="evs-form-group">
            <label for="email" class="evs-label">E-mailadres</label>
            <input type="email" name="email" id="email" class="evs-input" required>
        </div>

        <div class="evs-form-group">
            <label for="telefoon" class="evs-label">Telefoonnummer</label>
            <input type="tel" name="telefoon" id="telefoon" class="evs-input" required>
        </div>

        <div class="evs-form-group">
            <label for="adres" class="evs-label">Straat en huisnummer</label>
            <input type="text" name="adres" id="adres" class="evs-input" required>
        </div>
        <div class="evs-form-group evs-form-group-inline">
            <div>
                <label for="postcode" class="evs-label">Postcode</label>
                <input type="text" name="postcode" id="postcode" class="evs-input" required>
            </div>
            <div>
                <label for="plaats" class="evs-label">Plaats</label>
                <input type="text" name="plaats" id="plaats" class="evs-input" required>
            </div>
        </div>

        <div class="evs-form-group">
            <label class="evs-label">Gewenste montagedatum</label>
            <div class="evs-options-container">
                <label class="evs-option-card">
                    <input type="radio" name="montagedatum_type" value="spoed" class="evs-radio-input" checked>
                    <div class="evs-card-content">Zo spoedig mogelijk</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="montagedatum_type" value="in_overleg" class="evs-radio-input">
                    <div class="evs-card-content">In overleg</div>
                </label>
                <label class="evs-option-card">
                    <input type="radio" name="montagedatum_type" value="datum" class="evs-radio-input">
                    <div class="evs-card-content">Kies een datum:</div>
                </label>
            </div>
            <input type="date" name="montagedatum" id="montagedatum_picker" class="evs-input evs-conditional-field">
        </div>

        <div class="evs-form-group">
            <label for="opmerkingen" class="evs-label">Opmerkingen</label>
            <textarea name="opmerkingen" id="opmerkingen" class="evs-textarea" rows="4"></textarea>
        </div>

        <div class="evs-checkbox-group">
            <input type="checkbox" name="privacy_akkoord" id="privacy_akkoord" required>
            <label for="privacy_akkoord">Ik ga akkoord met de <a href="<?php echo esc_url($privacy_policy_url); ?>" target="_blank">privacyverklaring</a>.</label>
        </div>

        <button type="button" class="evs-btn evs-btn-prev">Vorige</button>
        <button type="submit" class="evs-btn evs-btn-submit">Offerte Aanvragen</button>
    </div>

    <div id="evs-form-feedback"></div>
</form>