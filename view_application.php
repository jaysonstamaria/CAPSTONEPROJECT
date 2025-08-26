<?php
// File: view_application.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login('admin');

$app_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$current_user_id = $_SESSION['user_id'];
$redirect_url = 'admin_dashboard.php';

if (!$app_id) {
    header("Location: $redirect_url?error=" . urlencode("Invalid application ID."));
    exit;
}

// --- Fetch Application, Car, Company, and User Details ---
$application = null;
$stmt_app_details = $mysqli->prepare("SELECT a.*,
                                 u.username as client_username, u.email as login_email,
                                 assigning_admin.username as assigning_admin_name,
                                 last_reviewer.username as last_reviewer_name,
                                 fc.name as assigned_company_name,
                                 c.make as selected_car_make, c.model as selected_car_model,
                                 c.year as selected_car_year, c.price as selected_car_price
                          FROM applications a
                          JOIN users u ON a.user_id = u.id
                          LEFT JOIN users assigning_admin ON a.assigned_by = assigning_admin.id
                          LEFT JOIN users last_reviewer ON a.reviewed_by = last_reviewer.id
                          LEFT JOIN financing_companies fc ON a.assigned_company_id = fc.id
                          LEFT JOIN cars c ON a.selected_car_id = c.id
                          WHERE a.id = ?");

if ($stmt_app_details) {
    $stmt_app_details->bind_param("i", $app_id);
    $stmt_app_details->execute();
    $application = $stmt_app_details->get_result()->fetch_assoc();
    $stmt_app_details->close();
}
if (!$application) {
    header("Location: $redirect_url?error=" . urlencode("Application not found."));
    exit;
}
$page_title = "Application Details #" . $application['id'];

// --- Fetch Uploaded Requirement Files ---
$requirements_files = [];
$stmt_req_files = $mysqli->prepare("SELECT requirement_type, file_name, file_path, uploaded_at FROM application_requirements WHERE application_id = ? ORDER BY requirement_type");
if ($stmt_req_files) {
    $stmt_req_files->bind_param("i", $app_id);
    $stmt_req_files->execute();
    $result_req_files = $stmt_req_files->get_result();
    while ($row_file = $result_req_files->fetch_assoc()) {
        $requirements_files[$row_file['requirement_type']][] = $row_file;
    }
    $stmt_req_files->close();
}

// --- Fetch Financing Companies for the dropdown ---
$financing_companies = [];
$result_companies = $mysqli->query("SELECT id, name FROM financing_companies WHERE is_active = 1 ORDER BY name ASC");
if($result_companies){
    while($company_row = $result_companies->fetch_assoc()){
        $financing_companies[] = $company_row;
    }
}

include 'includes/header.php';
?>
<style>
    /* Add these styles to your main style.css */
    .application-section { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .application-section:last-child { border-bottom: none; }
    .application-section h4 { color: var(--brand-color); margin-top: 0; margin-bottom: 10px; }
    .application-section p { margin-bottom: 8px; }
    .application-section strong { min-width: 180px; display: inline-block; color: var(--light-text-color); }
    .requirements-list ul { list-style: none; padding-left: 0; }
    .action-box { margin-top: 30px; padding: 20px; background-color: #e9f5ff; border: 1px solid #cce7ff; border-radius: 5px; }
    .action-box h3 { margin-top: 0; }
</style>

<h2>Application Details (ID: <?php echo $application['id']; ?>) - Client: <?php echo sanitize_output($application['client_username']); ?></h2>

<?php if (isset($_GET['error'])): ?><div class="errors"><?php echo sanitize_output(urldecode($_GET['error'])); ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="success"><?php echo sanitize_output(urldecode($_GET['success'])); ?></div><?php endif; ?>

<div class="application-details dashboard-section">
    <div class="application-section"><h4>Personal Information</h4><!-- Display all personal info fields from $application array --></div>
    <div class="application-section"><h4>Mother's Maiden Name</h4><!-- Display mother's maiden name fields --></div>
    <div class="application-section"><h4>Car & Financing Request</h4><!-- Display selected car, preferred term, etc. --></div>
    <div class="application-section"><h4>Source of Income & Uploaded Requirements</h4>
        <p><strong>Source of Income:</strong> <?php echo sanitize_output(ucfirst($application['source_of_income'] ?: 'N/A')); ?></p>
        <div class="requirements-list">
            <?php if (empty($requirements_files)): ?>
                <p>No requirement files were uploaded.</p>
            <?php else: ?>
                <ul>
                <?php foreach ($requirements_files as $req_type => $files): ?>
                    <li><strong><?php echo sanitize_output(ucwords(str_replace(['_', 'employed', 'business'], [' ', '', ''], $req_type))); ?>:</strong>
                        <ul style="padding-left: 20px;"><?php foreach ($files as $file): ?><li><a href="<?php echo sanitize_output($file['file_path']); ?>" target="_blank"><?php echo sanitize_output($file['file_name']); ?></a></li><?php endforeach; ?></ul>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="application-section">
        <h4>Processing Status</h4>
        <p><strong>Overall Status:</strong><span class="status-<?php echo sanitize_output($application['status']); ?> large-status"><?php echo sanitize_output(ucwords(str_replace('_', ' ', $application['status']))); ?></span></p>
        <?php if ($application['status'] !== 'pending_review'): ?>
            <p><strong>Assigned To:</strong> <?php echo sanitize_output($application['assigned_company_name'] ?? 'N/A'); ?> by <?php echo sanitize_output($application['assigning_admin_name'] ?? 'N/A'); ?> on <?php echo $application['assigned_at'] ? date("M j, Y, g:i a", strtotime($application['assigned_at'])) : 'N/A'; ?></p>
        <?php endif; ?>
        <?php if ($application['status'] === 'approved' || $application['status'] === 'rejected'): ?>
            <p><strong>Final Outcome Updated By:</strong> <?php echo sanitize_output($application['last_reviewer_name'] ?? 'N/A'); ?> at <?php echo $application['reviewed_at'] ? date("F j, Y, g:i a", strtotime($application['reviewed_at'])) : 'N/A'; ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Conditional Action Boxes -->
<?php if ($application['status'] === 'pending_review'): ?>
    <div class="action-box">
        <h3>Assign to Financing Company</h3>
        <p>Review the details above. If the application is viable, choose a financing company to forward it to.</p>
        <form action="process_admin_action.php" method="post">
            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>"><input type="hidden" name="action" value="assign_company">
            <div><label for="assigned_company_id">Select Company:</label>
                <select id="assigned_company_id" name="assigned_company_id" required>
                    <option value="">-- Choose a company --</option>
                    <?php foreach($financing_companies as $company): ?><option value="<?php echo $company['id']; ?>"><?php echo sanitize_output($company['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button primary">Forward Application</button>
             <button type="submit" form="rejectForm" class="button secondary" style="background-color:var(--error-color); color:#fff;">Reject Now</button>
        </form>
         <form id="rejectForm" action="process_admin_action.php" method="post" style="display:none;"><input type="hidden" name="application_id" value="<?php echo $application['id']; ?>"><input type="hidden" name="action" value="mark_rejected"></form>
    </div>
<?php endif; ?>

<?php if ($application['status'] === 'forwarded_to_finance'): ?>
    <div class="action-box">
        <h3>Update Final Outcome</h3>
        <p>Update the status below once you receive the decision from <strong><?php echo sanitize_output($application['assigned_company_name']); ?></strong>.</p>
        <form action="process_admin_action.php" method="post" style="display: inline-block;"><input type="hidden" name="application_id" value="<?php echo $application['id']; ?>"><input type="hidden" name="action" value="mark_approved"><button type="submit" class="button primary">Mark as APPROVED</button></form>
        <form action="process_admin_action.php" method="post" style="display: inline-block;"><input type="hidden" name="application_id" value="<?php echo $application['id']; ?>"><input type="hidden" name="action" value="mark_rejected"><button type="submit" class="button danger">Mark as REJECTED</button></form>
    </div>
<?php endif; ?>

<?php if ($application['status'] === 'approved' && empty($application['loan_term_months'])): ?>
    <div class="action-box" style="background-color: #d4edda;"><!-- ... "Setup Loan" button as before ... --></div>
<?php endif; ?>

<p style="margin-top: 30px;"><a href="<?php echo $redirect_url; ?>">&laquo; Back to Dashboard</a></p>

<?php include 'includes/footer.php'; ?>