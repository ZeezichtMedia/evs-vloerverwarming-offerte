<?php

namespace EVS\Repositories;

/**
 * Repository for managing invoices (facturen)
 */
class InvoiceRepository extends BaseRepository
{
    /**
     * Get the table name for invoices
     */
    protected function getTableName(): string
    {
        return $this->wpdb->prefix . 'evs_facturen';
    }

    /**
     * Find invoice by quote ID
     */
    public function findByQuoteId(int $quote_id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE quote_id = %d",
            $quote_id
        );
        
        $result = $this->wpdb->get_row($sql, ARRAY_A);
        return $result ?: null;
    }

    /**
     * Find invoices by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findAll(['status' => $status]);
    }

    /**
     * Find overdue invoices
     */
    public function findOverdue(): array
    {
        $sql = "SELECT * FROM {$this->table_name} 
                WHERE status IN ('sent', 'overdue') 
                AND due_date < CURDATE() 
                ORDER BY due_date ASC";
        
        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Generate next invoice number
     */
    public function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $prefix = "FAC-{$year}-";
        
        // Get the highest number for this year
        $sql = $this->wpdb->prepare(
            "SELECT invoice_number FROM {$this->table_name} 
             WHERE invoice_number LIKE %s 
             ORDER BY invoice_number DESC 
             LIMIT 1",
            $prefix . '%'
        );
        
        $last_number = $this->wpdb->get_var($sql);
        
        if ($last_number) {
            // Extract the number part and increment
            $number_part = (int) str_replace($prefix, '', $last_number);
            $next_number = $number_part + 1;
        } else {
            $next_number = 1;
        }
        
        return $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get invoice statistics
     */
    public function getStatistics(): array
    {
        $stats = [];
        
        // Total invoices
        $stats['total_invoices'] = $this->count();
        
        // Invoices by status
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status";
        $status_counts = $this->wpdb->get_results($sql, ARRAY_A);
        
        $stats['by_status'] = [];
        foreach ($status_counts as $row) {
            $stats['by_status'][$row['status']] = (int) $row['count'];
        }
        
        // Total revenue
        $sql = "SELECT SUM(total_amount) as total_revenue FROM {$this->table_name} WHERE status = 'paid'";
        $stats['total_revenue'] = (float) $this->wpdb->get_var($sql);
        
        // Outstanding amount
        $sql = "SELECT SUM(total_amount) as outstanding FROM {$this->table_name} WHERE status IN ('sent', 'overdue')";
        $stats['outstanding_amount'] = (float) $this->wpdb->get_var($sql);
        
        // Average invoice value
        $stats['average_value'] = $stats['total_invoices'] > 0 
            ? ($stats['total_revenue'] + $stats['outstanding_amount']) / $stats['total_invoices'] 
            : 0;
        
        return $stats;
    }

    /**
     * Get monthly revenue data
     */
    public function getMonthlyRevenue(int $year): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT 
                MONTH(paid_date) as month,
                SUM(total_amount) as revenue
             FROM {$this->table_name} 
             WHERE status = 'paid' 
             AND YEAR(paid_date) = %d
             GROUP BY MONTH(paid_date)
             ORDER BY month",
            $year
        );
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        // Fill in missing months with 0
        $monthly_data = array_fill(1, 12, 0);
        foreach ($results as $row) {
            $monthly_data[(int) $row['month']] = (float) $row['revenue'];
        }
        
        return $monthly_data;
    }

    /**
     * Search invoices by multiple criteria
     */
    public function search(array $criteria): array
    {
        $where_clauses = [];
        $params = [];

        if (!empty($criteria['customer_name'])) {
            $where_clauses[] = "customer_name LIKE %s";
            $params[] = '%' . $criteria['customer_name'] . '%';
        }

        if (!empty($criteria['invoice_number'])) {
            $where_clauses[] = "invoice_number LIKE %s";
            $params[] = '%' . $criteria['invoice_number'] . '%';
        }

        if (!empty($criteria['status'])) {
            $where_clauses[] = "status = %s";
            $params[] = $criteria['status'];
        }

        if (!empty($criteria['date_from'])) {
            $where_clauses[] = "invoice_date >= %s";
            $params[] = $criteria['date_from'];
        }

        if (!empty($criteria['date_to'])) {
            $where_clauses[] = "invoice_date <= %s";
            $params[] = $criteria['date_to'];
        }

        $sql = "SELECT * FROM {$this->table_name}";
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        $sql .= " ORDER BY invoice_date DESC";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }
}
