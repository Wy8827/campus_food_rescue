<?php   
session_start();  
require_once __DIR__ . '/../../config/constants.php';  
require_once __DIR__ . '/../../config/session.php';  
require_once __DIR__ . '/../../config/db.php';  

requireRole('admin');    

// --- 1. Get statistics ---  
$queryTotal = "SELECT (SELECT COUNT(*) FROM listing_audit_log) + (SELECT COUNT(*) FROM user_audit_log) AS total"; 
$stmtTotal = mysqli_query($conn, $queryTotal);  
$totalEntries = mysqli_fetch_assoc($stmtTotal)['total'] ?? 0;  

// Critical actions in the last 24 hours  
$criticalQuery = "          
    SELECT                   
        (SELECT COUNT(*) FROM listing_audit_log WHERE action_type = 'remove_listing' AND performed_at >= NOW() - INTERVAL 1 DAY) +                  
        (SELECT COUNT(*) FROM user_audit_log WHERE action_type = 'ban_user' AND performed_at >= NOW() - INTERVAL 1 DAY) AS critical_total";  
$stmtCritical = mysqli_query($conn, $criticalQuery);  
$criticalActions = mysqli_fetch_assoc($stmtCritical)['critical_total'] ?? 0;  

// Active administrator count  
$stmtAdmin = mysqli_query($conn, "SELECT COUNT(*) AS active_admins FROM user WHERE role = 'admin' AND account_status = 'active'");  
$activeAdmins = mysqli_fetch_assoc($stmtAdmin)['active_admins'] ?? 0;  

// Pagination
$limit = 10;   
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;  
if ($page < 1) $page = 1;  
$totalPages = max(1, (int)ceil($totalEntries / $limit));  

if ($page > $totalPages && $totalEntries > 0) {          
    $page = $totalPages;  
}
$offset = ($page - 1) * $limit;  

$from = $totalEntries > 0 ? $offset + 1 : 0;  
$to = min($offset + $limit, $totalEntries);  

// --- 2. Get combined Audit Logs ---  
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
    LIMIT ? OFFSET ?";  

