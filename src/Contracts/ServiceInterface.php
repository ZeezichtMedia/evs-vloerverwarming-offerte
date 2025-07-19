<?php

namespace EVS\Contracts;

/**
 * Base service interface for business logic services
 */
interface ServiceInterface
{
    /**
     * Validate input data
     */
    public function validate(array $data): array;

    /**
     * Process business logic
     */
    public function process(array $data): array;
}
