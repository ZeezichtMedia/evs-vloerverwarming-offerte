<?php
/**
 * EVS Email Service
 *
 * Handles all email functionality for the plugin using templates.
 * Separates email logic from main plugin class for better maintainability.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class EVS_Email_Service {

    /**
     * Constructor to hook in email template actions.
     */
    public function __construct() {
        add_action('evs_email_header', [$this, 'get_email_header']);
        add_action('evs_email_footer', [$this, 'get_email_footer']);
    }

    /**
     * Send the admin notification email.
     */
    public function send_admin_notification($quote_data, $quote_id) {
        $to = get_option('admin_email');
        $subject = 'Nieuwe offerte aanvraag - EVS Vloerverwarming #' . $quote_id;
        $message = $this->generate_admin_email_content($quote_data, $quote_id);
        
        return $this->_send_mail($to, $subject, $message);
    }

    /**
     * Send the customer confirmation email.
     */
    public function send_customer_confirmation($quote_data, $quote_id) {
        if (empty($quote_data['email'])) {
            return false;
        }
        
        $to = $quote_data['email'];
        $subject = 'Bevestiging van uw offerte aanvraag - EVS Vloerverwarmingen';
        $message = $this->generate_customer_email_content($quote_data, $quote_id);
        
        return $this->_send_mail($to, $subject, $message);
    }

    /**
     * Send the official quote to the customer.
     */
    public function send_quote_to_customer($quote_data, $quote_id) {
        if (empty($quote_data['email'])) {
            return false;
        }

        $to = $quote_data['email'];
        $subject = 'Uw offerte van EVS Vloerverwarmingen - #' . $quote_id;
        $message = $this->generate_quote_email_content($quote_data, $quote_id);

        return $this->_send_mail($to, $subject, $message);
    }

    /**
     * Send invoice to customer.
     */
    public function send_invoice_to_customer($invoice_data, $invoice_id) {
        if (empty($invoice_data['customer_email'])) {
            return false;
        }

        $to = $invoice_data['customer_email'];
        $subject = 'Uw factuur van EVS Vloerverwarmingen - #' . $invoice_data['invoice_number'];
        $message = $this->generate_invoice_email_content($invoice_data, $invoice_id);

        return $this->_send_mail($to, $subject, $message);
    }

    /**
     * Centralized mail sending function.
     */
    private function _send_mail($to, $subject, $message) {
        $admin_email = get_option('admin_email');
        $from_name = get_bloginfo('name');
        
        // Gebruik de robuuste WordPress-manier om de host te krijgen.
        $site_host = wp_parse_url(get_site_url(), PHP_URL_HOST);
        
        // Fix localhost issue for development
        if ($site_host === 'localhost' || strpos($site_host, 'localhost') !== false) {
            $from_email = $admin_email; // Use admin email for localhost
        } else {
            $from_email = 'noreply@' . preg_replace('#^www\.#' , '', $site_host);
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_name . ' <' . $admin_email . '>'
        ];

        $sent = wp_mail($to, $subject, $message, $headers);

        if (!$sent) {
            $error_details = [
                'to' => $to,
                'subject' => $subject,
                'from_email' => $from_email,
                'headers' => $headers,
                'message_length' => strlen($message)
            ];
            error_log(
                'EVS Plugin: Email failed to send. Details: ' . print_r($error_details, true)
            );
            
            // Also log WordPress mail errors if available
            global $phpmailer;
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                error_log('EVS Plugin: PHPMailer Error: ' . $phpmailer->ErrorInfo);
            }
        } else {
            error_log('EVS Plugin: Email sent successfully to: ' . $to . ' Subject: ' . $subject);
        }

        return $sent;
    }

    /**
     * Generate email content from a template file.
     */
    private function get_templated_email_content($template_name, $args) {
        extract($args);
        $email_service = $this; // Make email service available in template
        ob_start();
        include(EVS_IMPROVED_PATH . 'templates/emails/' . $template_name);
        return ob_get_clean();
    }

    private function generate_admin_email_content($quote_data, $quote_id) {
        return $this->get_templated_email_content('admin-notification.php', [
            'quote_data' => $quote_data,
            'quote_id' => $quote_id,
            'email_heading' => 'Nieuwe Offerte Aanvraag',
        ]);
    }

    private function generate_customer_email_content($quote_data, $quote_id) {
        return $this->get_templated_email_content('customer-confirmation.php', [
            'quote_data' => $quote_data,
            'quote_id' => $quote_id,
            'email_heading' => 'Bedankt voor uw aanvraag',
        ]);
    }

    private function generate_quote_email_content($quote_data, $quote_id) {
        return $this->get_templated_email_content('customer-quote.php', [
            'quote_data' => $quote_data,
            'quote_id' => $quote_id,
            'email_heading' => 'Uw Offerte #' . $quote_id,
        ]);
    }

    private function generate_invoice_email_content($invoice_data, $invoice_id) {
        return $this->get_templated_email_content('customer-invoice.php', [
            'invoice_data' => $invoice_data,
            'invoice_id' => $invoice_id,
            'email_heading' => 'Uw Factuur #' . $invoice_data['invoice_number'],
        ]);
    }

    /**
     * Load email header template.
     */
    public function get_email_header($email_heading) {
        include(EVS_IMPROVED_PATH . 'templates/emails/email-header.php');
    }

    /**
     * Load email footer template.
     */
    public function get_email_footer() {
        include(EVS_IMPROVED_PATH . 'templates/emails/email-footer.php');
    }

    /**
     * Format field values for display in emails.
     */
    public function format_field_value($field_type, $value, $extra_value = null) {
        $formats = [
            'verdieping' => [
                'begaande_grond' => 'Begaande grond',
                'eerste_verdieping' => 'Eerste verdieping',
                'zolder' => 'Zolder',
                'anders' => 'Anders, namelijk: ' . esc_html($extra_value)
            ],
            'type_vloer' => [
                'cement_dekvloer' => 'Cement dekvloer',
                'tegelvloer' => 'Tegelvloer',
                'betonvloer' => 'Betonvloer',
                'fermacellvloer' => 'Fermacellvloer'
            ],
            'warmtebron' => [
                'cv_ketel' => 'CV ketel',
                'hybride_warmtepomp' => 'Hybride warmtepomp',
                'volledige_warmtepomp' => 'Volledige warmtepomp',
                'stadsverwarming' => 'Stadsverwarming',
                'toekomstige_warmtepomp' => 'Toekomstige warmtepomp'
            ],
            'verdeler_aansluiten' => [
                'ja' => 'Ja',
                'nee' => 'Nee'
            ],
            'vloer_dichtsmeren' => [
                'ja' => 'Ja',
                'nee' => 'Nee'
            ],
            'montagedatum_type' => [
                'spoed' => 'Spoed',
                'in_overleg' => 'In overleg',
                'datum' => !empty($extra_value) ? date('d-m-Y', strtotime($extra_value)) : 'Datum niet opgegeven',
            ],
        ];

        return $formats[$field_type][$value] ?? ucwords(str_replace('_', ' ', $value));
    }
}
