<?php  
session_start();  
require_once __DIR__ . '/../../config/constants.php';  
require_once __DIR__ . '/../../config/session.php';  
require_once __DIR__ . '/../../config/db.php';  

requireRole('admin');   

// ----------------------------------------------------  
// 1. HANDLE EDIT USER FORM SUBMISSION (POST)
// ----------------------------------------------------  
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $editUserId = trim($_POST['user_id'] ?? '');
    $editName   = trim($_POST['user_name'] ?? '');
    $editEmail  = trim($_POST['email'] ?? '');
    $editRole   = $_POST['role'] ?? 'student';
    $editStatus = $_POST['account_status'] ?? 'active';
    $editNoShow = (int)($_POST['no_show_count'] ?? 0);

    // Reconstruct current query params for seamless redirection
    $redirectParams = [
        'search' => $_GET['search'] ?? '',
        'role'   => $_GET['role'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
        'page'   => $_GET['page'] ?? 1
    ];
    $redirectQuery = http_build_query(array_filter($redirectParams, fn($v) => $v !== '' && $v !== 'all'));

    if (!empty($editUserId) && !empty($editName) && !empty($editEmail)) {
        $updateStmt = mysqli_prepare($conn, "UPDATE `user` SET user_name = ?, email = ?, role = ?, account_status = ?, no_show_count = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($updateStmt, "ssssis", $editName, $editEmail, $editRole, $editStatus, $editNoShow, $editUserId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        // Redirect to prevent duplicate submission on page refresh
        header("Location: " . $_SERVER['PHP_SELF'] . ($redirectQuery ? '?' . $redirectQuery : ''));
        exit();
    }
}

// ----------------------------------------------------  
// 2. CAPTURE SEARCH & FILTER INPUTS  
// ----------------------------------------------------  
$search = isset($_GET['search']) ? trim($_GET['search']) : '';  
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';  
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';  

// Dynamic SQL query conditions  
$conditions = [];  
$params = [];  
$types = "";

if ($search !== '') {          
    $conditions[] = "(user_name LIKE ? OR email LIKE ? OR user_id = ?)";          
    $params[] = '%' . $search . '%';          
    $params[] = '%' . $search . '%';          
    $params[] = $search;      
    $types .= "sss"; 
}

if ($roleFilter !== 'all') {          
    $conditions[] = "role = ?";          
    $params[] = $roleFilter;      
    $types .= "s"; 
}

if ($statusFilter !== 'all') {          
    $conditions[] = "account_status = ?";          
    $params[] = $statusFilter;      
    $types .= "s"; 
}

$whereClause = "";  
if (count($conditions) > 0) {          
    $whereClause = " WHERE " . implode(" AND ", $conditions);  
}

// ----------------------------------------------------  
// 3. PAGINATION LOGIC  
// ----------------------------------------------------  
$limit = 10;  
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;  
if ($page < 1) $page = 1;  

// Count total records matching filter criteria
$countQuery = "SELECT COUNT(*) FROM `user`" . $whereClause; 
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
// 4. FETCH FILTERED DATA WITH ROLE-BASED SEQUENCING  
// ----------------------------------------------------  
$query = "SELECT user_id, user_name, email, role, account_status, no_show_count,                  
                 ROW_NUMBER() OVER (PARTITION BY role ORDER BY user_id ASC) AS role_seq           
          FROM `user` " . $whereClause . "            
          ORDER BY user_id ASC LIMIT ? OFFSET ?"; 

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
    <title>User Management - Campus Food Rescue</title> 
    <style>
        /* Modal Backdrop */
        .custom-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(17, 24, 39, 0.45);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
            box-sizing: border-box;
        }
        .custom-modal-backdrop.show {
            display: flex;
        }

        /* Modal Card Container */
        .custom-modal-card {
            background: #FFFFFF;
            width: 100%;
            max-width: 480px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Modal Header */
        .modal-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px solid #E5E7EB;
        }
        .modal-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 24px;
            line-height: 1;
            color: #9CA3AF;
            cursor: pointer;
        }
        .modal-close-btn:hover {
            color: #111827;
        }

        /* Modal Body */
        .modal-card-body {
            padding: 24px;
        }

        /* Modal Footer */
        .modal-card-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 16px 24px;
            background-color: #FAFAFA;
            border-top: 1px solid #E5E7EB;
        }

        /* View Details Specifics */
        .detail-profile-hero {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #F3F4F6;
        }
        .detail-name {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }
        .detail-meta {
            margin: 0;
            font-size: 13px;
            color: #6B7280;
        }
        .detail-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-item.full-width {
            grid-column: span 2;
        }
        .detail-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6B7280;
            margin-bottom: 4px;
        }
        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        /* Edit Form Controls */
        .modal-form-group {
            margin-bottom: 16px;
        }
        .modal-form-row {
            display: flex;
            gap: 12px;
        }
        .modal-form-group.half-width {
            flex: 1;
        }
        .modal-form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .modal-form-input {
            width: 100%;
            height: 40px;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            padding: 0 12px;
            font-size: 14px;
            color: #111827;
            box-sizing: border-box;
            outline: none;
            background-color: #FFFFFF;
        }
        .modal-form-input:focus {
            border-color: #275300;
        }
        .modal-form-input.disabled-input {
            background-color: #F3F4F6;
            color: #6B7280;
            cursor: not-allowed;
        }

        /* Modal Action Buttons */
        .btn-modal-cancel {
            height: 38px;
            padding: 0 16px;
            border-radius: 6px;
            border: 1px solid #D1D5DB;
            background: #FFFFFF;
            color: #374151;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-modal-cancel:hover {
            background-color: #F9FAFB;
        }
        .btn-modal-submit {
            height: 38px;
            padding: 0 18px;
            border-radius: 6px;
            border: none;
            background-color: #275300;
            color: #FFFFFF;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-modal-submit:hover {
            background-color: #1f4200;
        }
    </style>
