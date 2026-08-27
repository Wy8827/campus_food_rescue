<?php 
session_start(); 
require_once __DIR__ . '/../../config/constants.php'; 
require_once __DIR__ . '/../../config/session.php'; 
require_once __DIR__ . '/../../config/db.php'; 

requireRole('admin');   

// 1. Determine which view we are on (Food vs Provider)
$currentView = isset($_GET['view']) && $_GET['view'] === 'provider' ? 'provider' : 'food';

// ==========================================
// 2. Handle Approve / Reject Form Submissions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['type'], $_POST['id'])) {
    $action = $_POST['action'];
    $targetId = (int)$_POST['id'];
    $type = $_POST['type'];
    $adminId = $_SESSION['user_id'] ?? 1; // Fallback to 1 if session is not fully set

    try {
        if ($type === 'food') {
            // Food Listing Moderation Logic
            $newStatus = ($action === 'approve') ? 'active' : 'removed';
            $logAction = ($action === 'approve') ? 'approve_listing' : 'reject_listing';

            $updateQ = "UPDATE food_listing SET status = ?, approved_by = ?, approved_at = NOW() WHERE listing_id = ?";
            $stmt = mysqli_prepare($conn, $updateQ);
            mysqli_stmt_bind_param($stmt, "sii", $newStatus, $adminId, $targetId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $logQ = "INSERT INTO listing_audit_log (admin_id, listing_id, action_type, notes) VALUES (?, ?, ?, 'Processed via List Moderation')";
            $stmtLog = mysqli_prepare($conn, $logQ);
            mysqli_stmt_bind_param($stmtLog, "iis", $adminId, $targetId, $logAction);
            mysqli_stmt_execute($stmtLog);
            mysqli_stmt_close($stmtLog);

        } elseif ($type === 'provider') {
            // Provider Registration Moderation Logic
            $newStatus = ($action === 'approve') ? 'active' : 'suspended';
            $logAction = ($action === 'approve') ? 'approved' : 'suspended';

            $updateQ = "UPDATE provider SET provider_status = ? WHERE provider_id = ?";
            $stmt = mysqli_prepare($conn, $updateQ);
            mysqli_stmt_bind_param($stmt, "si", $newStatus, $targetId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $logQ = "INSERT INTO provider_audit_log (admin_id, provider_id, action_type, reason) VALUES (?, ?, ?, 'Processed via List Moderation')";
            $stmtLog = mysqli_prepare($conn, $logQ);
            mysqli_stmt_bind_param($stmtLog, "iis", $adminId, $targetId, $logAction);
            mysqli_stmt_execute($stmtLog);
            mysqli_stmt_close($stmtLog);
        }
        
        // Redirect to prevent form resubmission on refresh, maintaining the current view
        header("Location: ?view=" . $currentView);
        exit;
    } catch (Exception $e) {
        $errorMsg = "Action failed: " . $e->getMessage();
    }
}

// ==========================================
// 3. Fetch Data Based on Current View
// ==========================================
if ($currentView === 'food') {
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
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/moderation.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>List Moderation</title>
    <style>
        /* New Styles for the View Toggle Buttons */
        .view-tabs {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            border-bottom: 1px solid #EAECF0;
            padding-bottom: 12px;
        }
        .tab-btn {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            color: #667085;
            background-color: #F2F4F7;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            background-color: #385E29;
            color: #FFFFFF;
        }
        .tab-btn:hover:not(.active) {
            background-color: #E4E7EC;
        }
        .provider-icon-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #F2F4F7;
            border-radius: 8px;
            color: #98A2B3;
            font-size: 48px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- sidebar on the left -->
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <h1 class="page-title">Listing Moderation</h1>
                <p class="page-subtitle">Approve or reject incoming requests. Ensure all standards are met before publishing to the network.</p>

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

                <!-- Existing Toolbar (Only show filters if looking at food) -->
                <div class="toolbar-container" <?= $currentView === 'provider' ? 'style="display:none;"' : '' ?>>
                    <div class="selection-container">
                        <select class="filter-select">
                            <option value="all">Category: All</option>
                            <option value="vegetarian">Vegetarian</option>
                            <option value="fruit">Fruit</option>
                        </select>
                        <select class="filter-select">
                            <option value="urgency">Urgency: All</option>
                            <option value="high">High (Today)</option>
                            <option value="medium">Medium (Tomorrow)</option>
                            <option value="low">Low (Later)</option>
                        </select>
                    </div>
                    <div class="view-toggle-buttons">
                        <button class="toggle-btn"><ion-icon name="list-outline"></ion-icon></button>
                        <button class="toggle-btn active"><ion-icon name="grid-outline"></ion-icon></button>
                    </div>
                </div>

                <!-- Shared Moderation List using your exact CSS classes -->
                <ul class="moderation-list">
                    <?php if (empty($items)): ?>
                        <p style="text-align: center; width: 100%; color: #6B7280; padding: 40px;">
                            No pending <?= $currentView === 'food' ? 'listings' : 'registrations' ?> to review right now. Great job!
                        </p>
                    <?php else: ?>
                        <?php foreach($items as $item): ?>

                            <?php if ($currentView === 'food'): 
                                // FOOD CARD RENDERING
                                $tags = getListingTags($conn, $item['listing_id']);
                                $expiryTime = strtotime($item['expires_at']);
                                $timeDiff = $expiryTime - time();
                                $isUrgent = ($timeDiff < 86400);

                                if ($timeDiff <= 0) {
                                    $expiresText = "Expired";
                                } elseif ($timeDiff < 3600) {
                                    $expiresText = "Expires in " . floor($timeDiff / 60) . "m";
                                } else {
                                    $expiresText = "Expires in " . floor($timeDiff / 3600) . "h";
                                }
                                $imagePath = $item['image'] ? UPLOAD_URL . htmlspecialchars($item['image']) : '../../assets/images/placeholder.jpg';
                            ?>
                                <li class="moderation-list-item">
                                    <article class="listing-card">
                                        <div class="food-image-container">
                                            <img src="<?= $imagePath ?>" alt="Food Image" class="food-image">
                                        </div> 
                                        <div class="food-info-container">
                                            <div class="card-header-row">
                                                <span class="item-code">#FR-<?= str_pad($item['listing_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                                <span class="urgent-badge <?= $isUrgent ? 'badge-urgent' : 'badge-normal' ?>"> <?= $expiresText ?></span>
                                            </div>
                                            <h3 class="listing-title"><?= htmlspecialchars($item['food_name']) ?></h3>
                                            <div class="location-row">
                                                <ion-icon name="location-outline"></ion-icon>
                                                <span class="location"><?= htmlspecialchars($item['pickup_location']) ?></span>
                                            </div>
                                            <div class="meta-details">
                                                <span class="detail-text"><?= htmlspecialchars($item['provider_name']) ?> • <?= $item['total_quantity'] ?> Portions •</span>
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
                                            <form method="POST" action="" class="action-buttons" style="margin: 0; padding: 0;">
                                                <input type="hidden" name="type" value="food">
                                                <input type="hidden" name="id" value="<?= $item['listing_id'] ?>">
                                                <button type="submit" name="action" value="approve" class="approve-button">Approve</button>
                                                <button type="submit" name="action" value="reject" class="reject-button">Reject</button>
                                                <button type="button" class="flag-icon-btn"><ion-icon name="flag-outline"></ion-icon></button>
                                            </form>
                                        </div>
                                    </article>
                                </li>

                            <?php else: 
                                // PROVIDER CARD RENDERING
                            ?>
                                <li class="moderation-list-item">
                                    <article class="listing-card">
                                        <!-- Using a placeholder icon instead of a food image for vendors -->
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
                                                <span class="detail-text">📞 <?= htmlspecialchars($item['contact_number']) ?> • 🕒 <?= htmlspecialchars($item['operating_hours']) ?></span>
                                            </div>
                                            <!-- Provider request notes displayed in italics -->
                                            <p style="font-size: 13px; color:#475467; margin:0 0 12px 0; font-style:italic;">
                                                "<?= htmlspecialchars($item['request_note'] ?? 'No additional notes provided.') ?>"
                                            </p>
                                            <div class="card-divider"></div>
                                            <form method="POST" action="" class="action-buttons" style="margin: 0; padding: 0;">
                                                <input type="hidden" name="type" value="provider">
                                                <input type="hidden" name="id" value="<?= $item['provider_id'] ?>">
                                                <!-- Reusing the exact same button classes -->
                                                <button type="submit" name="action" value="approve" class="approve-button">Approve</button>
                                                <button type="submit" name="action" value="reject" class="reject-button">Reject</button>
                                                <button type="button" class="flag-icon-btn"><ion-icon name="alert-circle-outline"></ion-icon></button>
                                            </form>
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
</body>
</html>