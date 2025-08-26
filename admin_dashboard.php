<?php
// File: admin_dashboard.php

// 1. Include necessary files & Start Session
require_once 'includes/db_connect.php'; // Handles DB connection and session_start()
require_once 'includes/functions.php'; // Contains require_login(), sanitize_output()

// 2. Access Control: Only 'admin' role allowed
require_login('admin');
$page_title = "Admin Dashboard";

// Initialize data arrays and errors
$applications = [];
$cars = [];
$stats = [
    'pending_applications' => 0,
    'available_cars' => 0,
    'total_cars' => 0,
    'sold_cars' => 0
];
$dashboard_errors = []; // Collect all dashboard-level errors here

// 3. Data Fetching & Preparation

// --- Application Data Fetching ---
$app_filter_status = $_GET['status'] ?? 'pending_review'; // Default filter to show new applications
$app_allowed_statuses_filter = ['pending_review', 'forwarded_to_finance', 'approved', 'rejected', 'cancelled', 'all'];
// Validate the status filter to prevent unexpected values
if (!in_array($app_filter_status, $app_allowed_statuses_filter)) {
    $app_filter_status = 'pending_review';
}

$sql_apps = "SELECT a.id, a.selected_car_id, a.status, a.submitted_at, u.username as client_username, c.make, c.model, c.year
             FROM applications a
             JOIN users u ON a.user_id = u.id
             LEFT JOIN cars c ON a.selected_car_id = c.id";

$params_apps = [];
$types_apps = '';
if ($app_filter_status !== 'all') {
    $sql_apps .= " WHERE a.status = ?";
    $params_apps[] = $app_filter_status;
    $types_apps .= 's';
}
$sql_apps .= " ORDER BY a.submitted_at DESC";

$stmt_apps = $mysqli->prepare($sql_apps);
if ($stmt_apps) {
    if (!empty($params_apps)) { $stmt_apps->bind_param($types_apps, ...$params_apps); }
    if ($stmt_apps->execute()) {
        $result_apps = $stmt_apps->get_result();
        while ($row = $result_apps->fetch_assoc()) {
            $applications[] = $row;
        }
    } else {
        $dashboard_errors[] = "Error executing applications query: " . $stmt_apps->error;
        error_log("Admin Dashboard: Error executing applications query: " . $stmt_apps->error);
    }
    $stmt_apps->close();
} else {
    $dashboard_errors[] = "Could not prepare statement for applications: " . $mysqli->error;
    error_log("Admin Dashboard: Failed prepare statement for applications view: " . $mysqli->error);
}

// --- Car Inventory Data Fetching ---
$sql_cars = "SELECT c.id, c.make, c.model, c.year, c.price, c.status, c.down_payment_amount, c.description, u.username as admin_username
             FROM cars c
             JOIN users u ON c.added_by = u.id
             ORDER BY c.status ASC, c.added_at DESC";
$stmt_cars_main = $mysqli->prepare($sql_cars);
if ($stmt_cars_main) {
    if ($stmt_cars_main->execute()) {
        $result_cars_main = $stmt_cars_main->get_result();
        while ($row_car = $result_cars_main->fetch_assoc()) {
            $cars[] = $row_car;
        }
    } else {
        $dashboard_errors[] = "Error executing cars query: " . $stmt_cars_main->error;
    }
    $stmt_cars_main->close();
} else {
    $dashboard_errors[] = "Could not prepare statement for car inventory: " . $mysqli->error;
}

// --- Fetch Stats for Overview ---
$stmt_pending_apps_count = $mysqli->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'pending_review'");
if ($stmt_pending_apps_count) {
    if($stmt_pending_apps_count->execute()){
        $result_pending_count = $stmt_pending_apps_count->get_result()->fetch_assoc();
        $stats['pending_applications'] = $result_pending_count['count'] ?? 0;
    }
    $stmt_pending_apps_count->close();
}

$stmt_car_stats = $mysqli->prepare("SELECT status, COUNT(*) as count FROM cars GROUP BY status");
if ($stmt_car_stats) {
    if($stmt_car_stats->execute()){
        $result_car_stats = $stmt_car_stats->get_result();
        while ($row_car_stat = $result_car_stats->fetch_assoc()) {
            if ($row_car_stat['status'] === 'available') {
                $stats['available_cars'] = $row_car_stat['count'];
            } elseif ($row_car_stat['status'] === 'sold') {
                $stats['sold_cars'] = $row_car_stat['count'];
            }
            $stats['total_cars'] += $row_car_stat['count'];
        }
    }
    $stmt_car_stats->close();
}


