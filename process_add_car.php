<?php
// File: process_add_car.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login('admin');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: add_car.php');
    exit;
}

define('CAR_IMAGE_UPLOAD_DIR_RELATIVE', 'uploads/cars/');
define('CAR_IMAGE_UPLOAD_DIR_ABSOLUTE', __DIR__ . '/' . CAR_IMAGE_UPLOAD_DIR_RELATIVE);
define('CAR_IMAGE_MAX_FILE_SIZE', 3 * 1024 * 1024);
$car_image_allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$car_image_allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

$make = trim($_POST['make'] ?? '');
$model = trim($_POST['model'] ?? '');
$year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => date('Y') + 2]]);
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
$description = trim($_POST['description'] ?? '');
$admin_id = $_SESSION['user_id'];

$down_payment_amount_str = trim($_POST['down_payment_amount'] ?? '');
$amort_60_str = trim($_POST['amortization_60_months'] ?? '');
$amort_48_str = trim($_POST['amortization_48_months'] ?? '');
$amort_36_str = trim($_POST['amortization_36_months'] ?? '');
$freebies_text = trim($_POST['freebies_text'] ?? '');
$clearances_text = trim($_POST['clearances_text'] ?? '');

$down_payment_amount = ($down_payment_amount_str !== '' && is_numeric($down_payment_amount_str) && (float)$down_payment_amount_str >= 0) ? (float)$down_payment_amount_str : null;
$amort_60 = ($amort_60_str !== '' && is_numeric($amort_60_str) && (float)$amort_60_str >= 0) ? (float)$amort_60_str : null;
$amort_48 = ($amort_48_str !== '' && is_numeric($amort_48_str) && (float)$amort_48_str >= 0) ? (float)$amort_48_str : null;
$amort_36 = ($amort_36_str !== '' && is_numeric($amort_36_str) && (float)$amort_36_str >= 0) ? (float)$amort_36_str : null;

$errors = [];
$_SESSION['form_data'] = $_POST;

if (empty($make)) $errors['make'] = "Make is required.";
if (empty($model)) $errors['model'] = "Model is required.";
if ($year === false) $errors['year'] = "Invalid year provided.";
if ($price === false) $errors['price'] = "Invalid selling price provided.";
if (empty($description)) $errors['description'] = "Ad Title / Short Description is required.";

if (empty($errors)) {
    $mysqli->begin_transaction();
    try {
        $stmt_car = $mysqli->prepare("INSERT INTO cars
            (make, model, year, price, description, status, added_by, down_payment_amount, amortization_60_months, amortization_48_months, amortization_36_months, freebies_text, clearances_text)
            VALUES (?, ?, ?, ?, ?, 'available', ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt_car) throw new Exception("DB Error (Prepare Car Insert): " . $mysqli->error);
        $stmt_car->bind_param("ssidsiddddss", $make, $model, $year, $price, $description, $admin_id, $down_payment_amount, $amort_60, $amort_48, $amort_36, $freebies_text, $clearances_text);
        if (!$stmt_car->execute()) throw new Exception("DB Error (Execute Car Insert): " . $stmt_car->error);
        $new_car_id = $mysqli->insert_id;
        $stmt_car->close();
        if ($new_car_id == 0) throw new Exception("Failed to get new car ID after insert.");

        $is_first_image_processed = false;
        if (isset($_FILES['car_images']) && !empty($_FILES['car_images']['name'][0])) {
            $car_images_files = $_FILES['car_images'];
            $num_files = count($car_images_files['name']);

            for ($i = 0; $i < $num_files; $i++) {
                if ($car_images_files['error'][$i] === UPLOAD_ERR_OK) {
                    if ($car_images_files['size'][$i] > CAR_IMAGE_MAX_FILE_SIZE) {
                        $errors['car_images_upload'][] = sanitize_output($car_images_files['name'][$i]) . ": File too large."; continue;
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $car_images_files['tmp_name'][$i]);
                    finfo_close($finfo);
                    $extension = strtolower(pathinfo($car_images_files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($mime_type, $car_image_allowed_mime_types) || !in_array($extension, $car_image_allowed_extensions)) {
                        $errors['car_images_upload'][] = sanitize_output($car_images_files['name'][$i]) . ": Invalid file type."; continue;
                    }
                    if (!is_dir(CAR_IMAGE_UPLOAD_DIR_ABSOLUTE)) {
                        if (!mkdir(CAR_IMAGE_UPLOAD_DIR_ABSOLUTE, 0755, true)) {
                            $errors['car_images_upload'][] = "Car image upload directory cannot be created."; break;
                        }
                    }
                    $original_filename = $car_images_files['name'][$i];
                    $unique_filename = uniqid('car' . $new_car_id . '_', true) . '.' . $extension;
                    $target_path_absolute = CAR_IMAGE_UPLOAD_DIR_ABSOLUTE . $unique_filename;
                    $target_path_relative = CAR_IMAGE_UPLOAD_DIR_RELATIVE . $unique_filename;
                    if (move_uploaded_file($car_images_files['tmp_name'][$i], $target_path_absolute)) {
                        $is_primary_flag = ($is_first_image_processed) ? 0 : 1;
                        $stmt_img = $mysqli->prepare("INSERT INTO car_images (car_id, image_path, is_primary) VALUES (?, ?, ?)");
                        if ($stmt_img) {
                            $stmt_img->bind_param("isi", $new_car_id, $target_path_relative, $is_primary_flag);
                            if ($stmt_img->execute()) {
                                $is_first_image_processed = true;
                            } else {
                                $errors['car_images_upload'][] = "DB Error (Save Image " . sanitize_output($original_filename) . "): " . $stmt_img->error;
                                unlink($target_path_absolute);
                            }
                            $stmt_img->close();
                        }
                    } else { $errors['car_images_upload'][] = "Failed to upload " . sanitize_output($original_filename) . "."; }
                }
            }
        }
        if (isset($errors['car_images_upload'])) {
            throw new Exception("Errors occurred during image uploads.");
        }
        $mysqli->commit();
        unset($_SESSION['form_data']);
        header('Location: admin_dashboard.php?car_added=true');
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        $errors['general_db_error'] = "Operation failed: " . $e->getMessage();
        error_log("Process Add Car Error: " . $e->getMessage());
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    header('Location: add_car.php');
    exit;
}
?>