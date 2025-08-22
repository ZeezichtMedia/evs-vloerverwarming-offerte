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
            'id'            => 'ID',
            'customer_name' => 'Klant',
            'customer_email'=> 'E-mail',
            'type_vloer'    => 'Type Vloer',
            'area_m2'       => 'Oppervlakte',
            'total_price'   => 'Totaalprijs',
            'status'        => 'Status',
            'created_at'    => 'Datum',
            'actions'       => 'Acties'
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'customer_name':
                return $item['naam'];
            case 'customer_email':
                return $item['email'];
            case 'type_vloer':
                return ucfirst(str_replace('_', ' ', $item['type_vloer']));
            case 'area_m2':
                return number_format($item['area_m2'], 1, ',', '.') . ' m²';
            case 'status':
                return $this->format_status($item['status']);
            case 'created_at':
                return date('d-m-Y H:i', strtotime($item['created_at']));
            default:
                return print_r($item, true);
        }
    }
    
    function column_total_price($item) {
        // Use the correct, stored total price
        return '&euro; ' . number_format($item['total_price'], 2, ',', '.');
    }

    function column_actions($item) {
        $edit_url = admin_url('admin.php?page=evs-edit-quote&quote_id=' . $item['id']);
        $quote_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=send_quote&quote_id=' . $item['id']), 'send_quote_' . $item['id']);
        $invoice_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=create_invoice&quote_id=' . $item['id']), 'create_invoice_' . $item['id']);
        $delete_url = wp_nonce_url(admin_url('admin.php?page=evs-offertes&action=delete&quote_id=' . $item['id']), 'delete_quote_' . $item['id']);
        
        $actions = '<div class="evs-table-actions">';
        $actions .= '<a href="' . $edit_url . '" class="button button-small">Bewerken</a> ';
        $actions .= '<a href="' . $quote_url . '" class="button button-small">Offerte Versturen</a> ';
        $actions .= '<a href="' . $invoice_url . '" class="button button-small button-primary">Factuur Aanmaken</a> ';
        $actions .= '<a href="' . $delete_url . '" class="button button-small button-link-delete" onclick="return confirm(\'Weet je zeker dat je deze offerte wilt verwijderen?\')">Verwijderen</a>';
        $actions .= '</div>';
        
        return $actions;
    }
    
    function format_status($status) {
        $statuses = [
            'pending' => '<span class="evs-status-pending">In behandeling</span>',
            'sent' => '<span class="evs-status-sent">Verzonden</span>',
            'approved' => '<span class="evs-status-approved">Goedgekeurd</span>',
            'completed' => '<span class="evs-status-completed">Voltooid</span>',
            'cancelled' => '<span class="evs-status-cancelled">Geannuleerd</span>',
            'new' => '<span class="evs-status-new">Nieuw</span>'
        ];
        return isset($statuses[$status]) ? $statuses[$status] : $status;
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

        // --- SOLUTION: STRICT WHITELIST VALIDATION ---
        $orderby = 'created_at'; // Secure default
        if (isset($_GET['orderby']) && array_key_exists($_GET['orderby'], $sortable)) {
            $orderby = $_GET['orderby'];
        }

        $order = 'desc'; // Secure default
        if (isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc'])) {
            $order = strtolower($_GET['order']);
        }

        $offset = ($current_page - 1) * $per_page;
        // The query is now safe because the variables have been validated
        $query = "SELECT * FROM $table_name ORDER BY " . esc_sql($orderby) . " " . esc_sql($order) . " LIMIT %d OFFSET %d";
        $this->items = $wpdb->get_results(
            $wpdb->prepare($query, $per_page, $offset),
            ARRAY_A
        );
    }
    
    protected function get_sortable_columns() {
        return [
            'id'            => ['id', false],
            'customer_name' => ['naam', false],
            'customer_email'=> ['email', false],
            'total_price'   => ['total_price', false],
            'status'        => ['status', false],
            'created_at'    => ['created_at', true]
        ];
    }
}