// 4. Include the Header
include 'includes/header.php';
?>

<h2>Admin Dashboard</h2>
<p>Welcome, <?php echo sanitize_output($_SESSION['username']); ?>. Manage client applications and car inventory from here.</p>

<!-- Display Notifications -->
<?php if (!empty($dashboard_errors)): ?>
    <div class="errors">
        <strong>Dashboard Data Error(s):</strong>
        <ul><?php foreach($dashboard_errors as $err): ?><li><?php echo sanitize_output($err); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if (isset($_GET['car_added'])): ?><div class="success">New car added successfully!</div><?php endif; ?>
<?php if (isset($_GET['car_updated'])): ?><div class="success">Car status updated successfully!</div><?php endif; ?>
<?php if (isset($_GET['car_deleted'])): ?><div class="success">Car listing deleted successfully!</div><?php endif; ?>
<?php if (isset($_GET['app_updated'])): ?><div class="success">Application status updated successfully!</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="errors">Error: <?php echo sanitize_output(urldecode($_GET['error'])); ?></div><?php endif; ?>

<!-- Stats Overview Section -->
<section class="stats-overview">
    <div class="stat-card">
        <span class="stat-number"><?php echo $stats['pending_applications']; ?></span>
        <span class="stat-label">Pending Applications</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?php echo $stats['available_cars']; ?></span>
        <span class="stat-label">Available Cars</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?php echo $stats['sold_cars']; ?></span>
        <span class="stat-label">Cars Sold</span>
    </div>
    <div class="stat-card">
        <span class="stat-number"><?php echo $stats['total_cars']; ?></span>
        <span class="stat-label">Total Cars in Inventory</span>
    </div>
</section>

