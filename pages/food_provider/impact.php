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
$period = $_GET['period'] ?? 'month';
if (!in_array($period, ['week', 'month', 'year', 'all'], true)) {
    $period = 'month';
}

// Build the WHERE fragment for the selected period.
switch ($period) {
    case 'week':
        $periodWhere = "c.confirmed_at >= CURDATE() - INTERVAL 7 DAY";
        break;
    case 'year':
        $periodWhere = "YEAR(c.confirmed_at) = YEAR(CURDATE())";
        break;
    case 'all':
        $periodWhere = "1=1";
        break;
    case 'month':
    default:
        $periodWhere = "MONTH(c.confirmed_at) = MONTH(CURDATE()) AND YEAR(c.confirmed_at) = YEAR(CURDATE())";
        break;
}

// =========================================================
// Hero cards
// =========================================================
function fetchPeriodTotals(mysqli $conn, int $providerId, string $whereFrag): array {
    $stmt = mysqli_prepare($conn, "
        SELECT
            COALESCE(SUM((c.portion_claimed / f.total_quantity) * f.weight_kg), 0) AS weight_kg,
            COALESCE(SUM(c.portion_claimed), 0) AS meals,
            COUNT(DISTINCT f.listing_id) AS listings
        FROM claim c
        JOIN food_listing f ON c.listing_id = f.listing_id
        WHERE f.provider_id = ? AND c.status = 'completed' AND $whereFrag
    ");
    mysqli_stmt_bind_param($stmt, "i", $providerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "
        SELECT COALESCE(SUM(ir.co2_saved_kg), 0) AS co2, COALESCE(SUM(ir.water_saved_litre), 0) AS water
        FROM impact_record ir
        JOIN claim c ON ir.claim_id = c.claim_id
        JOIN food_listing f ON c.listing_id = f.listing_id
        WHERE f.provider_id = ? AND c.status = 'completed' AND $whereFrag
    ");
    mysqli_stmt_bind_param($stmt, "i", $providerId);
    mysqli_stmt_execute($stmt);
    $co2Row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return [
        'weight_kg' => round((float)$row['weight_kg'], 1),
        'meals'     => (int)$row['meals'],
        'listings'  => (int)$row['listings'],
        'co2'       => round((float)$co2Row['co2'], 1),
        'water'     => round((float)$co2Row['water'], 0),
    ];
}

$current = fetchPeriodTotals($conn, $providerId, $periodWhere);

// =========================================================
// Bar chart — food rescued per week, last 8 weeks
// =========================================================
$stmt = mysqli_prepare($conn, "
    SELECT YEARWEEK(c.confirmed_at, 1) AS yw,
           MIN(DATE(c.confirmed_at)) AS sample_date,
           COALESCE(SUM((c.portion_claimed / f.total_quantity) * f.weight_kg), 0) AS weight_kg
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed'
      AND c.confirmed_at >= CURDATE() - INTERVAL 8 WEEK
    GROUP BY yw
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$weeklyRaw = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Index results by ISO year-week (e.g. "2026-33") so they're easy to
// match against a generated 8-week timeline below.
$weeklyByKey = [];
foreach ($weeklyRaw as $w) {
    $key = date('o-W', strtotime($w['sample_date']));
    $weeklyByKey[$key] = round((float)$w['weight_kg'], 1);
}

// Build a full 8-week timeline (including zero weeks) so the chart
// always has 8 bars, not just the weeks that happen to have data.
$barData = [];
for ($i = 7; $i >= 0; $i--) {
    $mondayOfWeek = date('Y-m-d', strtotime("monday this week -$i week"));
    $key = date('o-W', strtotime($mondayOfWeek));
    $barData[] = [
        'label' => date('M j', strtotime($mondayOfWeek)),
        'value' => $weeklyByKey[$key] ?? 0.0,
    ];
}
$maxBar = max(array_column($barData, 'value')) ?: 1;

// =========================================================
// Donut — food by category (heuristic bucket on food_name)
// =========================================================
$stmt = mysqli_prepare($conn, "
    SELECT f.food_name, c.portion_claimed
    FROM claim c
    JOIN food_listing f ON c.listing_id = f.listing_id
    WHERE f.provider_id = ? AND c.status = 'completed' AND $periodWhere
");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$categoryRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$categoryTotals = [];
foreach ($categoryRows as $row) {
    $cat = guessFoodCategory($row['food_name']);
    $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0) + (int)$row['portion_claimed'];
}
arsort($categoryTotals);
$categoryTotalAll = array_sum($categoryTotals) ?: 1;
$categoryColors = ['#3b711a', '#6dab3c', '#a3d977', '#d1e8bd'];

// =========================================================
// Milestones (lifetime, not period-scoped)
// =========================================================
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM food_listing WHERE provider_id = ?");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$listingCountAllTime = (int)mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
mysqli_stmt_close($stmt);

$allTime = fetchPeriodTotals($conn, $providerId, "1=1");

// 30-day posting streak: longest run of consecutive calendar days with
// at least one listing created, ending at the most recent posting day.
$stmt = mysqli_prepare($conn, "SELECT DISTINCT DATE(created_at) AS d FROM food_listing WHERE provider_id = ? ORDER BY d ASC");
mysqli_stmt_bind_param($stmt, "i", $providerId);
mysqli_stmt_execute($stmt);
$postDates = array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'd');
mysqli_stmt_close($stmt);

$longestStreak = 0;
$currentStreak = 0;
$prevDate = null;
foreach ($postDates as $d) {
    if ($prevDate !== null && (strtotime($d) - strtotime($prevDate)) === 86400) {
        $currentStreak++;
    } else {
        $currentStreak = 1;
    }
    $longestStreak = max($longestStreak, $currentStreak);
    $prevDate = $d;
}

$milestones = [
    ['icon' => '🌱', 'title' => 'First rescue', 'achieved' => $listingCountAllTime >= 1,
        'sub' => $listingCountAllTime >= 1 ? 'Posted your first listing' : 'Post your first listing'],
    ['icon' => '🔥', 'title' => '50 meals rescued', 'achieved' => $allTime['meals'] >= 50,
        'sub' => $allTime['meals'] >= 50 ? 'Helped ' . $allTime['meals'] . ' students' : (50 - $allTime['meals']) . ' meals to go'],
    ['icon' => '⭐', 'title' => '100 kg saved', 'achieved' => $allTime['weight_kg'] >= 100,
        'sub' => $allTime['weight_kg'] >= 100 ? 'Major CO<sub>2</sub> impact' : round(100 - $allTime['weight_kg'], 1) . ' kg to go'],
    ['icon' => '🏆', 'title' => '200 meals rescued', 'achieved' => $allTime['meals'] >= 200,
        'sub' => $allTime['meals'] >= 200 ? 'Helped ' . $allTime['meals'] . ' students' : (200 - $allTime['meals']) . ' meals to go'],
    ['icon' => '💎', 'title' => '30-day streak', 'achieved' => $longestStreak >= 30,
        'sub' => $longestStreak >= 30 ? 'Posted daily for 30+ days' : 'Post every day for 30 days (' . $longestStreak . ' so far)'],
    ['icon' => '🌍', 'title' => '500 kg CO<sub>2</sub> saved', 'achieved' => $allTime['co2'] >= 500,
        'sub' => $allTime['co2'] >= 500 ? 'Huge environmental impact' : round(500 - $allTime['co2'], 1) . ' kg more to go'],
];

$periodLabels = ['week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'all' => 'All time'];
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
    <link rel="stylesheet" href="../../assets/css/provider/impact.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>My Impact</title>
</head>
<body>
    <div class="dashboard-container">
        <?php $provider_pending_claims_badge = getPendingClaimsCount($conn, $providerId); include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <h1 class="page-title">My Impact</h1>
                <p class="page-subtitle">Track how much food you've rescued and the environmental difference you've made.</p>

                <div class="im-period-tabs">
                    <?php foreach ($periodLabels as $key => $label): ?>
                        <a href="?period=<?= $key ?>" class="im-ptab <?= $period === $key ? 'active' : '' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <div class="im-hero">
                    <div class="im-hero-card im-green">
                        <div class="im-hero-icon im-white"><ion-icon name="cube-outline"></ion-icon></div>
                        <div class="im-hero-num"><?= $current['weight_kg'] ?> kg</div>
                        <div class="im-hero-label">Total food rescued</div>
                        <div class="im-hero-divider"></div>
                        <div class="im-hero-substat">
                            <span class="im-hero-substat-num"><?= $current['meals'] ?></span>
                            <span class="im-hero-substat-label">meals rescued across <?= $current['listings'] ?> listing<?= $current['listings'] === 1 ? '' : 's' ?></span>
                        </div>
                    </div>
                    <div class="im-hero-card im-light">
                        <div class="im-hero-icon im-green-bg"><ion-icon name="leaf-outline"></ion-icon></div>
                        <div class="im-hero-num im-dark"><?= $current['co2'] ?> kg</div>
                        <div class="im-hero-label im-dark">CO<sub>2</sub> emissions avoided</div>
                        <div class="im-hero-sub im-dark">From <?= $current['meals'] ?> meals rescued</div>
                    </div>
                    <div class="im-hero-card im-light">
                        <div class="im-hero-icon im-green-bg"><ion-icon name="water-outline"></ion-icon></div>
                        <div class="im-hero-num im-dark"><?= $current['water'] ?> L</div>
                        <div class="im-hero-label im-dark">Water saved</div>
                        <div class="im-hero-sub im-dark">From <?= $current['meals'] ?> meals rescued</div>
                    </div>
                </div>

                <div class="im-charts-row">
                    <div class="im-card">
                        <div class="im-card-title">Food rescued per week (kg)</div>
                        <div class="im-card-sub">Last 8 weeks</div>
                        <div class="im-bar-chart">
                            <?php foreach ($barData as $bar): ?>
                                <div class="im-bar-group">
                                    <div class="im-bar-val"><?= $bar['value'] ?></div>
                                    <div class="im-bar" style="height:<?= max(4, round(($bar['value'] / $maxBar) * 100)) ?>%"></div>
                                    <div class="im-bar-label"><?= $bar['label'] ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="im-card">
                        <div class="im-card-title">Food by category</div>
                        <div class="im-card-sub"><?= $periodLabels[$period] ?></div>
                        <div class="im-donut-wrap">
                            <?php if (empty($categoryTotals)): ?>
                                <p style="color:#A1A1AA; font-size:13px; text-align:center; padding:20px 0;">No completed pickups in this period yet.</p>
                            <?php else:
                                $svgCircles = '';
                                $offset = 0;
                                $circumference = 2 * M_PI * 38;
                                $colorIndex = 0;
                                foreach ($categoryTotals as $cat => $count) {
                                    $pct = $count / $categoryTotalAll;
                                    $dash = round($pct * $circumference, 2);
                                    $color = $categoryColors[$colorIndex % count($categoryColors)];
                                    $svgCircles .= "<circle cx='50' cy='50' r='38' fill='none' stroke='{$color}' stroke-width='14' stroke-dasharray='{$dash} {$circumference}' stroke-dashoffset='-{$offset}' transform='rotate(-90 50 50)'/>";
                                    $offset += $dash;
                                    $colorIndex++;
                                }
                            ?>
                                <svg class="im-donut-svg" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="38" fill="none" stroke="#E4E4E7" stroke-width="14"/>
                                    <?= $svgCircles ?>
                                    <text x="50" y="47" text-anchor="middle" font-size="13" font-weight="700" fill="#27272A"><?= $categoryTotalAll ?></text>
                                    <text x="50" y="58" text-anchor="middle" font-size="8" fill="#A1A1AA">meals</text>
                                </svg>
                                <div class="im-donut-legend">
                                    <?php $colorIndex = 0; foreach ($categoryTotals as $cat => $count): ?>
                                        <div class="im-legend-item">
                                            <div class="im-legend-dot" style="background:<?= $categoryColors[$colorIndex % count($categoryColors)] ?>"></div>
                                            <span class="im-legend-name"><?= htmlspecialchars($cat) ?></span>
                                            <span class="im-legend-val"><?= round(($count / $categoryTotalAll) * 100) ?>%</span>
                                        </div>
                                    <?php $colorIndex++; endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="im-card" style="margin-bottom:24px">
                    <div class="im-card-title">Milestones</div>
                    <div class="im-card-sub" style="margin-bottom:14px">Achievements unlocked by your food rescue activity</div>
                    <div class="im-milestones">
                        <?php foreach ($milestones as $ms): ?>
                            <div class="im-milestone <?= $ms['achieved'] ? 'im-achieved' : '' ?>">
                                <div class="im-ms-icon <?= $ms['achieved'] ? 'im-done' : 'im-todo' ?>"><span><?= $ms['icon'] ?></span></div>
                                <div>
                                    <div class="im-ms-title"><?= $ms['title'] ?></div>
                                    <div class="im-ms-sub"><?= $ms['sub'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>