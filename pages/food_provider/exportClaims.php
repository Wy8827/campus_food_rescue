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
    die("No provider profile is linked to this account yet.");
}


// Gate: block all provider functionality until an admin approves the account
requireApprovedProvider($conn, $providerId);
// Same filters as claimTracker.php, no pagination — export everything matching
$filters = [
    'status'     => $_GET['status'] ?? '',
    'listing_id' => $_GET['listing_id'] ?? '',
    'qr'         => $_GET['qr'] ?? '',
    'q'          => trim($_GET['q'] ?? ''),
];
$built = buildClaimFilterWhere($providerId, $filters);

$sql = "
    SELECT c.claim_id, c.portion_claimed, c.created_at, c.reservation_expires_at,
           c.confirmed_at, c.status,
           u.user_name, u.email,
           f.food_name, f.pickup_location
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    JOIN user u ON c.student_id = u.user_id
    WHERE {$built['where']}
    ORDER BY c.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
bindAndExecute($stmt, $built['params']);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$filename = 'claims_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Claim ID', 'Student', 'Email', 'Listing', 'Pickup Location', 'Quantity', 'Claimed At', 'Hold Expires', 'Confirmed At', 'Status', 'QR Status']);

foreach ($rows as $r) {
    $qr = claimQrStatus($r);
    fputcsv($out, [
        $r['claim_id'],
        $r['user_name'],
        $r['email'],
        $r['food_name'],
        $r['pickup_location'],
        $r['portion_claimed'],
        $r['created_at'],
        $r['reservation_expires_at'],
        $r['confirmed_at'] ?? '',
        ucfirst($r['status']),
        $qr['label'],
    ]);
}
fclose($out);
exit();
