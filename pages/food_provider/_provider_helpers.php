<?php
// ============================================================
//  FOOD PROVIDER HELPERS — included at the top of every page
//  inside pages/food_provider/. Assumes session.php, db.php and
//  constants.php have already been required by the calling page.
// ============================================================

/**
 * Look up the provider_id (provider profile row) that belongs to
 * the currently logged-in user. Every food_provider page needs this
 * because food_listing / claim rows are scoped by provider_id, not
 * user_id directly.
 */
function getProviderId(mysqli $conn, int $userId): ?int {
    $query = "SELECT provider_id FROM provider WHERE user_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? (int)$row['provider_id'] : null;
}

/**
 * Human readable "time left" / "time ago" style string used across
 * the dashboard and manage-listings cards.
 */
function timeLeftText(?string $datetime): string {
    if (!$datetime) return '-';
    $diff = strtotime($datetime) - time();
    if ($diff <= 0) return 'Ended';
    if ($diff < 3600) return floor($diff / 60) . 'm left';
    if ($diff < 86400) return floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm left';
    return floor($diff / 86400) . 'd left';
}

/**
 * Human readable "x mins ago" string, used for the QR Scanner's
 * "Last Scan" stat.
 */
function timeAgoText(?string $datetime): string {
    if (!$datetime) return '—';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    return floor($diff / 86400) . ' days ago';
}

/**
 * Format a food_listing.status value into the small pill labels
 * used on the dashboard / manage listings screens.
 */
function listingStatusBadge(string $status, int $remain): array {
    if ($status === 'expired')      return ['label' => 'EXPIRED',      'class' => 'badge-expired'];
    if ($status === 'removed')      return ['label' => 'REMOVED',      'class' => 'badge-expired'];
    if ($status === 'pending')      return ['label' => 'PENDING REVIEW','class' => 'badge-pending'];
    if ($status === 'fully_claimed') return ['label' => 'ALL CLAIMED', 'class' => 'badge-claimed'];
    if ($status === 'active' && $remain <= 3) return ['label' => 'LOW STOCK', 'class' => 'badge-low'];
    return ['label' => 'ACTIVE', 'class' => 'badge-active'];
}

