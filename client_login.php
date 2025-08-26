<?php
// File: client_login.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$errors = [];
$expected_role = 'client';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? null) === $expected_role) {
    header("Location: client_dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        $errors[] = "Username/Email and Password are required.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, username, email, password, role, is_verified FROM users WHERE username = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $username_or_email, $username_or_email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if ($user['role'] !== $expected_role) {
                    $errors[] = "Invalid credentials or access denied for this portal.";
                } elseif ($user['is_verified'] != 1) {
                    $errors[] = "Your account is not yet verified. Please check your email for an OTP or <a href='register.php'>register again</a> if your OTP has expired.";
                    $_SESSION['otp_user_email'] = $user['email'];
                } elseif (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $redirect_url = $_SESSION['redirect_url'] ?? 'client_dashboard.php';
                    unset($_SESSION['redirect_url']);
                    header("Location: " . $redirect_url);
                    exit;
                } else {
                    $errors[] = "Invalid credentials.";
                }
            } else {
                $errors[] = "Invalid credentials.";
            }
            $stmt->close();
        } else {
            $errors[] = "Login error. Please try again.";
        }
    }
}

$message = '';
if(isset($_GET['logged_out'])) $message = "You have been logged out successfully.";
if(isset($_GET['error']) && $_GET['error'] === 'login_required') $message = "You must log in to view that page.";
if(isset($_GET['verified'])) $message = "Your email has been verified! You can now log in.";

$page_title = "Client Login";
include 'includes/header.php';
?>

<h2>Client Login</h2>

<?php if ($message): ?><div class="info"><?php echo sanitize_output($message); ?></div><?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="errors">
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form action="client_login.php" method="post">
    <div>
        <label for="username_or_email">Username or Email:</label>
        <input type="text" id="username_or_email" name="username_or_email" required>
    </div>
    <div>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="button primary">Login</button>
</form>

<p style="margin-top: 15px;">
    Don't have an account? <a href="register.php">Register here</a>.<br>
    <a href="forgot_password.php">Forgot Password?</a>
</p>

<?php include 'includes/footer.php'; ?>