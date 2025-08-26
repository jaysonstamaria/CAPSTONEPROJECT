<?php
// File: change_password.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login(); // Any logged-in user can access
$errors = [];
$success_message = '';
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        $errors[] = "All password fields are required.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_new_password) {
        $errors[] = "New passwords do not match.";
    } else {
        $stmt_get_pass = $mysqli->prepare("SELECT password FROM users WHERE id = ?");
        if ($stmt_get_pass) {
            $stmt_get_pass->bind_param("i", $user_id);
            $stmt_get_pass->execute();
            $user_data = $stmt_get_pass->get_result()->fetch_assoc();
            $stmt_get_pass->close();

            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update_pass = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt_update_pass) {
                    $stmt_update_pass->bind_param("si", $new_hashed_password, $user_id);
                    if ($stmt_update_pass->execute()) {
                        $success_message = "Password changed successfully!";
                    } else {
                        $errors[] = "Failed to update password.";
                    }
                    $stmt_update_pass->close();
                }
            } else {
                $errors[] = "Incorrect current password.";
            }
        }
    }
}
$page_title = "Change Password";
include 'includes/header.php';
?>

<h2>Change Your Password</h2>

<?php if (!empty($errors)): ?><div class="errors"><ul><?php foreach ($errors as $error): ?><li><?php echo sanitize_output($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success_message): ?><div class="success"><?php echo sanitize_output($success_message); ?></div><?php endif; ?>

<?php if (empty($success_message)): ?>
<form action="change_password.php" method="post">
    <div>
        <label for="current_password">Current Password:</label>
        <input type="password" id="current_password" name="current_password" required>
    </div>
    <div>
        <label for="new_password">New Password (min 8 characters):</label>
        <input type="password" id="new_password" name="new_password" required>
    </div>
    <div>
        <label for="confirm_new_password">Confirm New Password:</label>
        <input type="password" id="confirm_new_password" name="confirm_new_password" required>
    </div>
    <button type="submit" class="button primary">Change Password</button>
</form>
<?php endif; ?>
<p style="margin-top:15px;">
    <?php
        $dashboard_link = 'index.php'; // Safe default
        if(isset($_SESSION['role'])){
            if($_SESSION['role'] === 'admin') $dashboard_link = 'admin_dashboard.php';
            if($_SESSION['role'] === 'client') $dashboard_link = 'client_dashboard.php';
        }
    ?>
    <a href="<?php echo $dashboard_link; ?>">Back to Dashboard</a>
</p>

<?php include 'includes/footer.php'; ?>