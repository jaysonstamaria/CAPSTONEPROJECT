<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

function require_login($required_role = null) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: client_login.php?error=login_required");
        exit;
    }
    if ($required_role !== null) {
        $user_role = $_SESSION['role'] ?? null;
        $allowed = false;
        if (is_array($required_role)) {
            if ($user_role && in_array($user_role, $required_role)) {
                $allowed = true;
            }
        } elseif ($user_role === $required_role) {
            $allowed = true;
        }
        if (!$allowed) {
            header("Location: index.php?error=access_denied");
            exit;
        }
    }
}

function sanitize_output($data) {
    if (is_scalar($data)) {
        return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
    }
    return '';
}
?>