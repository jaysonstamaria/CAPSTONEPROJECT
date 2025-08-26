<?php
// File: add_car.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login('admin');
$page_title = "Add New Car Listing";

$errors = $_SESSION['form_errors'] ?? [];
$car_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

include 'includes/header.php';
?>
<style>
    /* These styles should be moved to your main style.css */
    .financing-offer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
    .required-asterisk { color: var(--error-color); }
</style>

<h2>Add New Car Listing</h2>

<?php if (!empty($errors)): ?>
    <div class="errors">
        <strong>Please fix the following errors:</strong>
        <ul>
            <?php foreach ($errors as $field_errors):
                if (is_array($field_errors)) {
                    foreach($field_errors as $error_msg): ?><li><?php echo sanitize_output($error_msg); ?></li><?php endforeach;
                } else { ?><li><?php echo sanitize_output($field_errors); ?></li><?php }
            endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="process_add_car.php" method="post" enctype="multipart/form-data" novalidate>
    <fieldset>
        <legend>Basic Car Information</legend>
        <div><label for="make">Make: <span class="required-asterisk">*</span></label><input type="text" id="make" name="make" required value="<?php echo sanitize_output($car_data['make'] ?? ''); ?>"></div>
        <div><label for="model">Model: <span class="required-asterisk">*</span></label><input type="text" id="model" name="model" required value="<?php echo sanitize_output($car_data['model'] ?? ''); ?>"></div>
        <div><label for="year">Year: <span class="required-asterisk">*</span></label><input type="number" id="year" name="year" required min="1900" max="<?php echo date('Y') + 2; ?>" step="1" value="<?php echo sanitize_output($car_data['year'] ?? ''); ?>"></div>
        <div><label for="price">Selling Price (₱): <span class="required-asterisk">*</span></label><input type="number" id="price" name="price" required step="0.01" min="0" value="<?php echo sanitize_output($car_data['price'] ?? ''); ?>"></div>
        <div><label for="description">Ad Title / Short Description: <span class="required-asterisk">*</span></label><textarea id="description" name="description" rows="3" required maxlength="255"><?php echo sanitize_output($car_data['description'] ?? ''); ?></textarea></div>
    </fieldset>

    <fieldset>
        <legend>Car Images (Upload multiple: JPG, PNG, GIF, WEBP)</legend>
        <div>
            <label for="car_images">Select Images (the first will be primary):</label>
            <input type="file" id="car_images" name="car_images[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp">
            <small>You can select multiple files by holding Ctrl/Cmd while clicking.</small>
        </div>
    </fieldset>

    <fieldset>
        <legend>Financing Offer Details (Optional)</legend>
        <div class="financing-offer-grid">
            <div><label for="down_payment_amount">Down Payment (DP) Amount (₱):</label><input type="number" id="down_payment_amount" name="down_payment_amount" step="0.01" min="0" value="<?php echo sanitize_output($car_data['down_payment_amount'] ?? ''); ?>"></div>
            <div><label for="amortization_60_months">60 Months Amortization (₱):</label><input type="number" id="amortization_60_months" name="amortization_60_months" step="0.01" min="0" value="<?php echo sanitize_output($car_data['amortization_60_months'] ?? ''); ?>"></div>
            <div><label for="amortization_48_months">48 Months Amortization (₱):</label><input type="number" id="amortization_48_months" name="amortization_48_months" step="0.01" min="0" value="<?php echo sanitize_output($car_data['amortization_48_months'] ?? ''); ?>"></div>
            <div><label for="amortization_36_months">36 Months Amortization (₱):</label><input type="number" id="amortization_36_months" name="amortization_36_months" step="0.01" min="0" value="<?php echo sanitize_output($car_data['amortization_36_months'] ?? ''); ?>"></div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Promotional Details (Optional)</legend>
        <div>
            <label for="freebies_text">Freebies (Enter each on a new line):</label>
            <textarea id="freebies_text" name="freebies_text" rows="8" maxlength="2000"><?php echo sanitize_output($car_data['freebies_text'] ?? "Free Transfer of Ownership\nFree Chattel Mortgage Fee\nFree 3 Steps Detailing\nFree 10L Fuel\nFree Tint\nFree Floor Matting\nFree Basic Emergency Tools"); ?></textarea>
        </div>
        <div>
            <label for="clearances_text">Clearances / Assurances (Enter each on a new line):</label>
            <textarea id="clearances_text" name="clearances_text" rows="5" maxlength="1000"><?php echo sanitize_output($car_data['clearances_text'] ?? "NO LTO ALARM\nNO LTO APPREHENSION\nPNP CLEARED\nHPG CLEARED"); ?></textarea>
        </div>
    </fieldset>

    <div style="margin-top: 20px;">
        <button type="submit" class="button primary">Add Car Listing</button>
        <a href="admin_dashboard.php" class="button secondary" style="margin-left: 10px;">Cancel</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>