/** All dietary tags available for the create/edit listing forms. */
function getAllFoodTags(mysqli $conn): array {
    $res = mysqli_query($conn, "SELECT tag_id, tag_name FROM food_tags ORDER BY tag_name ASC");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

/** Tag IDs currently attached to a given listing (for pre-checking checkboxes on edit). */
function getListingTagIds(mysqli $conn, int $listingId): array {
    $stmt = mysqli_prepare($conn, "SELECT tag_id FROM food_listing_tags WHERE listing_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $listingId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $ids = array_column(mysqli_fetch_all($res, MYSQLI_ASSOC), 'tag_id');
    mysqli_stmt_close($stmt);
    return array_map('intval', $ids);
}

/**
 * Count of this provider's claims still awaiting pickup (pending or
 * confirmed, not yet expired). Used for the Claim Tracker sidebar badge.
 */
function getPendingClaimsCount(mysqli $conn, int $providerId): int {
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) FROM claim c
        JOIN food_listing f ON c.listing_id = f.listing_id
        WHERE f.provider_id = ? AND c.status IN ('pending','confirmed')
          AND c.reservation_expires_at >= NOW()
    ");
    mysqli_stmt_bind_param($stmt, "i", $providerId);
    mysqli_stmt_execute($stmt);
    $count = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    mysqli_stmt_close($stmt);
    return $count;
}

/**
 * Fetches this provider's approval status. Registration always inserts
 * a new provider row as 'pending_approval' (the schema default), and
 * admin's existing moderation.php already Approve/Suspend it — this
 * just reads that state back for the gate below.
 */
function getProviderApprovalStatus(mysqli $conn, int $providerId): string {
    $stmt = mysqli_prepare($conn, "SELECT provider_status FROM provider WHERE provider_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $providerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row['provider_status'] ?? 'pending_approval';
}

/**
 * Gate called right after getProviderId() on every food_provider page.
 * If the account isn't 'active' yet, renders a holding page (still
 * wrapped in the real sidebar/topbar so Settings/Logout stay reachable)
 * instead of that page's real content, and exits — so the calling page
 * needs nothing beyond this one call.
 */
function requireApprovedProvider(mysqli $conn, int $providerId, int $badgeCount = 0): void {
    $status = getProviderApprovalStatus($conn, $providerId);
    if ($status === 'active') {
        return;
    }

    if ($status === 'suspended') {
        $title = 'Account Suspended';
        $message = 'Your provider account has been suspended by an admin. Please contact support if you believe this is a mistake.';
        $icon = '⛔';
    } else {
        $title = 'Pending Admin Approval';
        $message = "Your provider application is still being reviewed. You'll be able to create and manage listings once an admin approves your account.";
        $icon = '⏳';
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
        <title><?= htmlspecialchars($title) ?></title>
    </head>
    <body>
        <div class="dashboard-container">
            <?php $provider_pending_claims_badge = $badgeCount; include __DIR__ . '/../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="topbar-container">
                    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
                </div>
                <div class="content-container" style="align-items:center; justify-content:center; min-height:60vh;">
                    <div style="max-width:420px; margin:60px auto; text-align:center; background:#FFFFFF; border:1px solid #E4E4E7; border-radius:12px; padding:40px 32px;">
                        <div style="font-size:40px; margin-bottom:16px;"><?= $icon ?></div>
                        <h2 style="margin-bottom:10px; color:#101828;"><?= htmlspecialchars($title) ?></h2>
                        <p style="color:#667085; font-size:14px; line-height:1.6;"><?= htmlspecialchars($message) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

/**
 * Countdown/elapsed string for a claim's reservation hold, used on the
 * Claim Tracker table ("4m 32s" while pending, "Ended" once past).
 */
function holdCountdownText(?string $expiresAt): string {
    if (!$expiresAt) return '-';
    $diff = strtotime($expiresAt) - time();
    if ($diff <= 0) return 'Ended';
    $m = floor($diff / 60);
    $s = $diff % 60;
    return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
}

/**
 * Lightweight food-category guesser from the listing name, used only
 * for the "Food by category" donut chart on the Impact page. The
 * schema has no dedicated category column (only dietary tags like
 * Halal/Vegan), so this buckets by keyword as a reasonable heuristic
 * rather than leaving the chart empty.
 */
function guessFoodCategory(string $foodName): string {
    $name = strtolower($foodName);
    $buckets = [
        'Rice dishes'        => ['nasi', 'rice', 'briyani', 'biryani'],
        'Noodles'             => ['mee', 'noodle', 'kuey teow', 'mihun', 'pasta', 'spaghetti'],
        'Breads & pastries'   => ['roti', 'bread', 'pastry', 'pastries', 'danish', 'bun', 'toast', 'sandwich'],
    ];
    foreach ($buckets as $label => $keywords) {
        foreach ($keywords as $kw) {
            if (str_contains($name, $kw)) return $label;
        }
    }
    return 'Other';
}

/**
 * mysqli_stmt_execute() (the procedural function) does NOT accept a
 * params array as a second argument — that shortcut only exists on the
 * object-oriented $stmt->execute($params) method (PHP 8.1+). This helper
 * builds the type string and binds a variable-length params array
 * (used by the Claim Tracker's dynamic filters) the old-fashioned way.
 */
function bindAndExecute(mysqli_stmt $stmt, array $params): bool {
    if (empty($params)) {
        return mysqli_stmt_execute($stmt);
    }
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
    }
    $refs = [$stmt, $types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);
    return mysqli_stmt_execute($stmt);
}

/**
 * Builds the shared WHERE clause + params for the Claim Tracker table,
 * used by both claimTracker.php (paginated view) and exportClaims.php
 * (CSV export) so the two never drift out of sync with each other.
 *
 * $filters keys: status, listing_id, qr, q  (all optional/nullable)
 * Returns ['where' => string, 'params' => array]
 */
function buildClaimFilterWhere(int $providerId, array $filters): array {
    $where = "f.provider_id = ?";
    $params = [$providerId];

    $status = $filters['status'] ?? '';
    if (in_array($status, ['pending', 'completed', 'expired', 'cancelled'], true)) {
        if ($status === 'pending') {
            $where .= " AND c.status IN ('pending','confirmed')";
        } else {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }
    }

    $listingId = (int)($filters['listing_id'] ?? 0);
    if ($listingId > 0) {
        $where .= " AND f.listing_id = ?";
        $params[] = $listingId;
    }

    $qr = $filters['qr'] ?? '';
    if ($qr === 'scanned') {
        $where .= " AND c.status = 'completed'";
    } elseif ($qr === 'awaiting') {
        $where .= " AND c.status IN ('pending','confirmed') AND c.reservation_expires_at >= NOW()";
    } elseif ($qr === 'expired') {
        $where .= " AND c.status = 'expired'";
    }

    $q = trim($filters['q'] ?? '');
    if ($q !== '') {
        $where .= " AND (u.user_name LIKE ? OR u.email LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    return ['where' => $where, 'params' => $params];
}

/**
 * Derives the QR/pickup status label + color class shown on the Claim
 * Tracker table, from the claim's own status (no separate token lookup
 * needed — status + confirmed_at already tell the full story).
 */
function claimQrStatus(array $claim): array {
    if ($claim['status'] === 'completed') {
        return ['label' => 'Scanned ' . date('g:i A', strtotime($claim['confirmed_at'])), 'class' => 'qr-scanned'];
    }
    if (in_array($claim['status'], ['pending', 'confirmed'], true)) {
        if (strtotime($claim['reservation_expires_at']) >= time()) {
            return ['label' => 'Awaiting', 'class' => 'qr-awaiting'];
        }
        return ['label' => 'Not scanned', 'class' => 'qr-expired'];
    }
    if ($claim['status'] === 'expired') {
        return ['label' => 'Not scanned', 'class' => 'qr-expired'];
    }
    return ['label' => 'Cancelled', 'class' => 'qr-cancelled'];
}