<!-- Application Management Section -->
<section id="application-management" class="dashboard-section">
    <h3>Manage Client Applications</h3>
    <div class="filter-controls">
        Filter by status:
        <?php
        $filter_links_html_apps = [];
        foreach ($app_allowed_statuses_filter as $status_val) {
            $active_class_app = ($app_filter_status == $status_val) ? 'class="active-filter"' : '';
            $display_text = ucwords(str_replace('_', ' ', $status_val));
            $filter_links_html_apps[] = "<a href=\"?status={$status_val}\" {$active_class_app}>" . $display_text . "</a>";
        }
        echo implode(' | ', $filter_links_html_apps);
        ?>
    </div>
    <h4><?php echo sanitize_output(ucwords(str_replace('_', ' ', $app_filter_status))); ?> Applications</h4>
    <?php if (empty($applications) && empty($dashboard_errors)): ?>
        <p>No applications found with status '<?php echo sanitize_output(str_replace('_', ' ', $app_filter_status)); ?>'.</p>
    <?php elseif(!empty($applications)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Car Applied For</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?php echo $app['id']; ?></td>
                        <td><?php echo sanitize_output($app['client_username']); ?></td>
                        <td>
                            <?php if ($app['year'] && $app['make']): ?>
                                <?php echo sanitize_output($app['year'] . ' ' . $app['make'] . ' ' . $app['model']); ?>
                            <?php else: ?>
                                <span style="color:var(--light-text-color);">General Request</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date("Y-m-d H:i", strtotime($app['submitted_at'])); ?></td>
                        <td>
                            <span class="status-<?php echo sanitize_output($app['status']); ?>">
                                <?php echo sanitize_output(ucwords(str_replace('_', ' ', $app['status']))); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_application.php?id=<?php echo $app['id']; ?>" class="button tertiary">Review / Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>


<!-- Car Inventory Management Section -->
<section id="car-management" class="dashboard-section">
    <h3>Manage Car Inventory</h3>
    <p><a href="add_car.php" class="button primary">Add New Car Listing</a></p>
    <?php if (empty($cars) && empty($dashboard_errors)): ?>
        <p>No cars currently listed in the inventory.</p>
    <?php elseif (!empty($cars)): ?>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Make & Model</th>
                    <th>Year</th>
                    <th>Price (₱)</th>
                    <th>DP (₱)</th>
                    <th>Status</th>
                    <th>Added By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cars as $car): ?>
                    <tr>
                        <td>
                            <?php
                            $primary_image_path = null;
                            $stmt_img_fetch = $mysqli->prepare("SELECT image_path FROM car_images WHERE car_id = ? AND is_primary = 1 LIMIT 1");
                            if ($stmt_img_fetch) {
                                $stmt_img_fetch->bind_param("i", $car['id']);
                                if($stmt_img_fetch->execute()){
                                    $res_img = $stmt_img_fetch->get_result();
                                    if ($img_row = $res_img->fetch_assoc()) {
                                        $primary_image_path = $img_row['image_path'];
                                    }
                                }
                                $stmt_img_fetch->close();
                            }
                            if (!empty($primary_image_path) && file_exists($primary_image_path)) {
                            ?>
                                <img src="<?php echo sanitize_output($primary_image_path); ?>" alt="<?php echo sanitize_output($car['make'] . ' ' . $car['model']); ?>" style="width: 100px; height: auto; object-fit: cover; border-radius: 3px;">
                            <?php } else { ?>
                                <span class="no-image-placeholder">(No Image)</span>
                            <?php } ?>
                        </td>
                        <td>
                            <strong><?php echo sanitize_output($car['make'] . ' ' . $car['model']); ?></strong><br>
                            <small title="<?php echo sanitize_output($car['description'] ?? ''); ?>"><?php echo sanitize_output(substr($car['description'] ?? '', 0, 40)); echo (strlen($car['description'] ?? '') > 40) ? '...' : ''; ?></small>
                        </td>
                        <td><?php echo sanitize_output($car['year']); ?></td>
                        <td><?php echo number_format(sanitize_output($car['price']), 2); ?></td>
                        <td><?php echo !is_null($car['down_payment_amount']) ? number_format(sanitize_output($car['down_payment_amount']), 0) . 'K' : 'N/A'; ?></td>
                        <td><span class="status-<?php echo sanitize_output($car['status']); ?>"><?php echo sanitize_output(ucfirst($car['status'])); ?></span></td>
                        <td><?php echo sanitize_output($car['admin_username']); ?></td>
                        <td class="actions-cell">
                            <?php if ($car['status'] === 'available'): ?>
                                <form action="process_car_action.php" method="post" style="display:inline;" title="Mark as Sold"><input type="hidden" name="car_id" value="<?php echo $car['id']; ?>"><input type="hidden" name="action" value="mark_sold"><button type="submit" class="action-button sold">Sold</button></form>
                            <?php else: ?>
                                 <form action="process_car_action.php" method="post" style="display:inline;" title="Mark as Available"><input type="hidden" name="car_id" value="<?php echo $car['id']; ?>"><input type="hidden" name="action" value="mark_available"><button type="submit" class="action-button available">Avail</button></form>
                            <?php endif; ?>
                            <form action="process_car_action.php" method="post" style="display:inline;" onsubmit="return confirm('DELETE: <?php echo sanitize_output($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?>? This is permanent.');" title="Delete Listing"><input type="hidden" name="car_id" value="<?php echo $car['id']; ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="action-button delete">Del</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
<style>
/* Add these styles to your main css/style.css file */
.stats-overview { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
.stat-card { flex: 1; min-width: 200px; background-color: #fff; padding: 20px; border-radius: 5px; border: 1px solid var(--border-color); text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.stat-card .stat-number { font-size: 2.5em; font-weight: bold; color: var(--brand-color); display: block; }
.stat-card .stat-label { font-size: 0.95em; color: var(--light-text-color); text-transform: uppercase; }
.dashboard-section { margin-bottom: 30px; padding: 25px; border: 1px solid var(--border-color); border-radius: 5px; background-color: #fff; }
.dashboard-section h3 { margin-top: 0; border-bottom: none; }
.no-image-placeholder { color: var(--light-text-color); font-style: italic; font-size: 0.9em; }
.actions-cell { white-space: nowrap; }
.action-button { padding: 4px 10px; font-size: 0.85em; margin: 2px; border: 1px solid var(--border-color); background-color: transparent; color: var(--text-color); cursor: pointer; border-radius: 3px; }
.action-button:hover { background-color: #f1f1f1; }
.action-button.sold { border-color: #ffc107; }
.action-button.available { border-color: #17a2b8; }
.action-button.delete { border-color: var(--error-color); color: var(--error-color); }
.action-button.delete:hover { background-color: var(--error-color); color: #fff; }
.filter-controls { margin-bottom: 20px; padding: 10px; background-color: var(--light-gray-bg); border-radius: 4px; }
.filter-controls a { text-decoration: none; margin: 0 8px; }
.filter-controls a.active-filter { font-weight: bold; color: var(--brand-color); text-decoration: underline; }
</style>