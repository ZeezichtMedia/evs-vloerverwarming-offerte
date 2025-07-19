<?php

namespace EVS\Validators;

/**
 * Base validator class with common validation methods
 */
abstract class BaseValidator
{
    protected array $errors = [];

    /**
     * Validate data and return errors
     */
    abstract public function validate(array $data): array;

    /**
     * Add error message
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Check if field is required and not empty
     */
    protected function required(string $field, $value, string $message = null): bool
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->addError($field, $message ?? "{$field} is verplicht");
            return false;
        }
        return true;
    }

    /**
     * Validate email format
     */
    protected function email(string $field, $value, string $message = null): bool
    {
        if (!empty($value) && !is_email($value)) {
            $this->addError($field, $message ?? "Ongeldig e-mailadres");
            return false;
        }
        return true;
    }

    /**
     * Validate numeric value
     */
    protected function numeric(string $field, $value, string $message = null): bool
    {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, $message ?? "{$field} moet een getal zijn");
            return false;
        }
        return true;
    }

    /**
     * Validate minimum value
     */
    protected function min(string $field, $value, float $min, string $message = null): bool
    {
        if (!empty($value) && (float)$value < $min) {
            $this->addError($field, $message ?? "{$field} moet minimaal {$min} zijn");
            return false;
        }
        return true;
    }

    /**
     * Validate maximum value
     */
    protected function max(string $field, $value, float $max, string $message = null): bool
    {
        if (!empty($value) && (float)$value > $max) {
            $this->addError($field, $message ?? "{$field} mag maximaal {$max} zijn");
            return false;
        }
        return true;
    }

    /**
     * Validate string length
     */
    protected function maxLength(string $field, $value, int $maxLength, string $message = null): bool
    {
        if (!empty($value) && strlen($value) > $maxLength) {
            $this->addError($field, $message ?? "{$field} mag maximaal {$maxLength} karakters bevatten");
            return false;
        }
        return true;
    }

    /**
     * Validate value is in allowed options
     */
    protected function in(string $field, $value, array $options, string $message = null): bool
    {
        if (!empty($value) && !in_array($value, $options)) {
            $this->addError($field, $message ?? "{$field} heeft een ongeldige waarde");
            return false;
        }
        return true;
    }

    /**
     * Validate phone number format (Dutch)
     */
    protected function phone(string $field, $value, string $message = null): bool
    {
        if (!empty($value)) {
            // Remove spaces, dashes, and other common separators
            $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);
            
            // Check if it's a valid Dutch phone number pattern
            if (!preg_match('/^(\+31|0031|0)[1-9][0-9]{8}$/', $cleaned)) {
                $this->addError($field, $message ?? "Ongeldig telefoonnummer");
                return false;
            }
        }
        return true;
    }

    /**
     * Validate date format
     */
    protected function date(string $field, $value, string $format = 'Y-m-d', string $message = null): bool
    {
        if (!empty($value)) {
            $date = \DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                $this->addError($field, $message ?? "Ongeldige datum");
                return false;
            }
        }
        return true;
    }

    /**
     * Reset errors
     */
    protected function resetErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Get all errors as flat array
     */
    protected function getFlatErrors(): array
    {
        $flatErrors = [];
        foreach ($this->errors as $fieldErrors) {
            $flatErrors = array_merge($flatErrors, $fieldErrors);
        }
        return $flatErrors;
    }
}
