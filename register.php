<?php
// File: register.php (Updated for Test OTP Mode)

require_once 'includes/db_connect.php'; // This now includes our DEV_MODE_DISPLAY_OTP constant
require_once 'includes/functions.php';
require_once 'includes/email_functions.php';

$errors = [];
$success_message = '';
$form_data = [];
$displayed_otp_for_testing = null; // Variable to hold the OTP for display in test mode

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $form_data = $_POST;

    // --- Validation (No changes here) ---
    if (empty($username)) $errors['username'] = "Username is required.";
    elseif (strlen($username) < 4) $errors['username'] = "Username must be at least 4 characters.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors['username'] = "Username can only contain letters, numbers, and underscores.";
    if (empty($email)) $errors['email'] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = "Invalid email format.";
    if (empty($password)) $errors['password'] = "Password is required.";
    elseif (strlen($password) < 8) $errors['password'] = "Password must be at least 8 characters long.";
    if ($password !== $confirm_password) $errors['confirm_password'] = "Passwords do not match.";

    // --- Check if username/email exists (No changes here) ---
    if (empty($errors)) {
        $clean_email = $mysqli->real_escape_string($email);
        $clean_username = $mysqli->real_escape_string($username);
        $mysqli->query("DELETE FROM users WHERE (email = '{$clean_email}' OR username = '{$clean_username}') AND is_verified = 0 AND otp_expiry < NOW()");
        $stmt_check = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors['general'] = "An account with this username or email already exists.";
            }
            $stmt_check->close();
        }
    }

    // --- Prepare User Data & OTP if No Errors ---
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'client';
        $otp = rand(100000, 999999);
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $is_verified = 0;

        $stmt_insert_user = $mysqli->prepare("INSERT INTO users (username, email, password, role, otp_code, otp_expiry, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if ($stmt_insert_user) {
            $stmt_insert_user->bind_param("ssssisi", $username, $email, $hashed_password, $role, $otp, $otp_expiry, $is_verified);
            if ($stmt_insert_user->execute()) {
                $_SESSION['otp_user_email'] = $email; // Pass email to next page regardless of mode

                // *** NEW LOGIC: Check DEV_MODE_DISPLAY_OTP constant ***
                if (defined('DEV_MODE_DISPLAY_OTP') && DEV_MODE_DISPLAY_OTP === TRUE) {
                    // --- TEST MODE ---
                    $success_message = "Registration data saved! You are in TEST MODE.";
                    $displayed_otp_for_testing = $otp; // Store OTP for display
                } else {
                    // --- LIVE MODE ---
                    $email_subject = "Your Verification Code for CarsRUs";
                    $email_body_html = "<h2>Welcome to CarsRUs!</h2><p>Hello " . sanitize_output($username) . ",</p><p>Your One-Time Password (OTP) is:</p><p style='font-size: 24px; font-weight: bold;'>" . $otp . "</p><p>This code is valid for 15 minutes.</p><p>Thanks,<br>The CarsRUs Team</p>";

                    if (send_email($email, $email_subject, $email_body_html)) {
                        header("Location: verify_otp.php"); // Redirect on successful email send
                        exit;
                    } else {
                        $errors['general'] = "Registration data was saved, but we could not send the OTP email. Please contact support.";
                    }
                }

            } else {
                $errors['general'] = "Registration failed. This username or email may already be in use.";
            }
            $stmt_insert_user->close();
        } else {
            $errors['general'] = "Database error during registration preparation.";
        }
    }
}
$page_title = "Client Registration";
include 'includes/header.php';
?>

<h2>Client Registration</h2>
<p>Create your account to start your car application process.</p>

<?php if (isset($errors['general'])): ?><div class="errors"><?php echo sanitize_output($errors['general']); ?></div><?php endif; ?>

<?php // Display success message for TEST MODE
if ($success_message): ?>
    <div class="success"><?php echo sanitize_output($success_message); ?></div>
    <?php if ($displayed_otp_for_testing): ?>
        <div class="info" style="font-size: 1.2em; text-align:center; padding: 15px; margin-top:10px;">
            <strong>For Testing - Your OTP is: <?php echo $displayed_otp_for_testing; ?></strong>
        </div>
        <p style="text-align:center; margin-top:15px;">
            <a href="verify_otp.php" class="button primary">Proceed to Verify OTP</a>
        </p>
    <?php endif; ?>
<?php else: // Display the form if not successful or not in test mode success state ?>
    <form action="register.php" method="post" novalidate>
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required value="<?php echo sanitize_output($form_data['username'] ?? ''); ?>">
            <?php if(isset($errors['username'])) echo '<small class="error-text">'.$errors['username'].'</small>'; ?>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo sanitize_output($form_data['email'] ?? ''); ?>">
            <?php if(isset($errors['email'])) echo '<small class="error-text">'.$errors['email'].'</small>'; ?>
        </div>
        <div>
            <label for="password">Password (min 8 characters):</label>
            <input type="password" id="password" name="password" required>
            <?php if(isset($errors['password'])) echo '<small class="error-text">'.$errors['password'].'</small>'; ?>
        </div>
        <div>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <?php if(isset($errors['confirm_password'])) echo '<small class="error-text">'.$errors['confirm_password'].'</small>'; ?>
        </div>
        <button type="submit" class="button primary">Register</button>
    </form>
    <p style="margin-top: 15px;">Already have an account? <a href="client_login.php">Login here</a>.</p>
    <style>.error-text { color: var(--error-color); font-size: 0.8em; display: block; margin-top: 2px; }</style>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>