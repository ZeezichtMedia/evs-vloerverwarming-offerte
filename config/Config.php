<?php
namespace EVS\Config;

class Config
{
    private static $instance = null;
    private $config = [];

    private function __construct()
    {
        $dotenv = new \Dotenv\Dotenv(__DIR__ . '/../../');
        $dotenv->load();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key, $default = null)
    {
        return getenv($key) ?: $default;
    }

    public function isDebug(): bool
    {
        return $this->get('APP_DEBUG', false) === 'true';
    }
}
