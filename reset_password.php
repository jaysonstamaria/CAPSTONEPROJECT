<?php
// File: reset_password.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$errors = [];
$success_message = '';
$token_valid = false;
$email_from_url = trim($_GET['email'] ?? '');
$token_from_url = trim($_GET['token'] ?? '');

if (empty($email_from_url) || empty($token_from_url) || !filter_var($email_from_url, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid password reset link.";
} else {
    $stmt_check_token = $mysqli->prepare("SELECT id, password_reset_token, password_reset_expiry FROM users WHERE email = ? AND is_verified = 1");
    if ($stmt_check_token) {
        $stmt_check_token->bind_param("s", $email_from_url);
        $stmt_check_token->execute();
        $result = $stmt_check_token->get_result();
        $user = $result->fetch_assoc();
        $stmt_check_token->close();

        if ($user && !empty($user['password_reset_token']) && !empty($user['password_reset_expiry'])) {
            $token_expiry_time = new DateTime($user['password_reset_expiry']);
            if (new DateTime() > $token_expiry_time) {
                $errors[] = "Password reset link has expired. Please <a href='forgot_password.php'>request a new one</a>.";
            } elseif (password_verify($token_from_url, $user['password_reset_token'])) {
                $token_valid = true;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_email_temp'] = $email_from_url;
            } else {
                $errors[] = "Invalid password reset link.";
            }
        } else {
            $errors[] = "Invalid password reset link or no pending reset found.";
        }
    }
}

if ($token_valid && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['reset_user_id'])) {
    $user_id_to_reset = $_SESSION['reset_user_id'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($new_password)) {
        $errors[] = "New password is required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "New passwords do not match.";
    } else {
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update = $mysqli->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expiry = NULL WHERE id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("si", $new_hashed_password, $user_id_to_reset);
            if ($stmt_update->execute()) {
                $success_message = "Your password has been reset successfully! You can now <a href='client_login.php'>log in</a>.";
                $token_valid = false;
                unset($_SESSION['reset_user_id'], $_SESSION['reset_email_temp']);
            } else {
                $errors[] = "Failed to reset password.";
            }
            $stmt_update->close();
        }
    }
}

$page_title = "Reset Password";
include 'includes/header.php';
?>

<h2>Reset Your Password</h2>

<?php if (!empty($errors)): ?><div class="errors"><ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success_message): ?><div class="success"><?php echo $success_message; ?></div><?php endif; ?>

<?php if ($token_valid && empty($success_message)): ?>
    <p>Resetting password for: <strong><?php echo sanitize_output($_SESSION['reset_email_temp'] ?? $email_from_url); ?></strong></p>
    <form action="reset_password.php?email=<?php echo urlencode($email_from_url); ?>&token=<?php echo urlencode($token_from_url); ?>" method="post">
        <div>
            <label for="new_password">New Password (min 8 characters):</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        <div>
            <label for="confirm_new_password">Confirm New Password:</label>
            <input type="password" id="confirm_new_password" name="confirm_new_password" required>
        </div>
        <button type="submit" class="button primary">Reset Password</button>
    </form>
<?php elseif(empty($success_message)): ?>
     <p style="margin-top:15px;"><a href="client_login.php">Back to Login</a></p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>