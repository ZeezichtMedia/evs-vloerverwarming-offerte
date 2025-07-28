<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class EVS_Invoices_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Factuur',
            'plural'   => 'Facturen',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'invoice_number' => 'Factuurnummer',
            'customer_name'  => 'Klantnaam',
            'created_at'     => 'Datum',
            'total_amount'   => 'Totaalbedrag',
            'status'         => 'Status'
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_invoices';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Validate orderby parameter against a whitelist of sortable columns
        $sortable_columns = $this->get_sortable_columns();
        $orderby = 'created_at'; // Secure default
        if (isset($_GET['orderby']) && array_key_exists($_GET['orderby'], $sortable_columns)) {
            $orderby = $_GET['orderby'];
        }

        // Validate order parameter
        $order = 'desc'; // Secure default
        if (isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc'])) {
            $order = strtolower($_GET['order']);
        }

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // The query is now safe as $orderby and $order are whitelisted
        $query = "SELECT * FROM $table_name ORDER BY " . esc_sql($orderby) . " " . esc_sql($order) . " LIMIT %d OFFSET %d";
        $this->items = $wpdb->get_results(
            $wpdb->prepare($query, $per_page, $offset),
            ARRAY_A
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'total_amount':
                return '&euro; ' . number_format($item[$column_name], 2, ',', '.');
            case 'created_at':
                return date('d-m-Y', strtotime($item[$column_name]));
            case 'customer_name':
            case 'invoice_number':
            case 'status':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    public function get_sortable_columns() {
        return [
            'invoice_number' => ['invoice_number', false],
            'customer_name'  => ['customer_name', false],
            'created_at'     => ['created_at', true],
            'total_amount'   => ['total_amount', false],
            'status'         => ['status', false]
        ];
    }

    function column_invoice_number($item) {
        // Generate secure URLs with nonces for the actions
        $edit_url = wp_nonce_url(sprintf('?page=evs-invoices&action=edit&invoice_id=%s', $item['id']), 'evs_edit_invoice_' . $item['id']);
        $delete_url = wp_nonce_url(sprintf('?page=%s&action=delete&invoice_id=%s', $_REQUEST['page'], $item['id']), 'evs_delete_invoice_' . $item['id']);

        $actions = [
            'edit'   => sprintf('<a href="%s">Bewerken</a>', esc_url($edit_url)),
            'delete' => sprintf('<a href="%s" onclick="return confirm(\'Weet u zeker dat u deze factuur wilt verwijderen?\')">Verwijderen</a>', esc_url($delete_url)),
        ];
        return sprintf('%1$s %2$s', $item['invoice_number'], $this->row_actions($actions));
    }

    function column_cb($item) {
        return sprintf('<input type="checkbox" name="invoice[]" value="%s" />', $item['id']);
    }
}
