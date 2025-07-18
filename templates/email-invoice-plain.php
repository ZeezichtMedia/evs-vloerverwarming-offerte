<?php echo esc_html($labels['subject']); ?>

<?php echo esc_html($labels['greeting']); ?>

<?php echo esc_html($labels['intro']); ?>

<?php echo esc_html($labels['invoice_details']); ?>
<?php echo esc_html($labels['invoice_number']); ?> <?php echo esc_html($invoice_data['invoice_number']); ?>
<?php echo esc_html($labels['invoice_date']); ?> <?php echo esc_html($invoice_data['invoice_date_formatted']); ?>
<?php echo esc_html($labels['due_date']); ?> <?php echo esc_html($invoice_data['due_date_formatted']); ?>

<?php echo esc_html($labels['customer_details']); ?>
<?php echo esc_html($labels['name']); ?> <?php echo esc_html($invoice_data['customer_name']); ?>
<?php if (!empty($invoice_data['customer_address'])): ?>
<?php echo esc_html($labels['address']); ?> <?php echo esc_html($invoice_data['customer_address']); ?>
<?php endif; ?>
<?php if (!empty($invoice_data['customer_postal']) || !empty($invoice_data['customer_city'])): ?>
<?php echo esc_html($labels['postal_city']); ?> <?php echo esc_html($invoice_data['customer_postal'] . ' ' . $invoice_data['customer_city']); ?>
<?php endif; ?>

<?php echo esc_html($labels['services']); ?>
<?php if ($invoice_data['drilling_price'] > 0): ?>
<?php echo esc_html($labels['drilling']); ?> € <?php echo number_format($invoice_data['drilling_price'], 2, ',', '.'); ?>
<?php endif; ?>
<?php if ($invoice_data['sealing_price'] > 0): ?>
<?php echo esc_html($labels['sealing']); ?> € <?php echo number_format($invoice_data['sealing_price'], 2, ',', '.'); ?>
<?php endif; ?>
<?php if ($invoice_data['verdeler_price'] > 0): ?>
<?php echo esc_html($labels['verdeler']); ?> € <?php echo number_format($invoice_data['verdeler_price'], 2, ',', '.'); ?>
<?php endif; ?>

<?php echo esc_html($labels['subtotal']); ?> € <?php echo number_format($invoice_data['subtotal'], 2, ',', '.'); ?>
<?php echo esc_html($labels['btw']); ?> € <?php echo number_format($invoice_data['btw_amount'], 2, ',', '.'); ?>
<?php echo esc_html($labels['total']); ?> € <?php echo number_format($invoice_data['total_amount'], 2, ',', '.'); ?>

<?php echo esc_html($labels['payment_info']); ?>
<?php echo esc_html($labels['payment_term']); ?>

<?php echo esc_html($labels['footer_thanks']); ?>
<?php echo esc_html($labels['footer_contact']); ?>

<?php echo esc_html($labels['footer_greeting']); ?>
<?php echo esc_html($labels['company_name']); ?>

---
<?php echo esc_html($labels['contact_info']); ?>
<?php echo esc_html($labels['company_details']); ?>
