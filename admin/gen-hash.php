<?php
/**
 * Admin Password Hash Generator
 * Usage: php gen-hash.php
 * Then copy the hash into setup.sql or run the UPDATE query shown.
 * DELETE this file from the server after use!
 */

// Set your desired admin password here:
$plainPassword = 'Admin@123';

$hash = password_hash($plainPassword, PASSWORD_DEFAULT);

echo "Plain Password : $plainPassword\n";
echo "Hashed Password: $hash\n\n";
echo "Run this SQL to update the admin account:\n";
echo "UPDATE admin_users SET password='$hash' WHERE username='admin';\n";
?>
