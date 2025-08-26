<?php
// File: verify_otp.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$user_email_for_otp = $_SESSION['otp_user_email'] ?? null;

if (!$user_email_for_otp) {
    header("Location: register.php?error=" . urlencode("Session expired or invalid access. Please register again."));
    exit;
}

$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_otp = trim($_POST['otp'] ?? '');

    if (empty($submitted_otp) || !ctype_digit($submitted_otp) || strlen($submitted_otp) != 6) {
        $errors[] = "Please enter a valid 6-digit OTP.";
    } else {
        $stmt_check_otp = $mysqli->prepare("SELECT id, otp_code, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
        if ($stmt_check_otp) {
            $stmt_check_otp->bind_param("s", $user_email_for_otp);
            $stmt_check_otp->execute();
            $result = $stmt_check_otp->get_result();
            $user_otp_data = $result->fetch_assoc();
            $stmt_check_otp->close();

            if ($user_otp_data) {
                $stored_otp = $user_otp_data['otp_code'];
                $otp_expiry_time = new DateTime($user_otp_data['otp_expiry']);
                $current_time = new DateTime();

                if ($current_time > $otp_expiry_time) {
                    $errors[] = "OTP has expired. Please <a href='register.php'>register again</a> to get a new OTP.";
                } elseif ($stored_otp == $submitted_otp) {
                    $stmt_verify = $mysqli->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = ?");
                    if ($stmt_verify) {
                        $stmt_verify->bind_param("i", $user_otp_data['id']);
                        if ($stmt_verify->execute()) {
                            unset($_SESSION['otp_user_email']);
                            header("Location: client_login.php?verified=true");
                            exit;
                        } else {
                            $errors[] = "Failed to update verification status. Please contact support.";
                        }
                        $stmt_verify->close();
                    }
                } else {
                    $errors[] = "Invalid OTP entered. Please try again.";
                }
            } else {
                $errors[] = "Could not find pending verification for your email. Please <a href='register.php'>register again</a>.";
            }
        }
    }
}
$page_title = "Verify Your Account";
include 'includes/header.php';
?>

<h2>Verify Your Account</h2>
<p>An OTP has been sent to <strong><?php echo sanitize_output($user_email_for_otp); ?></strong>. Please enter it below to activate your account.</p>

<?php if (!empty($errors)): ?>
    <div class="errors">
        <ul><?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form action="verify_otp.php" method="post">
    <div>
        <label for="otp">Enter 6-Digit OTP:</label>
        <input type="text" id="otp" name="otp" maxlength="6" required pattern="\d{6}" title="Enter 6 digits" style="text-align:center; font-size: 1.2em; letter-spacing: 5px;">
    </div>
    <button type="submit" class="button primary">Verify OTP</button>
</form>
<p style="margin-top:15px;"><small>Didn't receive the email? Check your spam folder or <a href="register.php">start the registration process again</a> to get a new OTP.</small></p>

<?php include 'includes/footer.php'; ?>
