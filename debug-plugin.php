<?php
/**
 * Debug script to test plugin loading without WordPress
 */

// Mock WordPress functions that might be called during plugin initialization
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost/';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        echo "add_action called: $hook\n";
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        echo "add_filter called: $hook\n";
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) {
        echo "add_shortcode called: $tag\n";
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

echo "Starting plugin debug...\n";

try {
    // Try to load the plugin
    require_once 'evs-vloerverwarming-offerte.php';
    echo "Plugin loaded successfully!\n";
    
    // Try to instantiate the main class
    $plugin = EVS_Vloerverwarming_Offerte::instance();
    echo "Plugin instantiated successfully!\n";
    
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "Debug completed.\n";
