<?php
if (!defined('ABSPATH')) {
    exit;
}

$invoice_id = intval($_GET['invoice_id'] ?? 0);
?>

<div class="wrap">
    <h1><?php esc_html_e('Factuur Bewerken', 'evs-vloerverwarming'); ?> - <?php echo esc_html($invoice['invoice_number']); ?></h1>
    
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <div id="post-body-content">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Factuurgegevens', 'evs-vloerverwarming'); ?></h2>
                    </div>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('update_invoice_' . $invoice_id); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Factuurnummer', 'evs-vloerverwarming'); ?></th>
                                    <td><strong><?php echo esc_html($invoice['invoice_number']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Status', 'evs-vloerverwarming'); ?></th>
                                    <td>
                                        <select name="status" id="status">
                                            <option value="concept" <?php selected($invoice['status'], 'concept'); ?>><?php esc_html_e('Concept', 'evs-vloerverwarming'); ?></option>
                                            <option value="sent" <?php selected($invoice['status'], 'sent'); ?>><?php esc_html_e('Verzonden', 'evs-vloerverwarming'); ?></option>
                                            <option value="paid" <?php selected($invoice['status'], 'paid'); ?>><?php esc_html_e('Betaald', 'evs-vloerverwarming'); ?></option>
                                            <option value="overdue" <?php selected($invoice['status'], 'overdue'); ?>><?php esc_html_e('Vervallen', 'evs-vloerverwarming'); ?></option>
                                            <option value="cancelled" <?php selected($invoice['status'], 'cancelled'); ?>><?php esc_html_e('Geannuleerd', 'evs-vloerverwarming'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Factuurdatum', 'evs-vloerverwarming'); ?></th>
                                    <td><?php echo esc_html(date('d-m-Y H:i', strtotime($invoice['invoice_date']))); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Vervaldatum', 'evs-vloerverwarming'); ?></th>
                                    <td><?php echo esc_html(date('d-m-Y', strtotime($invoice['due_date']))); ?></td>
                                </tr>
                                <?php if (!empty($invoice['sent_date'])): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Verzonden op', 'evs-vloerverwarming'); ?></th>
                                    <td><?php echo esc_html(date('d-m-Y H:i', strtotime($invoice['sent_date']))); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($invoice['paid_date'])): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Betaald op', 'evs-vloerverwarming'); ?></th>
                                    <td><?php echo esc_html(date('d-m-Y H:i', strtotime($invoice['paid_date']))); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <h3><?php esc_html_e('Klantgegevens', 'evs-vloerverwarming'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="customer_name"><?php esc_html_e('Naam', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($invoice['customer_name']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="customer_email"><?php esc_html_e('E-mail', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($invoice['customer_email']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="customer_phone"><?php esc_html_e('Telefoon', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="text" id="customer_phone" name="customer_phone" value="<?php echo esc_attr($invoice['customer_phone']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="customer_address"><?php esc_html_e('Adres', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="text" id="customer_address" name="customer_address" value="<?php echo esc_attr($invoice['customer_address']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="customer_postal"><?php esc_html_e('Postcode', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="text" id="customer_postal" name="customer_postal" value="<?php echo esc_attr($invoice['customer_postal']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="customer_city"><?php esc_html_e('Plaats', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="text" id="customer_city" name="customer_city" value="<?php echo esc_attr($invoice['customer_city']); ?>" class="regular-text" /></td>
                                </tr>
                            </table>

                            <h3><?php esc_html_e('Prijzen', 'evs-vloerverwarming'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="drilling_price"><?php esc_html_e('Boorprijs', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="number" step="0.01" id="drilling_price" name="drilling_price" value="<?php echo esc_attr($invoice['drilling_price']); ?>" class="regular-text" /> €</td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="sealing_price"><?php esc_html_e('Dichtsmeerprijs', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="number" step="0.01" id="sealing_price" name="sealing_price" value="<?php echo esc_attr($invoice['sealing_price']); ?>" class="regular-text" /> €</td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="verdeler_price"><?php esc_html_e('Verdelerprijs', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="number" step="0.01" id="verdeler_price" name="verdeler_price" value="<?php echo esc_attr($invoice['verdeler_price']); ?>" class="regular-text" /> €</td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Totaalbedrag (incl. BTW)', 'evs-vloerverwarming'); ?></th>
                                    <td><strong>€ <?php echo number_format($invoice['total_amount'], 2, ',', '.'); ?></strong></td>
                                </tr>
                            </table>

                            <h3><?php esc_html_e('Betaling', 'evs-vloerverwarming'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="payment_method"><?php esc_html_e('Betaalmethode', 'evs-vloerverwarming'); ?></label></th>
                                    <td>
                                        <select name="payment_method" id="payment_method">
                                            <option value=""><?php esc_html_e('Selecteer...', 'evs-vloerverwarming'); ?></option>
                                            <option value="bank_transfer" <?php selected($invoice['payment_method'], 'bank_transfer'); ?>><?php esc_html_e('Bankoverschrijving', 'evs-vloerverwarming'); ?></option>
                                            <option value="ideal" <?php selected($invoice['payment_method'], 'ideal'); ?>><?php esc_html_e('iDEAL', 'evs-vloerverwarming'); ?></option>
                                            <option value="cash" <?php selected($invoice['payment_method'], 'cash'); ?>><?php esc_html_e('Contant', 'evs-vloerverwarming'); ?></option>
                                            <option value="pin" <?php selected($invoice['payment_method'], 'pin'); ?>><?php esc_html_e('PIN', 'evs-vloerverwarming'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="payment_reference"><?php esc_html_e('Betalingsreferentie', 'evs-vloerverwarming'); ?></label></th>
                                    <td><input type="text" id="payment_reference" name="payment_reference" value="<?php echo esc_attr($invoice['payment_reference']); ?>" class="regular-text" /></td>
                                </tr>
                            </table>

                            <h3><?php esc_html_e('Notities', 'evs-vloerverwarming'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="notes"><?php esc_html_e('Interne notities', 'evs-vloerverwarming'); ?></label></th>
                                    <td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea($invoice['notes']); ?></textarea></td>
                                </tr>
                            </table>

                            <p class="submit">
                                <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php esc_attr_e('Factuur Opslaan', 'evs-vloerverwarming'); ?>" style="margin-right: 10px;">
                                <a href="<?php echo admin_url('admin.php?page=evs-invoices'); ?>" class="button button-secondary"><?php esc_html_e('Terug naar overzicht', 'evs-vloerverwarming'); ?></a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <div id="postbox-container-1" class="postbox-container">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Factuur Acties', 'evs-vloerverwarming'); ?></h2>
                    </div>
                    <div class="inside">
                        <div class="submitbox">
                            <div class="misc-pub-section">
                                <strong><?php esc_html_e('Status:', 'evs-vloerverwarming'); ?></strong> 
                                <span class="status-<?php echo esc_attr($invoice['status']); ?>">
                                    <?php 
                                    $status_labels = [
                                        'concept' => __('Concept', 'evs-vloerverwarming'),
                                        'sent' => __('Verzonden', 'evs-vloerverwarming'),
                                        'paid' => __('Betaald', 'evs-vloerverwarming'),
                                        'overdue' => __('Vervallen', 'evs-vloerverwarming'),
                                        'cancelled' => __('Geannuleerd', 'evs-vloerverwarming')
                                    ];
                                    echo esc_html($status_labels[$invoice['status']] ?? ucfirst($invoice['status']));
                                    ?>
                                </span>
                            </div>
                            
                            <?php if ($invoice['status'] !== 'sent' && $invoice['status'] !== 'paid'): ?>
                            <div class="misc-pub-section">
                                <form method="post" action="">
                                    <?php wp_nonce_field('evs_send_invoice_' . $invoice_id, 'evs_send_invoice_nonce'); ?>
                                    <input type="submit" name="send_invoice" class="button button-large button-primary" value="<?php esc_attr_e('Factuur Verzenden', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center;">
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($invoice['status'] === 'sent'): ?>
                            <div class="misc-pub-section">
                                <form method="post" action="">
                                    <?php wp_nonce_field('evs_mark_paid_' . $invoice_id, 'evs_mark_paid_nonce'); ?>
                                    <input type="submit" name="mark_paid" class="button button-large" value="<?php esc_attr_e('Markeer als Betaald', 'evs-vloerverwarming'); ?>" style="width:100%; text-align:center;">
                                </form>
                            </div>
                            <?php endif; ?>
                            
                            <div class="misc-pub-section">
                                <a href="<?php echo admin_url('admin.php?page=evs-invoices&action=delete&invoice_id=' . $invoice_id); ?>" 
                                   class="button button-link-delete" 
                                   onclick="return confirm('<?php esc_attr_e('Weet je zeker dat je deze factuur wilt verwijderen?', 'evs-vloerverwarming'); ?>')">
                                    <?php esc_html_e('Factuur Verwijderen', 'evs-vloerverwarming'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle"><?php esc_html_e('Gerelateerde Offerte', 'evs-vloerverwarming'); ?></h2>
                    </div>
                    <div class="inside">
                        <?php if ($invoice['quote_id']): ?>
                            <p>
                                <a href="<?php echo admin_url('admin.php?page=evs-edit-offer&offer_id=' . $invoice['quote_id']); ?>" class="button">
                                    <?php esc_html_e('Bekijk Offerte', 'evs-vloerverwarming'); ?> #<?php echo esc_html($invoice['quote_id']); ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <p><?php esc_html_e('Geen gerelateerde offerte gevonden.', 'evs-vloerverwarming'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.status-concept { color: #666; }
.status-sent { color: #0073aa; }
.status-paid { color: #46b450; }
.status-overdue { color: #dc3232; }
.status-cancelled { color: #666; text-decoration: line-through; }
</style>
