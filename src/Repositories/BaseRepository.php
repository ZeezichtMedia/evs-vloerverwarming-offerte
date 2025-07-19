<?php

namespace EVS\Repositories;

use EVS\Contracts\RepositoryInterface;

/**
 * Base repository class with common database operations
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected $wpdb;
    protected $table_name;
    protected $primary_key = 'id';

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->getTableName();
    }

    /**
     * Get the table name for this repository
     */
    abstract protected function getTableName(): string;

    /**
     * Find a record by ID
     */
    public function find(int $id): ?array
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %d",
            $id
        );
        
        $result = $this->wpdb->get_row($sql, ARRAY_A);
        return $result ?: null;
    }

    /**
     * Find all records with optional conditions
     */
    public function findAll(array $conditions = []): array
    {
        $sql = "SELECT * FROM {$this->table_name}";
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $field => $value) {
                $where_clauses[] = "{$field} = %s";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Create a new record
     */
    public function create(array $data): int
    {
        $data = $this->sanitizeData($data);
        
        $result = $this->wpdb->insert($this->table_name, $data);
        
        if ($result === false) {
            throw new \Exception("Failed to create record: " . $this->wpdb->last_error);
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update a record by ID
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->sanitizeData($data);
        
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            [$this->primary_key => $id]
        );

        return $result !== false;
    }

    /**
     * Delete a record by ID
     */
    public function delete(int $id): bool
    {
        $result = $this->wpdb->delete(
            $this->table_name,
            [$this->primary_key => $id]
        );

        return $result !== false;
    }

    /**
     * Count records with optional conditions
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table_name}";
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $field => $value) {
                $where_clauses[] = "{$field} = %s";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Sanitize data before database operations
     */
    protected function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_email($value)) {
                $sanitized[$key] = sanitize_email($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get last database error
     */
    public function getLastError(): string
    {
        return $this->wpdb->last_error;
    }
}
