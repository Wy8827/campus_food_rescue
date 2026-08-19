<?php 
session_start(); 
require_once __DIR__ . '/../../config/db.php';
$pdo = getDB();

// --- 1. get statistics ---
// Total entries
$stmtTotal = $pdo->query("SELECT (SELECT COUNT(*) FROM listing_audit_log) + (SELECT COUNT(*) FROM user_audit_log) AS total");
$totalEntries = $stmtTotal->fetch()['total'];

// Critical actions in the last 24 hours (e.g., removing a listing or banning a user)
$criticalQuery = "
    SELECT 
        (SELECT COUNT(*) FROM listing_audit_log WHERE action_type = 'remove_listing' AND performed_at >= NOW() - INTERVAL 1 DAY) +
        (SELECT COUNT(*) FROM user_audit_log WHERE action_type = 'ban_user' AND performed_at >= NOW() - INTERVAL 1 DAY) AS critical_total
";
$stmtCritical = $pdo->query($criticalQuery);
$criticalActions = $stmtCritical->fetch()['critical_total'];

// Active administrator count
$stmtAdmin = $pdo->query("SELECT COUNT(*) AS active_admins FROM user WHERE role = 'admin' AND account_status = 'active'");
$activeAdmins = $stmtAdmin->fetch()['active_admins'];

$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$totalPages = max(1, (int)ceil($totalEntries / $limit));
if ($page > $totalPages && $totalEntries > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

// calculate $from and $to for frontend display
$from = $totalEntries > 0 ? $offset + 1 : 0;
$to = min($offset + $limit, $totalEntries);

// --- 2. get all Audit Logs (combining data from both tables) ---
$logQuery = "
    SELECT * FROM (
        SELECT 
            lal.performed_at AS timestamp,
            u.user_name AS admin_name,
            lal.admin_id AS admin_id, 
            lal.action_type AS action_type,
            CONCAT('Listing ID: ', lal.listing_id) AS target_entity,
            lal.notes AS details
        FROM listing_audit_log lal
        JOIN user u ON lal.admin_id = u.user_id

        UNION ALL

        SELECT 
            ual.performed_at AS timestamp,
            u.user_name AS admin_name,
            ual.admin_id AS admin_id, 
            ual.action_type AS action_type,
            CONCAT('User ID: ', ual.affected_user_id) AS target_entity,
            ual.notes AS details
        FROM user_audit_log ual
        JOIN user u ON ual.admin_id = u.user_id
    ) AS combined_logs
    ORDER BY timestamp DESC
    LIMIT :limit OFFSET :offset
";
$stmtLogs = $pdo->prepare($logQuery);
$stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtLogs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/userManagement.css">
    <script src="https://cdn.plot.ly/plotly-3.6.0.min.js" charset="utf-8"></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>Audit Log</title>
</head>
<body>
    <div class="dashboard-container">
        <!-- sidebar on the left -->
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">

            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <div>
                    <h1 class="page-title">Audit Log</h1>
                    <p class="page-subtitle">System activity records and security monitoring.</p>

                    <div class="summary-card-container">
                        <div class="summary-card">
                            <span class="card-title">TOTAL ENTRIES</span>
                            <span class="card-value"><?= htmlspecialchars($totalEntries) ?></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">CRITICAL ACTIONS(24H)</span>
                            <span class="card-value"><?= htmlspecialchars($criticalActions) ?></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">ACTIVE ADMIN</span>
                            <span class="card-value"><?= htmlspecialchars($activeAdmins) ?></span>
                        </div>

                        <div class="summary-card">
                            <span class="card-title">LOG RETENTION</span>
                            <span class="card-value">90<span class="unit">Days</span></span>
                        </div>
                    </div>

                    
                    <span class="section-title">Vendor Contribution Analysis</span> </br>
                    <span class="section-subtitle">Detailed metrics per participating location</span>
                    <div class="user-list-container">
                        <table class="user-list-table">
                            <thead>
                                <tr>
                                    <th>TIMESTAMP</th>
                                    <th>ADMIN NAME/ID</th>
                                    <th>ACTION TYPE</th>
                                    <th>TARGET ENTITY</th>
                                    <th>DETAILS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): 
                                        $words = explode(" ", $log['admin_name']);
                                        $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                                        
                                        // formatting operation type name (e.g., remove_listing becomes Remove Listing)
                                        $formattedAction = ucwords(str_replace('_', ' ', htmlspecialchars($log['action_type'])));
                                        
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                            
                                            <td>
                                                <div class="user-profile">
                                                    <div class="avatar"><?php echo $initials; ?></div>
                                                    <div class="user-info">
                                                        <div class="user-name"><?php echo htmlspecialchars($log['admin_name']); ?></div>
                                                        <div class="user-meta">Admin ID: <?php echo htmlspecialchars($log['admin_id']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td>
                                                <span class="role-badge action-<?= htmlspecialchars($log['action_type']) ?>">
                                                    <?php echo $formattedAction; ?>
                                                </span>
                                            </td>
                                            
                                            <td><span style="font-weight: 500; color: #374151;"><?php echo htmlspecialchars($log['target_entity']); ?></span></td>
            
                                            <td style="color: #6B7280; font-size: 13px;"><?php echo htmlspecialchars($log['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">No audit logs found.</td>
                                    </tr>
                                <?php endif; ?>

                            </tbody>
                        </table>

                            <div class="user-list-footer">
                                <span class="showing-text">Showing <?= $from ?>-<?= $to ?> of <?= $totalEntries ?> Logs</span>
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?>" class="pagination-btn">&lt;</a>
                                    <?php else: ?>
                                        <span class="pagination-btn disabled">&lt;</span>
                                    <?php endif; ?>

                                    <span><b><?= $page ?></b> / <?= $totalPages ?></span>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?= $page + 1 ?>" class="pagination-btn">&gt;</a>
                                    <?php else: ?>
                                        <span class="pagination-btn disabled">&gt;</span>
                                    <?php endif; ?>
                            </div>
                    </div>


                </div>

                
            </div>

        </div>
    </div>
    
</body>
</html>