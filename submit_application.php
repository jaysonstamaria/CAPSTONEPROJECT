<?php
// File: submit_application.php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

require_login('client');
$user_id = $_SESSION['user_id'];
$page_title = "Car Application Form"; // Default title

// --- Get and Validate car_id from URL ---
$selected_car_id = filter_input(INPUT_GET, 'car_id', FILTER_VALIDATE_INT);
$selected_car_details = null;

if (!$selected_car_id) {
    header("Location: listings.php?error=" . urlencode("Please select a car to apply for first."));
    exit;
}

// Fetch details of the selected car
$stmt_car = $mysqli->prepare("SELECT id, make, model, year, price FROM cars WHERE id = ? AND status = 'available'");
if ($stmt_car) {
    $stmt_car->bind_param("i", $selected_car_id);
    $stmt_car->execute();
    $result_car = $stmt_car->get_result();
    $selected_car_details = $result_car->fetch_assoc();
    $stmt_car->close();

    if (!$selected_car_details) {
        header("Location: listings.php?error=" . urlencode("Selected car not found or is no longer available."));
        exit;
    }
    $page_title = "Application for " . sanitize_output($selected_car_details['year'] . ' ' . $selected_car_details['make'] . ' ' . $selected_car_details['model']);
} else {
    error_log("Failed to prepare statement for selected car: " . $mysqli->error);
    header("Location: listings.php?error=" . urlencode("Error retrieving car details."));
    exit;
}

$errors = [];
$form_data = [];

