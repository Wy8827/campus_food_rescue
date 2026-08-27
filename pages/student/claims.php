<?php
session_start();

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Get Claims
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.claim_id,
        c.listing_id,
        c.portion_claimed,
        c.created_at,
        c.reservation_expires_at,
        c.confirmed_at,
        c.status,

        fl.food_name,
        fl.pickup_location,
        fl.image,
        fl.expires_at,
        fl.weight_kg,

        ir.co2_saved_kg,
        ir.water_saved_litre

    FROM claim c

    INNER JOIN food_listing fl
        ON fl.listing_id = c.listing_id

    LEFT JOIN impact_record ir
        ON ir.claim_id = c.claim_id

    WHERE c.student_id = ?

    ORDER BY c.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $studentId
);

mysqli_stmt_execute($stmt);

$claimsResult = mysqli_stmt_get_result($stmt);

$activeClaim = null;
$history = [];

while ($row = mysqli_fetch_assoc($claimsResult)) {

    if (
        $activeClaim === null &&
        in_array(
            $row['status'],
            ['pending', 'confirmed'],
            true
        )
    ) {

        $activeClaim = $row;

    } else {

        $history[] = $row;
    }
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Get Raw Token
|--------------------------------------------------------------------------
|
| The database stores only the SHA-256 hash.
| The raw token is temporarily kept in the student's session.
|
*/

$activeToken = null;

if ($activeClaim) {

    $claimId = (int) $activeClaim['claim_id'];

    $activeToken =
        $_SESSION['claim_tokens'][$claimId]
        ?? null;
}

/*
|--------------------------------------------------------------------------
| Status Helpers
|--------------------------------------------------------------------------
*/

function claimStatusLabel(string $status): string
{
    return ucfirst(
        str_replace('_', ' ', $status)
    );
}

function claimStatusClass(string $status): string
{
    return match ($status) {

        'completed' =>
            'status-completed',

        'confirmed' =>
            'status-confirmed',

        'cancelled' =>
            'status-cancelled',

        'expired' =>
            'status-expired',

        default =>
            'status-pending',
    };
}
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/global.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/sidebar.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/topbar.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/student/claims.css"
    >

    <!-- QR Code Library -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
    ></script>

    <title>
        My Claims | Campus Food Rescue
    </title>

</head>

<body>

<div class="dashboard-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="topbar-container">

            <?php include '../../includes/topbar.php'; ?>

        </div>

        <div class="content-container claims-page">

            <h1 class="page-title">
                My Claims
            </h1>

            <p class="page-subtitle">
                Manage your active pickups and view your collection history.
            </p>

            <?php if ($activeClaim): ?>

                <?php

                $claimImage = !empty($activeClaim['image'])
                    ? '../../' . ltrim(
                        $activeClaim['image'],
                        '/'
                    )
                    : '../../assets/images/logo.png';

                ?>

                <section class="active-claim-grid">

                    <!-- QR -->
                    <div class="qr-card">

                        <?php if ($activeToken): ?>

                            <div
                                id="claimQRCode"
                                class="qr-placeholder"
                            ></div>

                            <div class="claim-token-display">
                                <?= htmlspecialchars($activeToken) ?>
                            </div>

                            <p class="qr-caption">
                                Show this QR code to the food provider
                                when collecting your food.
                            </p>

                        <?php else: ?>

                            <div class="qr-placeholder">

                                <div class="qr-grid"></div>

                                <span>
                                    QR UNAVAILABLE
                                </span>

                            </div>

                            <p class="qr-caption">
                                This claim was created earlier and the
                                temporary token is no longer available.
                            </p>

                        <?php endif; ?>

                    </div>

                    <!-- Claim -->
                    <div class="active-claim-card">

                        <div class="claim-status-line">

                            <span
                                class="status-pill <?= claimStatusClass(
                                    $activeClaim['status']
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    claimStatusLabel(
                                        $activeClaim['status']
                                    )
                                ) ?>
                            </span>

                            <span class="claim-id">
                                Claim #<?= (int) $activeClaim['claim_id'] ?>
                            </span>

                        </div>

                        <div class="active-food-row">

                            <img
                                src="<?= htmlspecialchars($claimImage) ?>"
                                alt="<?= htmlspecialchars(
                                    $activeClaim['food_name']
                                ) ?>"
                            >

                            <div>

                                <h2>
                                    <?= htmlspecialchars(
                                        $activeClaim['food_name']
                                    ) ?>
                                </h2>

                                <p>

                                    <?= (int) $activeClaim['portion_claimed'] ?>

                                    portion<?= (
                                        (int) $activeClaim['portion_claimed']
                                        === 1
                                    ) ? '' : 's' ?>

                                </p>

                            </div>

                        </div>

                        <div class="claim-details">

                            <div>

                                <span>
                                    Pickup location
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        $activeClaim['pickup_location']
                                    ) ?>
                                </strong>

                            </div>

                            <div>

                                <span>
                                    Claimed
                                </span>

                                <strong>
                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y, g:i A',
                                            strtotime(
                                                $activeClaim['created_at']
                                            )
                                        )
                                    ) ?>
                                </strong>

                            </div>

                            <div>

                                <span>
                                    Reservation expires
                                </span>

                                <strong
                                    class="reservation-countdown"
                                    data-expire="<?= htmlspecialchars(
                                        $activeClaim[
                                            'reservation_expires_at'
                                        ]
                                    ) ?>"
                                >
                                    Loading...
                                </strong>

                            </div>

                        </div>

                    </div>

                    <!-- Impact -->
                    <div class="impact-mini-card">

                        <span>
                            Your impact
                        </span>

                        <strong>
                            <?= number_format(
                                (float) (
                                    $activeClaim['co2_saved_kg']
                                    ?? 0
                                ),
                                2
                            ) ?>
                            kg
                        </strong>

                        <small>
                            CO₂ saved from completed claims
                        </small>

                    </div>

                </section>

            <?php else: ?>

                <section class="no-active-claim">

                    <div class="empty-icon">
                        ✓
                    </div>

                    <h2>
                        No active claims
                    </h2>

                    <p>
                        Claim surplus food from the Browse Food page
                        to see your pickup here.
                    </p>

                    <a
                        href="dashboard.php"
                        class="primary-button"
                    >
                        Browse Food
                    </a>

                </section>

            <?php endif; ?>

            <!-- History -->

            <section class="history-section">

                <div class="section-heading">

                    <h2>
                        Claim History
                    </h2>

                    <span>
                        <?= count($history) ?> records
                    </span>

                </div>

                <div class="history-table-wrap">

                    <table class="history-table">

                        <thead>

                            <tr>

                                <th>Item</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Status</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if ($history): ?>

                            <?php foreach ($history as $row): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['food_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $row['pickup_location']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $row['created_at']
                                                )
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) $row['portion_claimed'] ?>
                                    </td>

                                    <td>

                                        <span
                                            class="status-pill <?= claimStatusClass(
                                                $row['status']
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                claimStatusLabel(
                                                    $row['status']
                                                )
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="table-empty"
                                >
                                    No previous claims yet.
                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </div>

    </main>

