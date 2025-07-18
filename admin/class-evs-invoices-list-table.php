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

        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'created_at';
        $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc';

        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
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
        $actions = [
            'edit'   => sprintf('<a href="?page=%s&action=%s&invoice_id=%s">Bewerken</a>', 'evs-invoices', 'edit', $item['id']),
            'delete' => sprintf('<a href="?page=%s&action=%s&invoice_id=%s">Verwijderen</a>', $_REQUEST['page'], 'delete', $item['id']),
        ];
        return sprintf('%1$s %2$s', $item['invoice_number'], $this->row_actions($actions));
    }

    function column_cb($item) {
        return sprintf('<input type="checkbox" name="invoice[]" value="%s" />', $item['id']);
    }
}
