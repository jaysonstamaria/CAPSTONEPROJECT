<?php
// File: car_details.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$car_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$car_details = null;
$car_all_images = [];

if (!$car_id) {
    header("Location: listings.php?error=" . urlencode("Invalid car specified."));
    exit;
}

$stmt_car = $mysqli->prepare("SELECT * FROM cars WHERE id = ? AND status = 'available'");
if ($stmt_car) {
    $stmt_car->bind_param("i", $car_id);
    $stmt_car->execute();
    $result_car = $stmt_car->get_result();
    $car_details = $result_car->fetch_assoc();
    $stmt_car->close();
    if (!$car_details) {
        header("Location: listings.php?error=" . urlencode("Car not found or is no longer available."));
        exit;
    }
    $page_title = sanitize_output($car_details['year'] . ' ' . $car_details['make'] . ' ' . $car_details['model']);
} else {
    header("Location: listings.php?error=" . urlencode("Error retrieving car information."));
    exit;
}

$stmt_images = $mysqli->prepare("SELECT image_path, is_primary FROM car_images WHERE car_id = ? ORDER BY is_primary DESC, id ASC");
if ($stmt_images) {
    $stmt_images->bind_param("i", $car_id);
    $stmt_images->execute();
    $result_images = $stmt_images->get_result();
    while ($img_row = $result_images->fetch_assoc()) {
        $car_all_images[] = $img_row;
    }
    $stmt_images->close();
}

include 'includes/header.php';
?>

<div class="car-detail-container">
    <div class="car-gallery">
        <div class="main-image-container">
            <?php
            $primary_img_src = 'path/to/default-image.png'; // A fallback image you should create
            if (!empty($car_all_images)) {
                $primary_img_src = sanitize_output($car_all_images[0]['image_path']); // First image is primary due to ORDER BY
            }
            ?>
            <img src="<?php echo $primary_img_src; ?>" alt="<?php echo sanitize_output($car_details['make'] . ' ' . $car_details['model']); ?>" id="mainCarImage">
        </div>

        <?php if (count($car_all_images) > 1): ?>
            <div class="thumbnail-gallery">
                <?php foreach ($car_all_images as $index => $img): ?>
                    <?php if (file_exists($img['image_path'])): ?>
                        <img src="<?php echo sanitize_output($img['image_path']); ?>"
                             alt="Thumbnail <?php echo $index + 1; ?>"
                             onclick="changeMainImage('<?php echo sanitize_output($img['image_path']); ?>', this)"
                             class="<?php echo ($index == 0) ? 'active-thumb' : ''; ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="car-info-panel">
        <h2><?php echo sanitize_output($car_details['year'] . ' ' . $car_details['make'] . ' ' . $car_details['model']); ?></h2>
        <?php if(!empty($car_details['description'])): ?>
            <p class="ad-title"><?php echo sanitize_output($car_details['description']); ?></p>
        <?php endif; ?>
        <p class="price">₱<?php echo number_format(sanitize_output($car_details['price']), 2); ?></p>

        <?php if(!empty($car_details['down_payment_amount']) || !empty($car_details['amortization_60_months'])): ?>
        <div class="details-section financing-offer-summary">
            <h4>Financing Offer</h4>
            <?php if(!empty($car_details['down_payment_amount'])): ?>
                <p style="font-size:1.2em; font-weight:bold; color:var(--brand-color); margin-bottom:10px;"><?php echo number_format(sanitize_output($car_details['down_payment_amount']),0); ?>K DP ONLY</p>
            <?php endif; ?>
            <ul>
                <?php if(!empty($car_details['amortization_60_months'])): ?><li>60 Months - ₱<?php echo number_format(sanitize_output($car_details['amortization_60_months'])); ?> / month</li><?php endif; ?>
                <?php if(!empty($car_details['amortization_48_months'])): ?><li>48 Months - ₱<?php echo number_format(sanitize_output($car_details['amortization_48_months'])); ?> / month</li><?php endif; ?>
                <?php if(!empty($car_details['amortization_36_months'])): ?><li>36 Months - ₱<?php echo number_format(sanitize_output($car_details['amortization_36_months'])); ?> / month</li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if(!empty($car_details['freebies_text'])): ?>
        <div class="details-section">
            <h4>Freebies Included</h4>
            <div><?php echo nl2br(sanitize_output($car_details['freebies_text'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if(!empty($car_details['clearances_text'])): ?>
        <div class="details-section">
            <h4>Clearances / Assurances</h4>
            <div><?php echo nl2br(sanitize_output($car_details['clearances_text'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="apply-button-container">
            <a href="submit_application.php?car_id=<?php echo $car_details['id']; ?>" class="button primary large-button">Apply for this Car Now</a>
        </div>
         <p style="text-align:center; margin-top:20px;"><a href="listings.php">&laquo; Back to All Listings</a></p>
    </div>
</div>

<script>
    function changeMainImage(newImageSrc, clickedThumbnail) {
        document.getElementById('mainCarImage').src = newImageSrc;
        const thumbnails = document.querySelectorAll('.thumbnail-gallery img');
        thumbnails.forEach(thumb => thumb.classList.remove('active-thumb'));
        if (clickedThumbnail) {
            clickedThumbnail.classList.add('active-thumb');
        }
    }
</script>

<style>
    /* These styles should ideally be in your main style.css */
    .car-detail-container { display: flex; flex-wrap: wrap; gap: 30px; }
    .car-gallery { flex: 1 1 500px; }
    .car-info-panel { flex: 1 1 300px; }
    .main-image-container { margin-bottom: 15px; border: 1px solid var(--border-color); padding: 5px; text-align: center; }
    .main-image-container img { max-width: 100%; max-height: 450px; object-fit: contain; }
    .thumbnail-gallery { display: flex; flex-wrap: wrap; gap: 10px; margin-top:10px; }
    .thumbnail-gallery img { width: 100px; height: 75px; object-fit: cover; border: 2px solid transparent; cursor: pointer; border-radius: 3px; }
    .thumbnail-gallery img.active-thumb, .thumbnail-gallery img:hover { border-color: var(--brand-color); }
    .car-info-panel .price { font-size: 1.8em; font-weight: bold; color: var(--brand-color); margin-bottom: 15px; }
    .details-section { margin-bottom: 20px; padding-bottom:15px; border-bottom: 1px solid #f0f0f0; }
    .details-section:last-child { border-bottom: none; }
    .details-section h4 { margin-bottom: 8px; font-size: 1.1em; color: var(--text-color); }
    .details-section ul { list-style: none; padding-left: 0; margin-left: 5px; }
    .details-section ul li { margin-bottom: 5px; }
    .apply-button-container { margin-top: 25px; text-align: center; }
    .large-button { padding: 12px 25px; font-size: 1.1em; }
</style>

<?php include 'includes/footer.php'; ?>