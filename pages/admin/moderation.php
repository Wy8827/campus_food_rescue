<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('admin');

// 1. Determine which view we are on (Food vs Provider)
$currentView = isset($_GET['view']) && $_GET['view'] === 'provider' ? 'provider' : 'food';
$errorMsg = '';
$successMsg = '';

// ==========================================
// 2. Handle Approve / Reject Submissions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['type'], $_POST['id'])) {
    $action = $_POST['action']; // 'approve' or 'reject'
    $targetId = (int)$_POST['id'];
    $type = $_POST['type']; // 'food' or 'provider'
    $reason = trim($_POST['reason'] ?? '');
    $adminId = $_SESSION['user_id'] ?? 1;

    try {
        if ($type === 'food') {
            // Food Listing Moderation Logic
            if ($action === 'approve') {
                $newStatus = 'active';
                $logAction = 'approve_listing';
                $noteText = 'Approved listing publishing to campus network.';
            } elseif ($action === 'reject') {
                $newStatus = 'removed';
                $logAction = 'reject_listing';
                $noteText = !empty($reason) ? "Rejected reason: " . $reason : "Listing rejected by administrator.";
            }

            // Update food listing status
            $updateQ = "UPDATE food_listing SET status = ?, approved_by = ?, approved_at = NOW() WHERE listing_id = ?";
            $stmt = mysqli_prepare($conn, $updateQ);
            mysqli_stmt_bind_param($stmt, "sii", $newStatus, $adminId, $targetId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Record in listing audit log
            $logQ = "INSERT INTO listing_audit_log (admin_id, listing_id, action_type, notes) VALUES (?, ?, ?, ?)";
            $stmtLog = mysqli_prepare($conn, $logQ);
            mysqli_stmt_bind_param($stmtLog, "iiss", $adminId, $targetId, $logAction, $noteText);
            mysqli_stmt_execute($stmtLog);
            mysqli_stmt_close($stmtLog);

        } elseif ($type === 'provider') {
            // Provider Registration Moderation Logic
            if ($action === 'approve') {
                $newStatus = 'active';
                $logAction = 'approve_provider';
                $actionDesc = 'approved';
            } elseif ($action === 'reject') {
                $newStatus = 'suspended';
                $logAction = 'reject_provider';
                $actionDesc = 'rejected' . (!empty($reason) ? " (Reason: $reason)" : "");
            }

            // 1. Update Provider status
            $updateQ = "UPDATE provider SET provider_status = ? WHERE provider_id = ?";
            $stmt = mysqli_prepare($conn, $updateQ);
            mysqli_stmt_bind_param($stmt, "si", $newStatus, $targetId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // 2. Fetch provider related user_id and store name
            $getUserQ = "SELECT user_id, provider_name FROM provider WHERE provider_id = ?";
            $stmtUser = mysqli_prepare($conn, $getUserQ);
            mysqli_stmt_bind_param($stmtUser, "i", $targetId);
            mysqli_stmt_execute($stmtUser);
            $resUser = mysqli_stmt_get_result($stmtUser);
            $providerData = mysqli_fetch_assoc($resUser);
            $targetUserId = $providerData['user_id'] ?? $targetId;
            $providerName = $providerData['provider_name'] ?? 'Provider';
            mysqli_stmt_close($stmtUser);

            // 3. Record in user audit log
            $noteText = "Provider registration " . $actionDesc . " for " . $providerName;
            $logQ = "INSERT INTO user_audit_log (admin_id, affected_user_id, action_type, notes) VALUES (?, ?, ?, ?)";
            $stmtLog = mysqli_prepare($conn, $logQ);
            mysqli_stmt_bind_param($stmtLog, "iiss", $adminId, $targetUserId, $logAction, $noteText);
            mysqli_stmt_execute($stmtLog);
            mysqli_stmt_close($stmtLog);
        }

        // Redirect after POST
        header("Location: ?view=" . $currentView);
        exit;
    } catch (Exception $e) {
        $errorMsg = "Action failed: " . $e->getMessage();
    }
}

// ==========================================
// 3. Fetch Data Based on Current View
// ==========================================
$allCategories = [];
if ($currentView === 'food') {
    $tagListQuery = "SELECT DISTINCT tag_name FROM food_tags ORDER BY tag_name ASC";
    $tagListResult = mysqli_query($conn, $tagListQuery);
    if ($tagListResult) {
        $allCategories = mysqli_fetch_all($tagListResult, MYSQLI_ASSOC);
    }

    // Fetch pending food listings
    $query = "
        SELECT 
            f.listing_id, f.food_name, f.description, f.total_quantity, 
            f.pickup_location, f.expires_at, f.image, f.status,
            p.provider_name
        FROM food_listing f
        JOIN provider p ON f.provider_id = p.provider_id
        WHERE f.status = 'pending' 
          AND f.expires_at > NOW() 
        ORDER BY f.expires_at ASC
    ";
    $result = mysqli_query($conn, $query);
    $items = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Helper function for tags
    function getListingTags($conn, $listingId) {
        $tagQuery = "SELECT t.tag_name FROM food_tags t JOIN food_listing_tags flt ON t.tag_id = flt.tag_id WHERE flt.listing_id = ?";
        $stmt = mysqli_prepare($conn, $tagQuery);
        mysqli_stmt_bind_param($stmt, "i", $listingId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $tags = mysqli_fetch_all($res, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return array_column($tags, 'tag_name');
    }
} else {
    // Fetch pending provider registrations
    $query = "
        SELECT 
            provider_id, provider_name, contact_number, location, 
            operating_hours, request_note
        FROM provider 
        WHERE provider_status = 'pending_approval'
    ";
    $result = mysqli_query($conn, $query);
    $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/userManagement.css">
    <link rel="stylesheet" href="../../assets/css/moderation.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>List Moderation</title>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>
            <div class="content-container">
                <h1 class="page-title">Listing Moderation</h1>
                <p class="page-subtitle">Approve or reject incoming requests. Ensure quality and safety standards before publishing.</p>

                <?php if(!empty($errorMsg)): ?>
                    <div style="color: #721c24; background-color: #f8d7da; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                        <?= htmlspecialchars($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <!-- View Toggle Tabs -->
                <div class="view-tabs">
                    <a href="?view=food" class="tab-btn <?= $currentView === 'food' ? 'active' : '' ?>">Food Listings</a>
                    <a href="?view=provider" class="tab-btn <?= $currentView === 'provider' ? 'active' : '' ?>">Provider Registrations</a>
                </div>

                <!-- Toolbar (Only for food listings) -->
                <div class="toolbar-container" <?= $currentView === 'provider' ? 'style="display:none;"' : '' ?>>
                    <div class="selection-container">
                        <select id="categoryFilter" class="filter-select">
                            <option value="all">Category: All</option>
                            <?php if (!empty($allCategories)): ?>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars(strtolower($cat['tag_name'])) ?>">
                                        <?= htmlspecialchars($cat['tag_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="vegetarian">Vegetarian</option>
                                <option value="fruit">Fruit</option>
                            <?php endif; ?>
                        </select>
                        <select id="urgencyFilter" class="filter-select">
                            <option value="all">Urgency: All</option>
                            <option value="high">High (&le; 24 Hours)</option>
                            <option value="medium">Medium (1 - 2 Days)</option>
                            <option value="low">Low (&gt; 2 Days)</option>
                        </select>
                    </div>
                    <div class="view-toggle-buttons">
                        <button type="button" class="toggle-btn" id="listViewBtn"><ion-icon name="list-outline"></ion-icon></button>
                        <button type="button" class="toggle-btn active" id="gridViewBtn"><ion-icon name="grid-outline"></ion-icon></button>
                    </div>
                </div>

                <ul class="moderation-list" id="moderationList">
                    <li id="noFilterMatchMessage" class="no-filter-match">
                        No pending listings match the selected filter criteria.
                    </li>

                    <?php if (empty($items)): ?>
                        <p style="text-align: center; width: 100%; color: #6B7280; padding: 40px;">
                            No pending <?= $currentView === 'food' ? 'listings' : 'registrations' ?> to review right now. Great job!
                        </p>
                    <?php else: ?>
                        <?php foreach($items as $item): ?>
                            <?php if ($currentView === 'food'): 
                                $tags = getListingTags($conn, $item['listing_id']);
                                $expiryTime = strtotime($item['expires_at']);
                                $timeDiff = $expiryTime - time();
                                $isUrgent = ($timeDiff < 86400);

                                if ($timeDiff <= 86400) {
                                    $urgencyLevel = 'high';
                                } elseif ($timeDiff <= 172800) {
                                    $urgencyLevel = 'medium';
                                } else {
                                    $urgencyLevel = 'low';
                                }

                                if ($timeDiff <= 0) {
                                    $expiresText = "Expired";
                                } elseif ($timeDiff < 3600) {
                                    $expiresText = "Expires in " . floor($timeDiff / 60) . "m";
                                } elseif ($timeDiff < 86400) {
                                    $hours = floor($timeDiff / 3600);
                                    $mins = floor(($timeDiff % 3600) / 60);
                                    $expiresText = "Expires in " . $hours . "h" . ($mins > 0 ? " " . $mins . "m" : "");
                                } else {
                                    $days = floor($timeDiff / 86400);
                                    $hours = floor(($timeDiff % 86400) / 3600);
                                    $expiresText = "Expires in " . $days . "d" . ($hours > 0 ? " " . $hours . "h" : "");
                                }
                                $imagePath = $item['image'] ? UPLOAD_URL . htmlspecialchars($item['image']) : '../../assets/images/placeholder.jpg';
                                $tagDataString = strtolower(implode(',', $tags));
                            ?>
                                <li class="moderation-list-item food-item-card" 
                                    data-tags="<?= htmlspecialchars($tagDataString) ?>" 
                                    data-urgency="<?= $urgencyLevel ?>">
                                    <article class="listing-card">
                                        <div class="food-image-container">
                                            <img src="<?= $imagePath ?>" alt="Food Image" class="food-image">
                                        </div>
                                        <div class="food-info-container">
                                            <div class="card-header-row">
                                                <span class="item-code">#FR-<?= str_pad($item['listing_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                                <span class="urgent-badge <?= $isUrgent ? 'badge-urgent' : 'badge-normal' ?>"><?= $expiresText ?></span>
                                            </div>
                                            <h3 class="listing-title"><?= htmlspecialchars($item['food_name']) ?></h3>
                                            <div class="location-row">
                                                <ion-icon name="location-outline"></ion-icon>
                                                <span class="location"><?= htmlspecialchars($item['pickup_location']) ?></span>
                                            </div>
                                            <div class="meta-details">
                                                <span class="detail-text"><?= htmlspecialchars($item['provider_name']) ?> &bull; <?= $item['total_quantity'] ?> Portions</span>
                                            </div>
                                            <div class="diet-tags-container">
                                                <?php if (!empty($tags)): ?>
                                                    <?php foreach($tags as $tag): ?>
                                                        <span class="diet-tag <?= strtolower($tag) === 'vegan' ? 'vegan' : '' ?>"><?= htmlspecialchars($tag) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="diet-tag">No Tags</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-divider"></div>
                                            <div class="action-buttons" style="margin: 0; padding: 0;">
                                                <!-- Direct Approve Form -->
                                                <form method="POST" action="" style="display:inline;">
                                                    <input type="hidden" name="type" value="food">
                                                    <input type="hidden" name="id" value="<?= $item['listing_id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="approve-button">Approve</button>
                                                </form>

                                                <!-- Trigger Reject Modal -->
                                                <button type="button" class="reject-button" onclick="openReasonModal('reject', 'food', <?= $item['listing_id'] ?>, '<?= htmlspecialchars(addslashes($item['food_name'])) ?>')">Reject</button>
                                            </div>
                                        </div>
                                    </article>
                                </li>
                            <?php else: ?>
                                <li class="moderation-list-item">
                                    <article class="listing-card">
                                        <div class="food-image-container">
                                            <div class="provider-icon-placeholder">
                                                <ion-icon name="storefront-outline"></ion-icon>
                                            </div>
                                        </div>
                                        <div class="food-info-container">
                                            <div class="card-header-row">
                                                <span class="item-code">#PRV-<?= str_pad($item['provider_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                                <span class="urgent-badge badge-normal">New Registration</span>
                                            </div>
                                            <h3 class="listing-title"><?= htmlspecialchars($item['provider_name']) ?></h3>
                                            <div class="location-row">
                                                <ion-icon name="location-outline"></ion-icon>
                                                <span class="location"><?= htmlspecialchars($item['location']) ?></span>
                                            </div>
                                            <div class="meta-details">
                                                <span class="detail-text"><?= htmlspecialchars($item['contact_number']) ?> &bull; <?= htmlspecialchars($item['operating_hours']) ?></span>
                                            </div>
                                            <p style="font-size: 13px; color:#475467; margin:0 0 12px 0; font-style:italic;">
                                                "<?= htmlspecialchars($item['request_note'] ?? 'No additional notes provided.') ?>"
                                            </p>
                                            <div class="card-divider"></div>
                                            <div class="action-buttons" style="margin: 0; padding: 0;">
                                                <!-- Direct Approve Form -->
                                                <form method="POST" action="" style="display:inline;">
                                                    <input type="hidden" name="type" value="provider">
                                                    <input type="hidden" name="id" value="<?= $item['provider_id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="approve-button">Approve</button>
                                                </form>

                                                <!-- Trigger Reject Modal -->
                                                <button type="button" class="reject-button" onclick="openReasonModal('reject', 'provider', <?= $item['provider_id'] ?>, '<?= htmlspecialchars(addslashes($item['provider_name'])) ?>')">Reject</button>
                                            </div>
                                        </div>
                                    </article>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- REASON / AUDIT ACTION MODAL (For Reject Only)         -->
    <!-- ===================================================== -->
    <div id="reasonModal" class="custom-modal-backdrop" onclick="handleReasonBackdropClick(event)">
        <div class="custom-modal-card" style="max-width: 460px;">
            <div class="modal-card-header">
                <h2 class="modal-card-title" id="reasonModalTitle">Reject Item</h2>
                <button type="button" class="modal-close-btn" onclick="closeReasonModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" id="modalAction" value="reject">
                <input type="hidden" name="type" id="modalType" value="">
                <input type="hidden" name="id" id="modalTargetId" value="">

                <div class="modal-card-body">
                    <p id="reasonModalDesc" style="font-size: 13.5px; color: #4B5563; margin-bottom: 12px;"></p>
                    <div class="modal-form-group">
                        <label class="modal-form-label" for="actionReason">Rejection Reason</label>
                        <textarea name="reason" id="actionReason" class="modal-form-input" rows="4" style="resize: vertical; font-family: inherit;" placeholder="e.g., Incomplete description, expired pickup time..." required></textarea>
                    </div>
                </div>
                <div class="modal-card-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeReasonModal()">Cancel</button>
                    <button type="submit" id="reasonSubmitBtn" class="btn-modal-submit" style="background-color: #DC2626;">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/moderation.js"></script>
</body>
</html>