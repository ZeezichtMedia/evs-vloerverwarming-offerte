<?php

namespace EVS\Container;

use EVS\Repositories\QuoteRepository;
use EVS\Repositories\InvoiceRepository;
use EVS\Services\QuoteService;
use EVS\Services\InvoiceService;
use EVS\Services\PricingService;
use EVS\Validators\QuoteValidator;
use EVS\Validators\InvoiceValidator;

/**
 * Simple dependency injection container
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];

    /**
     * Bind a class or interface to a concrete implementation
     */
    public function bind(string $abstract, $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    /**
     * Bind a singleton instance
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        $this->instances[$abstract] = null;
    }

    /**
     * Resolve a class from the container
     */
    public function make(string $abstract)
    {
        // Return existing singleton instance
        if (isset($this->instances[$abstract]) && $this->instances[$abstract] !== null) {
            return $this->instances[$abstract];
        }

        // Get concrete class
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // Create instance
        $instance = $this->build($concrete);

        // Store singleton instance
        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build an instance of the given class
     */
    private function build($concrete)
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$concrete} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency {$parameter->getName()}");
            }
        }

        return $dependencies;
    }

    /**
     * Register all EVS services
     */
    public function registerServices(): void
    {
        // Repositories (singletons)
        $this->singleton(QuoteRepository::class);
        $this->singleton(InvoiceRepository::class);

        // Validators (singletons)
        $this->singleton(QuoteValidator::class);
        $this->singleton(InvoiceValidator::class);

        // Services (singletons)
        $this->singleton(PricingService::class);
        $this->singleton(QuoteService::class);
        $this->singleton(InvoiceService::class);
    }

    /**
     * Get quote service
     */
    public function getQuoteService(): QuoteService
    {
        return $this->make(QuoteService::class);
    }

    /**
     * Get invoice service
     */
    public function getInvoiceService(): InvoiceService
    {
        return $this->make(InvoiceService::class);
    }

    /**
     * Get pricing service
     */
    public function getPricingService(): PricingService
    {
        return $this->make(PricingService::class);
    }

    /**
     * Get quote repository
     */
    public function getQuoteRepository(): QuoteRepository
    {
        return $this->make(QuoteRepository::class);
    }

    /**
     * Get invoice repository
     */
    public function getInvoiceRepository(): InvoiceRepository
    {
        return $this->make(InvoiceRepository::class);
    }
}
