<?php
/**
 * EVS Plugin Installation Checker
 * 
 * Run this file to check if the plugin is ready for WordPress installation
 */

// Prevent direct access
if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    die('This script can only be run from command line or WordPress admin.');
}

echo "=== EVS Vloerverwarming Plugin Installation Checker ===\n\n";

// Check PHP version
$php_version = PHP_VERSION;
$min_php = '7.4';
echo "PHP Version: $php_version ";
if (version_compare($php_version, $min_php, '>=')) {
    echo "✅ OK\n";
} else {
    echo "❌ ERROR: Minimum PHP $min_php required\n";
    exit(1);
}

// Check required files
$required_files = [
    'evs-vloerverwarming-offerte-improved.php',
    'includes/class-evs-admin-manager.php',
    'includes/class-evs-database-manager.php',
    'includes/class-evs-email-service.php',
    'includes/class-evs-form-handler.php',
    'includes/class-evs-pricing-calculator.php',
    'templates/forms/quote-form.php',
    'assets/css/evs-form.css',
    'assets/js/evs-form.js'
];

echo "\nChecking required files:\n";
$missing_files = [];
foreach ($required_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file\n";
    } else {
        echo "❌ $file (MISSING)\n";
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    echo "\n❌ ERROR: Missing required files. Cannot install plugin.\n";
    exit(1);
}

// Check file permissions
echo "\nChecking file permissions:\n";
$writable_dirs = [
    __DIR__,
    __DIR__ . '/assets',
    __DIR__ . '/templates'
];

foreach ($writable_dirs as $dir) {
    if (is_writable($dir)) {
        echo "✅ " . basename($dir) . "/ (writable)\n";
    } else {
        echo "⚠️  " . basename($dir) . "/ (not writable - may cause issues)\n";
    }
}

// Check syntax of main files
echo "\nChecking PHP syntax:\n";
$php_files = [
    'evs-vloerverwarming-offerte-improved.php',
    'includes/class-evs-admin-manager.php',
    'includes/class-evs-database-manager.php'
];

foreach ($php_files as $file) {
    $output = [];
    $return_code = 0;
    exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return_code);
    
    if ($return_code === 0) {
        echo "✅ $file (syntax OK)\n";
    } else {
        echo "❌ $file (syntax error)\n";
        echo "   " . implode("\n   ", $output) . "\n";
    }
}

// Plugin information
echo "\n=== Plugin Information ===\n";
$main_file = file_get_contents(__DIR__ . '/evs-vloerverwarming-offerte-improved.php');
preg_match('/Version:\s*(.+)/', $main_file, $version_match);
preg_match('/Plugin Name:\s*(.+)/', $main_file, $name_match);

if (isset($version_match[1])) {
    echo "Plugin Version: " . trim($version_match[1]) . "\n";
}
if (isset($name_match[1])) {
    echo "Plugin Name: " . trim($name_match[1]) . "\n";
}

echo "\n=== Installation Instructions ===\n";
echo "1. Create a ZIP file of this entire folder\n";
echo "2. Go to WordPress Admin → Plugins → Add New → Upload Plugin\n";
echo "3. Upload the ZIP file and activate the plugin\n";
echo "4. Go to EVS Offertes → Settings to configure\n";
echo "5. Add shortcode [evs_offerte_form] to a page\n";

echo "\n✅ Plugin is ready for WordPress installation!\n";
?>
