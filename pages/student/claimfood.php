<?php
session_start();

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';

requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$listingId = (int) ($_GET['listing_id'] ?? 0);
$error = '';

if ($listingId <= 0) {
    header('Location: dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Process Claim
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $listingId = (int) ($_POST['listing_id'] ?? 0);
    $portion = (int) ($_POST['portion'] ?? 1);

    if ($portion < 1) {
        $portion = 1;
    }

    mysqli_begin_transaction($conn);

    try {

        /*
        |--------------------------------------------------------------------------
        | Lock food listing
        |--------------------------------------------------------------------------
        */

        $listingStmt = mysqli_prepare(
            $conn,
            "SELECT
                listing_id,
                remain_quantity,
                status,
                expires_at
             FROM food_listing
             WHERE listing_id = ?
             FOR UPDATE"
        );

        if (!$listingStmt) {
            throw new Exception('Unable to check food listing.');
        }

        mysqli_stmt_bind_param(
            $listingStmt,
            'i',
            $listingId
        );

        mysqli_stmt_execute($listingStmt);

        $listingResult = mysqli_stmt_get_result($listingStmt);
        $listingData = mysqli_fetch_assoc($listingResult);

        mysqli_stmt_close($listingStmt);

        if (!$listingData) {
            throw new Exception(
                'This food listing no longer exists.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check listing status
        |--------------------------------------------------------------------------
        */

        if ($listingData['status'] !== 'active') {
            throw new Exception(
                'This food listing is no longer available.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check expiry
        |--------------------------------------------------------------------------
        */

        if (strtotime($listingData['expires_at']) <= time()) {
            throw new Exception(
                'This food listing has expired.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check quantity
        |--------------------------------------------------------------------------
        */

        $remaining = (int) $listingData['remain_quantity'];

        if ($remaining < $portion) {
            throw new Exception(
                'There are not enough portions remaining.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate active claim
        |--------------------------------------------------------------------------
        */

        $duplicateStmt = mysqli_prepare(
            $conn,
            "SELECT claim_id
             FROM claim
             WHERE listing_id = ?
             AND student_id = ?
             AND status IN ('pending', 'confirmed')
             LIMIT 1"
        );

        if (!$duplicateStmt) {
            throw new Exception(
                'Unable to check existing claims.'
            );
        }

        mysqli_stmt_bind_param(
            $duplicateStmt,
            'ii',
            $listingId,
            $studentId
        );

        mysqli_stmt_execute($duplicateStmt);

        $duplicateResult = mysqli_stmt_get_result(
            $duplicateStmt
        );

        $duplicate = mysqli_fetch_assoc(
            $duplicateResult
        );

        mysqli_stmt_close($duplicateStmt);

        if ($duplicate) {
            throw new Exception(
                'You already have an active claim for this food.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create 10-minute reservation
        |--------------------------------------------------------------------------
        */

        $reservationExpires = date(
            'Y-m-d H:i:s',
            time() + 600
        );

        /*
        |--------------------------------------------------------------------------
        | Create claim
        |--------------------------------------------------------------------------
        */

        $claimStmt = mysqli_prepare(
            $conn,
            "INSERT INTO claim
            (
                listing_id,
                student_id,
                portion_claimed,
                reservation_expires_at,
                status
            )
            VALUES (?, ?, ?, ?, 'pending')"
        );

        if (!$claimStmt) {
            throw new Exception(
                'Unable to create claim.'
            );
        }

        mysqli_stmt_bind_param(
            $claimStmt,
            'iiis',
            $listingId,
            $studentId,
            $portion,
            $reservationExpires
        );

        if (!mysqli_stmt_execute($claimStmt)) {
            throw new Exception(
                'Unable to save claim.'
            );
        }

        $claimId = mysqli_insert_id($conn);

        mysqli_stmt_close($claimStmt);

        /*
        |--------------------------------------------------------------------------
        | Generate 6-character hexadecimal token
        |--------------------------------------------------------------------------
        | Using: strtoupper(bin2hex(random_bytes(3)))
        | Example outputs: A8F9D2, 7C2E1F, B3D6E1
        */

        $rawToken = strtoupper(bin2hex(random_bytes(3)));

        /*
        |--------------------------------------------------------------------------
        | Hash the token before storing
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash('sha256', $rawToken);

        /*
        |--------------------------------------------------------------------------
        | Store ONLY the SHA-256 hash
        |--------------------------------------------------------------------------
        */

        $tokenStmt = mysqli_prepare(
            $conn,
            "INSERT INTO claim_tokens
            (
                claim_id,
                token_hash,
                expires_at
            )
            VALUES (?, ?, ?)"
        );

        if (!$tokenStmt) {
            throw new Exception(
                'Unable to prepare claim token.'
            );
        }

        mysqli_stmt_bind_param(
            $tokenStmt,
            'iss',
            $claimId,
            $tokenHash,
            $reservationExpires
        );

        if (!mysqli_stmt_execute($tokenStmt)) {
            throw new Exception(
                'Unable to save claim token.'
            );
        }

        mysqli_stmt_close($tokenStmt);

        /*
        |--------------------------------------------------------------------------
        | Reduce remaining food
        |--------------------------------------------------------------------------
        */

        $newRemain = $remaining - $portion;

        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE food_listing
             SET remain_quantity = ?
             WHERE listing_id = ?"
        );

        if (!$updateStmt) {
            throw new Exception(
                'Unable to update food quantity.'
            );
        }

        mysqli_stmt_bind_param(
            $updateStmt,
            'ii',
            $newRemain,
            $listingId
        );

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception(
                'Unable to update food quantity.'
            );
        }

        mysqli_stmt_close($updateStmt);

        /*
        |--------------------------------------------------------------------------
        | Commit everything
        |--------------------------------------------------------------------------
        */

        mysqli_commit($conn);

        /*
        |--------------------------------------------------------------------------
        | Temporarily store RAW token in session
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Database contains only the hash.
        | The raw token is needed to create the student's QR.
        |
        */

        if (!isset($_SESSION['claim_tokens'])) {
            $_SESSION['claim_tokens'] = [];
        }

        $_SESSION['claim_tokens'][$claimId] = $rawToken;

        /*
        |--------------------------------------------------------------------------
        | Redirect to claims
        |--------------------------------------------------------------------------
        */

        header('Location: claims.php?claimed=1');
        exit;

    } catch (Throwable $e) {

        mysqli_rollback($conn);

        $error = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Get Food Listing
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM food_listing
     WHERE listing_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $listingId
);

mysqli_stmt_execute($stmt);

$listing = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);

if (!$listing) {
    header('Location: dashboard.php');
    exit;
}

$imagePath = !empty($listing['image'])
    ? '../../' . ltrim($listing['image'], '/')
    : '../../assets/images/logo.png';
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
        href="../../assets/css/student/dashboard.css"
    >

    <title>
        Claim Food | Campus Food Rescue
    </title>

</head>

<body>

<div class="dashboard-container">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="topbar-container">

            <?php include '../../includes/topbar.php'; ?>

        </div>

        <div class="content-container">

            <a
                href="dashboard.php"
                class="back-link"
            >
                ← Back to Browse Food
            </a>

            <div class="claim-detail-page">

                <div class="claim-detail-image">

                    <img
                        src="<?= htmlspecialchars($imagePath) ?>"
                        alt="<?= htmlspecialchars($listing['food_name']) ?>"
                        onerror="this.onerror=null; this.src='../../assets/images/logo.png';"
                    >

                </div>

                <div class="claim-detail-copy">

                    <span class="eyebrow">
                        Food Rescue
                    </span>

                    <h1>
                        <?= htmlspecialchars($listing['food_name']) ?>
                    </h1>

                    <p>
                        <?= htmlspecialchars(
                            $listing['description'] ?? ''
                        ) ?>
                    </p>

                    <div class="detail-grid">

                        <div>
                            <span>Pickup</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $listing['pickup_location']
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Available</span>

                            <strong>
                                <?= (int) $listing['remain_quantity'] ?>
                                portions
                            </strong>
                        </div>

                        <div>
                            <span>Weight</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $listing['weight_kg']
                                ) ?>
                                kg
                            </strong>
                        </div>

                        <div>
                            <span>Expires</span>

                            <strong>
                                <?= htmlspecialchars(
                                    date(
                                        'd M Y, g:i A',
                                        strtotime(
                                            $listing['expires_at']
                                        )
                                    )
                                ) ?>
                            </strong>
                        </div>

                    </div>

                    <?php if ($error): ?>

                        <div class="form-error">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>

                    <?php if (
                        $listing['status'] === 'active' &&
                        strtotime($listing['expires_at']) > time() &&
                        (int) $listing['remain_quantity'] > 0
                    ): ?>

                        <form
                            method="POST"
                            class="claim-form"
                        >

                            <input
                                type="hidden"
                                name="listing_id"
                                value="<?= (int) $listing['listing_id'] ?>"
                            >

                            <label for="portion">
                                Portions to claim
                            </label>

                            <select
                                id="portion"
                                name="portion"
                            >

                                <?php
                                $maxPortions = min(
                                    5,
                                    (int) $listing['remain_quantity']
                                );
                                ?>

                                <?php for (
                                    $i = 1;
                                    $i <= $maxPortions;
                                    $i++
                                ): ?>

                                    <option value="<?= $i ?>">
                                        <?= $i ?>
                                    </option>

                                <?php endfor; ?>

                            </select>

                            <button
                                type="submit"
                                class="claim-button"
                            >
                                Confirm Claim
                            </button>

                            <small>
                                Your reservation is held for
                                10 minutes after claiming.
                            </small>

                        </form>

                    <?php else: ?>

                        <div class="form-error">
                            This listing is no longer available.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>