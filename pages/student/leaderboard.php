<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$period = ($_GET['period'] ?? 'week') === 'all' ? 'all' : 'week';
$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$nextWeekStart = date('Y-m-d 00:00:00', strtotime('monday next week'));
$periodCondition = $period === 'week'
    ? "AND c.confirmed_at >= '$weekStart' AND c.confirmed_at < '$nextWeekStart'"
    : '';

$sql = "
    SELECT
        u.user_id,
        u.user_name,
        COALESCE(SUM(c.portion_claimed), 0) AS meals_rescued,
        COALESCE(SUM(ir.co2_saved_kg), 0) AS co2_saved,
        COALESCE(SUM(ir.water_saved_litre), 0) AS water_saved,
        (SELECT COUNT(*) FROM claim all_claims WHERE all_claims.student_id = u.user_id) AS total_claims
    FROM user u
    LEFT JOIN claim c
        ON c.student_id = u.user_id
       AND c.status = 'completed'
        $periodCondition
    LEFT JOIN impact_record ir ON ir.claim_id = c.claim_id
    WHERE u.role = 'student'
      AND u.account_status = 'active'
    GROUP BY u.user_id, u.user_name
    ORDER BY meals_rescued DESC, co2_saved DESC, u.user_name ASC
";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die('Database error: ' . mysqli_error($conn));
}

$leaders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $leaders[] = $row;
}

$currentRank = null;
$currentStats = null;
foreach ($leaders as $index => $leader) {
    if ((int) $leader['user_id'] === $studentId) {
        $currentRank = $index + 1;
        $currentStats = $leader;
        break;
    }
}

$topThree = array_slice($leaders, 0, 3);
$displayRows = array_slice($leaders, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/student/leaderboard.css">
    <title>Leaderboard | Campus Food Rescue</title>
</head>
<body>
<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar-container">
            <?php include '../../includes/topbar.php'; ?>
        </div>

        <div class="content-container leaderboard-page">
            <h1 class="page-title">Campus Leaderboard</h1>
            <p class="page-subtitle">See who is making the biggest food rescue impact.</p>

            <section class="leaderboard-hero">
                <div class="podium-card">
                    <div class="podium-heading">Top 3 Campus Eco Champions</div>
                    <div class="podium">
                        <?php foreach ([1, 0, 2] as $position): ?>
                            <?php if (isset($topThree[$position])): ?>
                                <?php $leader = $topThree[$position]; $rank = $position + 1; ?>
                                <div class="podium-person rank-<?= $rank ?>">
                                    <div class="avatar-circle"><?= htmlspecialchars(strtoupper(substr($leader['user_name'], 0, 1))) ?></div>
                                    <span class="podium-rank">#<?= $rank ?></span>
                                    <strong><?= htmlspecialchars($leader['user_name']) ?></strong>
                                    <small><?= (int) $leader['meals_rescued'] ?> meals rescued</small>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="your-status-card">
                    <span>Your Status</span>
                    <strong>#<?= $currentRank ?? '-' ?></strong>
                    <small>Current Campus Rank</small>
                    <div class="status-divider"></div>
                    <div class="status-stat"><span>Meals Rescued</span><b><?= (int) ($currentStats['meals_rescued'] ?? 0) ?></b></div>
                    <div class="status-stat"><span>CO₂ Saved</span><b><?= number_format((float) ($currentStats['co2_saved'] ?? 0), 1) ?> kg</b></div>
                </div>
            </section>

            <section class="leaderboard-table-card">
                <div class="leaderboard-tabs">
                    <a class="tab <?= $period === 'week' ? 'active' : '' ?>" href="leaderboard.php?period=week">This Week</a>
                    <a class="tab <?= $period === 'all' ? 'active' : '' ?>" href="leaderboard.php?period=all">All Time</a>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Meals Rescued</th>
                            <th>CO₂ Saved</th>
                            <th>Badge</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($displayRows): ?>
                            <?php foreach ($displayRows as $index => $leader): ?>
                                <?php $isYou = (int) $leader['user_id'] === $studentId; ?>
                                <tr class="<?= $isYou ? 'current-user' : '' ?>">
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="student-cell">
                                            <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($leader['user_name'], 0, 1))) ?></span>
                                            <span><?= htmlspecialchars($leader['user_name']) ?><?= $isYou ? ' <b>(You)</b>' : '' ?></span>
                                        </div>
                                    </td>
                                    <td><?= (int) $leader['meals_rescued'] ?></td>
                                    <td><?= number_format((float) $leader['co2_saved'], 1) ?> kg</td>
                                    <?php
                                    $mealsRescued = (int) $leader['meals_rescued'];
                                    $badge = (int) $leader['total_claims'] >= 1 ? '1st Save' : 'No badge';
                                    if ($mealsRescued >= 10) {
                                        $badge = 'Zero Waste Hero';
                                    } elseif ($mealsRescued >= 5) {
                                        $badge = 'Food Saver';
                                    }
                                    ?>
                                    <td><span class="badge-chip"><?= htmlspecialchars($badge) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="table-empty">No leaderboard data yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
