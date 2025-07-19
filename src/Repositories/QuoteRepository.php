<?php

namespace EVS\Repositories;

/**
 * Repository for managing quotes (offertes)
 */
class QuoteRepository extends BaseRepository
{
    /**
     * Get the table name for quotes
     */
    protected function getTableName(): string
    {
        return $this->wpdb->prefix . 'evs_offertes';
    }

    /**
     * Find quotes by customer email
     */
    public function findByCustomerEmail(string $email): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE customer_email = %s ORDER BY created_at DESC",
            $email
        );
        
        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Find quotes by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findAll(['status' => $status]);
    }

    /**
     * Get quotes created in date range
     */
    public function findByDateRange(string $start_date, string $end_date): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC",
            $start_date,
            $end_date
        );
        
        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get quotes with total value above threshold
     */
    public function findHighValueQuotes(float $min_value): array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE total_price >= %f ORDER BY total_price DESC",
            $min_value
        );
        
        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get quote statistics
     */
    public function getStatistics(): array
    {
        $stats = [];
        
        // Total quotes
        $stats['total_quotes'] = $this->count();
        
        // Quotes by status
        $sql = "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status";
        $status_counts = $this->wpdb->get_results($sql, ARRAY_A);
        
        $stats['by_status'] = [];
        foreach ($status_counts as $row) {
            $stats['by_status'][$row['status']] = (int) $row['count'];
        }
        
        // Total value
        $sql = "SELECT SUM(total_price) as total_value FROM {$this->table_name}";
        $stats['total_value'] = (float) $this->wpdb->get_var($sql);
        
        // Average value
        $stats['average_value'] = $stats['total_quotes'] > 0 
            ? $stats['total_value'] / $stats['total_quotes'] 
            : 0;
        
        return $stats;
    }

    /**
     * Search quotes by multiple criteria
     */
    public function search(array $criteria): array
    {
        $where_clauses = [];
        $params = [];

        if (!empty($criteria['customer_name'])) {
            $where_clauses[] = "customer_name LIKE %s";
            $params[] = '%' . $criteria['customer_name'] . '%';
        }

        if (!empty($criteria['customer_email'])) {
            $where_clauses[] = "customer_email LIKE %s";
            $params[] = '%' . $criteria['customer_email'] . '%';
        }

        if (!empty($criteria['status'])) {
            $where_clauses[] = "status = %s";
            $params[] = $criteria['status'];
        }

        if (!empty($criteria['floor_type'])) {
            $where_clauses[] = "floor_type = %s";
            $params[] = $criteria['floor_type'];
        }

        if (!empty($criteria['min_price'])) {
            $where_clauses[] = "total_price >= %f";
            $params[] = $criteria['min_price'];
        }

        if (!empty($criteria['max_price'])) {
            $where_clauses[] = "total_price <= %f";
            $params[] = $criteria['max_price'];
        }

        $sql = "SELECT * FROM {$this->table_name}";
        
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }
        
        $sql .= " ORDER BY created_at DESC";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }
}
