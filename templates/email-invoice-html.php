<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($labels['subject']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2c5aa0; color: white; padding: 20px; text-align: center; margin-bottom: 30px; }
        .content { background-color: #f9f9f9; padding: 25px; border-radius: 8px; margin-bottom: 20px; }
        .invoice-details { background-color: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .services-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .services-table th, .services-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .services-table th { background-color: #f5f5f5; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f0f8ff; }
        .payment-info { background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .footer { text-align: center; padding: 20px; background-color: #f8f9fa; color: #666; font-size: 14px; margin-top: 30px; }
        .company-details { margin-top: 10px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo esc_html($labels['company_name']); ?></h1>
        <h2><?php echo esc_html($labels['subject']); ?></h2>
    </div>

    <div class="content">
        <p><?php echo esc_html($labels['greeting']); ?></p>
        <p><?php echo esc_html($labels['intro']); ?></p>

        <div class="invoice-details">
            <h3><?php echo esc_html($labels['invoice_details']); ?></h3>
            <table style="width: 100%;">
                <tr>
                    <td><strong><?php echo esc_html($labels['invoice_number']); ?></strong></td>
                    <td><?php echo esc_html($invoice_data['invoice_number']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html($labels['invoice_date']); ?></strong></td>
                    <td><?php echo esc_html($invoice_data['invoice_date_formatted']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html($labels['due_date']); ?></strong></td>
                    <td><?php echo esc_html($invoice_data['due_date_formatted']); ?></td>
                </tr>
            </table>
        </div>

        <div class="invoice-details">
            <h3><?php echo esc_html($labels['customer_details']); ?></h3>
            <p>
                <strong><?php echo esc_html($labels['name']); ?></strong> <?php echo esc_html($invoice_data['customer_name']); ?><br>
                <?php if (!empty($invoice_data['customer_address'])): ?>
                    <strong><?php echo esc_html($labels['address']); ?></strong> <?php echo esc_html($invoice_data['customer_address']); ?><br>
                <?php endif; ?>
                <?php if (!empty($invoice_data['customer_postal']) || !empty($invoice_data['customer_city'])): ?>
                    <strong><?php echo esc_html($labels['postal_city']); ?></strong> <?php echo esc_html($invoice_data['customer_postal'] . ' ' . $invoice_data['customer_city']); ?><br>
                <?php endif; ?>
            </p>
        </div>

        <div class="invoice-details">
            <h3><?php echo esc_html($labels['services']); ?></h3>
            <table class="services-table">
                <thead>
                    <tr>
                        <th>Omschrijving</th>
                        <th style="text-align: right;">Bedrag</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoice_data['drilling_price'] > 0): ?>
                    <tr>
                        <td><?php echo esc_html($labels['drilling']); ?></td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice_data['drilling_price'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($invoice_data['sealing_price'] > 0): ?>
                    <tr>
                        <td><?php echo esc_html($labels['sealing']); ?></td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice_data['sealing_price'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($invoice_data['verdeler_price'] > 0): ?>
                    <tr>
                        <td><?php echo esc_html($labels['verdeler']); ?></td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice_data['verdeler_price'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <td><strong><?php echo esc_html($labels['subtotal']); ?></strong></td>
                        <td style="text-align: right;"><strong>€ <?php echo number_format($invoice_data['subtotal'], 2, ',', '.'); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php echo esc_html($labels['btw']); ?></td>
                        <td style="text-align: right;">€ <?php echo number_format($invoice_data['btw_amount'], 2, ',', '.'); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong><?php echo esc_html($labels['total']); ?></strong></td>
                        <td style="text-align: right;"><strong>€ <?php echo number_format($invoice_data['total_amount'], 2, ',', '.'); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="payment-info">
            <h3><?php echo esc_html($labels['payment_info']); ?></h3>
            <p><?php echo esc_html($labels['payment_term']); ?></p>
        </div>

        <p><?php echo esc_html($labels['footer_thanks']); ?></p>
        <p><?php echo esc_html($labels['footer_contact']); ?></p>
        
        <p><?php echo esc_html($labels['footer_greeting']); ?><br>
        <strong><?php echo esc_html($labels['company_name']); ?></strong></p>
    </div>

    <div class="footer">
        <p><?php echo esc_html($labels['contact_info']); ?></p>
        <div class="company-details">
            <p><?php echo esc_html($labels['company_details']); ?></p>
        </div>
    </div>
</body>
</html>
