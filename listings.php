<?php
// File: listings.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$page_title = "Available Cars";

$available_cars = [];
$sql_list_cars = "SELECT id, make, model, year, price, description,
                         down_payment_amount, amortization_60_months
                  FROM cars
                  WHERE status = 'available'
                  ORDER BY added_at DESC";
$stmt_list_cars = $mysqli->prepare($sql_list_cars);

if ($stmt_list_cars) {
    $stmt_list_cars->execute();
    $result_list_cars = $stmt_list_cars->get_result();
    while ($row_list_car = $result_list_cars->fetch_assoc()) {
        $primary_image_path = null;
        $stmt_img = $mysqli->prepare("SELECT image_path FROM car_images WHERE car_id = ? AND is_primary = 1 LIMIT 1");
        if ($stmt_img) {
            $stmt_img->bind_param("i", $row_list_car['id']);
            $stmt_img->execute();
            $res_img_list = $stmt_img->get_result();
            if ($img_row_list = $res_img_list->fetch_assoc()) {
                $primary_image_path = $img_row_list['image_path'];
            }
            $stmt_img->close();
        }
        $row_list_car['primary_image'] = $primary_image_path;
        $available_cars[] = $row_list_car;
    }
    $stmt_list_cars->close();
} else {
    error_log("Prepare failed for listings page: " . $mysqli->error);
    $listing_error = "Could not retrieve car listings at this time.";
}

include 'includes/header.php';
?>

<h2>Available Cars Inventory</h2>

<?php if (isset($listing_error)): ?>
    <div class="errors"><?php echo sanitize_output($listing_error); ?></div>
<?php endif; ?>

<?php if (empty($available_cars) && !isset($listing_error)): ?>
    <p>Sorry, there are currently no cars available in our inventory. Please check back soon!</p>
<?php elseif (!empty($available_cars)): ?>
    <p>Browse our selection of quality pre-owned vehicles. Click on any car to view more details and start your application.</p>
    <div class="car-listings">
        <?php foreach ($available_cars as $car): ?>
            <div class="car-card">
                <?php
                 $primary_image_to_display = $car['primary_image'] ?? null;
                 $alt_text = sanitize_output($car['year'] . ' ' . $car['make'] . ' ' . $car['model']);
                 ?>
                <a href="car_details.php?id=<?php echo $car['id']; ?>" style="text-decoration:none; color:inherit;">
                    <?php if (!empty($primary_image_to_display) && file_exists($primary_image_to_display)): ?>
                        <img src="<?php echo sanitize_output($primary_image_to_display); ?>" alt="<?php echo $alt_text; ?>" class="car-image">
                    <?php else: ?>
                        <div class="car-no-image">(Image Not Available)</div>
                    <?php endif; ?>
                    <div class="car-card-content">
                        <h3><?php echo $alt_text; ?></h3>
                        <?php if(!empty($car['description'])): ?>
                            <p class="car-ad-title"><?php echo sanitize_output($car['description']); ?></p>
                        <?php endif; ?>
                        <p class="car-price">₱<?php echo number_format(sanitize_output($car['price']), 2); ?></p>

                        <?php if(!empty($car['down_payment_amount']) || !empty($car['amortization_60_months'])): ?>
                            <div class="financing-offer-summary">
                                <?php if(!empty($car['down_payment_amount'])): ?>
                                    <p style="font-weight:bold; color:var(--brand-color); font-size:1.1em;"><?php echo number_format(sanitize_output($car['down_payment_amount']),0); ?>K DP ONLY</p>
                                <?php endif; ?>
                                <?php if(!empty($car['amortization_60_months'])): ?>
                                    <ul style="list-style:none; padding:0; margin:5px 0 0 0;">
                                        <li>60 Mo - ₱<?php echo number_format(sanitize_output($car['amortization_60_months'])); ?></li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>