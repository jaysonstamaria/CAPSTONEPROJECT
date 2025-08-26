<?php
// File: process_admin_action.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login('admin');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: admin_dashboard.php');
    exit;
}

$app_id = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$admin_id = $_SESSION['user_id'];
$redirect_url_base = 'view_application.php?id=' . ($app_id ?: 0);

if (!$app_id || empty($action)) {
    header("Location: admin_dashboard.php?error=" . urlencode("Invalid action or application ID."));
    exit;
}

$success_message = '';
$mysqli->begin_transaction();
try {
    switch ($action) {
        case 'assign_company':
            $company_id = filter_input(INPUT_POST, 'assigned_company_id', FILTER_VALIDATE_INT);
            if (!$company_id) throw new Exception("You must select a financing company.");
            $stmt = $mysqli->prepare("UPDATE applications SET status = 'forwarded_to_finance', assigned_company_id = ?, assigned_by = ?, assigned_at = NOW() WHERE id = ? AND status = 'pending_review'");
            $stmt->bind_param("iii", $company_id, $admin_id, $app_id);
            if (!$stmt->execute()) throw new Exception("DB Error (Assign): " . $stmt->error);
            if ($stmt->affected_rows > 0) {
                $success_message = "Application successfully forwarded to the financing company.";
            } else { throw new Exception("Application could not be assigned. It may have already been processed."); }
            $stmt->close();
            break;

        case 'mark_approved':
            $stmt = $mysqli->prepare("UPDATE applications SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'forwarded_to_finance'");
            $stmt->bind_param("ii", $admin_id, $app_id);
            if (!$stmt->execute()) throw new Exception("DB Error (Approve): " . $stmt->error);
            if ($stmt->affected_rows > 0) {
                $success_message = "Application has been marked as APPROVED.";
            } else { throw new Exception("Application could not be approved. It may not have been in 'forwarded' status."); }
            $stmt->close();
            break;

        case 'mark_rejected':
            $stmt = $mysqli->prepare("UPDATE applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND (status = 'pending_review' OR status = 'forwarded_to_finance')");
            $stmt->bind_param("ii", $admin_id, $app_id);
            if (!$stmt->execute()) throw new Exception("DB Error (Reject): " . $stmt->error);
            if ($stmt->affected_rows > 0) {
                $success_message = "Application has been marked as REJECTED.";
            } else { throw new Exception("Application could not be rejected. It may have already been processed."); }
            $stmt->close();
            break;

        default:
            throw new Exception("Invalid action specified.");
    }
    $mysqli->commit();
    header("Location: " . $redirect_url_base . "&success=" . urlencode($success_message));
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Admin Action Failed: " . $e->getMessage());
    header("Location: " . $redirect_url_base . "&error=" . urlencode($e->getMessage()));
    exit;
}
?>