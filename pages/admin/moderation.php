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
    $adminId = $_SESSION['user_id'] ?? 1;

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
            $logAction = ($action === 'approve') ? 'approve_provider' : 'reject_provider';

            // 1. update Provider status
            $updateQ = "UPDATE provider SET provider_status = ? WHERE provider_id = ?";
            $stmt = mysqli_prepare($conn, $updateQ);
            mysqli_stmt_bind_param($stmt, "si", $newStatus, $targetId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // 2. check if provider related user_id and store name for logging
            $getUserQ = "SELECT user_id, provider_name FROM provider WHERE provider_id = ?";
            $stmtUser = mysqli_prepare($conn, $getUserQ);
            mysqli_stmt_bind_param($stmtUser, "i", $targetId);
            mysqli_stmt_execute($stmtUser);
            $resUser = mysqli_stmt_get_result($stmtUser);
            $providerData = mysqli_fetch_assoc($resUser);
            $targetUserId = $providerData['user_id'] ?? $targetId;
            $providerName = $providerData['provider_name'] ?? 'Provider';
            mysqli_stmt_close($stmtUser);

            // 3. write into user_audit_log
            $noteText = "Provider registration " . ($action === 'approve' ? 'approved' : 'rejected') . " for " . $providerName;
            $logQ = "INSERT INTO user_audit_log (admin_id, affected_user_id, action_type, notes) VALUES (?, ?, ?, ?)";
            $stmtLog = mysqli_prepare($conn, $logQ);
            mysqli_stmt_bind_param($stmtLog, "iiss", $adminId, $targetUserId, $logAction, $noteText);
            mysqli_stmt_execute($stmtLog);
            mysqli_stmt_close($stmtLog);
        }
        
        // Redirect to prevent form resubmission on refresh
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
        .no-filter-match {
            display: none;
            text-align: center;
            width: 100%;
            color: #6B7280;
            padding: 40px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
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

                <div class="view-tabs">
                    <a href="?view=food" class="tab-btn <?= $currentView === 'food' ? 'active' : '' ?>">Food Listings</a>
                    <a href="?view=provider" class="tab-btn <?= $currentView === 'provider' ? 'active' : '' ?>">Provider Registrations</a>
                </div>

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
                                                <span class="detail-text">📞 <?= htmlspecialchars($item['contact_number']) ?> • 🕒 <?= htmlspecialchars($item['operating_hours']) ?></span>
                                            </div>
                                            <p style="font-size: 13px; color:#475467; margin:0 0 12px 0; font-style:italic;">
                                                "<?= htmlspecialchars($item['request_note'] ?? 'No additional notes provided.') ?>"
                                            </p>
                                            <div class="card-divider"></div>
                                            <form method="POST" action="" class="action-buttons" style="margin: 0; padding: 0;">
                                                <input type="hidden" name="type" value="provider">
                                                <input type="hidden" name="id" value="<?= $item['provider_id'] ?>">
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categorySelect = document.getElementById('categoryFilter');
            const urgencySelect = document.getElementById('urgencyFilter');
            const foodCards = document.querySelectorAll('.food-item-card');
            const noMatchMsg = document.getElementById('noFilterMatchMessage');

            function applyFilters() {
                if (!categorySelect || !urgencySelect) return;

                const selectedCategory = categorySelect.value.toLowerCase().trim();
                const selectedUrgency = urgencySelect.value.toLowerCase().trim();
                let visibleCount = 0;

                foodCards.forEach(card => {
                    const cardTags = (card.getAttribute('data-tags') || '').split(',').map(t => t.trim());
                    const cardUrgency = (card.getAttribute('data-urgency') || '').trim();

                    const matchesCategory = (selectedCategory === 'all') || cardTags.includes(selectedCategory);
                    const matchesUrgency = (selectedUrgency === 'all') || (cardUrgency === selectedUrgency);

                    if (matchesCategory && matchesUrgency) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (noMatchMsg) {
                    if (visibleCount === 0 && foodCards.length > 0) {
                        noMatchMsg.style.display = 'block';
                    } else {
                        noMatchMsg.style.display = 'none';
                    }
                }
            }

            if (categorySelect && urgencySelect) {
                categorySelect.addEventListener('change', applyFilters);
                urgencySelect.addEventListener('change', applyFilters);
            }

            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const moderationList = document.getElementById('moderationList');

            if (listViewBtn && gridViewBtn && moderationList) {
                listViewBtn.addEventListener('click', function () {
                    listViewBtn.classList.add('active');
                    gridViewBtn.classList.remove('active');
                    moderationList.classList.add('list-view');
                });

                gridViewBtn.addEventListener('click', function () {
                    gridViewBtn.classList.add('active');
                    listViewBtn.classList.remove('active');
                    moderationList.classList.remove('list-view');
                });
            }
        });
    </script>
</body>
</html>