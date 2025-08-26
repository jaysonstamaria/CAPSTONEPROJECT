<?php
// File: index.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Welcome to CarsRUs";

// --- Fetch Featured Cars ---
$featured_cars = [];
$limit_featured = 3;
try {
    $stmt_featured = $mysqli->prepare("SELECT id, make, model, year, price, description FROM cars WHERE status = 'available' ORDER BY added_at DESC LIMIT ?");
    if ($stmt_featured) {
        $stmt_featured->bind_param("i", $limit_featured);
        $stmt_featured->execute();
        $result_featured = $stmt_featured->get_result();
        while ($row_featured = $result_featured->fetch_assoc()) {
            // Fetch the primary image for each featured car
            $primary_image_path_featured = null;
            $stmt_img_feat = $mysqli->prepare("SELECT image_path FROM car_images WHERE car_id = ? AND is_primary = 1 LIMIT 1");
            if ($stmt_img_feat) {
                $stmt_img_feat->bind_param("i", $row_featured['id']);
                $stmt_img_feat->execute();
                $res_img_feat = $stmt_img_feat->get_result();
                if ($img_row_feat = $res_img_feat->fetch_assoc()) {
                    $primary_image_path_featured = $img_row_feat['image_path'];
                }
                $stmt_img_feat->close();
            }
            $row_featured['primary_image'] = $primary_image_path_featured;
            $featured_cars[] = $row_featured;
        }
        $stmt_featured->close();
    }
} catch (Exception $e) {
    error_log("Error fetching featured cars: " . $e->getMessage());
}

include 'includes/header.php';
?>

<section class="welcome-section" style="text-align: center; padding: 40px 20px;">
    <h2>Welcome to CarsRUs</h2>
    <p style="font-size: 1.1em; color: var(--light-text-color);">Your trusted partner for quality pre-owned vehicles. Find your next car today!</p>
    <div class="cta-buttons" style="margin-top: 25px;">
        <a href="listings.php" class="button primary">View All Available Cars</a>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="client_login.php" class="button secondary">Client Login</a>
        <?php elseif ($_SESSION['role'] === 'client'): ?>
             <a href="client_dashboard.php" class="button secondary">My Dashboard</a>
        <?php endif; ?>
    </div>
</section>

<hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">

<?php if (!empty($featured_cars)): ?>
<section class="featured-cars" style="padding: 20px;">
    <h2 style="text-align: center; border-bottom:none;">Recently Added Cars</h2>
    <div class="car-listings">
        <?php foreach ($featured_cars as $car): ?>
            <div class="car-card">
                 <?php
                 $primary_image_feat = $car['primary_image'] ?? null;
                 $alt_text_feat = sanitize_output($car['year'] . ' ' . $car['make'] . ' ' . $car['model']);
                 ?>
                <a href="car_details.php?id=<?php echo $car['id']; ?>" style="text-decoration:none; color:inherit;">
                    <?php if (!empty($primary_image_feat) && file_exists($primary_image_feat)): ?>
                        <img src="<?php echo sanitize_output($primary_image_feat); ?>" alt="<?php echo $alt_text_feat; ?>" class="car-image">
                    <?php else: ?>
                        <div class="car-no-image">(Image Not Available)</div>
                    <?php endif; ?>
                    <div class="car-card-content">
                        <h3><?php echo $alt_text_feat; ?></h3>
                        <?php if(!empty($car['description'])): ?>
                            <p class="car-ad-title"><?php echo sanitize_output($car['description']); ?></p>
                        <?php endif; ?>
                        <p class="car-price">₱<?php echo number_format(sanitize_output($car['price']), 2); ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
     <p style="text-align: center; margin-top: 30px;"><a href="listings.php" class="button secondary">See All Available Cars &raquo;</a></p>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>