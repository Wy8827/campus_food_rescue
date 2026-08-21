<?php 
session_start(); 
require_once __DIR__ . '/../../config/constants.php'; 
require_once __DIR__ . '/../../config/session.php'; 
require_once __DIR__ . '/../../config/db.php'; 

requireRole('admin');  

// ---------------------------------------------------- 
// 1. CAPTURE SEARCH & FILTER INPUTS 
// ---------------------------------------------------- 
$search = isset($_GET['search']) ? trim($_GET['search']) : ''; 
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all'; 
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all'; 

// Build Dynamic SQL Query Conditions 
$conditions = []; 
$params = []; 
$types = ""; // Record mysqli parameter types (s=string, i=int)

if ($search !== '') {     
    $conditions[] = "(u.user_name LIKE ? OR u.email LIKE ? OR u.user_id = ?)";     
    $params[] = '%' . $search . '%';     
    $params[] = '%' . $search . '%';     
    $params[] = $search; 
    $types .= "sss";
}
if ($roleFilter !== 'all') {     
    $conditions[] = "u.role = ?";     
    $params[] = $roleFilter; 
    $types .= "s";
}
if ($statusFilter !== 'all') {     
    $conditions[] = "u.account_status = ?";     
    $params[] = $statusFilter; 
    $types .= "s";
}

$whereClause = ""; 
if (count($conditions) > 0) {     
    $whereClause = " WHERE " . implode(" AND ", $conditions); 
}

// ---------------------------------------------------- 
// 2. PAGINATION LOGIC 
// ---------------------------------------------------- 
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; 
if ($page < 1) $page = 1; 

// Get the total number of filtered users (Alias 'u' added to match conditions)
$countQuery = "SELECT COUNT(*) FROM `user` u" . $whereClause;
$totalStmt = mysqli_prepare($conn, $countQuery); 

if (!empty($params)) {
    mysqli_stmt_bind_param($totalStmt, $types, ...$params); 
}
mysqli_stmt_execute($totalStmt); 
$totalResult = mysqli_stmt_get_result($totalStmt);
$totalUsers = (int)mysqli_fetch_row($totalResult)[0]; 
mysqli_stmt_close($totalStmt);

$totalPages = max(1, (int)ceil($totalUsers / $limit)); 
if ($page > $totalPages && $totalUsers > 0) {     
    $page = $totalPages; 
}
$offset = ($page - 1) * $limit; 

// ---------------------------------------------------- 
// 3. FETCH FILTERED DATA WITH ROLE-BASED SEQUENCING & AUDIT LOGS
// Subquery added to fetch the latest admin note for the user
// ---------------------------------------------------- 
$query = "SELECT u.user_id, u.user_name, u.email, u.role, u.account_status, u.no_show_count,
                 (SELECT notes FROM user_audit_log ual WHERE ual.affected_user_id = u.user_id ORDER BY ual.performed_at DESC LIMIT 1) AS status_reason,
                 ROW_NUMBER() OVER (PARTITION BY u.role ORDER BY u.user_id ASC) AS role_seq
          FROM `user` u " . $whereClause . " 
          ORDER BY u.user_id ASC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $query); 

$fetchParams = $params;
$fetchParams[] = $limit;
$fetchParams[] = $offset;
$fetchTypes = $types . "ii"; 

mysqli_stmt_bind_param($stmt, $fetchTypes, ...$fetchParams); 
mysqli_stmt_execute($stmt); 
$result = mysqli_stmt_get_result($stmt);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC); 
mysqli_stmt_close($stmt);

$from = $totalUsers > 0 ? $offset + 1 : 0; 
$to = min($offset + $limit, $totalUsers); 

// Build query string for pagination links 
$urlParams = [     
    'search' => $search,     
    'role' => $roleFilter,     
    'status' => $statusFilter 
];
$queryString = http_build_query($urlParams); 

