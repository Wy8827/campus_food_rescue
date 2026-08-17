<?php session_start();

    require_once __DIR__ . '/../../config/db.php';

        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT user_id, user_name, email, role, account_status FROM `user`");
        $stmt-> execute();
        $users = $stmt->fetchAll();
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
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>User Management</title>
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
                <div class="user-page-header">
                    <div class="user-page-header-left">
                        <h1 class="page-title">User Management</h1>
                        <p class="page-subtitle">Oversee system participants, adjust roles, and monitor engagement metrics across
                        the campus food rescue network.</p>
                    </div>

                    <div class="user-page-header-right">
                        <button class="export-button"><img src="../../assets/images/export.png" alt="Export Icon" class="export-button-img">Export List</button>
                        <button class="add-user-button"><img src="../../assets/images/adduser.png" alt="Add User Icon" class="add-user-button-img">Manual Add</button>
                    </div>
                </div>

                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search by name, email, or ID">
                    <select class="filter-selection">
                        <option value="all">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="student">Student</option>
                        <option value="food-provider">Food Provider</option>
                    </select>

                    <select class="filter-selection">
                        <option value="status">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="flagged">Flagged</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="user-list-container">
                    <table class="user-list-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($users)): ?>
                                <?php foreach($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['user_id']) ?></td>
                                        <td><?= htmlspecialchars($user['user_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['role']) ?></td>
                                        <td><?= htmlspecialchars($user['account_status']) ?></td>
                                        <td>
                                            <button class="edit-button">Edit</button>
                                            <button class="suspend-button">Suspend</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                
            </div>

        </div>
    </div>
    
</body>
</html>