</div>

<script>

/*
|--------------------------------------------------------------------------
| Generate QR Code
|--------------------------------------------------------------------------
*/

<?php if ($activeToken): ?>

new QRCode(
    document.getElementById("claimQRCode"),
    {
        text: <?= json_encode($activeToken) ?>,
        width: 220,
        height: 220,
        correctLevel: QRCode.CorrectLevel.M
    }
);

<?php endif; ?>


/*
|--------------------------------------------------------------------------
| Reservation Countdown
|--------------------------------------------------------------------------
*/

function updateReservationCountdown() {

    const element =
        document.querySelector(
            '.reservation-countdown'
        );

    if (!element) {
        return;
    }

    const expire =
        new Date(
            element.dataset.expire.replace(
                ' ',
                'T'
            )
        ).getTime();

    const diff =
        expire - Date.now();

    if (diff <= 0) {

        element.textContent =
            'Expired';

        return;
    }

    const minutes =
        Math.floor(
            diff / 60000
        );

    const seconds =
        Math.floor(
            (diff % 60000) / 1000
        );

    element.textContent =
        `${minutes}m ${String(seconds).padStart(2, '0')}s`;
}

updateReservationCountdown();

setInterval(
    updateReservationCountdown,
    1000
);

</script>

</body>

</html>