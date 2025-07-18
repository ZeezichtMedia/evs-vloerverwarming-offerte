<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class EVS_Offertes_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'Offerte',
            'plural'   => 'Offertes',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'customer_name' => 'Klantnaam',
            'customer_email'=> 'Email',
            'created_at'    => 'Datum',
            'total_price'   => 'Totaalprijs',
            'status'        => 'Status'
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'customer_name':
            case 'customer_email':
            case 'created_at':
            case 'status':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }
    
    function column_total_price($item) {
        $total = (float)$item['drilling_price'] + (float)$item['sealing_price'];
        return '&euro; ' . number_format($total, 2, ',', '.');
    }

    function column_customer_name($item) {
        $actions = array(
            'edit'      => sprintf('<a href="?page=%s&action=%s&offer_id=%s">Bewerken</a>', $_REQUEST['page'], 'edit', $item['id']),
            'delete'    => sprintf('<a href="?page=%s&action=%s&offer_id=%s">Verwijderen</a>', $_REQUEST['page'], 'delete', $item['id']),
        );
        return sprintf('%1$s %2$s', $item['customer_name'], $this->row_actions($actions) );
    }

    function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="offer[]" value="%s" />', $item['id']
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evs_offertes';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $orderby = (isset($_GET['orderby']) && in_array($_GET['orderby'], array_keys($this->get_sortable_columns()))) ? $_GET['orderby'] : 'created_at';
        $order = (isset($_GET['order']) && in_array($_GET['order'], ['asc', 'desc'])) ? $_GET['order'] : 'desc';

        $offset = ($current_page - 1) * $per_page;
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ), ARRAY_A
        );
    }
    
    protected function get_sortable_columns() {
        return [
            'customer_name' => ['customer_name', false],
            'created_at'    => ['created_at', true],
            'status'        => ['status', false],
        ];
    }
}
