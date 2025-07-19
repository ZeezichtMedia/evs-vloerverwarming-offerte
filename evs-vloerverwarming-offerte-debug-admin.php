<?php
/**
 * Plugin Name: EVS Vloerverwarming Offerte - Admin Debug
 * Plugin URI: https://evs-vloerverwarmingen.nl
 * Description: Debug version that shows errors in WordPress admin
 * Version: 4.0.0-admin-debug
 * Author: EVS Vloerverwarmingen
 * Text Domain: evs-vloerverwarming
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Global debug messages array
global $evs_debug_messages;
$evs_debug_messages = array();

// Debug function that stores messages
function evs_debug_store($message) {
    global $evs_debug_messages;
    $evs_debug_messages[] = '[' . date('H:i:s') . '] ' . $message;
    
    // Also log to error log if possible
    if (function_exists('error_log')) {
        error_log('[EVS DEBUG] ' . $message);
    }
}

// Function to display debug messages in admin
function evs_show_debug_messages() {
    global $evs_debug_messages;
    
    if (!empty($evs_debug_messages)) {
        echo '<div class="notice notice-info"><h3>EVS Debug Messages:</h3><pre style="background: #f1f1f1; padding: 10px; overflow-x: auto;">';
        foreach ($evs_debug_messages as $message) {
            echo esc_html($message) . "\n";
        }
        echo '</pre></div>';
    }
}

evs_debug_store('Plugin file loaded - starting debug process');

// Plugin constants with defensive checks
define('EVS_PLUGIN_VERSION', '4.0.0-admin-debug');

if (function_exists('plugin_dir_path')) {
    define('EVS_PLUGIN_PATH', plugin_dir_path(__FILE__));
    evs_debug_store('EVS_PLUGIN_PATH defined with plugin_dir_path: ' . plugin_dir_path(__FILE__));
} else {
    define('EVS_PLUGIN_PATH', dirname(__FILE__) . '/');
    evs_debug_store('EVS_PLUGIN_PATH defined with dirname fallback: ' . dirname(__FILE__) . '/');
}

evs_debug_store('Constants defined successfully');

// Test autoloader
$autoloader_path = __DIR__ . '/vendor/autoload.php';
evs_debug_store('Looking for autoloader at: ' . $autoloader_path);

if (file_exists($autoloader_path)) {
    evs_debug_store('Autoloader found, attempting to load');
    try {
        require_once $autoloader_path;
        evs_debug_store('Autoloader loaded successfully');
    } catch (Exception $e) {
        evs_debug_store('EXCEPTION in autoloader: ' . $e->getMessage());
        evs_debug_store('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
        
        // Show error in admin
        if (function_exists('add_action')) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>EVS Autoloader Exception:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
                evs_show_debug_messages();
            });
        }
        return;
    } catch (Error $e) {
        evs_debug_store('ERROR in autoloader: ' . $e->getMessage());
        evs_debug_store('Error file: ' . $e->getFile() . ' line: ' . $e->getLine());
        
        // Show error in admin
        if (function_exists('add_action')) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>EVS Autoloader Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
                evs_show_debug_messages();
            });
        }
        return;
    }
} else {
    evs_debug_store('CRITICAL: Autoloader not found at expected path');
    
    // Show error in admin
    if (function_exists('add_action')) {
        add_action('admin_notices', function() use ($autoloader_path) {
            echo '<div class="notice notice-error"><p><strong>EVS Critical Error:</strong> Autoloader not found at ' . esc_html($autoloader_path) . '</p></div>';
            evs_show_debug_messages();
        });
    }
    return;
}

evs_debug_store('About to test class loading');

// Test if we can load our classes
try {
    evs_debug_store('Testing if EVS\\Container\\Container class exists');
    
    if (class_exists('EVS\\Container\\Container')) {
        evs_debug_store('Container class found, attempting to instantiate');
        $container = new EVS\Container\Container();
        evs_debug_store('Container created successfully');
        
        evs_debug_store('Testing service registration');
        $container->registerServices();
        evs_debug_store('Services registered successfully');
        
    } else {
        evs_debug_store('CRITICAL: Container class not found after autoloader');
        
        // List what classes are available
        $declared_classes = get_declared_classes();
        $evs_classes = array_filter($declared_classes, function($class) {
            return strpos($class, 'EVS\\') === 0;
        });
        
        evs_debug_store('Available EVS classes: ' . implode(', ', $evs_classes));
    }
    
} catch (Exception $e) {
    evs_debug_store('EXCEPTION during class testing: ' . $e->getMessage());
    evs_debug_store('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
    evs_debug_store('Stack trace: ' . $e->getTraceAsString());
} catch (Error $e) {
    evs_debug_store('ERROR during class testing: ' . $e->getMessage());
    evs_debug_store('Error file: ' . $e->getFile() . ' line: ' . $e->getLine());
    evs_debug_store('Stack trace: ' . $e->getTraceAsString());
}

evs_debug_store('About to define main class');

// Minimal main class
final class EVS_Vloerverwarming_Offerte_Debug {
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            evs_debug_store('Creating new plugin instance');
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        evs_debug_store('Plugin constructor called');
        
        // Show debug messages in admin
        if (function_exists('add_action')) {
            add_action('admin_notices', 'evs_show_debug_messages');
        }
        
        evs_debug_store('Constructor completed successfully');
    }
}

evs_debug_store('Main class defined');

// Initialization function
function evs_debug_init() {
    evs_debug_store('Init function called');
    try {
        $plugin = EVS_Vloerverwarming_Offerte_Debug::instance();
        evs_debug_store('Plugin instance created successfully - ACTIVATION SHOULD SUCCEED');
        return $plugin;
    } catch (Exception $e) {
        evs_debug_store('EXCEPTION in init: ' . $e->getMessage());
        evs_debug_store('Exception file: ' . $e->getFile() . ' line: ' . $e->getLine());
    } catch (Error $e) {
        evs_debug_store('ERROR in init: ' . $e->getMessage());
        evs_debug_store('Error file: ' . $e->getFile() . ' line: ' . $e->getLine());
    }
}

evs_debug_store('About to register WordPress hooks');

// Register hooks with defensive checks
if (function_exists('add_action')) {
    evs_debug_store('Registering plugins_loaded hook');
    add_action('plugins_loaded', 'evs_debug_init');
    
    // Also show debug messages on activation
    add_action('admin_notices', 'evs_show_debug_messages');
} else {
    evs_debug_store('CRITICAL: add_action function not available');
}

evs_debug_store('Plugin file completed - if you see this, the plugin should activate');