$stmtLogs = mysqli_prepare($conn, $logQuery);  
mysqli_stmt_bind_param($stmtLogs, "ii", $limit, $offset);  
mysqli_stmt_execute($stmtLogs);  
$resultLogs = mysqli_stmt_get_result($stmtLogs); 
$logs = mysqli_fetch_all($resultLogs, MYSQLI_ASSOC);  
mysqli_stmt_close($stmtLogs); 
?>
<!DOCTYPE html> 
<html lang="en"> 
<head>     
    <meta charset="UTF-8">     
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">     
    <link rel="stylesheet" href="../../assets/css/topbar.css">     
    <link rel="stylesheet" href="../../assets/css/dashboard.css">     
    <link rel="stylesheet" href="../../assets/css/userManagement.css">     
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>     
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>     
    <title>Audit Log - Campus Food Rescue</title> 

    <!-- Audit Log Specific High-Texture Badges -->
    <style>
        .audit-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 6px;
            color: #FFFFFF;
            white-space: nowrap;
        }
        .badge-green  { background-color: #15803D; }
        .badge-amber  { background-color: #CA8A04; }
        .badge-red    { background-color: #DC2626; }
        .badge-grey   { background-color: #4B5563; }
    </style>
</head> 
<body>     
    <div class="dashboard-container">         
        <?php include '../../includes/sidebar.php'; ?>         
        <div class="main-content">             
            <div class="topbar-container">                 
                <?php include '../../includes/topbar.php'; ?>             
            </div>             
            <div class="content-container">                 
                <!-- Title & Subtitle -->
                <div class="dashboard-header" style="margin-bottom: 20px;">                     
                    <h1 class="page-title">Audit Log</h1>                     
                    <p class="page-subtitle">System activity records and security monitoring.</p>                 
                </div>

                <!-- 4 Top KPI Cards -->
                <div class="summary-card-container">                     
                    <div class="summary-card">                         
                        <span class="card-title">TOTAL ENTRIES</span>
                        <div class="card-value"><?= htmlspecialchars($totalEntries) ?></div>                     
                    </div>                     

                    <div class="summary-card">                         
                        <span class="card-title">CRITICAL ACTIONS(24H)</span>
                        <div class="card-value"><?= htmlspecialchars($criticalActions) ?></div>                     
                    </div>                     

                    <div class="summary-card">                         
                        <span class="card-title">ACTIVE ADMIN</span>
                        <div class="card-value"><?= htmlspecialchars($activeAdmins) ?></div>                     
                    </div>                     

                    <div class="summary-card">                         
                        <span class="card-title">LOG RETENTION</span>
                        <div class="card-value">90<span class="unit">Days</span></div>                     
                    </div>                 
                </div>                                  

                <!-- Subheading -->
                <div style="margin-top: 10px; margin-bottom: 18px;">
                    <div style="font-size: 16px; font-weight: 700; color: #111827;">System Activity Logs</div>
                    <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">Detailed metrics per participating location and system event</div>
                </div>

                <!-- Audit Log Table -->
                <div class="table-card">                     
                    <table class="user-list-table">                         
                        <thead>                             
                            <tr>                                 
                                <th style="width: 18%;">TIMESTAMP</th>                                 
                                <th style="width: 20%;">ADMIN NAME/ID</th>                                 
                                <th style="width: 16%;">ACTION TYPE</th>                                 
                                <th style="width: 16%;">TARGET ENTITY</th>                                 
                                <th style="width: 30%;">DETAILS</th>                             
                            </tr>                         
                        </thead>                         
                        <tbody>                             
                            <?php if (count($logs) > 0): ?>                                 
                                <?php foreach ($logs as $log):                                      
                                    $words = explode(" ", trim($log['admin_name']));                                     
                                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));                                                                                  
                                    $formattedAction = ucwords(str_replace('_', ' ', htmlspecialchars($log['action_type']))); 
                                    
                                    // Map Action Type to Exact Colors
                                    $badgeClass = 'badge-green';
                                    if (in_array($log['action_type'], ['throttle_user', 'warn_user', 'reject_listing', 'reject_provider'])) {
                                        $badgeClass = 'badge-amber';
                                    } elseif (in_array($log['action_type'], ['remove_listing', 'ban_user'])) {
                                        $badgeClass = 'badge-red';
                                    }
                                ?>                                     
                                    <tr>                                         
                                        <td>
                                            <span style="font-size: 13px; color: #111827;">
                                                <?= htmlspecialchars($log['timestamp']) ?>
                                            </span>
                                        </td>                                                                                  
                                        <td>                                             
                                            <div class="user-profile-cell">                                                 
                                                <div class="avatar-circle avatar-admin"><?= $initials ?></div>                                                 
                                                <div class="user-info-text">                                                     
                                                    <div class="user-name-title"><?= htmlspecialchars($log['admin_name']) ?></div>                                                     
                                                    <div class="user-meta-subtitle">Admin ID: <?= htmlspecialchars($log['admin_id']) ?></div>                                                 
                                                </div>                                             
                                            </div>                                         
                                        </td>                                                                                  
                                        <td>                                             
                                            <span class="audit-badge <?= $badgeClass ?>">                                                 
                                                <?= $formattedAction ?>                                             
                                            </span>                                         
                                        </td>                                                                                  
                                        <td>
                                            <span style="font-weight: 600; color: #111827; font-size: 13px;">
                                                <?= htmlspecialchars($log['target_entity']) ?>
                                            </span>
                                        </td>                                                                                  
                                        <td>
                                            <span style="color: #4B5563; font-size: 13px;">
                                                <?= htmlspecialchars($log['details']) ?>
                                            </span>
                                        </td>                                     
                                    </tr>                                 
                                <?php endforeach; ?>                             
                            <?php else: ?>                                 
                                <tr>                                     
                                    <td colspan="5" class="table-empty-cell">No audit log records recorded yet.</td>                                 
                                </tr>                             
                            <?php endif; ?>                         
                        </tbody>                     
                    </table>                         

                    <!-- Pagination Controls -->
                    <div class="table-pagination-footer">                             
                        <span class="pagination-summary">Showing <?= $from ?>-<?= $to ?> of <?= $totalEntries ?> Logs</span>                             
                        <div class="pagination-controls">                                 
                            <?php if ($page > 1): ?>                                     
                                <a href="?page=<?= $page - 1 ?>" class="page-nav-btn">&lt;</a>                                 
                            <?php else: ?>                                     
                                <span class="page-nav-btn disabled">&lt;</span>                                 
                            <?php endif; ?>                                 

                            <span class="current-page-indicator"><b><?= $page ?></b> / <?= $totalPages ?></span>                                 

                            <?php if ($page < $totalPages): ?>                                     
                                <a href="?page=<?= $page + 1 ?>" class="page-nav-btn">&gt;</a>                                 
                            <?php else: ?>                                     
                                <span class="page-nav-btn disabled">&gt;</span>                                 
                            <?php endif; ?>                         
                        </div>                 
                    </div>             
                </div>                          
            </div>         
        </div>     
    </div> 
</body> 
</html>