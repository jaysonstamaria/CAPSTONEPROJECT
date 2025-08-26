<?php
$adminPassword = 'admin123'; // <<< CHOOSE YOUR STRONG ADMIN PASSWORD
echo "Admin Hash: <pre>" . password_hash($adminPassword, PASSWORD_DEFAULT) . "</pre>";
echo "<hr>DELETE THIS FILE AFTER USE!";
?>