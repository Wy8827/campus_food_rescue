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


// Gate: block all provider functionality until an admin approves the account
requireApprovedProvider($conn, $providerId);
$allTags = getAllFoodTags($conn);
$errors = [];
$successMsg = '';

/** Fetch a single listing, but ONLY if it belongs to this provider. */
function fetchOwnedListing(mysqli $conn, int $listingId, int $providerId): ?array {
    $stmt = mysqli_prepare($conn, "SELECT * FROM food_listing WHERE listing_id = ? AND provider_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $listingId, $providerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// =========================================================
// Handle POST actions (update / cancel), scoped to this provider
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)($_POST['listing_id'] ?? 0);
    $owned = fetchOwnedListing($conn, $targetId, $providerId);

    if (!$owned) {
        $errors[] = "That listing could not be found for your account.";
    } elseif (isset($_POST['cancel_listing'])) {
        $stmt = mysqli_prepare($conn, "UPDATE food_listing SET status = 'removed' WHERE listing_id = ? AND provider_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $targetId, $providerId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $successMsg = "Listing cancelled.";
    } elseif (isset($_POST['update_listing'])) {
        $food_name       = trim($_POST['food_name'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $remain_quantity = trim($_POST['remain_quantity'] ?? '');
        $pickup_location = trim($_POST['pickup_location'] ?? '');
        $expires_at      = trim($_POST['expires_at'] ?? '');
        $selectedTags    = array_map('intval', $_POST['tags'] ?? []);

        if ($food_name === '' || strlen($food_name) > 200) {
            $errors[] = "Please provide a valid food name.";
        }
        if ($remain_quantity === '' || !ctype_digit($remain_quantity)) {
            $errors[] = "Remaining quantity must be a whole number.";
        } elseif ((int)$remain_quantity > (int)$owned['total_quantity']) {
            $errors[] = "Remaining quantity cannot exceed the initial quantity (" . (int)$owned['total_quantity'] . ").";
        }
        if ($pickup_location === '') {
            $errors[] = "Please provide a pickup location.";
        }
        if ($expires_at === '' || strtotime($expires_at) === false) {
            $errors[] = "Please provide a valid expiring time.";
        }

        if (empty($errors)) {
            $expiresFormatted = date('Y-m-d H:i:s', strtotime($expires_at));
            $remainInt = (int)$remain_quantity;

            $stmt = mysqli_prepare($conn, "
                UPDATE food_listing
                SET food_name = ?, description = ?, remain_quantity = ?, pickup_location = ?, expires_at = ?
                WHERE listing_id = ? AND provider_id = ?
            ");
            mysqli_stmt_bind_param(
                $stmt, "ssissii",
                $food_name, $description, $remainInt, $pickup_location, $expiresFormatted, $targetId, $providerId
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                // Refresh dietary tags: clear then re-insert selection
                $del = mysqli_prepare($conn, "DELETE FROM food_listing_tags WHERE listing_id = ?");
                mysqli_stmt_bind_param($del, "i", $targetId);
                mysqli_stmt_execute($del);
                mysqli_stmt_close($del);

                if (!empty($selectedTags)) {
                    $tagStmt = mysqli_prepare($conn, "INSERT INTO food_listing_tags (listing_id, tag_id) VALUES (?, ?)");
                    foreach ($selectedTags as $tagId) {
                        mysqli_stmt_bind_param($tagStmt, "ii", $targetId, $tagId);
                        mysqli_stmt_execute($tagStmt);
                    }
                    mysqli_stmt_close($tagStmt);
                }

                $successMsg = "Listing updated successfully.";
            } else {
                $errors[] = "Failed to update the listing. Please try again.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// =========================================================
// Fetch all of this provider's listings for the left panel
// =========================================================
$stmt = mysqli_prepare($conn, "
    SELECT listing_id, food_name, remain_quantity, total_quantity, status, expires_at
    FROM food_listing
    WHERE provider_id = ?
    ORDER BY FIELD(status,'pending','active','fully_claimed','expired','removed'), expires_at ASC
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$allListings = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Which listing is selected for the detail panel on the right
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0);
if (!$selectedId && !empty($allListings)) {
    $selectedId = (int)$allListings[0]['listing_id'];
}
$selectedListing = $selectedId ? fetchOwnedListing($conn, $selectedId, $providerId) : null;
$selectedTagIds = $selectedListing ? getListingTagIds($conn, $selectedId) : [];
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
    <link rel="stylesheet" href="../../assets/css/provider/manageListings.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Manage Listings</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <h1 class="page-title">Manage Listing</h1>
                <p class="page-subtitle">Update details or manage the status of your current food offerings.</p>

                <div style="margin-top:20px;">
                    <?php if ($successMsg): ?><div class="alert-banner alert-success"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert-banner alert-error">
                            <ul style="margin:0; padding-left:18px;">
                                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="manage-listings-grid">
                        <!-- LEFT: listing picker -->
                        <div class="listings-panel">
                            <div class="listings-panel-header">
                                <h2>Your Listings</h2>
                                <span class="total-pill">Total: <?= count($allListings) ?></span>
                            </div>

                            <div class="listing-mini-list">
                                <?php if (empty($allListings)): ?>
                                    <p class="empty-panel-msg">You haven't created any listings yet.<br><a href="createListing.php">Create your first one</a>.</p>
                                <?php else: foreach ($allListings as $l):
                                    $badge = listingStatusBadge($l['status'], (int)$l['remain_quantity']);
                                    $isDead = in_array($l['status'], ['expired','removed'], true);
                                ?>
                                    <a class="listing-mini-card <?= (int)$l['listing_id'] === $selectedId ? 'selected' : '' ?> <?= $isDead ? 'is-expired' : '' ?>" href="?id=<?= $l['listing_id'] ?>">
                                        <div class="listing-mini-top">
                                            <span class="listing-mini-name"><?= htmlspecialchars($l['food_name']) ?></span>
                                            <span class="status-pill <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                                        </div>
                                        <div class="listing-mini-meta">
                                            <span><ion-icon name="cube-outline"></ion-icon> <?= (int)$l['remain_quantity'] ?>/<?= (int)$l['total_quantity'] ?> Units</span>
                                            <span><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars(timeLeftText($l['expires_at'])) ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <!-- RIGHT: detail / edit panel -->
                        <div class="detail-panel">
                            <?php if (!$selectedListing): ?>
                                <p class="empty-panel-msg">Select a listing on the left to view or edit its details.</p>
                            <?php else:
                                $tags = getListingTagIds($conn, $selectedId);
                                $isLocked = in_array($selectedListing['status'], ['expired','removed'], true);
                            ?>
                                <div class="detail-panel-header">
                                    <div>
                                        <h2>Listing Details</h2>
                                        <p>Edit information for '<?= htmlspecialchars($selectedListing['food_name']) ?>'</p>
                                    </div>
                                    <?php $badge = listingStatusBadge($selectedListing['status'], (int)$selectedListing['remain_quantity']); ?>
                                    <span class="status-pill <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                                </div>

                                <?php if ($isLocked): ?>
                                    <p style="color:#98A2B3; font-size:14px; margin-bottom:16px;">
                                        This listing is <?= strtolower($badge['label']) ?> and can no longer be edited.
                                    </p>
                                <?php endif; ?>

                                <form method="POST" action="?id=<?= $selectedId ?>">
                                    <input type="hidden" name="listing_id" value="<?= $selectedId ?>">

                                    <div class="field-row">
                                        <div class="field-group">
                                            <label class="field-label">Food Item Name</label>
                                            <input type="text" name="food_name" class="text-input" value="<?= htmlspecialchars($selectedListing['food_name']) ?>" <?= $isLocked ? 'readonly' : 'required' ?>>
                                        </div>
                                        <div class="field-group">
                                            <img src="<?= $selectedListing['image'] ? UPLOAD_URL . htmlspecialchars($selectedListing['image']) : '../../assets/images/logo.png' ?>" class="listing-image-preview" alt="Listing image">
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div class="field-group">
                                            <label class="field-label">Quantity Remaining (Units)</label>
                                            <input type="number" name="remain_quantity" min="0" max="<?= (int)$selectedListing['total_quantity'] ?>" class="text-input" value="<?= (int)$selectedListing['remain_quantity'] ?>" <?= $isLocked ? 'readonly' : 'required' ?>>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label">Initial Quantity</label>
                                            <input type="number" class="text-input" value="<?= (int)$selectedListing['total_quantity'] ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="field-group">
                                        <label class="field-label">Dietary Information</label>
                                        <div class="checkbox-grid">
                                            <?php foreach ($allTags as $tag): ?>
                                                <label class="checkbox-item">
                                                    <input type="checkbox" name="tags[]" value="<?= $tag['tag_id'] ?>" <?= in_array((int)$tag['tag_id'], $tags, true) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                                                    <?= htmlspecialchars($tag['tag_name']) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="field-row">
                                        <div class="field-group">
                                            <label class="field-label">Pickup Location</label>
                                            <input type="text" name="pickup_location" class="text-input" value="<?= htmlspecialchars($selectedListing['pickup_location']) ?>" <?= $isLocked ? 'readonly' : 'required' ?>>
                                        </div>
                                        <div class="field-group">
                                            <label class="field-label">Expiring Time</label>
                                            <input type="datetime-local" name="expires_at" class="text-input" value="<?= date('Y-m-d\TH:i', strtotime($selectedListing['expires_at'])) ?>" <?= $isLocked ? 'readonly' : 'required' ?>>
                                        </div>
                                    </div>

                                    <div class="field-group">
                                        <label class="field-label">Additional Notes (Optional)</label>
                                        <textarea name="description" class="textarea-input" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($selectedListing['description'] ?? '') ?></textarea>
                                    </div>

                                    <?php if (!$isLocked): ?>
                                        <div class="detail-footer-actions">
                                            <button type="submit" name="cancel_listing" value="1" class="btn-danger-outline" onclick="return confirm('Cancel this listing? Students will no longer be able to claim it.');">Cancel Listing</button>
                                            <div style="display:flex; gap:12px;">
                                                <a href="?id=<?= $selectedId ?>" class="btn-outline" style="text-decoration:none; display:inline-flex; align-items:center;">Discard Changes</a>
                                                <button type="submit" name="update_listing" value="1" class="btn-primary">Update Listing</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
