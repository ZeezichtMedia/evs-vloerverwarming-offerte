<div class="wrap">
    <h1 class="wp-heading-inline">EVS Offertes</h1>
    
    <ul class="subsubsub">
        <li><a href="?page=evs-offertes&status=all" <?php echo $current_status === 'all' ? 'class="current"' : ''; ?>>Alle <span class="count">(<?php echo esc_html($status_counts['all'] ?? 0); ?>)</span></a></li>
        <li><a href="?page=evs-offertes&status=pending" <?php echo $current_status === 'pending' ? 'class="current"' : ''; ?>>In behandeling <span class="count">(<?php echo esc_html($status_counts['pending'] ?? 0); ?>)</span></a></li>
        <li><a href="?page=evs-offertes&status=sent" <?php echo $current_status === 'sent' ? 'class="current"' : ''; ?>>Verzonden <span class="count">(<?php echo esc_html($status_counts['sent'] ?? 0); ?>)</span></a></li>
        <li><a href="?page=evs-offertes&status=accepted" <?php echo $current_status === 'accepted' ? 'class="current"' : ''; ?>>Geaccepteerd <span class="count">(<?php echo esc_html($status_counts['accepted'] ?? 0); ?>)</span></a></li>
        <li><a href="?page=evs-offertes&status=completed" <?php echo $current_status === 'completed' ? 'class="current"' : ''; ?>>Voltooid <span class="count">(<?php echo esc_html($status_counts['completed'] ?? 0); ?>)</span></a></li>
    </ul>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Klant</th>
                <th scope="col">E-mail</th>
                <th scope="col">Type Vloer</th>
                <th scope="col">Oppervlakte</th>
                <th scope="col">Totaalprijs</th>
                <th scope="col">Status</th>
                <th scope="col">Datum</th>
                <th scope="col">Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($quotes)): ?>
                <tr>
                    <td colspan="9">Geen offertes gevonden.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($quotes as $quote): ?>
                    <tr>
                        <td><?php echo esc_html($quote['id']); ?></td>
                        <td><strong><?php echo esc_html($quote['naam']); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr($quote['email']); ?>"><?php echo esc_html($quote['email']); ?></a></td>
                        <td><?php echo esc_html($this->format_floor_type($quote['type_vloer'])); ?></td>
                        <td><?php echo number_format($quote['area_m2'], 1); ?> m²</td>
                        <td><strong>€<?php echo number_format($quote['total_price'], 2); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($quote['status']); ?>">
                                <?php echo esc_html($this->format_status($quote['status'])); ?>
                            </span>
                        </td>
                        <td><?php 
                            if (!empty($quote['created_at']) && $quote['created_at'] !== '0000-00-00 00:00:00') {
                                echo date('d-m-Y H:i', strtotime($quote['created_at']));
                            } else {
                                echo 'Geen datum';
                            }
                        ?></td>
                        <td class="quote-actions">
                                <?php
                                $edit_url = add_query_arg([
                                    'page'         => 'evs-offertes',
                                    'admin_action' => 'edit',
                                    'quote_id'     => $quote['id'],
                                ], admin_url('admin.php'));

                                $nonce_action = 'evs_admin_action_edit_' . $quote['id'];
                                ?>
                                <a href="<?php echo esc_url(wp_nonce_url($edit_url, $nonce_action)); ?>" class="button button-small">Bewerken</a>
                            
                            <?php if ($quote['status'] !== 'offerte_verstuurd'): ?>
                                <a href="<?php echo wp_nonce_url('?page=evs-offertes&action=send_quote&quote_id=' . $quote['id'], 'evs_admin_action'); ?>" 
                                   class="button button-small button-primary">Versturen</a>
                            <?php endif; ?>
                            
                            <?php if ($quote['status'] === 'accepted'): ?>
                                <a href="<?php echo add_query_arg(['page' => 'evs-edit-quote', 'quote_id' => $quote['id']], admin_url('admin.php')); ?>" 
                                   class="button button-small button-primary">🎉 Factuur Maken</a>
                            <?php elseif ($quote['status'] === 'goedgekeurd'): ?>
                                <a href="<?php echo wp_nonce_url('?page=evs-offertes&action=create_invoice&quote_id=' . $quote['id'], 'evs_admin_action'); ?>" 
                                   class="button button-small">Factuur maken</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
