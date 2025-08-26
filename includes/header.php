<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!function_exists('sanitize_output')) { require_once __DIR__ . '/functions.php'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? sanitize_output($page_title) . ' | CarsRUs' : 'CarsRUs Auto Dealer'; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <h1><a href="index.php">CarsRUs</a></h1>
        <nav>
            <ul>
                <?php if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'client')): ?>
                    <li><a href="index.php">Home</a></li>
                <?php endif; ?>
                <li><a href="listings.php">Car Listings</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php $role = $_SESSION['role']; ?>
                    <?php if ($role === 'client'): ?>
                        <li><a href="client_dashboard.php">My Dashboard</a></li>
                    <?php elseif ($role === 'admin'): ?>
                        <li><a href="admin_dashboard.php">Admin Dashboard</a></li>
                        <li><a href="reports.php">Reports</a></li>
                    <?php endif; ?>
                    <li><a href="change_password.php">Change Password</a></li>
                    <li><a href="logout.php">Logout (<?php echo sanitize_output($_SESSION['username']); ?>)</a></li>
                <?php else: ?>
                    <li><a href="register.php">Register</a></li>
                    <li><a href="client_login.php">Client Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main class="container">