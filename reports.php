<?php
// File: reports.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login('admin');
$page_title = "Financing Reports";

$report_data = [];
// Query to get performance summary for each active financing company
$sql_report = "SELECT
                   fc.name as company_name,
                   COUNT(a.id) as total_forwarded,
                   SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as total_approved,
                   SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as total_rejected
               FROM financing_companies fc
               LEFT JOIN applications a ON fc.id = a.assigned_company_id
               WHERE fc.is_active = 1
               GROUP BY fc.id, fc.name
               ORDER BY total_approved DESC, total_forwarded DESC";

$result_report = $mysqli->query($sql_report);
if ($result_report) {
    while ($row = $result_report->fetch_assoc()) {
        // Calculate approval rate
        $row['approval_rate'] = ($row['total_forwarded'] > 0) ? ($row['total_approved'] / $row['total_forwarded']) * 100 : 0;
        $report_data[] = $row;
    }
} else {
    $report_error = "Could not generate report: " . $mysqli->error;
}


include 'includes/header.php';
?>

<h2>Financing Company Performance Report</h2>
<p>This report shows the number of applications forwarded to each financing partner and their outcomes.</p>

<?php if (isset($report_error)): ?>
    <div class="errors"><?php echo sanitize_output($report_error); ?></div>
<?php elseif (empty($report_data)): ?>
    <div class="info">No data available to generate a report. No applications have been forwarded yet.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Financing Company</th>
                <th>Total Forwarded</th>
                <th>Total Approved</th>
                <th>Total Rejected</th>
                <th>Approval Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($report_data as $company_data): ?>
                <tr>
                    <td><strong><?php echo sanitize_output($company_data['company_name']); ?></strong></td>
                    <td><?php echo (int)$company_data['total_forwarded']; ?></td>
                    <td style="color:var(--success-color);"><?php echo (int)$company_data['total_approved']; ?></td>
                    <td style="color:var(--error-color);"><?php echo (int)$company_data['total_rejected']; ?></td>
                    <td><strong><?php echo number_format($company_data['approval_rate'], 1); ?>%</strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>