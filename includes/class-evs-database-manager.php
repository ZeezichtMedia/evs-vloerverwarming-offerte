<?php
/**
 * EVS Database Manager
 * 
 * Handles all database operations for the plugin
 * Separates database logic from main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVS_Database_Manager {
    
    private $wpdb;
    private $table_name;
    private $invoices_table_name;
    
    /**
     * Constructor
     */
    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'evs_offertes';
        $this->invoices_table_name = $this->wpdb->prefix . 'evs_invoices';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Create quotes table
        $quotes_sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            naam varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            telefoon varchar(20),
            adres text,
            postcode varchar(10),
            plaats varchar(100),
            verdieping varchar(50) NOT NULL,
            verdieping_anders varchar(255),
            type_vloer varchar(50) NOT NULL,
            area_m2 decimal(10,2) NOT NULL,
            strekkende_meter decimal(10,2) NOT NULL,
            warmtebron varchar(50) NOT NULL,
            verdeler_aansluiten TINYINT(1) DEFAULT 0 NOT NULL,
            verdeler_price decimal(10,2) DEFAULT 0,
            vloer_dichtsmeren TINYINT(1) DEFAULT 0 NOT NULL,
            vloer_schuren TINYINT(1) DEFAULT 0 NOT NULL,
            montagedatum_type varchar(20) NOT NULL,
            montagedatum date,
            opmerkingen text,
            drilling_price decimal(10,2) NOT NULL,
            sealing_price decimal(10,2) DEFAULT 0,
            total_price decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        
        // Create invoices table
        $invoices_sql = "CREATE TABLE {$this->invoices_table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            quote_id mediumint(9) NOT NULL,
            invoice_number varchar(50) NOT NULL UNIQUE,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_address text,
            invoice_date date NOT NULL,
            due_date date NOT NULL,
            subtotal decimal(10,2) NOT NULL,
            vat_amount decimal(10,2) NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'draft',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_quote_id (quote_id),
            INDEX idx_invoice_number (invoice_number),
            INDEX idx_status (status),
            INDEX idx_invoice_date (invoice_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($quotes_sql);
        dbDelta($invoices_sql);
    }
    
    /**
     * Save quote to database
     * 
     * @param array $quote_data Quote data
     * @return int|false Quote ID or false on failure
     */
    public function save_quote($quote_data) {
        
        
        $data = array(
            'naam' => $quote_data['naam'],
            'email' => $quote_data['email'],
            'telefoon' => $quote_data['telefoon'] ?? '',
            'adres' => $quote_data['adres'] ?? '',
            'postcode' => $quote_data['postcode'] ?? '',
            'plaats' => $quote_data['plaats'] ?? '',
            'verdieping' => $quote_data['verdieping'],
            'verdieping_anders' => $quote_data['verdieping_anders'] ?? '',
            'type_vloer' => $quote_data['type_vloer'],
            'area_m2' => $quote_data['area_m2'],
            'strekkende_meter' => $quote_data['strekkende_meter'],
            'warmtebron' => $quote_data['warmtebron'],
            'verdeler_aansluiten' => $quote_data['verdeler_aansluiten'],
            'verdeler_price' => $quote_data['verdeler_price'],
            'vloer_dichtsmeren' => $quote_data['vloer_dichtsmeren'],
            'vloer_schuren' => $quote_data['vloer_schuren'] ?? 0,
            'montagedatum_type' => $quote_data['montagedatum_type'],
            'montagedatum' => !empty($quote_data['montagedatum']) ? $quote_data['montagedatum'] : null,
            'opmerkingen' => $quote_data['opmerkingen'] ?? '',
            'drilling_price' => $quote_data['drilling_price'],
            'sealing_price' => $quote_data['sealing_price'],
            'total_price' => $quote_data['total_price'],
            'status' => 'pending'
        );
        
        $formats = $this->get_quote_data_formats(array_keys($data));
        
        $result = $this->wpdb->insert($this->table_name, $data, $formats);
        
        if ($result === false) {
            error_log('EVS Database Error: ' . $this->wpdb->last_error);
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get quote by ID
     * 
     * @param int $quote_id Quote ID
     * @return array|null Quote data or null if not found
     */
    public function get_quote($quote_id) {
        
        
        $query = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $quote_id);
        return $this->wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Update quote
     * 
     * @param int $quote_id Quote ID
     * @param array $quote_data Updated quote data (only fields to update)
     * @return bool Success status
     */
    public function update_quote($quote_id, $quote_data) {
        

        if (empty($quote_data)) {
            return true; // Nothing to update
        }

        $where = array('id' => $quote_id);

        $formats = $this->get_quote_data_formats(array_keys($quote_data));

        $where_format = array('%d'); // id is an integer

        $result = $this->wpdb->update($this->table_name, $quote_data, $where, $formats, $where_format);

        if ($result === false) {
            error_log('EVS Database Update Error: ' . $this->wpdb->last_error);
            return false;
        }
        return true;
    }

    /**
     * Get the database format for each piece of quote data.
     *
     * @param array $data_keys The keys of the data.
     * @return array The formats.
     */
    private function get_quote_data_formats($data_keys) {
        $formats = [];
        $float_fields = ['total_price', 'price_per_meter', 'connection_costs', 'sealing_costs', 'sanding_costs', 'milling_costs', 'verdeler_price', 'drilling_price', 'sealing_price', 'strekkende_meter'];
        $float_fields[] = 'area_m2'; // Add area_m2 to float fields
        $int_fields = ['id', 'verdeler_aansluiten', 'vloer_dichtsmeren', 'vloer_schuren'];

        foreach ($data_keys as $key) {
            if (in_array($key, $float_fields)) {
                $formats[] = '%f';
            } elseif (in_array($key, $int_fields)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }
    
    /**
     * Delete quote
     * 
     * @param int $quote_id Quote ID
     * @return bool Success status
     */
    public function delete_quote($quote_id) {
        $result = $this->wpdb->delete($this->table_name, array('id' => $quote_id), array('%d'));
        return $result !== false;
    }

    /**
     * Creates an invoice from a given quote ID.
     *
     * @param int $quote_id The ID of the quote to convert.
     * @return int|false The new invoice ID on success, false on failure.
     */
    public function create_invoice_from_quote($quote_id) {
        $quote = $this->get_quote($quote_id);

        if (!$quote) {
            error_log('EVS Invoice Error: Could not find quote with ID ' . $quote_id);
            return false;
        }

        // Check if an invoice already exists for this quote
        $existing_invoice = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->invoices_table_name} WHERE quote_id = %d",
            $quote_id
        ));
        if ($existing_invoice) {
            error_log('EVS Invoice Error: An invoice already exists for quote ID ' . $quote_id);
            return false; // Prevent creating a duplicate
        }

        // Populate all required fields
        $total_amount = (float) $quote['total_price'];
        $subtotal = $total_amount / 1.21; // Assuming 21% VAT
        $vat_amount = $total_amount - $subtotal;

        $invoice_data = [
            'quote_id'        => $quote_id,
            'invoice_number'  => $this->get_next_invoice_number(), // Generate a unique number
            'customer_name'   => $quote['naam'],
            'customer_email'  => $quote['email'],
            'customer_address' => trim($quote['adres'] . ', ' . $quote['postcode'] . ' ' . $quote['plaats'], ', '),
            'subtotal'        => $subtotal,
            'vat_amount'      => $vat_amount,
            'total_amount'    => $total_amount,
            'status'          => 'unpaid', // 'unpaid' is a more logical status than 'draft'
            'invoice_date'    => current_time('Y-m-d'),
            'due_date'        => date('Y-m-d', strtotime('+30 days')),
        ];

        $formats = ['%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s'];
        $result = $this->wpdb->insert($this->invoices_table_name, $invoice_data, $formats);

        if ($result === false) {
            error_log('EVS Invoice Error: ' . $this->wpdb->last_error);
            return false;
        }

        $this->update_quote_status($quote_id, 'invoiced');

        return $this->wpdb->insert_id;
    }
    
    /**
     * Get all quotes with optional filtering
     * 
     * @param array $args Query arguments
     * @return array Quotes data
     */
    public function get_quotes($args = array()) {
        
        
        $defaults = array(
            'status' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $allowed_orderby = ['id', 'naam', 'email', 'total_price', 'created_at', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
        $order_sql = sprintf('ORDER BY %s %s', $orderby, $order);
        
        $limit_sql = sprintf('LIMIT %d OFFSET %d', (int)$args['limit'], (int)$args['offset']);
        
        // Prepare the query. wpdb->prepare can safely handle an empty array for values.
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} {$where_sql} {$order_sql} {$limit_sql}",
            $where_values
        );

        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get quotes count
     * 
     * @param string $status Optional status filter
     * @return int Number of quotes
     */
    public function get_quotes_count($status = '') {
        
        
        if (!empty($status)) {
            $query = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status);
        } else {
            $query = "SELECT COUNT(*) FROM {$this->table_name}";
        }
        
        return (int) $this->wpdb->get_var($query);
    }
    
    /**
     * Update quote status
     * 
     * @param int $quote_id Quote ID
     * @param string $status New status
     * @return bool Success status
     */
    public function update_quote_status($quote_id, $status) {
        
        
        $result = $this->wpdb->update(
            $this->table_name,
            array('status' => $status),
            array('id' => $quote_id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Save invoice to database
     * 
     * @param array $invoice_data Invoice data
     * @return int|false Invoice ID or false on failure
     */
    public function save_invoice($invoice_data) {
        
        
        $data = array(
            'quote_id' => $invoice_data['quote_id'],
            'invoice_number' => $invoice_data['invoice_number'],
            'customer_name' => $invoice_data['customer_name'],
            'customer_email' => $invoice_data['customer_email'],
            'customer_address' => $invoice_data['customer_address'] ?? '',
            'invoice_date' => $invoice_data['invoice_date'],
            'due_date' => $invoice_data['due_date'],
            'subtotal' => $invoice_data['subtotal'],
            'vat_amount' => $invoice_data['vat_amount'],
            'total_amount' => $invoice_data['total_amount'],
            'status' => $invoice_data['status'] ?? 'draft',
            'notes' => $invoice_data['notes'] ?? ''
        );
        
        $formats = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s');
        
        $result = $this->wpdb->insert($this->invoices_table_name, $data, $formats);
        
        if ($result === false) {
            error_log('EVS Invoice Database Error: ' . $this->wpdb->last_error);
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get invoice by ID
     * 
     * @param int $invoice_id Invoice ID
     * @return array|null Invoice data or null if not found
     */
    public function get_invoice($invoice_id) {
        
        
        $query = $this->wpdb->prepare("SELECT * FROM {$this->invoices_table_name} WHERE id = %d", $invoice_id);
        return $this->wpdb->get_row($query, ARRAY_A);
    }
    
    /**
     * Get next invoice number
     * 
     * @return string Next invoice number
     */


    public function get_next_invoice_number() {
        
        
        $year = date('Y');
        $prefix = 'EVS-' . $year . '-';
        
        $query = $this->wpdb->prepare(
            "SELECT invoice_number FROM {$this->invoices_table_name} 
             WHERE invoice_number LIKE %s 
             ORDER BY invoice_number DESC 
             LIMIT 1",
            $prefix . '%'
        );
        
        $last_number = $this->wpdb->get_var($query);
        
        if ($last_number) {
            $number_part = (int) str_replace($prefix, '', $last_number);
            $next_number = $number_part + 1;
        } else {
            $next_number = 1;
        }
        
        return $prefix . str_pad($next_number, 4, '0', STR_PAD_LEFT);
    }
}