</head> 
<body>     
    <div class="dashboard-container">         
        <!-- Sidebar Navigation -->
        <?php include '../../includes/sidebar.php'; ?>         
        <div class="main-content">             
            <!-- Topbar Header -->
            <div class="topbar-container">                 
                <?php include '../../includes/topbar.php'; ?>             
            </div>             
            <div class="content-container">                 
                <!-- Header Section -->
                <div class="user-page-header">                     
                    <div class="user-page-header-left">                         
                        <h1 class="page-title">User Management</h1>                         
                        <p class="page-subtitle">Oversee system participants, adjust roles, and monitor engagement metrics across the campus food rescue network.</p>                     
                    </div>                     
                    <div class="user-page-header-right">                         
                        <button type="button" class="export-button">
                            <ion-icon name="download-outline"></ion-icon>
                            <span>Export List</span>
                        </button>                         
                        <button type="button" class="add-user-button">
                            <ion-icon name="person-add-outline"></ion-icon>
                            <span>Manual Add</span>
                        </button>                     
                    </div>                 
                </div>                 

                <!-- Search and Filter Bar -->                 
                <form method="GET" action="" class="filter-toolbar-box">                     
                    <div class="search-input-wrapper">
                        <input type="text" name="search" class="search-input" placeholder="Search by name, email, or ID" value="<?= htmlspecialchars($search) ?>">                                          
                    </div>

                    <select name="role" class="filter-select-input" onchange="this.form.submit()">                         
                        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>                         
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>                         
                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>                         
                        <option value="provider" <?= $roleFilter === 'provider' ? 'selected' : '' ?>>Food Provider</option>                     
                    </select>                     

                    <select name="status" class="filter-select-input" onchange="this.form.submit()">                         
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>                         
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>                         
                        <option value="throttled" <?= $statusFilter === 'throttled' ? 'selected' : '' ?>>Throttled</option>                         
                        <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned</option>                     
                    </select>                                          
                </form>                 

                <!-- User Table Container -->
                <div class="table-card">                     
                    <table class="user-list-table">                         
                        <thead>                             
                            <tr>                                 
                                <th style="width: 28%;">USER PROFILE</th>                                 
                                <th style="width: 14%;">ROLE</th>                                 
                                <th style="width: 10%;">NO-SHOW</th>                                 
                                <th style="width: 16%;">CREDIT SCORE</th>                                 
                                <th style="width: 12%;">STATUS</th>                                 
                                <th style="width: 20%; text-align: right;">ACTIONS</th>                             
                            </tr>                         
                        </thead>                         
                        <tbody>                             
                            <?php if(!empty($users)): ?>                                 
                                <?php foreach($users as $user):                                      
                                    $statusLower = strtolower($user['account_status']);                                     
                                    $roleLower = strtolower($user['role']);                                                                          
                                    
                                    // Generate initials for avatar                                     
                                    $words = explode(" ", trim($user['user_name']));                                     
                                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));                                                                          
                                    
                                    // Credit score calculation based on no-show count                                    
                                    $noShow = isset($user['no_show_count']) ? (int)$user['no_show_count'] : 0;                                     
                                    $creditScore = max(0, 100 - ($noShow * 14));                                      
                                    $scoreColor = 'green';                                     
                                    if ($creditScore < 50) $scoreColor = 'red';                                     
                                    elseif ($creditScore < 80) $scoreColor = 'orange';                                                                          
                                    
                                    // Role icon and badge configuration                                     
                                    $roleConfig = [                                         
                                        'admin' => ['icon' => 'shield', 'prefix' => 'A', 'class' => 'role-badge-admin', 'label' => 'Admin'],                                         
                                        'provider' => ['icon' => 'restaurant', 'prefix' => 'P', 'class' => 'role-badge-provider', 'label' => 'Provider'],                                         
                                        'student' => ['icon' => 'school', 'prefix' => 'S', 'class' => 'role-badge-student', 'label' => 'Student'],                                     
                                    ];                                     
                                    $currentRole = $roleConfig[$roleLower] ?? ['icon' => 'person', 'prefix' => 'U', 'class' => 'role-badge-admin', 'label' => ucfirst($user['role'])];
                                    $displayId = $currentRole['prefix'] . $user['role_seq'];                                 
                                ?>                                     
                                    <tr>                                         
                                        <td>                                             
                                            <div class="user-profile-cell">                                                 
                                                <div class="avatar-circle avatar-<?= $roleLower ?>"><?= $initials ?></div>                                                 
                                                <div class="user-info-text">                                                     
                                                    <div class="user-name-title"><?= htmlspecialchars($user['user_name']) ?></div>                                                     
                                                    <div class="user-meta-subtitle"><?= $displayId ?> &bull; <?= htmlspecialchars($user['email']) ?></div>                                                 
                                                </div>                                             
                                            </div>                                         
                                        </td>                                         
                                        <td>                                             
                                            <div class="role-badge-box <?= $currentRole['class'] ?>">                                                 
                                                <ion-icon name="<?= $currentRole['icon'] ?>"></ion-icon>                                                 
                                                <span><?= $currentRole['label'] ?></span>                                             
                                            </div>                                         
                                        </td>                                         
                                        <td>
                                            <span class="noshow-value"><?= $noShow ?></span>
                                        </td>                                         
                                        <td>                                             
                                            <div class="credit-score-widget">                                                 
                                                <div class="progress-track">                                                     
                                                    <div class="progress-fill fill-<?= $scoreColor ?>" style="width: <?= $creditScore ?>%;"></div>                                                 
                                                </div>                                                 
                                                <span class="score-label text-<?= $scoreColor ?>"><?= $creditScore ?>%</span>                                             
                                            </div>                                         
                                        </td>                                         
                                        <td>                                             
                                            <div class="status-indicator status-<?= $statusLower ?>">                                                 
                                                <span class="dot"></span>                                                 
                                                <span><?= ucfirst(htmlspecialchars($user['account_status'])) ?></span>                                             
                                            </div>                                         
                                        </td>                                         
                                        <td>                                             
                                            <div class="action-btn-group">                                                 
                                                <!-- Trigger Edit User Modal -->
                                                <button type="button" class="btn-edit-user"
                                                    data-id="<?= htmlspecialchars($user['user_id']) ?>"
                                                    data-display-id="<?= htmlspecialchars($displayId) ?>"
                                                    data-name="<?= htmlspecialchars($user['user_name']) ?>"
                                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                                    data-role="<?= htmlspecialchars($user['role']) ?>"
                                                    data-status="<?= htmlspecialchars($user['account_status']) ?>"
                                                    data-noshow="<?= htmlspecialchars($noShow) ?>"
                                                    onclick="openEditModal(this)">
                                                    Edit User
                                                </button>                                                 

                                                <!-- Trigger View Details Modal -->
                                                <button type="button" class="btn-view-details"
                                                    data-id="<?= htmlspecialchars($user['user_id']) ?>"
                                                    data-display-id="<?= htmlspecialchars($displayId) ?>"
                                                    data-name="<?= htmlspecialchars($user['user_name']) ?>"
                                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                                    data-role="<?= htmlspecialchars($user['role']) ?>"
                                                    data-status="<?= htmlspecialchars($user['account_status']) ?>"
                                                    data-noshow="<?= htmlspecialchars($noShow) ?>"
                                                    data-score="<?= htmlspecialchars($creditScore) ?>"
                                                    data-score-color="<?= htmlspecialchars($scoreColor) ?>"
                                                    data-initials="<?= htmlspecialchars($initials) ?>"
                                                    data-role-label="<?= htmlspecialchars($currentRole['label']) ?>"
                                                    onclick="openDetailModal(this)">
                                                    View Details
                                                </button>                                             
                                            </div>                                         
                                        </td>                                     
                                    </tr>                                 
                                <?php endforeach; ?>                             
                            <?php else: ?>                                 
                                <tr>                                     
                                    <td colspan="6" class="table-empty-cell">No users found matching your search criteria.</td>                                 
                                </tr>                             
                            <?php endif; ?>                         
                        </tbody>                     
                    </table>                     

                    <!-- Pagination Footer -->                     
                    <div class="table-pagination-footer">                         
                        <span class="pagination-summary">Showing <?= $from ?>-<?= $to ?> of <?= $totalUsers ?> Users</span>                         
                        <div class="pagination-controls">                             
                            <?php if ($page > 1): ?>                                 
                                <a href="?page=<?= $page - 1 ?>&<?= $queryString ?>" class="page-nav-btn">&lt;</a>                             
                            <?php else: ?>                                 
                                <span class="page-nav-btn disabled">&lt;</span>                             
                            <?php endif; ?>                             
                            
                            <span class="current-page-indicator"><b><?= $page ?></b> / <?= $totalPages ?></span>                             
                            
                            <?php if ($page < $totalPages): ?>                                 
                                <a href="?page=<?= $page + 1 ?>&<?= $queryString ?>" class="page-nav-btn">&gt;</a>                             
                            <?php else: ?>                                 
                                <span class="page-nav-btn disabled">&gt;</span>                             
                            <?php endif; ?>                         
                        </div>                     
                    </div>                 
                </div>             
            </div>         
        </div>     
    </div> 

    <!-- ===================================================== -->
    <!-- 1. VIEW USER DETAILS MODAL                            -->
    <!-- ===================================================== -->
    <div id="detailModal" class="custom-modal-backdrop" onclick="handleBackdropClick(event, 'detailModal')">
        <div class="custom-modal-card">
            <div class="modal-card-header">
                <h2 class="modal-card-title">User Details</h2>
                <button type="button" class="modal-close-btn" onclick="closeModal('detailModal')">&times;</button>
            </div>
            <div class="modal-card-body">
                <div class="detail-profile-hero">
                    <div id="detailAvatar" class="avatar-circle" style="width: 54px; height: 54px; font-size: 18px;"></div>
                    <div class="detail-hero-info">
                        <h3 id="detailName" class="detail-name"></h3>
                        <p id="detailMeta" class="detail-meta"></p>
                    </div>
                </div>

                <div class="detail-info-grid">
                    <div class="detail-item">
                        <span class="detail-label">System User ID</span>
                        <span id="detailUserId" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Role</span>
                        <span id="detailRole" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Account Status</span>
                        <span id="detailStatus" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">No-Show Count</span>
                        <span id="detailNoShow" class="detail-value"></span>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label">Credit Score</span>
                        <div class="credit-score-widget" style="margin-top: 6px;">
                            <div class="progress-track" style="width: 120px; height: 8px;">
                                <div id="detailScoreFill" class="progress-fill" style="width: 100%;"></div>
                            </div>
                            <span id="detailScoreText" class="score-label"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-card-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('detailModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- 2. EDIT USER MODAL                                    -->
    <!-- ===================================================== -->
    <div id="editModal" class="custom-modal-backdrop" onclick="handleBackdropClick(event, 'editModal')">
        <div class="custom-modal-card">
            <div class="modal-card-header">
                <h2 class="modal-card-title">Edit User</h2>
                <button type="button" class="modal-close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="modal-card-body">
                    <div class="modal-form-group">
                        <label class="modal-form-label">Display ID</label>
                        <input type="text" id="editDisplayId" class="modal-form-input disabled-input" readonly>
                    </div>

                    <div class="modal-form-group">
                        <label class="modal-form-label" for="editUserName">User Name</label>
                        <input type="text" name="user_name" id="editUserName" class="modal-form-input" required>
                    </div>

                    <div class="modal-form-group">
                        <label class="modal-form-label" for="editEmail">Email Address</label>
                        <input type="email" name="email" id="editEmail" class="modal-form-input" required>
                    </div>

                    <div class="modal-form-row">
                        <div class="modal-form-group half-width">
                            <label class="modal-form-label" for="editRole">Role</label>
                            <select name="role" id="editRole" class="modal-form-input">
                                <option value="student">Student</option>
                                <option value="provider">Food Provider</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="modal-form-group half-width">
                            <label class="modal-form-label" for="editStatus">Status</label>
                            <select name="account_status" id="editStatus" class="modal-form-input">
                                <option value="active">Active</option>
                                <option value="throttled">Throttled</option>
                                <option value="banned">Banned</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-form-group">
                        <label class="modal-form-label" for="editNoShow">No-Show Count</label>
                        <input type="number" name="no_show_count" id="editNoShow" class="modal-form-input" min="0" required>
                    </div>
                </div>

                <div class="modal-card-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================== -->
    <!-- JAVASCRIPT CONTROLLERS                                -->
    <!-- ===================================================== -->
    <script>
    // Open and populate View Details modal
    function openDetailModal(btn) {
        const data = btn.dataset;

        document.getElementById('detailName').innerText = data.name;
        document.getElementById('detailMeta').innerText = `${data.displayId} • ${data.email}`;
        document.getElementById('detailUserId').innerText = data.id;
        document.getElementById('detailRole').innerText = data.roleLabel;
        document.getElementById('detailStatus').innerText = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        document.getElementById('detailNoShow').innerText = data.noshow;

        // Avatar configuration
        const avatar = document.getElementById('detailAvatar');
        avatar.innerText = data.initials;
        avatar.className = `avatar-circle avatar-${data.role.toLowerCase()}`;

        // Credit score bar and text styling
        const fill = document.getElementById('detailScoreFill');
        fill.style.width = data.score + '%';
        fill.className = `progress-fill fill-${data.scoreColor}`;

        const scoreText = document.getElementById('detailScoreText');
        scoreText.innerText = data.score + '%';
        scoreText.className = `score-label text-${data.scoreColor}`;

        document.getElementById('detailModal').classList.add('show');
    }

    // Open and populate Edit User modal
    function openEditModal(btn) {
        const data = btn.dataset;

        document.getElementById('editUserId').value = data.id;
        document.getElementById('editDisplayId').value = data.displayId;
        document.getElementById('editUserName').value = data.name;
        document.getElementById('editEmail').value = data.email;
        document.getElementById('editRole').value = data.role.toLowerCase();
        document.getElementById('editStatus').value = data.status.toLowerCase();
        document.getElementById('editNoShow').value = data.noshow;

        document.getElementById('editModal').classList.add('show');
    }

    // Close specified modal
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }

    // Close modal when clicking on backdrop
    function handleBackdropClick(event, modalId) {
        if (event.target.id === modalId) {
            closeModal(modalId);
        }
    }

    // Close open modals on Escape key press
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal('detailModal');
            closeModal('editModal');
        }
    });
    </script>
</body> 
</html>