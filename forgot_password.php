<?php
// File: forgot_password.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/email_functions.php';

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } else {
        $stmt_check_email = $mysqli->prepare("SELECT id, username FROM users WHERE email = ? AND is_verified = 1");
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            $result = $stmt_check_email->get_result();
            $user = $result->fetch_assoc();
            $stmt_check_email->close();

            if ($user) {
                $token_plain = bin2hex(random_bytes(32));
                $token_hash = password_hash($token_plain, PASSWORD_DEFAULT);
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt_update_token = $mysqli->prepare("UPDATE users SET password_reset_token = ?, password_reset_expiry = ? WHERE id = ?");
                if ($stmt_update_token) {
                    $stmt_update_token->bind_param("ssi", $token_hash, $expiry, $user['id']);
                    if ($stmt_update_token->execute()) {
                        $reset_link_actual = SITE_URL . "reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token_plain);
                        $email_subject_reset = "Password Reset Request for CarsRUs";
                        $email_body_html_reset = "<p>Hello " . sanitize_output($user['username']) . ",</p><p>We received a request to reset your password. Click the link below. This link is valid for 1 hour:</p><p><a href='" . $reset_link_actual . "'>" . $reset_link_actual . "</a></p><p>If you did not request this, please ignore this email.</p><p>Thanks,<br>The CarsRUs Team</p>";

                        if (send_email($email, $email_subject_reset, $email_body_html_reset)) {
                             $success_message = "If an account with that email exists, a password reset link has been sent. Please check your inbox (and spam folder).";
                        } else {
                            $errors[] = "Could not send the reset email. Please contact support.";
                        }
                    } else {
                        $errors[] = "Failed to set reset token.";
                    }
                    $stmt_update_token->close();
                }
            } else {
                $success_message = "If an account with that email exists, a password reset link has been sent. Please check your inbox (and spam folder).";
            }
        }
    }
}
$page_title = "Forgot Password";
include 'includes/header.php';
?>

<h2>Forgot Your Password?</h2>
<p>Enter your email address below, and if a verified account exists, we'll send a link to reset your password.</p>

<?php if (!empty($errors)): ?><div class="errors"><ul><?php foreach ($errors as $error): ?><li><?php echo sanitize_output($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success_message): ?><div class="success"><?php echo sanitize_output($success_message); ?></div><?php endif; ?>

<?php if (empty($success_message) || !empty($errors)): ?>
<form action="forgot_password.php" method="post">
    <div>
        <label for="email">Your Email Address:</label>
        <input type="email" id="email" name="email" required>
    </div>
    <button type="submit" class="button primary">Send Reset Link</button>
</form>
<?php endif; ?>
<p style="margin-top:15px;"><a href="client_login.php">Back to Login</a></p>

<?php include 'includes/footer.php'; ?>