// ==========================================
// 4. HANDLE EDIT USER FORM SUBMISSION (HYBRID APPROACH)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user_edit'])) {
    $targetUserId = (int)$_POST['target_user_id'];
    $newStatus = $_POST['account_status'];
    $adminNote = trim($_POST['admin_note'] ?? '');
    $resetSecurity = isset($_POST['reset_security']) ? true : false;
    $adminId = $_SESSION['user_id']; // Current Admin ID

    try {
        // Step 4.1: Fetch old status to compare changes
        $checkQ = "SELECT account_status FROM user WHERE user_id = ?";
        $checkStmt = mysqli_prepare($conn, $checkQ);
        mysqli_stmt_bind_param($checkStmt, "i", $targetUserId);
        mysqli_stmt_execute($checkStmt);
        $oldStatus = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))['account_status'];
        mysqli_stmt_close($checkStmt);

        // Step 4.2: Update Account Status
        $updateQ = "UPDATE user SET account_status = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $updateQ);
        mysqli_stmt_bind_param($stmt, "si", $newStatus, $targetUserId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Step 4.3: Log into user_audit_log if status changed or note provided
        if ($oldStatus !== $newStatus || !empty($adminNote)) {
            $actionType = ($newStatus === 'banned') ? 'ban_user' : (($newStatus === 'throttled') ? 'throttle_user' : 'update_user');
            $logNote = empty($adminNote) ? "Status changed to $newStatus" : $adminNote;
            
            $logQ = "INSERT INTO user_audit_log (admin_id, affected_user_id, action_type, notes) VALUES (?, ?, ?, ?)";
            $logStmt = mysqli_prepare($conn, $logQ);
            mysqli_stmt_bind_param($logStmt, "iiss", $adminId, $targetUserId, $actionType, $logNote);
            mysqli_stmt_execute($logStmt);
            mysqli_stmt_close($logStmt);
        }

        // Step 4.4: Reset Security Question (If Checked)
        if ($resetSecurity) {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
            $defaultQuestion = "Admin Reset - Please update your security question.";
            
            $resetQ = "UPDATE user SET security_question = ?, security_answer = ? WHERE user_id = ?";
            $resetStmt = mysqli_prepare($conn, $resetQ);
            mysqli_stmt_bind_param($resetStmt, "ssi", $defaultQuestion, $defaultHash, $targetUserId);
            mysqli_stmt_execute($resetStmt);
            mysqli_stmt_close($resetStmt);
            
            // Log security reset to audit log
            $resetLogQ = "INSERT INTO user_audit_log (admin_id, affected_user_id, action_type, notes) VALUES (?, ?, 'reset_security', 'Admin reset security question to default')";
            $rlStmt = mysqli_prepare($conn, $resetLogQ);
            mysqli_stmt_bind_param($rlStmt, "ii", $adminId, $targetUserId);
            mysqli_stmt_execute($rlStmt);
            mysqli_stmt_close($rlStmt);
        }

        // Redirect to prevent form resubmission
        header("Location: userManagement.php?msg=updated");
        exit;
    } catch (Exception $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/userManagement.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Reusing moderation.css for the View Details card styling -->
    <link rel="stylesheet" href="../../assets/css/moderation.css">
    <script src="../../assets/js/userManagement.js" defer></script>
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>User Management</title>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="topbar-container">
                <?php include '../../includes/topbar.php'; ?>
            </div>

            <div class="content-container">
                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">User Management</h1>
                        <p class="page-subtitle">Oversee system participants, adjust roles, and monitor engagement metrics across the campus food rescue network.</p>
                    </div>

                    <div class="user-page-header-right">
                        <button class="export-button"><img src="../../assets/images/export.png" alt="Export Icon" class="export-button-img">Export List</button>
                        <button class="add-user-button"><img src="../../assets/images/adduser.png" alt="Add User Icon" class="add-user-button-img">Manual Add</button>
                    </div>
                </div>

                <!-- Search and Filter Form -->
                <form method="GET" action="" class="search-container">
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, or ID" value="<?= htmlspecialchars($search) ?>">
                    
                    <select name="role" class="filter-selection" onchange="this.form.submit()">
                        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="provider" <?= $roleFilter === 'provider' ? 'selected' : '' ?>>Food Provider</option>
                    </select>

                    <select name="status" class="filter-selection" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="throttled" <?= $statusFilter === 'throttled' ? 'selected' : '' ?>>Throttled</option>
                        <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>
                    </select>
                    
                    <button type="submit" style="display: none;"></button>
                </form>

                <div class="user-list-container">
                    <table class="user-list-table">
                        <thead>
                            <tr>
                                <th>USER PROFILE</th>
                                <th>ROLE</th>
                                <th>NO-SHOW</th>
                                <th>CREDIT SCORE</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($users)): ?>
                                <?php foreach($users as $user): 
                                    $statusLower = strtolower($user['account_status']);
                                    $roleLower = strtolower($user['role']);
                                    
                                    // Generate user initials for avatar
                                    $words = explode(" ", $user['user_name']);
                                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                                    
                                    // Credit score calculation based on no-show count
                                    $noShow = isset($user['no_show_count']) ? (int)$user['no_show_count'] : 0;
                                    $creditScore = max(0, 100 - ($noShow * 14)); 

                                    $scoreColor = 'green';
                                    if ($creditScore < 50) $scoreColor = 'red';
                                    elseif ($creditScore < 80) $scoreColor = 'orange';
                                    
                                    $noShowClass = $noShow >= 2 ? 'no-show-high' : '';

                                    // Role configuration for icons and display prefixes
                                    $roleIcon = [
                                        'admin' => ['icon' => '<ion-icon name="shield-half-outline"></ion-icon>', 'prefix' => 'A'],
                                        'provider' => ['icon' => '<ion-icon name="restaurant-outline"></ion-icon>', 'prefix' => 'P'],
                                        'student' => ['icon' => '<ion-icon name="school-outline"></ion-icon>', 'prefix' => 'S'],
                                    ];

                                    // Construct role-based sequential display ID
                                    $prefix = $roleIcon[$roleLower]['prefix'] ?? '';
                                    $displayId = $prefix . $user['role_seq'];
                                ?>
                                    <tr class="row-<?= $statusLower ?>">
                                        <td>
                                            <div class="user-profile">
                                                <div class="avatar avatar-<?= $roleLower ?>"><?= $initials ?></div>
                                                <div class="user-info">
                                                    <div class="user-name"><?= htmlspecialchars($user['user_name']) ?></div>
                                                    <div class="user-meta"><?= $displayId ?> &bull; <?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= $roleLower ?>">
                                                <span class="role-icon"><?= $roleIcon[$roleLower]['icon'] ?? '' ?></span>
                                                <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                            </span>
                                        </td>
                                        <td class="<?= $noShowClass ?>"><?= $noShow ?></td>
                                        <td>
                                            <div class="credit-score">
                                                <div class="score-bar-bg">
                                                    <div class="score-bar-fill fill-<?= $scoreColor ?>" style="width: <?= $creditScore ?>%;"></div>
                                                </div>
                                                <span class="score-text score-<?= $scoreColor ?>"><?= $creditScore ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="status-indicator status-<?= $statusLower ?>">
                                                <span class="dot"></span>
                                                <?= ucfirst(htmlspecialchars($user['account_status'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="gap: 15px; display: flex; flex-direction: row;">
                                                <!-- Action buttons passing data via attributes to the JavaScript modals -->
                                                <button type="button" class="edit-button" 
                                                    data-id="<?= htmlspecialchars($user['user_id']) ?>" 
                                                    data-status="<?= $statusLower ?>" 
                                                    onclick="openEditModal(this)">Edit User</button>
                                                
                                                <button type="button" class="view-button" 
                                                    data-id="<?= $displayId ?>" 
                                                    data-name="<?= htmlspecialchars($user['user_name']) ?>" 
                                                    data-email="<?= htmlspecialchars($user['email']) ?>" 
                                                    data-role="<?= ucfirst(htmlspecialchars($user['role'])) ?>" 
                                                    data-score="<?= $creditScore ?>"
                                                    data-noshow="<?= $noShow ?>" 
                                                    data-status="<?= ucfirst(htmlspecialchars($user['account_status'])) ?>"
                                                    data-initials="<?= $initials ?>"
                                                    data-roleclass="avatar-<?= $roleLower ?>"
                                                    data-reason="<?= htmlspecialchars($user['status_reason'] ?? 'No recent administrative logs.') ?>"
                                                    onclick="openViewModal(this)">View Details</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">No users found matching your search criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination Footer -->
                    <div class="user-list-footer">
                        <span class="showing-text">Showing <?= $from ?>-<?= $to ?> of <?= $totalUsers ?> Users</span>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&<?= $queryString ?>" class="pagination-btn">&lt;</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">&lt;</span>
                            <?php endif; ?>

                            <span><b><?= $page ?></b> / <?= $totalPages ?></span>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>&<?= $queryString ?>" class="pagination-btn">&gt;</a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">&gt;</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- FLOATING MODALS OVERLAY                    -->
    <!-- ========================================== -->
    <div id="modal-overlay" class="modal-overlay" style="display: none;">

        <div id="edit-modal" class="modal-content" style="display: none; padding: 0; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); width: 100%; max-width: 450px; background: #FFFFFF;">
            
            <!-- Modal Header -->
            <div style="padding: 16px 24px; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; background: #F9FAFB;">
                <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px;">
                    <ion-icon name="create-outline" style="color: #6B7280; font-size: 20px;"></ion-icon> 
                    Edit User Configuration
                </h2>
                <button type="button" onclick="closeModals()" style="background: none; border: none; cursor: pointer; color: #9CA3AF; font-size: 22px; display: flex; align-items: center; justify-content: center; transition: color 0.2s;" onmouseover="this.style.color='#4B5563'" onmouseout="this.style.color='#9CA3AF'">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <form method="POST" action="userManagement.php" style="padding: 24px;">
                <input type="hidden" name="target_user_id" id="edit_user_id" value="">
                
                <!-- Form Group: Account Status -->
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">Account Status</label>
                    <div style="position: relative;">
                        <!-- Using modern-input class for focus state -->
                        <select name="account_status" id="edit_account_status" class="modern-input" style="width: 100%; height: 42px; border: 1px solid #D1D5DB; border-radius: 8px; padding: 0 36px 0 12px; font-size: 14px; color: #1F2937; appearance: none; background: #FFFFFF; cursor: pointer;">
                            <option value="active">Active</option>
                            <option value="throttled">Throttled</option>
                            <option value="banned">Banned</option>
                        </select>
                        <ion-icon name="chevron-down-outline" style="position: absolute; right: 12px; top: 12px; font-size: 16px; color: #6B7280; pointer-events: none;"></ion-icon>
                    </div>
                </div>

                <!-- Form Group: Admin Note -->
                <div style="margin-bottom: 24px;">
                    <label style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                        Action Reason <span style="color: #9CA3AF; font-weight: 400; font-size: 12px;">(Req. for Ban/Throttle)</span>
                    </label>
                    <input type="text" name="admin_note" id="edit_admin_note" class="modern-input" placeholder="e.g., Multiple no-shows reported..." style="width: 100%; height: 42px; border: 1px solid #D1D5DB; border-radius: 8px; padding: 0 12px; font-size: 14px; box-sizing: border-box;">
                </div>

                <!-- Danger Zone: Reset Security (Highlighted to prevent accidental clicks) -->
                <div style="background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 8px; padding: 16px; margin-bottom: 24px; transition: background 0.2s;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="reset_security" value="1" style="margin-top: 3px; accent-color: #DC2626; width: 16px; height: 16px; cursor: pointer;">
                        <div>
                            <span style="display: block; font-size: 14px; font-weight: 600; color: #991B1B; line-height: 1.2;">Reset Security Question</span>
                            <span style="display: block; font-size: 12px; color: #B91C1C; margin-top: 6px; line-height: 1.4;">Reverts answer to <b>'123456'</b>. Check this only if the user has lost access to their account.</span>
                        </div>
                    </label>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 8px;">
                    <button type="button" onclick="closeModals()" class="btn-secondary" style="padding: 10px 20px; background: #FFFFFF; color: #374151; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;">Cancel</button>
                    <button type="submit" name="save_user_edit" class="btn-primary" style="padding: 10px 20px; background: #275300; color: #FFFFFF; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Save Changes</button>
                </div>
            </form>
        </div>

        <!-- 2. VIEW DETAILS MODAL (Modernized UI) -->
        <div id="view-modal" class="modal-content" style="display: none; padding: 0; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); width: 100%; max-width: 400px; background: #FFFFFF;">
            
            <!-- Header Banner -->
            <div style="height: 100px; background: linear-gradient(135deg, #E8F5E9 0%, #C2C9B7 100%); position: relative;">
                <!-- top-left corner for ID -->
                <div style="position: absolute; top: 16px; left: 16px;">
                    <span class="item-code" id="view_user_id" style="background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 6px; font-weight: 700; color: #374151; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-size: 12px;">#USER-ID</span>
                </div>
                <!-- top-right corner for Status Badge -->
                <div style="position: absolute; top: 16px; right: 16px;">
                    <span class="urgent-badge badge-normal" id="view_status" style="background: #FFFFFF; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Active</span>
                </div>
            </div>
            
            <!-- Overlapping Avatar-->
            <div style="display: flex; justify-content: center; margin-top: -40px;">
                <div id="view_avatar" class="avatar" style="width: 80px; height: 80px; font-size: 28px; border: 4px solid #FFFFFF; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 50%; z-index: 2;">JD</div>
            </div>

            <!-- User Info Container-->
            <div style="padding: 16px 24px 24px 24px; text-align: center;">
                <h3 id="view_name" style="margin: 0; font-size: 20px; font-weight: 700; color: #111827;">User Name</h3>
                
                <div style="display: flex; align-items: center; justify-content: center; gap: 6px; color: #6B7280; font-size: 14px; margin-top: 6px;">
                    <ion-icon name="mail-outline"></ion-icon>
                    <span id="view_email">email@example.com</span>
                </div>

                <!-- Stats Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 24px;">
                    <div style="background: #F9FAFB; border: 1px solid #F3F4F6; border-radius: 8px; padding: 12px; text-align: center;">
                        <span style="display: block; font-size: 11px; color: #6B7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Role</span>
                        <span id="view_role" style="display: block; font-size: 15px; font-weight: 700; color: #1F2937; margin-top: 4px;">Student</span>
                    </div>
                    <div style="background: #F9FAFB; border: 1px solid #F3F4F6; border-radius: 8px; padding: 12px; text-align: center;">
                        <span style="display: block; font-size: 11px; color: #6B7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">No-Show</span>
                        <span id="view_noshow" style="display: block; font-size: 15px; font-weight: 700; color: #1F2937; margin-top: 4px;">0</span>
                    </div>
                </div>

                <!-- Credit Score Progress -->
                <div style="margin-top: 24px; text-align: left;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 13px; font-weight: 700; color: #374151;">Credit Score</span>
                        <span id="view_score_text" class="score-green" style="font-weight: 800; font-size: 14px;">100%</span>
                    </div>
                    <div class="score-bar-bg" style="width: 100%; height: 8px; border-radius: 4px; background: #E5E7EB; overflow: hidden;">
                        <div class="score-bar-fill fill-green" id="view_score_bar" style="height: 100%; width: 100%; border-radius: 4px;"></div>
                    </div>
                </div>

                <!-- Dynamic Reason Container -->
                <div id="view_reason_container" style="margin-top: 20px; background: #FFF5F5; border: 1px solid #FCA5A5; padding: 12px; border-radius: 8px; display: none; text-align: left;">
                    <span style="font-size: 12px; font-weight: bold; color: #B42318; display: flex; align-items: center; gap: 4px; margin-bottom: 4px;">
                        <ion-icon name="alert-circle"></ion-icon> Admin Note:
                    </span>
                    <span id="view_reason" style="font-size: 13px; color: #7F1D1D; line-height: 1.4;"></span>
                </div>

                <!-- Action Button -->
                <button type="button" onclick="closeModals()" style="margin-top: 24px; width: 100%; padding: 10px; background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#E5E7EB'" onmouseout="this.style.background='#F3F4F6'">
                    Close Profile
                </button>
            </div>
        </div>

    </div>



</body>
</html>