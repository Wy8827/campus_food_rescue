<?php 
session_start();

require_once __DIR__ . '/../../config/db.php';
$pdo = getDB();

// ----------------------------------------------------
// 1. CAPTURE SEARCH & FILTER INPUTS
// ----------------------------------------------------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build Dynamic SQL Query Conditions
$conditions = [];
$params = [];

if ($search !== '') {
    // Search by name, email, or exact ID
    $conditions[] = "(user_name LIKE :search OR email LIKE :search OR user_id = :search_id)";
    $params[':search'] = '%' . $search . '%';
    $params[':search_id'] = $search; // Assuming ID is an exact match
}

if ($roleFilter !== 'all') {
    $conditions[] = "role = :role";
    $params[':role'] = $roleFilter;
}

if ($statusFilter !== 'all') {
    $conditions[] = "account_status = :status";
    $params[':status'] = $statusFilter;
}

// Create the WHERE clause string
$whereClause = "";
if (count($conditions) > 0) {
    $whereClause = " WHERE " . implode(" AND ", $conditions);
}

// ----------------------------------------------------
// 2. PAGINATION LOGIC
// ----------------------------------------------------
$limit = 10; // Number of users per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Get the total number of filtered users
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM `user`" . $whereClause);
foreach($params as $key => $val) {
    $totalStmt->bindValue($key, $val);
}
$totalStmt->execute();
$totalUsers = (int)$totalStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalUsers / $limit));
if ($page > $totalPages && $totalUsers > 0) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

// ----------------------------------------------------
// 3. FETCH FILTERED DATA
// ----------------------------------------------------
$stmt = $pdo->prepare("SELECT user_id, user_name, email, role, account_status, no_show_count 
                       FROM `user` 
                       " . $whereClause . " 
                       ORDER BY user_id ASC 
                       LIMIT :limit OFFSET :offset");

// Bind search parameters
foreach($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
// Bind pagination parameters
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$from = $totalUsers > 0 ? $offset + 1 : 0;
$to = min($offset + $limit, $totalUsers);

// Build query string for pagination links (so filters aren't lost on page 2)
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
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/userManagement.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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

                <!-- UPDATED: Changed div to form and added onchange auto-submit -->
                <form method="GET" action="" class="search-container">
                    <!-- Search input retains its value after submission -->
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email, or ID" value="<?= htmlspecialchars($search) ?>">
                    
                    <!-- Dropdowns auto-submit the form when changed -->
                    <select name="role" class="filter-selection" onchange="this.form.submit()">
                        <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="provider" <?= $roleFilter === 'provider' ? 'selected' : '' ?>>Food Provider</option>
                    </select>

                    <select name="status" class="filter-selection" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Throttled" <?= $statusFilter === 'Throttled' ? 'selected' : '' ?>>Throttled</option>
                        <option value="Banned" <?= $statusFilter === 'Banned' ? 'selected' : '' ?>>Banned</option>
                    </select>
                    
                    <!-- Hidden submit button so pressing 'Enter' in the text box works naturally -->
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
                                    
                                    $words = explode(" ", $user['user_name']);
                                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                                    
                                    $noShow = isset($user['no_show_count']) ? (int)$user['no_show_count'] : 0;
                                    $creditScore = max(0, 100 - ($noShow * 14)); 

                                    $scoreColor = 'green';
                                    if ($creditScore < 50) $scoreColor = 'red';
                                    elseif ($creditScore < 80) $scoreColor = 'orange';
                                    
                                    $noShowClass = $noShow >= 2 ? 'no-show-high' : '';

                                    $roleIcon = [
                                        'admin' => '<ion-icon name="shield-half-outline"></ion-icon>',
                                        'provider' => '<ion-icon name="restaurant-outline"></ion-icon>',
                                        'student' => '<ion-icon name="school-outline"></ion-icon>',
                                    ];
                                ?>
                                    <tr class="row-<?= $statusLower ?>">
                                        <td>
                                            <div class="user-profile">
                                                <div class="avatar avatar-<?= $roleLower ?>"><?= $initials ?></div>
                                                <div class="user-info">
                                                    <div class="user-name"><?= htmlspecialchars($user['user_name']) ?></div>
                                                    <div class="user-meta"><?= htmlspecialchars($user['user_id']) ?> &bull; <?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= $roleLower ?>">
                                                <span class="role-icon"><?= $roleIcon[$roleLower] ?? '' ?></span>
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
                                                <button class="edit-button">Edit User</button>
                                                <button class="view-button">View Details</button>
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

                    <!-- UPDATED: Pagination now includes search parameters -->
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
</body>
</html>