<?php
session_start();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/db.php';
requireRole('student');

$studentId = (int) ($_SESSION['user_id'] ?? 0);
$period = ($_GET['period'] ?? 'week') === 'all' ? 'all' : 'week';
$periodOptions = ['week', 'all'];

function getLeaderboardRows($conn, $period) {
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

    return $leaders;
}

$leaderboardData = [];
foreach ($periodOptions as $option) {
    $leaderboardData[$option] = getLeaderboardRows($conn, $option);
}

$allTimeSql = "
    SELECT
        u.user_id,
        COALESCE(SUM(c.portion_claimed), 0) AS all_time_meals_rescued,
        COALESCE(COUNT(DISTINCT c.claim_id), 0) AS all_time_total_claims
    FROM user u
    LEFT JOIN claim c
        ON c.student_id = u.user_id
       AND c.status = 'completed'
    WHERE u.role = 'student'
      AND u.account_status = 'active'
    GROUP BY u.user_id
    ORDER BY all_time_meals_rescued DESC, u.user_name ASC
";

$allTimeResult = mysqli_query($conn, $allTimeSql);
if (!$allTimeResult) {
    die('Database error: ' . mysqli_error($conn));
}

$allTimeBadgeMap = [];
while ($row = mysqli_fetch_assoc($allTimeResult)) {
    $allTimeBadgeMap[(int) $row['user_id']] = [
        'meals' => (int) $row['all_time_meals_rescued'],
        'claims' => (int) $row['all_time_total_claims'],
    ];
}

$currentPeriod = in_array($period, $periodOptions, true) ? $period : 'week';
$currentLeaders = $leaderboardData[$currentPeriod];

$currentRank = null;
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

            <div class="leaderboard-tabs">
                <button type="button" class="tab <?= $currentPeriod === 'week' ? 'active' : '' ?>" data-period="week">This Week</button>
                <button type="button" class="tab <?= $currentPeriod === 'all' ? 'active' : '' ?>" data-period="all">All Time</button>
            </div>

            <?php foreach ($periodOptions as $option): ?>
                <?php
                $panelLeaders = $leaderboardData[$option];
                $panelTopThree = array_slice($panelLeaders, 0, 3);
                $panelDisplayRows = array_slice($panelLeaders, 0, 10);

                $panelCurrentRank = null;
                $panelCurrentStats = null;
                foreach ($panelLeaders as $index => $leader) {
                    if ((int) $leader['user_id'] === $studentId) {
                        $panelCurrentRank = $index + 1;
                        $panelCurrentStats = $leader;
                        break;
                    }
                }
                ?>

                <section class="leaderboard-panel <?= $option === $currentPeriod ? 'active' : '' ?>" data-period="<?= htmlspecialchars($option) ?>">
                    <section class="leaderboard-hero">
                        <div class="podium-card">
                            <div class="podium-heading">Top 3 Campus Eco Champions</div>
                            <div class="podium">
                                <?php foreach ([1, 0, 2] as $position): ?>
                                    <?php if (isset($panelTopThree[$position])): ?>
                                        <?php $leader = $panelTopThree[$position]; $rank = $position + 1; ?>
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
                            <strong>#<?= $panelCurrentRank ?? '-' ?></strong>
                            <small>Current Campus Rank</small>
                            <div class="status-divider"></div>
                            <div class="status-stat"><span>Meals Rescued</span><b><?= (int) ($panelCurrentStats['meals_rescued'] ?? 0) ?></b></div>
                            <div class="status-stat"><span>CO₂ Saved</span><b><?= number_format((float) ($panelCurrentStats['co2_saved'] ?? 0), 1) ?> kg</b></div>
                        </div>
                    </section>

                    <section class="leaderboard-table-card">
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
                                <?php if ($panelDisplayRows): ?>
                                    <?php foreach ($panelDisplayRows as $index => $leader): ?>
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
                                            $userId = (int) $leader['user_id'];
                                            $allTimeStats = $allTimeBadgeMap[$userId] ?? ['meals' => 0, 'claims' => 0];
                                            $allTimeMeals = (int) $allTimeStats['meals'];
                                            $allTimeClaims = (int) $allTimeStats['claims'];

                                            $badge = 'No badge';
                                            if ($allTimeClaims >= 1) {
                                                $badge = '1st Save';
                                            }
                                            if ($allTimeMeals >= 10) {
                                                $badge = 'Zero Waste Hero';
                                            } elseif ($allTimeMeals >= 5) {
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
                </section>
            <?php endforeach; ?>
        </div>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.tab');
        const panels = document.querySelectorAll('.leaderboard-panel');

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.getAttribute('data-period');

                tabs.forEach((button) => {
                    button.classList.toggle('active', button === tab);
                });

                panels.forEach((panel) => {
                    panel.classList.toggle('active', panel.getAttribute('data-period') === target);
                });
            });
        });
    });
</script>
</body>
</html>
