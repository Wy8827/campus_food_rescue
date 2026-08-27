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

// Keep both status columns truthful before anything below reads them.
syncExpiredListings($conn);
syncExpiredClaims($conn);

$flagMsg = '';
$flagErr = '';

// =========================================================
// Handle "Flag" action — records a no-show against the student's
// account (user.no_show_count). Repeated no-shows auto-throttle the
// student's account, mirroring the anti-abuse fields already built
// into the `user` table.
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flag_claim'])) {
    $claimId = (int)($_POST['claim_id'] ?? 0);

    // Ownership + eligibility check in one query: must belong to this
    // provider's listing AND actually be an expired (no-show) claim.
    $stmt = mysqli_prepare($conn, "
        SELECT c.claim_id, c.student_id
        FROM claim c
        JOIN food_listing f ON c.listing_id = f.listing_id
        WHERE c.claim_id = ? AND f.provider_id = ? AND c.status = 'expired'
    ");
    mysqli_stmt_bind_param($stmt, "ii", $claimId, $providerId);
    mysqli_stmt_execute($stmt);
    $claim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$claim) {
        $flagErr = "That claim can't be flagged (not found, not yours, or not expired).";
    } else {
        $studentId = (int)$claim['student_id'];
        $stmt = mysqli_prepare($conn, "UPDATE user SET no_show_count = no_show_count + 1 WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $studentId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Auto-throttle after repeated no-shows
        $stmt = mysqli_prepare($conn, "SELECT no_show_count FROM user WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $studentId);
        mysqli_stmt_execute($stmt);
        $newCount = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['no_show_count'];
        mysqli_stmt_close($stmt);

        if ($newCount >= 3) {
            $throttledUntil = date('Y-m-d H:i:s', strtotime('+7 days'));
            $stmt = mysqli_prepare($conn, "UPDATE user SET account_status = 'throttled', throttled_until = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $throttledUntil, $studentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $flagMsg = "No-show recorded. This student has now reached $newCount no-shows and has been temporarily throttled.";
        } else {
            $flagMsg = "No-show recorded ($newCount total for this student).";
        }
    }
}

// =========================================================
// Filters (GET) — self-posting-style: the page reads its own query
// string so filter links / search / pagination all just navigate.
// =========================================================
$filters = [
    'status'     => $_GET['status'] ?? '',
    'listing_id' => $_GET['listing_id'] ?? '',
    'q'          => trim($_GET['q'] ?? ''),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;

$built = buildClaimFilterWhere($providerId, $filters);
$where = $built['where'];
$params = $built['params'];

// Total count for pagination
$countSql = "
    SELECT COUNT(*) FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    JOIN user u ON c.student_id = u.user_id
    WHERE $where
";
$stmt = mysqli_prepare($conn, $countSql);
bindAndExecute($stmt, $params);
$totalRows = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Main page of results
$sql = "
    SELECT c.claim_id, c.portion_claimed, c.created_at, c.reservation_expires_at,
           c.confirmed_at, c.status, c.student_id,
           u.user_name, u.email,
           f.listing_id, f.food_name, f.pickup_location
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    JOIN user u ON c.student_id = u.user_id
    WHERE $where
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = mysqli_prepare($conn, $sql);
bindAndExecute($stmt, $params);
$claims = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// =========================================================
// Stat row + filter-panel counts
// =========================================================
$stmt = mysqli_prepare($conn, "
    SELECT
        SUM(CASE WHEN c.status IN ('pending','confirmed') AND c.reservation_expires_at >= NOW() THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN c.status IN ('pending','confirmed') AND c.reservation_expires_at >= NOW() AND TIMESTAMPDIFF(MINUTE, NOW(), c.reservation_expires_at) <= 15 THEN 1 ELSE 0 END) AS expiring_soon,
        SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN c.status = 'expired' THEN 1 ELSE 0 END) AS expired_count,
        SUM(CASE WHEN c.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        COUNT(*) AS total_count
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$counts = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM claim c JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed' AND DATE(c.confirmed_at) = CURDATE()
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$completedToday = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM claim c JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed' AND DATE(c.confirmed_at) = CURDATE() - INTERVAL 1 DAY
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$completedYesterday = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);
$vsYesterday = $completedYesterday > 0 ? $completedToday - $completedYesterday : $completedToday;

$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM claim c JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'expired' AND c.reservation_expires_at >= CURDATE() - INTERVAL 7 DAY
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$expiredThisWeek = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);

// Listing dropdown options
$stmt = mysqli_prepare($conn, "SELECT listing_id, food_name FROM food_listing WHERE provider_id = ? ORDER BY food_name ASC");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$listingOptions = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Helper to build filter links while preserving other active filters
function filterUrl(array $filters, array $overrides): string {
    $merged = array_merge($filters, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '');
    return '?' . http_build_query($merged);
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
    <link rel="stylesheet" href="../../assets/css/provider/claimTracker.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Claim Tracker</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <?php if ($flagMsg): ?><div class="alert-banner alert-success"><?= htmlspecialchars($flagMsg) ?></div><?php endif; ?>
                <?php if ($flagErr): ?><div class="alert-banner alert-error"><?= htmlspecialchars($flagErr) ?></div><?php endif; ?>

                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">Claim Tracker</h1>
                        <p class="page-subtitle">All students who claimed your listings — pending, completed, expired.</p>
                    </div>
                    <div class="user-page-header-right">
                        <a class="export-btn" href="exportClaims.php?<?= http_build_query($filters) ?>">
                            <ion-icon name="download-outline"></ion-icon> Export CSV
                        </a>
                    </div>
                </div>

                <div class="ct-stat-row">
                    <div class="ct-stat-card">
                        <div class="ct-stat-label">Pending pickup</div>
                        <div class="ct-stat-val ct-amber"><?= (int)$counts['pending_count'] ?></div>
                        <div class="ct-stat-sub"><?= (int)$counts['expiring_soon'] ?> expiring soon</div>
                    </div>
                    <div class="ct-stat-card">
                        <div class="ct-stat-label">Completed today</div>
                        <div class="ct-stat-val ct-green"><?= $completedToday ?></div>
                        <div class="ct-stat-sub"><?= $vsYesterday >= 0 ? '+' : '' ?><?= $vsYesterday ?> vs yesterday</div>
                    </div>
                    <div class="ct-stat-card">
                        <div class="ct-stat-label">Expired (no-show)</div>
                        <div class="ct-stat-val ct-red"><?= $expiredThisWeek ?></div>
                        <div class="ct-stat-sub">This week</div>
                    </div>
                    <div class="ct-stat-card">
                        <div class="ct-stat-label">Total claims</div>
                        <div class="ct-stat-val"><?= (int)$counts['total_count'] ?></div>
                        <div class="ct-stat-sub">All time</div>
                    </div>
                </div>

                <div class="ct-layout">
                    <!-- Filter panel -->
                    <div class="ct-filter-panel">
                        <div class="ct-filter-title">Filter claims</div>

                        <div class="ct-filter-group">
                            <span class="ct-filter-label">Status</span>
                            <a class="ct-filter-option <?= $filters['status'] === '' ? 'on' : '' ?>" href="<?= filterUrl($filters, ['status' => '', 'page' => '']) ?>">
                                <span class="ct-fdot" style="background:#a1a1aa"></span>All<span class="ct-fcount"><?= (int)$counts['total_count'] ?></span>
                            </a>
                            <a class="ct-filter-option <?= $filters['status'] === 'pending' ? 'on' : '' ?>" href="<?= filterUrl($filters, ['status' => 'pending', 'page' => '']) ?>">
                                <span class="ct-fdot" style="background:#b45309"></span>Pending<span class="ct-fcount"><?= (int)$counts['pending_count'] ?></span>
                            </a>
                            <a class="ct-filter-option <?= $filters['status'] === 'completed' ? 'on' : '' ?>" href="<?= filterUrl($filters, ['status' => 'completed', 'page' => '']) ?>">
                                <span class="ct-fdot" style="background:#3b711a"></span>Completed<span class="ct-fcount"><?= (int)$counts['completed_count'] ?></span>
                            </a>
                            <a class="ct-filter-option <?= $filters['status'] === 'expired' ? 'on' : '' ?>" href="<?= filterUrl($filters, ['status' => 'expired', 'page' => '']) ?>">
                                <span class="ct-fdot" style="background:#dc2626"></span>Expired<span class="ct-fcount"><?= (int)$counts['expired_count'] ?></span>
                            </a>
                            <a class="ct-filter-option <?= $filters['status'] === 'cancelled' ? 'on' : '' ?>" href="<?= filterUrl($filters, ['status' => 'cancelled', 'page' => '']) ?>">
                                <span class="ct-fdot" style="background:#a1a1aa"></span>Cancelled<span class="ct-fcount"><?= (int)$counts['cancelled_count'] ?></span>
                            </a>
                        </div>

                        <form method="GET" class="ct-filter-group" id="listingFilterForm">
                            <span class="ct-filter-label">Listing</span>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status']) ?>">
                            <input type="hidden" name="q" value="<?= htmlspecialchars($filters['q']) ?>">
                            <select name="listing_id" class="ct-filter-select" onchange="this.form.submit()">
                                <option value="">All listings</option>
                                <?php foreach ($listingOptions as $lo): ?>
                                    <option value="<?= $lo['listing_id'] ?>" <?= (string)$filters['listing_id'] === (string)$lo['listing_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lo['food_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <!-- Table -->
                    <div class="ct-table-panel">
                        <div class="ct-table-header">
                            <form method="GET" class="ct-search">
                                <input type="hidden" name="status" value="<?= htmlspecialchars($filters['status']) ?>">
                                <input type="hidden" name="listing_id" value="<?= htmlspecialchars($filters['listing_id']) ?>">
                                <ion-icon name="search-outline"></ion-icon>
                                <input type="text" name="q" placeholder="Search student name or email…" value="<?= htmlspecialchars($filters['q']) ?>">
                            </form>
                        </div>

                        <div class="ct-table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th><th>Listing</th><th>Qty</th><th>Claimed at</th>
                                        <th>Hold expires</th><th>QR status</th><th>Status</th><th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($claims)): ?>
                                        <tr><td colspan="8" style="text-align:center; padding:30px; color:#a1a1aa;">No claims match these filters.</td></tr>
                                    <?php else: foreach ($claims as $cl):
                                        $initials = strtoupper(substr($cl['user_name'], 0, 1) . (strpos($cl['user_name'], ' ') ? substr(strstr($cl['user_name'], ' '), 1, 1) : ''));
                                        $qr = claimQrStatus($cl);
                                        $statusBadgeClass = ['pending' => 'b-pending', 'confirmed' => 'b-pending', 'completed' => 'b-done', 'expired' => 'b-exp', 'cancelled' => 'b-cancel'][$cl['status']] ?? 'b-pending';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="ct-stu">
                                                    <div class="ct-avatar"><?= htmlspecialchars($initials) ?></div>
                                                    <div>
                                                        <div class="ct-sname"><?= htmlspecialchars($cl['user_name']) ?></div>
                                                        <div class="ct-semail"><?= htmlspecialchars($cl['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="ct-lname"><?= htmlspecialchars($cl['food_name']) ?></div>
                                                <div class="ct-lloc"><?= htmlspecialchars($cl['pickup_location']) ?></div>
                                            </td>
                                            <td><?= (int)$cl['portion_claimed'] ?></td>
                                            <td style="color:#a1a1aa"><?= date('g:i A', strtotime($cl['created_at'])) ?></td>
                                            <td>
                                                <?php if (in_array($cl['status'], ['pending', 'confirmed'], true) && strtotime($cl['reservation_expires_at']) >= time()): ?>
                                                    <span class="ct-timer ct-t-a"><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars(holdCountdownText($cl['reservation_expires_at'])) ?></span>
                                                <?php else: ?>
                                                    <span style="color:#a1a1aa"><?= $cl['confirmed_at'] ? date('g:i A', strtotime($cl['confirmed_at'])) : '—' ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="ct-qrs <?= $qr['class'] ?>"><span class="ct-qdot"></span><?= htmlspecialchars($qr['label']) ?></span></td>
                                            <td><span class="badge <?= $statusBadgeClass ?>"><?= ucfirst($cl['status']) ?></span></td>
                                            <td>
                                                <div class="ct-act">
                                                    <button type="button" class="ct-ab ct-a-view"
                                                        data-name="<?= htmlspecialchars($cl['user_name']) ?>"
                                                        data-email="<?= htmlspecialchars($cl['email']) ?>"
                                                        data-listing="<?= htmlspecialchars($cl['food_name']) ?>"
                                                        data-location="<?= htmlspecialchars($cl['pickup_location']) ?>"
                                                        data-qty="<?= (int)$cl['portion_claimed'] ?>"
                                                        data-claimed="<?= date('M j, Y g:i A', strtotime($cl['created_at'])) ?>"
                                                        data-status="<?= ucfirst($cl['status']) ?>"
                                                        data-qr="<?= htmlspecialchars($qr['label']) ?>">View</button>
                                                    <?php if ($cl['status'] === 'expired'): ?>
                                                        <form method="POST" onsubmit="return confirm('Flag this as a no-show? This adds to the student\'s no-show count and may throttle their account.');">
                                                            <input type="hidden" name="claim_id" value="<?= $cl['claim_id'] ?>">
                                                            <button type="submit" name="flag_claim" value="1" class="ct-ab ct-a-flag"><ion-icon name="flag-outline"></ion-icon> Flag</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="ct-pagination">
                            <div class="ct-pinfo">Showing <?= empty($claims) ? 0 : $offset + 1 ?>–<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> claims</div>
                            <div class="ct-pbtns">
                                <a class="ct-pb <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= filterUrl($filters, ['page' => max(1, $page - 1)]) ?>">‹</a>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a class="ct-pb <?= $p === $page ? 'on' : '' ?>" href="<?= filterUrl($filters, ['page' => $p]) ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <a class="ct-pb <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= filterUrl($filters, ['page' => min($totalPages, $page + 1)]) ?>">›</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View detail modal -->
    <div class="ct-modal-overlay" id="viewModal">
        <div class="ct-modal">
            <div class="ct-modal-header">
                <h3>Claim Details</h3>
                <button type="button" id="closeModal" class="ct-modal-close">&times;</button>
            </div>
            <div class="ct-modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('viewModal');
        const modalBody = document.getElementById('modalBody');
        document.querySelectorAll('.ct-a-view').forEach(btn => {
            btn.addEventListener('click', () => {
                const d = btn.dataset;
                modalBody.innerHTML = `
                    <div class="ct-modal-row"><span>Student</span><strong>${d.name}</strong></div>
                    <div class="ct-modal-row"><span>Email</span><strong>${d.email}</strong></div>
                    <div class="ct-modal-row"><span>Listing</span><strong>${d.listing}</strong></div>
                    <div class="ct-modal-row"><span>Pickup location</span><strong>${d.location}</strong></div>
                    <div class="ct-modal-row"><span>Quantity</span><strong>${d.qty}</strong></div>
                    <div class="ct-modal-row"><span>Claimed at</span><strong>${d.claimed}</strong></div>
                    <div class="ct-modal-row"><span>QR status</span><strong>${d.qr}</strong></div>
                    <div class="ct-modal-row"><span>Status</span><strong>${d.status}</strong></div>
                `;
                modal.classList.add('open');
            });
        });
        document.getElementById('closeModal').addEventListener('click', () => modal.classList.remove('open'));
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('open'); });

        // Auto-refresh so hold countdowns / newly-expired claims stay live
        // without the provider needing to manually reload. Skipped while
        // the detail modal is open so an in-progress "View" isn't yanked
        // away mid-read.
        setTimeout(() => {
            if (!modal.classList.contains('open')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
