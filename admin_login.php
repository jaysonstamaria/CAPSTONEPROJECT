<?php
// File: admin_login.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
$errors = [];
$expected_role = 'admin';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? null) === $expected_role) {
    header("Location: admin_dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        $errors[] = "Username/Email and Password are required.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, username, password, role FROM users WHERE username = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $username_or_email, $username_or_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if ($user['role'] !== $expected_role) {
                    $errors[] = "Invalid credentials or access denied for this portal.";
                } elseif (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $redirect_url = $_SESSION['redirect_url'] ?? 'admin_dashboard.php';
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

$page_title = "Admin Login";
include 'includes/header.php';
?>

<h2>Admin Login Portal</h2>

<?php if (!empty($errors)): ?>
    <div class="errors">
        <ul> <?php foreach ($errors as $error): ?> <li><?php echo sanitize_output($error); ?></li> <?php endforeach; ?> </ul>
    </div>
<?php endif; ?>

<form action="admin_login.php" method="post">
    <div>
        <label for="username_or_email">Admin Username or Email:</label>
        <input type="text" id="username_or_email" name="username_or_email" required>
    </div>
    <div>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="button primary">Admin Login</button>
</form>
<p style="margin-top: 15px;"><a href="forgot_password.php">Forgot Password?</a></p>

<?php include 'includes/footer.php'; ?>