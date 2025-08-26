<?php
// File: client_dashboard.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Ensure only logged-in clients can access this page
require_login('client');
$user_id = $_SESSION['user_id'];
$page_title = "My Dashboard"; 

// --- Fetch Client's Submitted Applications and Loan Details ---
$applications = [];
$dashboard_error_msg = '';
$stmt_apps = $mysqli->prepare("SELECT
                                  a.id, a.selected_car_id, a.status, a.submitted_at,
                                  a.agreed_car_price, a.loan_term_months, a.monthly_payment_amount,
                                  a.loan_status, a.next_payment_due_date,
                                  c.make as car_make, c.model as car_model, c.year as car_year
                               FROM applications a
                               LEFT JOIN cars c ON a.selected_car_id = c.id
                               WHERE a.user_id = ?
                               ORDER BY a.submitted_at DESC");
if ($stmt_apps) {
    $stmt_apps->bind_param("i", $user_id);
    $stmt_apps->execute();
    $result_apps = $stmt_apps->get_result();
    while ($row = $result_apps->fetch_assoc()) {
        // If loan is active or paid off, fetch payment summary
        if ($row['status'] === 'approved' && ($row['loan_status'] === 'active' || $row['loan_status'] === 'paid_off') && !empty($row['agreed_car_price'])) {
            $stmt_payment_sum = $mysqli->prepare("SELECT SUM(amount_paid) as total_paid FROM payments WHERE application_id = ?");
            if ($stmt_payment_sum) {
                $stmt_payment_sum->bind_param("i", $row['id']);
                $stmt_payment_sum->execute();
                $payment_sum_result = $stmt_payment_sum->get_result()->fetch_assoc();
                $row['total_paid_on_loan'] = $payment_sum_result['total_paid'] ?? 0;
                $row['remaining_balance_on_loan'] = ($row['agreed_car_price'] ?? 0) - $row['total_paid_on_loan'];
                $stmt_payment_sum->close();
            }
        }
        $applications[] = $row;
    }
    $stmt_apps->close();
} else {
    error_log("Failed to prepare statement for client applications: " . $mysqli->error);
    $dashboard_error_msg = "Could not retrieve your applications.";
}

// --- Fetch Payment History for all of client's active/paid_off loans ---
$payment_history_by_app = [];
$stmt_all_payments = $mysqli->prepare(
    "SELECT p.application_id, p.payment_date, p.amount_paid, p.payment_method
     FROM payments p
     JOIN applications a ON p.application_id = a.id
     WHERE a.user_id = ? AND (a.loan_status = 'active' OR a.loan_status = 'paid_off')
     ORDER BY p.application_id, p.payment_date DESC"
);
if($stmt_all_payments){
    $stmt_all_payments->bind_param("i", $user_id);
    $stmt_all_payments->execute();
    $result_all_payments = $stmt_all_payments->get_result();
    while($payment_row = $result_all_payments->fetch_assoc()){
        // Group payments by their application ID
        $payment_history_by_app[$payment_row['application_id']][] = $payment_row;
    }
    $stmt_all_payments->close();
}

include 'includes/header.php';
?>

<h2>My Dashboard</h2>
<p>Welcome, <?php echo sanitize_output($_SESSION['username']); ?>. Here you can monitor the status of your car applications and loan payments.</p>

<?php if (isset($dashboard_error_msg)): ?><div class="errors"><?php echo sanitize_output($dashboard_error_msg); ?></div><?php endif; ?>
<?php if (isset($_GET['submitted']) && isset($_GET['app_id'])): ?>
    <div class="success">
        Your application (ID: <?php echo sanitize_output($_GET['app_id']); ?>) has been submitted successfully!
        Our team will review it and you will see status updates here.
    </div>
<?php endif; ?>

<section id="my-requests" class="dashboard-section">
    <h3>Your Applications & Loans</h3>
    <?php if (empty($applications)): ?>
        <p>You have not submitted any applications yet.</p>
        <p><a href="listings.php" class="button primary">Browse Cars to Apply</a></p>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="application-summary-card dashboard-section" style="margin-bottom:20px;">
                <h4>
                    Application #<?php echo $app['id']; ?> for
                    <?php if ($app['car_year'] && $app['car_make']): ?>
                        <strong><?php echo sanitize_output($app['car_year'] . ' ' . $app['car_make'] . ' ' . $app['car_model']); ?></strong>
                    <?php else: ?>
                        <strong>a requested car</strong>
                    <?php endif; ?>
                </h4>
                <p><small>Submitted on: <?php echo date("F j, Y, g:i a", strtotime($app['submitted_at'])); ?></small></p>

                <p><strong>Application Status:</strong>
                    <span class="status-<?php echo sanitize_output($app['status']); ?> large-status">
                        <?php echo sanitize_output(ucfirst(str_replace('_', ' ', $app['status']))); ?>
                    </span>
                </p>

                <?php // If application is approved AND loan terms are set, show loan details ?>
                <?php if ($app['status'] === 'approved' && !empty($app['loan_term_months'])): ?>
                    <div class="loan-details-client" style="margin-top: 15px; padding-top:15px; border-top: 1px solid #eee;">
                        <h5>Your Loan Details:</h5>
                        <div class="loan-summary-grid">
                            <div><strong>Agreed Car Price:</strong><br>₱<?php echo number_format(sanitize_output($app['agreed_car_price']), 2); ?></div>
                            <div><strong>Final Loan Term:</strong><br><?php echo sanitize_output($app['loan_term_months']); ?> Months</div>
                            <div><strong>Monthly Payment:</strong><br>₱<?php echo number_format(sanitize_output($app['monthly_payment_amount']), 2); ?></div>
                        </div>
                        <div class="loan-summary-grid" style="margin-top:15px;">
                             <div>
                                <strong>Loan Status:</strong><br>
                                <span class="status-<?php echo sanitize_output($app['loan_status']); ?>">
                                    <?php echo sanitize_output(ucfirst($app['loan_status'])); ?>
                                </span>
                            </div>
                            <?php if ($app['loan_status'] === 'active'): ?>
                                <div><strong>Next Payment Due:</strong><br><?php echo $app['next_payment_due_date'] ? date("F j, Y", strtotime($app['next_payment_due_date'])) : 'N/A'; ?></div>
                                <div><strong>Total Paid:</strong><br>₱<?php echo number_format(sanitize_output($app['total_paid_on_loan'] ?? 0), 2); ?></div>
                                <div><strong style="color: var(--error-color);">Remaining Balance:</strong><br>₱<?php echo number_format(sanitize_output($app['remaining_balance_on_loan'] ?? ($app['agreed_car_price'] ?? 0)), 2); ?></div>
                            <?php elseif($app['loan_status'] === 'paid_off'): ?>
                                <div style="grid-column: span 3; color: var(--success-color); font-weight: bold;">Congratulations! This loan is fully paid off.</div>
                            <?php endif; ?>
                        </div>

                        <?php // Display Payment History for this loan ?>
                        <?php if (isset($payment_history_by_app[$app['id']]) && !empty($payment_history_by_app[$app['id']])): ?>
                            <h6 style="margin-top:20px;">Payment History:</h6>
                            <table style="font-size:0.9em; margin-top:10px;">
                                <thead><tr><th>Date</th><th>Amount Paid</th><th>Method</th></tr></thead>
                                <tbody>
                                    <?php foreach ($payment_history_by_app[$app['id']] as $payment): ?>
                                        <tr>
                                            <td><?php echo date("M j, Y", strtotime($payment['payment_date'])); ?></td>
                                            <td>₱<?php echo number_format(sanitize_output($payment['amount_paid']), 2); ?></td>
                                            <td><?php echo sanitize_output($payment['payment_method'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif($app['loan_status'] === 'active'): ?>
                             <p><small>No payments recorded yet for this loan.</small></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<p style="margin-top:30px;"><a href="listings.php" class="button primary">Apply for Another Car</a></p>

<?php include 'includes/footer.php'; ?>
<style>
/* Add these styles to your main style.css */
.dashboard-section { margin-bottom: 30px; padding: 25px; border: 1px solid var(--border-color); border-radius: 5px; background-color: #fff; }
.dashboard-section h3 { margin-top: 0; }
.large-status { font-size: 1.1em; padding: 5px 12px; }
.loan-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}
.loan-summary-grid > div {
    background-color: var(--light-gray-bg);
    padding: 10px;
    border-radius: 4px;
}
.loan-summary-grid > div strong {
    font-size: 0.9em;
    color: var(--light-text-color);
}
</style>