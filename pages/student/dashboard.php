<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');

$searchQuery = trim($_GET['query'] ?? '');
$selectedLocation = trim($_GET['location'] ?? '');
$selectedTag = (int)($_GET['tag'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get Available Pickup Locations
|--------------------------------------------------------------------------
*/

$locations = [];

$locationSql = "
    SELECT DISTINCT pickup_location
    FROM food_listing
    WHERE pickup_location IS NOT NULL
      AND pickup_location <> ''
    ORDER BY pickup_location ASC
";

$locationResult = mysqli_query($conn, $locationSql);

if ($locationResult) {

    while ($locationRow = mysqli_fetch_assoc($locationResult)) {

        $locations[] = $locationRow['pickup_location'];

    }

}


/*
|--------------------------------------------------------------------------
| Get Dietary Tags
|--------------------------------------------------------------------------
*/

$tags = [];

$tagSql = "
    SELECT tag_id, tag_name
    FROM food_tags
    ORDER BY tag_name ASC
";

$tagResult = mysqli_query($conn, $tagSql);

if ($tagResult) {
    while ($tagRow = mysqli_fetch_assoc($tagResult)) {
        $tags[] = $tagRow;
    }
}

/*
|--------------------------------------------------------------------------
| Get Selected Tag Name
|--------------------------------------------------------------------------
*/

$selectedTagName = '';

if ($selectedTag > 0) {
    foreach ($tags as $tag) {
        if ((int)$tag['tag_id'] === $selectedTag) {
            $selectedTagName = $tag['tag_name'];
            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Build Food Listing Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        fl.listing_id,
        fl.food_name,
        fl.description,
        fl.total_quantity,
        fl.remain_quantity,
        fl.weight_kg,
        fl.pickup_location,
        fl.image,
        fl.expires_at,
        UNIX_TIMESTAMP(fl.expires_at) AS expires_timestamp,

        GROUP_CONCAT(
            DISTINCT ft.tag_name
            ORDER BY ft.tag_name
            SEPARATOR ', '
        ) AS tags

    FROM food_listing fl

    LEFT JOIN food_listing_tags flt
        ON fl.listing_id = flt.listing_id

    LEFT JOIN food_tags ft
        ON flt.tag_id = ft.tag_id

    WHERE fl.status = 'active'
      AND fl.remain_quantity > 0
      AND fl.expires_at > NOW()
";


$params = [];
$types = '';


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($searchQuery !== '') {

    $sql .= "
        AND (
            fl.food_name LIKE ?
            OR fl.description LIKE ?
        )
    ";

    $searchPattern = '%' . $searchQuery . '%';

    $params[] = $searchPattern;
    $params[] = $searchPattern;

    $types .= 'ss';

}


/*
|--------------------------------------------------------------------------
| Location Filter
|--------------------------------------------------------------------------
*/

if ($selectedLocation !== '') {

    $sql .= "
        AND fl.pickup_location = ?
    ";

    $params[] = $selectedLocation;

    $types .= 's';

}


/*
|--------------------------------------------------------------------------
| Dietary Tag Filter
|--------------------------------------------------------------------------
*/

if ($selectedTag > 0) {

    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM food_listing_tags filter_flt
            WHERE filter_flt.listing_id = fl.listing_id
              AND filter_flt.tag_id = ?
        )
    ";

    $params[] = $selectedTag;

    $types .= 'i';

}


/*
|--------------------------------------------------------------------------
| Group and Sort
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        fl.listing_id,
        fl.food_name,
        fl.description,
        fl.total_quantity,
        fl.remain_quantity,
        fl.weight_kg,
        fl.pickup_location,
        fl.image,
        fl.expires_at

    ORDER BY
        fl.expires_at ASC,
        fl.listing_id DESC
";


/*
|--------------------------------------------------------------------------
| Prepare Query
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {

    die(
        'Database error: ' .
        htmlspecialchars(mysqli_error($conn))
    );

}


/*
|--------------------------------------------------------------------------
| Bind Parameters
|--------------------------------------------------------------------------
*/

if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );

}


/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


/*
|--------------------------------------------------------------------------
| CO2 Display Estimate
|--------------------------------------------------------------------------
*/

$displayCo2PerKg = CO2_EMISSION_FACTOR;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/student/dashboard.css">
    <title>Browse Food | Campus Food Rescue</title>
</head>
<body>
<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar-container">
            <?php include '../../includes/topbar.php'; ?>
        </div>

        <div class="content-container">
            <div class="page-heading-row">
                <div>
                    <h1 class="page-title">Available Food</h1>
                    <p class="page-subtitle">Help reduce waste by claiming perfectly good food on campus.</p>
                </div>
            </div>

            <form
                action="dashboard.php"
                method="GET"
                class="food-filter-bar"
            >

                <!-- Search -->

                <div class="search-input-wrap">

                    <span class="search-symbol">
                        ⌕
                    </span>

                    <input
                        type="search"
                        name="query"
                        value="<?= htmlspecialchars($searchQuery) ?>"
                        placeholder="Search for food..."
                        aria-label="Search for food"
                    >

                </div>


                <!-- Location -->

                <select
                    name="location"
                    aria-label="Filter by pickup location"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        All Locations
                    </option>

                    <?php foreach ($locations as $location): ?>

                        <option
                            value="<?= htmlspecialchars($location) ?>"
                            <?= $selectedLocation === $location ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars($location) ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- Dietary -->

                <select
                    name="tag"
                    aria-label="Filter by dietary tag"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        All Dietary
                    </option>

                    <?php foreach ($tags as $tag): ?>

                        <option
                            value="<?= (int)$tag['tag_id'] ?>"
                            <?= $selectedTag === (int)$tag['tag_id'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars($tag['tag_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- Search Button -->

                <button
                    type="submit"
                    class="primary-button"
                >
                    Search
                </button>


                <!-- Clear -->

                <?php if (
                    $searchQuery !== '' ||
                    $selectedLocation !== '' ||
                    $selectedTag > 0
                ): ?>

                    <a
                        href="dashboard.php"
                        class="clear-filter"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </form>

            <div class="active-filter-row">

                <span class="filter-label">
                    Filters:
                </span>


                <?php if ($selectedLocation !== ''): ?>

                    <span class="filter-chip active">
                        📍 <?= htmlspecialchars($selectedLocation) ?>
                    </span>

                <?php endif; ?>


                <?php if ($selectedTagName !== ''): ?>

                    <span class="filter-chip active">
                        <?= htmlspecialchars($selectedTagName) ?>
                    </span>

                <?php endif; ?>


                <?php if (
                    $selectedLocation === '' &&
                    $selectedTagName === '' &&
                    $searchQuery === ''
                ): ?>

                    <span class="filter-chip active">
                        All Food
                    </span>

                <?php endif; ?>

            </div>

            <div class="food-listings-container">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $imagePath = !empty($row['image'])
                            ? '../../uploads/food/' . htmlspecialchars($row['image'])
                            : '../../assets/images/logo.png';

                        $weightKg = (float) ($row['weight_kg'] ?? 0);
                        $totalQuantity = max(1, (int) $row['total_quantity']);
                        $remainingFoodKg = $weightKg * ((int) $row['remain_quantity'] / $totalQuantity);
                        $estimatedCo2 = $remainingFoodKg * $displayCo2PerKg;
                        $tagList = $row['tags'] ? array_filter(array_map('trim', explode(',', $row['tags']))) : [];
                        ?>

                        <article class="food-listing-card">
                            <div class="food-image-container">
                                <img
                                    src="<?= htmlspecialchars($imagePath) ?>"
                                    alt="<?= htmlspecialchars($row['food_name']) ?>"
                                    class="food-image"
                                    onerror="this.onerror=null; this.src='../../assets/images/logo.png';"
                                >

                                <div class="urgency-badge" data-expire="<?= (int) $row['expires_timestamp'] ?>">
                                    <span class="urgency-dot"></span>
                                    <span class="countdown">Loading...</span>
                                </div>

                                <div class="co2-badge">
                                    CO₂ <?= number_format($estimatedCo2, 2) ?> kg
                                </div>
                            </div>

                            <div class="food-card-body">
                                <div class="food-card-title-row">
                                    <h2><?= htmlspecialchars($row['food_name']) ?></h2>
                                    <?php if ($tagList): ?>
                                        <div class="food-tags">
                                            <?php foreach (array_slice($tagList, 0, 2) as $tagName): ?>
                                                <span class="food-tag"><?= htmlspecialchars($tagName) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <p class="food-description">
                                    <?= htmlspecialchars($row['description'] ?? 'Surplus food available for collection.') ?>
                                </p>

                                <div class="food-meta">
                                    <span>⌖ <?= htmlspecialchars($row['pickup_location']) ?></span>
                                    <span>◉ <?= htmlspecialchars($row['remain_quantity']) ?> portions</span>
                                </div>

                                <a
                                    href="claimfood.php?listing_id=<?= urlencode($row['listing_id']) ?>"
                                    class="claim-button"
                                >
                                    Claim
                                </a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">⌂</div>
                        <h2>No food listings found</h2>
                        <p>Try another search or clear your filters.</p>
                        <a href="dashboard.php" class="primary-button">View All Food</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function updateCountdowns() {
    document.querySelectorAll('.urgency-badge').forEach(badge => {
        const countdown = badge.querySelector('.countdown');
        const dot = badge.querySelector('.urgency-dot');
        const expire = Number(badge.dataset.expire) * 1000;
        const diff = expire - Date.now();

        if (diff <= 0) {
            countdown.textContent = 'Expired';
            badge.classList.add('expired');
            return;
        }

        const totalMinutes = Math.floor(diff / 60000);
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;

        countdown.textContent = hours > 0
            ? `${hours}h ${minutes}m left`
            : `${minutes}m left`;

        badge.classList.remove('urgent', 'warning', 'safe');
        if (totalMinutes <= 10) {
            badge.classList.add('urgent');
        } else if (totalMinutes <= 30) {
            badge.classList.add('warning');
        } else {
            badge.classList.add('safe');
        }
    });
}

updateCountdowns();
setInterval(updateCountdowns, 1000);
</script>
</body>
</html>
