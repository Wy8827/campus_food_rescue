<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/_provider_helpers.php';

requireRole('provider');

$userId = (int)($_SESSION['user_id'] ?? 0);
$providerId = getProviderId($conn, $userId);
if (!$providerId) {
    die("No provider profile is linked to this account yet. Please contact support.");
}

$allTags = getAllFoodTags($conn);
$errors = [];
$success = false;

// Keep submitted values so the form can be redisplayed with them on error
$food_name = $description = $pickup_location = $expires_at = '';
$quantity = '';
$weight_kg = '';
$selectedTags = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $food_name       = trim($_POST['food_name'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $quantity        = trim($_POST['quantity'] ?? '');
    $weight_kg       = trim($_POST['weight_kg'] ?? '');
    $pickup_location = trim($_POST['pickup_location'] ?? '');
    $expires_at      = trim($_POST['expires_at'] ?? '');
    $selectedTags    = array_map('intval', $_POST['tags'] ?? []);

    // ---------------- Validation ----------------
    if ($food_name === '' || strlen($food_name) > 200) {
        $errors[] = "Please provide a food name (max 200 characters).";
    }
    if ($quantity === '' || !ctype_digit($quantity) || (int)$quantity <= 0) {
        $errors[] = "Quantity must be a whole number greater than 0.";
    }
    if ($weight_kg !== '' && (!is_numeric($weight_kg) || (float)$weight_kg < 0)) {
        $errors[] = "Weight (kg) must be a positive number.";
    }
    if ($pickup_location === '' || strlen($pickup_location) > 200) {
        $errors[] = "Please provide a pickup location (max 200 characters).";
    }
    if ($expires_at === '') {
        $errors[] = "Please set an expiring window.";
    } else {
        $expiresTimestamp = strtotime($expires_at);
        if ($expiresTimestamp === false || $expiresTimestamp <= time()) {
            $errors[] = "The expiring window must be a valid date/time in the future.";
        }
    }

    // ---------------- Image upload ----------------
    $imageFileName = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Image upload failed. Please try again.";
        } elseif ($file['size'] > MAX_IMAGE_SIZE) {
            $errors[] = "Image must be smaller than 5MB.";
        } elseif (!in_array(mime_content_type($file['tmp_name']), ALLOWED_IMG_TYPES, true)) {
            $errors[] = "Image must be a JPEG, PNG, or WEBP file.";
        } else {
            if (!is_dir(UPLOAD_PATH)) {
                mkdir(UPLOAD_PATH, 0755, true);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $imageFileName = 'listing_' . $providerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
            if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $imageFileName)) {
                $errors[] = "Could not save the uploaded image.";
                $imageFileName = null;
            }
        }
    }

    // ---------------- Insert ----------------
    if (empty($errors)) {
        $expiresFormatted = date('Y-m-d H:i:s', strtotime($expires_at));
        $weightParam = $weight_kg === '' ? null : (float)$weight_kg;

        $query = "INSERT INTO food_listing
                    (provider_id, food_name, description, total_quantity, remain_quantity, weight_kg, pickup_location, image, status, expires_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        $stmt = mysqli_prepare($conn, $query);
        $qtyInt = (int)$quantity;
        mysqli_stmt_bind_param(
            $stmt, "issiidsss",
            $providerId, $food_name, $description, $qtyInt, $qtyInt, $weightParam, $pickup_location, $imageFileName, $expiresFormatted
        );

        if (mysqli_stmt_execute($stmt)) {
            $newListingId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Attach tags
            if (!empty($selectedTags)) {
                $tagQuery = "INSERT INTO food_listing_tags (listing_id, tag_id) VALUES (?, ?)";
                $tagStmt = mysqli_prepare($conn, $tagQuery);
                foreach ($selectedTags as $tagId) {
                    mysqli_stmt_bind_param($tagStmt, "ii", $newListingId, $tagId);
                    mysqli_stmt_execute($tagStmt);
                }
                mysqli_stmt_close($tagStmt);
            }

            $success = true;
            // Reset the form for the next entry
            $food_name = $description = $pickup_location = $expires_at = $quantity = $weight_kg = '';
            $selectedTags = [];
        } else {
            $errors[] = "Failed to save the listing. Please try again.";
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/provider/provider.css">
    <link rel="stylesheet" href="../../assets/css/provider/createListing.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Create Listing</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <h1 class="page-title">Create New Listing</h1>
                <p class="page-subtitle">Provide details about the food donation to make it available for claim.</p>

                <div style="margin-top:20px;">
                    <?php if ($success): ?>
                        <div class="alert-banner alert-success">
                            Listing submitted! It will appear to students once an admin approves it. You can track its status in
                            <a href="manageListings.php">Manage Listings</a>.
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert-banner alert-error">
                            <ul style="margin:0; padding-left:18px;">
                                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data" class="form-card">
                        <div class="create-listing-grid">
                            <div>
                                <h3 class="form-section-title">Food Details</h3>
                                <div class="field-row">
                                    <div class="field-group">
                                        <label class="field-label">Food Type</label>
                                        <input type="text" name="food_name" class="text-input" placeholder="e.g., Assorted Sandwiches" value="<?= htmlspecialchars($food_name) ?>" maxlength="200" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Quantity</label>
                                        <div class="input-with-suffix">
                                            <input type="number" name="quantity" min="1" step="1" placeholder="0" value="<?= htmlspecialchars($quantity) ?>" required>
                                            <span class="input-suffix">unit(s)</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="field-group">
                                    <label class="field-label">Approx. Weight (kg) <span style="font-weight:400; color:#98A2B3;">— optional, used for impact stats</span></label>
                                    <input type="number" name="weight_kg" min="0" step="0.1" class="text-input" placeholder="e.g., 2.5" value="<?= htmlspecialchars($weight_kg) ?>" style="max-width:220px;">
                                </div>

                                <div class="field-group">
                                    <label class="field-label">Description / Notes <span style="font-weight:400; color:#98A2B3;">— optional</span></label>
                                    <textarea name="description" class="textarea-input" placeholder="e.g., Contains dairy in the cheese danishes. Please bring your own container if possible." maxlength="500"><?= htmlspecialchars($description) ?></textarea>
                                </div>

                                <h3 class="form-section-title" style="margin-top:22px;">Dietary Categorization</h3>
                                <div class="checkbox-grid">
                                    <?php foreach ($allTags as $tag): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="tags[]" value="<?= $tag['tag_id'] ?>" <?= in_array((int)$tag['tag_id'], $selectedTags, true) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($tag['tag_name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <h3 class="form-section-title" style="margin-top:22px;">Logistics</h3>
                                <div class="field-row">
                                    <div class="field-group">
                                        <label class="field-label">Pickup Location</label>
                                        <input type="text" name="pickup_location" class="text-input" placeholder="e.g., Main Cafeteria, Counter 3" value="<?= htmlspecialchars($pickup_location) ?>" maxlength="200" required>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Expiring Window</label>
                                        <input type="datetime-local" name="expires_at" class="text-input" value="<?= htmlspecialchars($expires_at) ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="form-section-title">Image</h3>
                                <label class="image-upload-box" for="imageInput" id="imageDropZone">
                                    <span class="image-upload-icon"><ion-icon name="cloud-upload-outline"></ion-icon></span>
                                    <span class="image-upload-title">Upload Food Image</span>
                                    <span class="image-upload-sub">Drag and drop or click to browse.<br>Max file size 5MB.</span>
                                </label>
                                <input type="file" id="imageInput" name="image" accept="image/jpeg,image/png,image/webp" style="display:none;">
                            </div>
                        </div>

                        <div class="form-divider"></div>

                        <div class="form-footer-actions">
                            <button type="reset" class="btn-outline">Reset</button>
                            <button type="submit" class="btn-primary">Publish Listing</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live preview for the uploaded food image
        const imageInput = document.getElementById('imageInput');
        const dropZone = document.getElementById('imageDropZone');
        imageInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    dropZone.innerHTML = '<img src="' + e.target.result + '" class="preview" alt="Preview">';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>
