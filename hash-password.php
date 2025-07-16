<?php
// A simple command-line script to generate a secure password hash.

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

if ($argc < 2) {
    echo "Usage: php hash-password.php [your_password_here]\n";
    exit(1);
}

$password = $argv[1];

// Generate a secure hash using the BCRYPT algorithm (default)
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: {$password}\n";
echo "Hashed Password (copy this to your .env file):\n{$hash}\n";
