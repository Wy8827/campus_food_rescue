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
    $editUserId   = trim($_POST['user_id'] ?? '');
    $editName     = trim($_POST['user_name'] ?? '');
    $editEmail    = trim($_POST['email'] ?? '');
    $editRole     = $_POST['role'] ?? 'student';
    $editStatus   = $_POST['account_status'] ?? 'active';
    $editNoShow   = (int)($_POST['no_show_count'] ?? 0);
    $editQuestion = trim($_POST['security_question'] ?? '');
    $editAnswer   = trim($_POST['security_answer'] ?? '');

    // Reconstruct current query params for seamless redirection
    $redirectParams = [
        'search' => $_GET['search'] ?? '',
        'role'   => $_GET['role'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
        'page'   => $_GET['page'] ?? 1
    ];
    $redirectQuery = http_build_query(array_filter($redirectParams, fn($v) => $v !== '' && $v !== 'all'));

    if (!empty($editUserId) && !empty($editName) && !empty($editEmail)) {
        // Check if admin is resetting security credentials (both question and answer provided)
        if (!empty($editQuestion) && !empty($editAnswer)) {
            $answerHash = password_hash(strtolower($editAnswer), PASSWORD_DEFAULT);
            $updateStmt = mysqli_prepare(
                $conn, 
                "UPDATE `user` SET user_name = ?, email = ?, role = ?, account_status = ?, no_show_count = ?, security_question = ?, security_answer = ? WHERE user_id = ?"
            );
            mysqli_stmt_bind_param($updateStmt, "ssssissi", $editName, $editEmail, $editRole, $editStatus, $editNoShow, $editQuestion, $answerHash, $editUserId);
        } elseif (!empty($editQuestion)) {
            // Update question only if answer was left unchanged
            $updateStmt = mysqli_prepare(
                $conn, 
                "UPDATE `user` SET user_name = ?, email = ?, role = ?, account_status = ?, no_show_count = ?, security_question = ? WHERE user_id = ?"
            );
            mysqli_stmt_bind_param($updateStmt, "ssssisi", $editName, $editEmail, $editRole, $editStatus, $editNoShow, $editQuestion, $editUserId);
        } else {
            // Standard user profile update without altering security credentials
            $updateStmt = mysqli_prepare(
                $conn, 
                "UPDATE `user` SET user_name = ?, email = ?, role = ?, account_status = ?, no_show_count = ? WHERE user_id = ?"
            );
            mysqli_stmt_bind_param($updateStmt, "ssssii", $editName, $editEmail, $editRole, $editStatus, $editNoShow, $editUserId);
        }

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
$roleFilter = $_GET['role'] ?? 'all';  
$statusFilter = $_GET['status'] ?? 'all';  

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
$query = "SELECT user_id, user_name, email, role, account_status, no_show_count, security_question,                 
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

// Standard security questions list
$securityQuestionsList = [
    'What is your favourite food?',
    "What was your first pet's name?",
    'What city were you born in?',
    "What is your mother's maiden name?"
];
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
    <link rel="stylesheet" href="../../assets/css/moderation.css">    
    <link rel="stylesheet" href="../../assets/css/userManagement.css">     
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>     
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>     
    <title>User Management - Campus Food Rescue</title> 
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
                                                    data-question="<?= htmlspecialchars($user['security_question'] ?? '') ?>"
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
                                                    data-question="<?= htmlspecialchars($user['security_question'] ?? 'Not set') ?>"
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
                        <span class="detail-label">Security Question</span>
                        <span id="detailQuestion" class="detail-value" style="color: #275300;"></span>
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

                    <!-- Reset Security Credentials Section -->
                    <div class="form-section-divider">
                        <span>Security Credentials Reset</span>
                        <hr>
                    </div>

                    <div class="modal-form-group">
                        <label class="modal-form-label" for="editSecurityQuestion">Security Question</label>
                        <select name="security_question" id="editSecurityQuestion" class="modal-form-input">
                            <option value="">-- Keep or Select Security Question --</option>
                            <?php foreach ($securityQuestionsList as $question): ?>
                                <option value="<?= htmlspecialchars($question) ?>"><?= htmlspecialchars($question) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-form-group">
                        <label class="modal-form-label" for="editSecurityAnswer">New Security Answer</label>
                        <input type="text" name="security_answer" id="editSecurityAnswer" class="modal-form-input" placeholder="Enter new answer (leave blank to keep unchanged)">
                        <div class="form-hint">Only type here if the user requested a security answer reset.</div>
                    </div>
                </div>

                <div class="modal-card-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/userManagement.js"></script>
</body> 
</html>