// --- Configuration for Requirement File Uploads ---
define('REQ_UPLOAD_DIR_RELATIVE', 'uploads/requirements/');
define('REQ_UPLOAD_DIR_ABSOLUTE', __DIR__ . '/' . REQ_UPLOAD_DIR_RELATIVE);
define('REQ_MAX_FILE_SIZE', 3 * 1024 * 1024); // 3 MB per file
define('REQ_ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);
define('REQ_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);


function handle_requirement_upload($file_input_name, $application_id, $requirement_type_prefix, &$mysqli, &$errors_ref) {
    if (isset($_FILES[$file_input_name]) && !empty($_FILES[$file_input_name]['name'][0])) {
        $files = $_FILES[$file_input_name];
        $file_count = count($files['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                if ($files['size'][$i] > REQ_MAX_FILE_SIZE) {
                    $errors_ref[$file_input_name][] = sanitize_output($files['name'][$i]) . ": File is too large (Max " . (REQ_MAX_FILE_SIZE / 1024 / 1024) . "MB)."; continue;
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $files['tmp_name'][$i]);
                finfo_close($finfo);
                $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($mime_type, REQ_ALLOWED_MIME_TYPES) || !in_array($extension, REQ_ALLOWED_EXTENSIONS)) {
                    $errors_ref[$file_input_name][] = sanitize_output($files['name'][$i]) . ": Invalid file type. Allowed: " . implode(', ', REQ_ALLOWED_EXTENSIONS); continue;
                }
                if (!is_dir(REQ_UPLOAD_DIR_ABSOLUTE)) {
                    if (!mkdir(REQ_UPLOAD_DIR_ABSOLUTE, 0755, true)) {
                         $errors_ref['general'] = "Upload directory for requirements cannot be created."; return false;
                    }
                }
                 if (!is_writable(REQ_UPLOAD_DIR_ABSOLUTE)) {
                     $errors_ref['general'] = "Upload directory for requirements is not writable."; return false;
                 }
                $original_filename = $files['name'][$i];
                $unique_filename = uniqid($requirement_type_prefix . '_app' . $application_id . '_', true) . '.' . $extension;
                $target_path_absolute = REQ_UPLOAD_DIR_ABSOLUTE . $unique_filename;
                $target_path_relative = REQ_UPLOAD_DIR_RELATIVE . $unique_filename;
                if (move_uploaded_file($files['tmp_name'][$i], $target_path_absolute)) {
                    $stmt_file = $mysqli->prepare("INSERT INTO application_requirements (application_id, requirement_type, file_name, file_path) VALUES (?, ?, ?, ?)");
                    if($stmt_file) {
                        $requirement_type_db = strtolower($requirement_type_prefix . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', str_replace('[]', '', $file_input_name)));
                        $stmt_file->bind_param("isss", $application_id, $requirement_type_db, $original_filename, $target_path_relative);
                        if(!$stmt_file->execute()){
                             $errors_ref[$file_input_name][] = "Could not save file info for " . sanitize_output($original_filename);
                             unlink($target_path_absolute);
                        }
                        $stmt_file->close();
                    } else {
                        $errors_ref[$file_input_name][] = "DB error preparing to save file " . sanitize_output($original_filename);
                        unlink($target_path_absolute);
                    }
                } else {
                    $errors_ref[$file_input_name][] = "Failed to upload " . sanitize_output($original_filename) . ".";
                }
            } elseif ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $errors_ref[$file_input_name][] = "Error uploading " . sanitize_output($files['name'][$i]) . " (Code: " . $files['error'][$i] . ").";
            }
        }
    }
    return true;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_car_id = filter_input(INPUT_POST, 'selected_car_id_hidden', FILTER_VALIDATE_INT);
    if ($posted_car_id != $selected_car_id) { $errors['general'] = "Car selection mismatch. Please start again."; }
    $form_data = $_POST;

    // Retrieve & Validate all form data...
    $first_name = trim($form_data['first_name'] ?? '');
    if (empty($first_name)) { $errors['first_name'] = "First name is required."; }
    $middle_name = trim($form_data['middle_name'] ?? '');
    $surname = trim($form_data['surname'] ?? '');
    if (empty($surname)) { $errors['surname'] = "Surname is required."; }
    $birthday_str = trim($form_data['birthday'] ?? '');
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    $birthday_obj = null;
    if (empty($birthday_str)) { $errors['birthday'] = "Birthday is required.";
    } elseif (!($birthday_obj = DateTime::createFromFormat('Y-m-d', $birthday_str)) || $birthday_obj->format('Y-m-d') !== $birthday_str) {
        $errors['birthday'] = "Invalid birthday format.";
    } else {
        $today = new DateTime();
        $calculated_age = $today->diff($birthday_obj)->y;
        if ($age != $calculated_age) { $age = $calculated_age; }
        if ($age < 18 || $age > 120) { $errors['age'] = "Applicant must be at least 18 years old."; }
    }
    $civil_status = trim($form_data['civil_status'] ?? '');
    if (empty($civil_status) || !in_array($civil_status, ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'])) { $errors['civil_status'] = "Please select a valid civil status."; }
    $present_address = trim($form_data['present_address'] ?? '');
    if (empty($present_address)) { $errors['present_address'] = "Present address is required."; }
    $years_of_stay = trim($form_data['years_of_stay'] ?? '');
    if (empty($years_of_stay)) { $errors['years_of_stay'] = "Years of stay is required."; }
    $residential_status = trim($form_data['residential_status'] ?? '');
    if (empty($residential_status)) { $errors['residential_status'] = "Residential status is required."; }
    $tin_number = trim($form_data['tin_number'] ?? '');
    $email_address_form = filter_var(trim($form_data['email_address'] ?? ''), FILTER_SANITIZE_EMAIL);
    if (empty($email_address_form) || !filter_var($email_address_form, FILTER_VALIDATE_EMAIL)) { $errors['email_address'] = "A valid email address is required."; }
    $owned_car_previously = trim($form_data['owned_car_previously'] ?? '');
    $contact_no = trim($form_data['contact_no'] ?? '');
    if (empty($contact_no)) { $errors['contact_no'] = "Contact number is required."; }
    $bank_name = trim($form_data['bank_name'] ?? '');
    $bank_account_no = trim($form_data['bank_account_no'] ?? '');
    $mother_maiden_firstname = trim($form_data['mother_maiden_firstname'] ?? '');
    $mother_maiden_middlename = trim($form_data['mother_maiden_middlename'] ?? '');
    $mother_maiden_lastname = trim($form_data['mother_maiden_lastname'] ?? '');
    $mother_maiden_birthday_str = trim($form_data['mother_maiden_birthday'] ?? '');
    $preferred_loan_term = filter_input(INPUT_POST, 'preferred_loan_term', FILTER_VALIDATE_INT);
    if ($preferred_loan_term !== null && !in_array($preferred_loan_term, [12, 24, 36, null, ''])) { $errors['preferred_loan_term'] = "Invalid loan term selected."; }
    $message = trim($form_data['message'] ?? '');
    $source_of_income = trim($form_data['source_of_income'] ?? '');
    if (empty($source_of_income)) { $errors['source_of_income'] = "Please select your source of income."; }
    if ($source_of_income === 'employed' && empty($_FILES['employed_valid_ids']['name'][0])) { $errors['employed_valid_ids'] = "Employed: At least one Valid ID is required."; }
    if ($source_of_income === 'business' && empty($_FILES['business_valid_ids']['name'][0])) { $errors['business_valid_ids'] = "Business: At least one Valid ID is required."; }
    $birthday_db = !empty($birthday_str) && empty($errors['birthday']) ? $birthday_str : null;
    $mother_maiden_birthday_db = !empty($mother_maiden_birthday_str) ? $mother_maiden_birthday_str : null;

    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            $stmt_app = $mysqli->prepare("INSERT INTO applications
                (user_id, selected_car_id, first_name, middle_name, surname, birthday, age, civil_status, present_address, years_of_stay, residential_status, tin_number, email_address, owned_car_previously, contact_no, bank_name_branch, bank_account_no, mother_maiden_firstname, mother_maiden_middlename, mother_maiden_lastname, mother_maiden_birthday, source_of_income, car_make, car_model, preferred_loan_term_months, message, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_review')");

            if (!$stmt_app) throw new Exception("Database error: " . $mysqli->error);
            $car_make_db = $selected_car_details['make'];
            $car_model_db = $selected_car_details['model'];
  $stmt_app = $mysqli->prepare("INSERT INTO applications
                (user_id, selected_car_id, first_name, middle_name, surname, birthday, age, civil_status, present_address, years_of_stay, residential_status, tin_number, email_address, owned_car_previously, contact_no, bank_name_branch, bank_account_no, mother_maiden_firstname, mother_maiden_middlename, mother_maiden_lastname, mother_maiden_birthday, source_of_income, car_make, car_model, preferred_loan_term_months, message, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_review')");

            if (!$stmt_app) {
                throw new Exception("Database error preparing application statement: " . $mysqli->error);
            }

            $car_make_db = $selected_car_details['make'];
            $car_model_db = $selected_car_details['model'];

            // This type string has exactly 26 characters, matching the 26 variables and 26 placeholders.
            $stmt_app->bind_param("iissssisssssssssssssssssis",
                $user_id,
                $selected_car_id,
                $first_name,
                $middle_name,
                $surname,
                $birthday_db,
                $age,
                $civil_status,
                $present_address,
                $years_of_stay,
                $residential_status,
                $tin_number,
                $email_address_form,
                $owned_car_previously,
                $contact_no,
                $bank_name, // This variable from the form maps to the `bank_name_branch` column
                $bank_account_no,
                $mother_maiden_firstname,
                $mother_maiden_middlename,
                $mother_maiden_lastname,
                $mother_maiden_birthday_db,
                $source_of_income,
                $car_make_db,
                $car_model_db,
                $preferred_loan_term,
                $message
            );            if (!$stmt_app->execute()) throw new Exception("Failed to submit application: " . $stmt_app->error);
            $application_id = $mysqli->insert_id;
            $stmt_app->close();

            $file_upload_errors_occurred = false;
            if ($source_of_income === 'employed') {
                handle_requirement_upload('employed_valid_ids', $application_id, 'employed', $mysqli, $errors);
                // ... other employed file uploads
            } elseif ($source_of_income === 'business') {
                handle_requirement_upload('business_valid_ids', $application_id, 'business', $mysqli, $errors);
                // ... other business file uploads
            }
            if (!empty($errors) && !$file_upload_errors_occurred) { // Check for new errors from file uploads
                $file_upload_errors_occurred = true;
            }
            if ($file_upload_errors_occurred) {
                throw new Exception("Errors occurred during file processing. Please review the errors and try again.");
            }
            $mysqli->commit();
            header("Location: client_dashboard.php?submitted=true&app_id=" . $application_id);
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors['general'] = $e->getMessage();
        }
    }
}

$user_login_email = '';
if(isset($_SESSION['user_id'])){
    $stmt_user_email_fetch = $mysqli->prepare("SELECT email FROM users WHERE id = ?");
    if($stmt_user_email_fetch){
        $stmt_user_email_fetch->bind_param("i", $_SESSION['user_id']);
        $stmt_user_email_fetch->execute();
        $res_user_email = $stmt_user_email_fetch->get_result();
        if($row_user_email = $res_user_email->fetch_assoc()){
            $user_login_email = $row_user_email['email'];
        }
        $stmt_user_email_fetch->close();
    }
}

include 'includes/header.php';
?>

<div class="selected-car-info dashboard-section">
    <h3>Applying for: <?php echo sanitize_output($selected_car_details['year'] . ' ' . $selected_car_details['make'] . ' ' . $selected_car_details['model']); ?></h3>
    <p><strong>Price:</strong> ₱<?php echo number_format(sanitize_output($selected_car_details['price']), 2); ?></p>
    <p><a href="listings.php">&laquo; Choose a different car</a></p>
</div>

<h2>Client Application Form</h2>
<p>Please fill out the form completely and accurately. Fields marked with <span class="required-asterisk">*</span> are required.</p>

<?php if (!empty($errors)): ?>
    <div class="errors">
        <strong>Please correct the following errors:</strong>
        <ul><?php foreach ($errors as $error_msg): ?><li><?php echo is_array($error_msg) ? implode('<br>', $error_msg) : sanitize_output($error_msg); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form action="submit_application.php?car_id=<?php echo $selected_car_id; ?>" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="selected_car_id_hidden" value="<?php echo $selected_car_id; ?>">

    <fieldset>
        <legend>Personal Information</legend>
        <div><label for="first_name">First Name: <span class="required-asterisk">*</span></label><input type="text" id="first_name" name="first_name" required value="<?php echo sanitize_output($form_data['first_name'] ?? ''); ?>"></div>
        <div><label for="middle_name">Middle Name:</label><input type="text" id="middle_name" name="middle_name" value="<?php echo sanitize_output($form_data['middle_name'] ?? ''); ?>"></div>
        <div><label for="surname">Surname: <span class="required-asterisk">*</span></label><input type="text" id="surname" name="surname" required value="<?php echo sanitize_output($form_data['surname'] ?? ''); ?>"></div>
        <div><label for="birthday">Birthday (YYYY-MM-DD): <span class="required-asterisk">*</span></label><input type="date" id="birthday" name="birthday" required value="<?php echo sanitize_output($form_data['birthday'] ?? ''); ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"></div>
        <div><label for="age">Age: <span class="required-asterisk">*</span></label><input type="number" id="age" name="age" readonly required value="<?php echo sanitize_output($form_data['age'] ?? ''); ?>" placeholder="Auto-calculated"></div>
        <div><label for="civil_status">Civil Status: <span class="required-asterisk">*</span></label>
            <select id="civil_status" name="civil_status" required>
                <option value="">-- Select --</option>
                <option value="Single" <?php if(isset($form_data['civil_status']) && $form_data['civil_status'] == 'Single') echo 'selected'; ?>>Single</option>
                <option value="Married" <?php if(isset($form_data['civil_status']) && $form_data['civil_status'] == 'Married') echo 'selected'; ?>>Married</option>
                <option value="Widowed" <?php if(isset($form_data['civil_status']) && $form_data['civil_status'] == 'Widowed') echo 'selected'; ?>>Widowed</option>
                <option value="Separated" <?php if(isset($form_data['civil_status']) && $form_data['civil_status'] == 'Separated') echo 'selected'; ?>>Separated</option>
                <option value="Divorced" <?php if(isset($form_data['civil_status']) && $form_data['civil_status'] == 'Divorced') echo 'selected'; ?>>Divorced</option>
            </select>
        </div>
        <div><label for="present_address">Present Address: <span class="required-asterisk">*</span></label><textarea id="present_address" name="present_address" rows="3" required><?php echo sanitize_output($form_data['present_address'] ?? ''); ?></textarea></div>
        <div><label for="years_of_stay">Years of Stay at Present Address: <span class="required-asterisk">*</span></label>
            <select id="years_of_stay" name="years_of_stay" required>
                <option value="">-- Select --</option>
                <option value="Less than 1 year" <?php if(isset($form_data['years_of_stay']) && $form_data['years_of_stay'] == 'Less than 1 year') echo 'selected'; ?>>Less than 1 year</option>
                <option value="1-2 years" <?php if(isset($form_data['years_of_stay']) && $form_data['years_of_stay'] == '1-2 years') echo 'selected'; ?>>1-2 years</option>
                <option value="3-5 years" <?php if(isset($form_data['years_of_stay']) && $form_data['years_of_stay'] == '3-5 years') echo 'selected'; ?>>3-5 years</option>
                <option value="More than 5 years" <?php if(isset($form_data['years_of_stay']) && $form_data['years_of_stay'] == 'More than 5 years') echo 'selected'; ?>>More than 5 years</option>
            </select>
        </div>
        <div><label for="residential_status">Residential Status: <span class="required-asterisk">*</span></label>
            <select id="residential_status" name="residential_status" required>
                <option value="">-- Select --</option>
                <option value="Owned" <?php if(isset($form_data['residential_status']) && $form_data['residential_status'] == 'Owned') echo 'selected'; ?>>Owned</option>
                <option value="Rented" <?php if(isset($form_data['residential_status']) && $form_data['residential_status'] == 'Rented') echo 'selected'; ?>>Rented</option>
                <option value="Living with Relatives" <?php if(isset($form_data['residential_status']) && $form_data['residential_status'] == 'Living with Relatives') echo 'selected'; ?>>Living with Relatives</option>
                <option value="Mortgaged" <?php if(isset($form_data['residential_status']) && $form_data['residential_status'] == 'Mortgaged') echo 'selected'; ?>>Mortgaged</option>
            </select>
        </div>
        <div><label for="tin_number">TIN Number:</label><input type="text" id="tin_number" name="tin_number" value="<?php echo sanitize_output($form_data['tin_number'] ?? ''); ?>"></div>
        <div><label for="email_address">Email Address: <span class="required-asterisk">*</span></label><input type="email" id="email_address" name="email_address" required value="<?php echo sanitize_output($form_data['email_address'] ?? $user_login_email); ?>"></div>
        <div><label for="owned_car_previously">Owned Car Previously?</label><select id="owned_car_previously" name="owned_car_previously"><option value="">--Select--</option><option value="Yes" <?php if(isset($form_data['owned_car_previously']) && $form_data['owned_car_previously'] == 'Yes') echo 'selected'; ?>>Yes</option><option value="No" <?php if(isset($form_data['owned_car_previously']) && $form_data['owned_car_previously'] == 'No') echo 'selected'; ?>>No</option></select></div>
        <div><label for="contact_no">Contact No.: <span class="required-asterisk">*</span></label><input type="text" id="contact_no" name="contact_no" required value="<?php echo sanitize_output($form_data['contact_no'] ?? ''); ?>"></div>
        <div><label for="bank_name">Bank Name:</label>
            <select id="bank_name" name="bank_name">
                <option value="">-- Select Bank (Optional) --</option>
                <option value="BDO" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'BDO') echo 'selected'; ?>>BDO Unibank</option>
                <option value="Chinabank" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'Chinabank') echo 'selected'; ?>>Chinabank</option>
                <option value="Metrobank" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'Metrobank') echo 'selected'; ?>>Metrobank</option>
                <option value="EastWest Bank" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'EastWest Bank') echo 'selected'; ?>>EastWest Bank</option>
                <option value="BPI" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'BPI') echo 'selected'; ?>>BPI</option>
                <option value="Landbank" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'Landbank') echo 'selected'; ?>>Landbank</option>
                <option value="Other" <?php if(isset($form_data['bank_name']) && $form_data['bank_name'] == 'Other') echo 'selected'; ?>>Other</option>
            </select>
        </div>
        <div><label for="bank_account_no">Bank Account No.:</label><input type="text" id="bank_account_no" name="bank_account_no" value="<?php echo sanitize_output($form_data['bank_account_no'] ?? ''); ?>"></div>
    </fieldset>

    <fieldset>
        <legend>Mother's Maiden Name</legend>
        <div><label for="mother_maiden_firstname">First Name:</label><input type="text" id="mother_maiden_firstname" name="mother_maiden_firstname" value="<?php echo sanitize_output($form_data['mother_maiden_firstname'] ?? ''); ?>"></div>
        <div><label for="mother_maiden_middlename">Middle Name:</label><input type="text" id="mother_maiden_middlename" name="mother_maiden_middlename" value="<?php echo sanitize_output($form_data['mother_maiden_middlename'] ?? ''); ?>"></div>
        <div><label for="mother_maiden_lastname">Last Name:</label><input type="text" id="mother_maiden_lastname" name="mother_maiden_lastname" value="<?php echo sanitize_output($form_data['mother_maiden_lastname'] ?? ''); ?>"></div>
        <div><label for="mother_maiden_birthday">Birthday (YYYY-MM-DD):</label><input type="date" id="mother_maiden_birthday" name="mother_maiden_birthday" value="<?php echo sanitize_output($form_data['mother_maiden_birthday'] ?? ''); ?>"></div>
    </fieldset>

    <fieldset>
        <legend>Financing Details for Selected Car</legend>
        <p><strong>Applying for Car:</strong> <?php echo sanitize_output($selected_car_details['year'] . ' ' . $selected_car_details['make'] . ' ' . $selected_car_details['model']); ?></p>
        <p><strong>Listed Price:</strong> ₱<?php echo number_format(sanitize_output($selected_car_details['price']), 2); ?></p>
        <div>
            <label for="preferred_loan_term">Preferred Loan Term (Optional Suggestion):</label>
            <select id="preferred_loan_term" name="preferred_loan_term">
                <option value="">-- No Preference --</option>
                <option value="12" <?php if(isset($form_data['preferred_loan_term']) && $form_data['preferred_loan_term'] == 12) echo 'selected'; ?>>12 Months</option>
                <option value="24" <?php if(isset($form_data['preferred_loan_term']) && $form_data['preferred_loan_term'] == 24) echo 'selected'; ?>>24 Months</option>
                <option value="36" <?php if(isset($form_data['preferred_loan_term']) && $form_data['preferred_loan_term'] == 36) echo 'selected'; ?>>36 Months</option>
            </select>
            <small>This is a suggestion only. Final terms will be determined by our finance partners.</small>
        </div>
        <div><label for="message">Message regarding this application (Optional):</label><textarea id="message" name="message" rows="3"><?php echo sanitize_output($form_data['message'] ?? ''); ?></textarea></div>
    </fieldset>

    <fieldset>
        <legend>Source of Income & Requirements <span class="required-asterisk">*</span></legend>
        <div>
            <label for="source_of_income">Select your primary source of income:</label>
            <select id="source_of_income" name="source_of_income" required>
                <option value="">-- Select Source --</option>
                <option value="employed" <?php if(isset($form_data['source_of_income']) && $form_data['source_of_income'] == 'employed') echo 'selected'; ?>>Employed</option>
                <option value="business" <?php if(isset($form_data['source_of_income']) && $form_data['source_of_income'] == 'business') echo 'selected'; ?>>Business Owner / Self-Employed</option>
            </select>
        </div>
        <div id="employed_requirements" style="display: none; margin-top:15px; padding:15px; border:1px dashed #ccc;">
            <h4>Employed Requirements (Upload JPG, PNG, PDF)</h4>
            <div><label for="employed_valid_ids">Valid ID(s): <span class="required-asterisk">*</span></label><input type="file" id="employed_valid_ids" name="employed_valid_ids[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="employed_company_id">Company ID (Optional):</label><input type="file" id="employed_company_id" name="employed_company_id[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="employed_coe">Latest COE with Compensation (Optional):</label><input type="file" id="employed_coe" name="employed_coe[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="employed_proof_of_billing">Proof of Billing (Optional):</label><input type="file" id="employed_proof_of_billing" name="employed_proof_of_billing[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
        </div>
        <div id="business_requirements" style="display: none; margin-top:15px; padding:15px; border:1px dashed #ccc;">
            <h4>Business Requirements (Upload JPG, PNG, PDF)</h4>
            <div><label for="business_valid_ids">Valid ID(s) of Owner: <span class="required-asterisk">*</span></label><input type="file" id="business_valid_ids" name="business_valid_ids[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="business_dti_permit">DTI / Business Permit (Optional):</label><input type="file" id="business_dti_permit" name="business_dti_permit[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="business_bank_statements">Latest 3 Months Bank Statements (Optional):</label><input type="file" id="business_bank_statements" name="business_bank_statements[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="business_proof_of_billing">Proof of Billing (Optional):</label><input type="file" id="business_proof_of_billing" name="business_proof_of_billing[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
            <div><label for="business_picture">Business Picture(s) (Optional):</label><input type="file" id="business_picture" name="business_picture[]" multiple accept=".jpg,.jpeg,.png,.pdf"></div>
        </div>
    </fieldset>

    <div style="margin-top: 30px;">
        <button type="submit" class="button primary">Submit Application</button>
    </div>
</form>

<script>
    // This function is responsible for showing/hiding the requirement sections
    function toggleRequirements() {
        // Find the necessary HTML elements by their ID
        const sourceSelect = document.getElementById('source_of_income');
        const employedDiv = document.getElementById('employed_requirements');
        const businessDiv = document.getElementById('business_requirements');

        // Make sure all elements were actually found before trying to use them
        if (!sourceSelect || !employedDiv || !businessDiv) {
            console.error("Required elements for toggling requirements are missing from the page.");
            return;
        }

        const selectedSource = sourceSelect.value;

        // Hide both sections first to reset the state
        employedDiv.style.display = 'none';
        businessDiv.style.display = 'none';

        // Show the correct section based on the selected value
        if (selectedSource === 'employed') {
            employedDiv.style.display = 'block';
        } else if (selectedSource === 'business') {
            businessDiv.style.display = 'block';
        }
    }

    // This function calculates age from the birthday input
    function calculateAge() {
        const birthdayInput = document.getElementById('birthday');
        const ageInput = document.getElementById('age');

        if (!birthdayInput || !ageInput) {
            return; // Exit if elements are not found
        }

        if (birthdayInput.value) {
            try {
                const birthDate = new Date(birthdayInput.value);
                if (isNaN(birthDate.getTime())) { // Check for invalid date
                    ageInput.value = ''; return;
                }
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                ageInput.value = age >= 0 ? age : '';
            } catch (e) {
                ageInput.value = ''; // Clear if any error during date parsing
            }
        } else {
            ageInput.value = '';
        }
    }

    // This ensures all the HTML is loaded before our script tries to find elements
    document.addEventListener('DOMContentLoaded', function() {
        const birthdayInput = document.getElementById('birthday');
        const sourceOfIncomeSelect = document.getElementById('source_of_income');

        // Attach the 'change' event listener to the birthday input
        if (birthdayInput) {
            birthdayInput.addEventListener('change', calculateAge);
            // Calculate age on page load if a birthday is already there (e.g., from form repopulation)
            if (birthdayInput.value) {
                calculateAge();
            }
        }

        // Attach the 'change' event listener to the source of income dropdown
        if (sourceOfIncomeSelect) {
            sourceOfIncomeSelect.addEventListener('change', toggleRequirements);
            // IMPORTANT: Call the function immediately on page load to set the initial correct state
            toggleRequirements();
        }
    });
</script>

<style>
    /* These styles should be moved to your main css/style.css file */
    .required-asterisk { color: var(--error-color); }
    fieldset { margin-bottom: 20px; padding: 20px; border: 1px solid var(--border-color); border-radius: 5px; background-color: var(--light-gray-bg); }
    legend { font-weight: 500; color: var(--brand-color); padding: 0 10px; font-size: 1.1em; }
    label { font-weight: 500; }
    input[type="file"] { border: 1px dashed #ccc; padding: 10px; background-color: #fff; }
    small { display: block; margin-top: 5px; color: var(--light-text-color); font-size: 0.85em;}
    .error-text { color: var(--error-color); font-size: 0.8em; display: block; margin-top: 2px; }
</style>

<?php include 'includes/footer.php'; ?>
