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
// Per-browser-session scan counters (reset when the session ends)
if (!isset($_SESSION['scan_stats'])) {
    $_SESSION['scan_stats'] = ['scans' => 0, 'manual' => 0];
}

$result = null; // ['ok' => bool, 'title' => .., 'message' => ..]

/**
 * Try to resolve a scanned/typed value into a pending claim that
 * belongs to one of THIS provider's listings.
 * Accepts either a raw claim token (hashed & matched against
 * claim_tokens) or a plain numeric student ID (matches their most
 * recent pending/confirmed claim on this provider).
 */
function resolveClaim(mysqli $conn, int $providerId, string $input): ?array {
    $input = trim($input);
    if ($input === '') return null;

    // 1) Try as a claim token
    $hash = hash('sha256', $input);
    $stmt = mysqli_prepare($conn, "
        SELECT c.claim_id, c.listing_id, c.student_id, c.portion_claimed, c.status,
               c.reservation_expires_at, ct.token_id, ct.used_at,
               f.food_name, f.provider_id, u.user_name
        FROM claim_tokens ct
        JOIN claim c ON ct.claim_id = c.claim_id
        JOIN food_listing f ON c.listing_id = f.listing_id
        JOIN user u ON c.student_id = u.user_id
        WHERE ct.token_hash = ? AND f.provider_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "si", $hash, $providerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) return $row;

    // 2) Fall back to numeric student ID lookup
    $numericId = preg_replace('/[^0-9]/', '', $input);
    if ($numericId === '') return null;

    $stmt = mysqli_prepare($conn, "
        SELECT c.claim_id, c.listing_id, c.student_id, c.portion_claimed, c.status,
               c.reservation_expires_at, NULL AS token_id, NULL AS used_at,
               f.food_name, f.provider_id, u.user_name
        FROM claim c
        JOIN food_listing f ON c.listing_id = f.listing_id
        JOIN user u ON c.student_id = u.user_id
        WHERE u.user_id = ? AND f.provider_id = ? AND c.status IN ('pending','confirmed')
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "ii", $numericId, $providerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_input'])) {
    $entryMode = ($_POST['entry_mode'] ?? 'manual') === 'scan' ? 'scan' : 'manual';
    $claim = resolveClaim($conn, $providerId, $_POST['claim_input']);

    if (!$claim) {
        $result = ['ok' => false, 'title' => 'Not Found', 'message' => 'No matching reservation was found for this provider.'];
    } elseif ($claim['used_at'] !== null) {
        $result = ['ok' => false, 'title' => 'Already Used', 'message' => 'This claim code has already been redeemed.'];
    } elseif (!in_array($claim['status'], ['pending', 'confirmed'], true)) {
        $result = ['ok' => false, 'title' => 'Not Claimable', 'message' => 'This claim is already ' . $claim['status'] . '.'];
    } elseif (strtotime($claim['reservation_expires_at']) < time()) {
        // Reservation window has passed — mark it expired instead of confirming
        $stmt = mysqli_prepare($conn, "UPDATE claim SET status = 'expired' WHERE claim_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt, "i", $claim['claim_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $result = ['ok' => false, 'title' => 'Reservation Expired', 'message' => htmlspecialchars($claim['user_name']) . "'s reservation window has passed."];
    } else {
        // Valid pickup: walk the claim through pending -> confirmed -> completed
        if ($claim['status'] === 'pending') {
            $stmt = mysqli_prepare($conn, "UPDATE claim SET status = 'confirmed' WHERE claim_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $claim['claim_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $stmt = mysqli_prepare($conn, "UPDATE claim SET status = 'completed', confirmed_at = NOW() WHERE claim_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $claim['claim_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($claim['token_id']) {
            $stmt = mysqli_prepare($conn, "UPDATE claim_tokens SET used_at = NOW() WHERE token_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $claim['token_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Record impact (skip if it already exists for this claim)
        $check = mysqli_prepare($conn, "SELECT impact_id FROM impact_record WHERE claim_id = ?");
        mysqli_stmt_bind_param($check, "i", $claim['claim_id']);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if (!$exists) {
            $portions = (int)$claim['portion_claimed'];
            $co2 = round(0.875 * $portions, 3);
            $water = round(17.5 * $portions, 2);
            $ins = mysqli_prepare($conn, "INSERT INTO impact_record (claim_id, quantity_rescued, co2_saved_kg, water_saved_litre) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "iidd", $claim['claim_id'], $portions, $co2, $water);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
        }

        $_SESSION['scan_stats'][$entryMode === 'scan' ? 'scans' : 'manual']++;

        $result = [
            'ok' => true,
            'title' => 'Pickup Confirmed',
            'message' => htmlspecialchars($claim['user_name']) . ' — ' . htmlspecialchars($claim['food_name']) . ' (x' . (int)$claim['portion_claimed'] . ')',
        ];
    }
}

// Recent validations (last 8 completed claims for this provider)
$stmt = mysqli_prepare($conn, "
    SELECT u.user_id, u.user_name, f.food_name, c.confirmed_at
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    JOIN user u ON c.student_id = u.user_id
    WHERE f.provider_id = ? AND c.status = 'completed'
    ORDER BY c.confirmed_at DESC
    LIMIT 8
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$recentValidations = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$lastScan = $recentValidations[0]['confirmed_at'] ?? null;
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
    <link rel="stylesheet" href="../../assets/css/provider/qrScanner.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <title>QR Scanner</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <h1 class="page-title">QR Scanner</h1>
                <p class="page-subtitle">Scan a student's claim QR code, or enter their code manually, to confirm pickup.</p>

                <?php if ($result): ?>
                    <div class="validation-result-card <?= $result['ok'] ? 'ok' : 'fail' ?>" style="margin-top:20px;">
                        <div class="vr-title"><?= $result['ok'] ? '✔ ' : '✕ ' ?><?= htmlspecialchars($result['title']) ?></div>
                        <div><?= $result['message'] ?></div>
                    </div>
                <?php endif; ?>

                <div class="scanner-grid" style="margin-top:12px;">
                    <div class="scanner-viewport-card">
                        <div id="qr-reader"></div>
                        <div class="scan-status-pill" id="scanStatusPill">
                            <div class="title">Awaiting Scan...</div>
                            <div class="sub">Position a valid student QR code within the frame above</div>
                        </div>
                    </div>

                    <div class="scanner-side-column">
                        <div class="side-card">
                            <h3>Manual Entry</h3>
                            <p class="hint">Enter the student's claim code or ID manually if scanning fails.</p>
                            <form method="POST" action="" class="manual-entry-row">
                                <input type="hidden" name="entry_mode" value="manual">
                                <input type="text" name="claim_input" placeholder="e.g. STD-8924 or claim token" required>
                                <button type="submit">Verify</button>
                            </form>
                        </div>

                        <div class="side-card">
                            <h3>Session Stats</h3>
                            <div class="stat-row"><span>Successful Scans</span><span><?= (int)$_SESSION['scan_stats']['scans'] ?></span></div>
                            <div class="stat-row"><span>Manual Entries</span><span><?= (int)$_SESSION['scan_stats']['manual'] ?></span></div>
                            <div class="stat-row"><span>Last Scan</span><span><?= htmlspecialchars(timeAgoText($lastScan)) ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="recent-table-wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="color:#101828;">Recent Validations</h3>
                        <a href="claimTracker.php" style="font-size:13px;">View All</a>
                    </div>
                    <div class="user-list-container">
                        <table class="user-list-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Item Details</th>
                                    <th>Timestamp</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentValidations)): ?>
                                    <tr><td colspan="4" style="text-align:center; padding:20px;">No validations yet.</td></tr>
                                <?php else: foreach ($recentValidations as $v): ?>
                                    <tr>
                                        <td>ID-<?= $v['user_id'] ?> &bull; <?= htmlspecialchars($v['user_name']) ?></td>
                                        <td><?= htmlspecialchars($v['food_name']) ?></td>
                                        <td><?= date('g:i A', strtotime($v['confirmed_at'])) ?></td>
                                        <td><span class="status-indicator status-active"><span class="dot"></span>Success</span></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Camera-based scanning: on a successful decode, auto-submit the
        // value as if it were entered manually, tagged as entry_mode=scan.
        try {
            const qrRegion = document.getElementById('qr-reader');
            const pill = document.getElementById('scanStatusPill');
            const html5QrCode = new Html5Qrcode("qr-reader");
            let submitted = false;

            function onScanSuccess(decodedText) {
                if (submitted) return;
                submitted = true;
                pill.querySelector('.title').textContent = 'Code detected';
                pill.querySelector('.sub').textContent = 'Verifying claim...';

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML =
                    '<input type="hidden" name="entry_mode" value="scan">' +
                    '<input type="hidden" name="claim_input" value="' + decodedText.replace(/"/g, '&quot;') + '">';
                document.body.appendChild(form);
                form.submit();
            }

            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 220 },
                onScanSuccess
            ).catch(function () {
                pill.querySelector('.title').textContent = 'Camera unavailable';
                pill.querySelector('.sub').textContent = 'Use Manual Entry on the right instead.';
            });
        } catch (e) {
            // html5-qrcode failed to load (e.g. offline) — manual entry still works.
        }
    </script>
</body>
</html>
