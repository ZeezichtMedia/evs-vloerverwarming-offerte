<?php
/**
 * Plugin Name: EVS Vloerverwarming Offerte - Minimal Test
 * Plugin URI: https://evs-vloerverwarmingen.nl
 * Description: Minimal test version to isolate fatal error
 * Version: 4.0.0-debug
 * Author: EVS Vloerverwarmingen
 * Text Domain: evs-vloerverwarming
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Log function for debugging
function evs_debug_log($message) {
    error_log('[EVS DEBUG] ' . $message);
}

evs_debug_log('Plugin file loaded');

// Plugin constants with defensive checks
define('EVS_PLUGIN_VERSION', '4.0.0-debug');

if (function_exists('plugin_dir_path')) {
    define('EVS_PLUGIN_PATH', plugin_dir_path(__FILE__));
    evs_debug_log('EVS_PLUGIN_PATH defined with plugin_dir_path');
} else {
    define('EVS_PLUGIN_PATH', dirname(__FILE__) . '/');
    evs_debug_log('EVS_PLUGIN_PATH defined with dirname fallback');
}

evs_debug_log('Constants defined');

// Test autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    evs_debug_log('Autoloader found, attempting to load');
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        evs_debug_log('Autoloader loaded successfully');
    } catch (Exception $e) {
        evs_debug_log('Autoloader failed: ' . $e->getMessage());
        return;
    } catch (Error $e) {
        evs_debug_log('Autoloader error: ' . $e->getMessage());
        return;
    }
} else {
    evs_debug_log('Autoloader not found');
    return;
}

evs_debug_log('About to define main class');

// Minimal main class
final class EVS_Vloerverwarming_Offerte_Minimal {
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            evs_debug_log('Creating new instance');
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        evs_debug_log('Constructor called');
        
        // Test if we can access our classes
        try {
            evs_debug_log('Testing Container class');
            $container = new EVS\Container\Container();
            evs_debug_log('Container created successfully');
            
            evs_debug_log('Testing service registration');
            $container->registerServices();
            evs_debug_log('Services registered successfully');
            
        } catch (Exception $e) {
            evs_debug_log('Exception in constructor: ' . $e->getMessage());
            evs_debug_log('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
        } catch (Error $e) {
            evs_debug_log('Error in constructor: ' . $e->getMessage());
            evs_debug_log('Error file: ' . $e->getFile() . ' line: ' . $e->getLine());
        }
        
        evs_debug_log('Constructor completed');
    }
}

evs_debug_log('Main class defined');

// Initialization function
function evs_minimal_init() {
    evs_debug_log('Init function called');
    try {
        $plugin = EVS_Vloerverwarming_Offerte_Minimal::instance();
        evs_debug_log('Plugin instance created successfully');
        return $plugin;
    } catch (Exception $e) {
        evs_debug_log('Exception in init: ' . $e->getMessage());
    } catch (Error $e) {
        evs_debug_log('Error in init: ' . $e->getMessage());
    }
}

evs_debug_log('About to register hooks');

// Register hooks with defensive checks
if (function_exists('add_action')) {
    evs_debug_log('Registering plugins_loaded hook');
    add_action('plugins_loaded', 'evs_minimal_init');
} else {
    evs_debug_log('add_action not available');
}

evs_debug_log('Plugin file completed');
