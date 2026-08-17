<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/moderation.css">
    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>
    <title>List Moderation</title>
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
                <h1 class="page-title">Listing Moderation</h1>
                <p class="page-subtitle">Approve or reject incoming food donations from campus vendors. Ensure all safety standards and
                dietary tags are correctly applied before publishing.</p>

                <div class="toolbar-container">
                    <div class="selection-container">
                        <select class="filter-select">
                            <option value="all">Category: All</option>
                            <option value="vegetarian">Vegetarian</option>
                            <option value="fruit">Fruit</option>
                        </select>

                        <select class="filter-select">
                            <option value="urgency">Urgency: All</option>
                            <option value="high">High (Today)</option>
                            <option value="medium">Medium (Tomorrow)</option>
                            <option value="low">Low (Later)</option>
                        </select>

                        <select class="filter-select">
                            <option value="status">Status: All</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="view-toggle-buttons">
                        <button class="toggle-btn"><ion-icon name="list-outline"></ion-icon></button>
                        <button class="toggle-btn active"><ion-icon name="grid-outline"></ion-icon></button>
                    </div>
                </div>

                <ul class="moderation-list">
                    <li class="moderation-list-item">
                        <article class="listing-card">
                            <div class="food-image-container">
                                <img src="../../uploads/food/nasi lemak.jpg" alt="Food Image" class="food-image">
                            </div> 

                            <div class="food-info-container">
                                <div class="card-header-row">
                                    <span class="item-code">#FR-2931</span>
                                    <span class="urgent-badge badge-urgent"> Expires in 45m</span>
                                </div>

                                <h3 class="listing-title">Bulk Salad Components</h3>
                                
                                <div class="location-row">
                                    <ion-icon name="location-outline"></ion-icon>
                                    <span class="location">North Campus Student Union</span>
                                </div>

                                <div class="meta-details">
                                    <span class="detail-text">Prepared Meals • 15 Trays •</span>
                                    <span class="diet-tag">Vegetarian</span>
                                </div>

                                <div class="card-divider"></div>

                                <div class="action-buttons">
                                    <button class="approve-button">Approve</button>
                                    <button class="reject-button">Reject</button>
                                    <button class="flag-icon-btn"><ion-icon name="flag-outline"></ion-icon></button>
                                </div>
                            </div>
                            
                        </article>
                    </li>

                    <li class="moderation-list-item">
                        <article class="listing-card">
                            <div class="food-image-container">
                                <img src="../../uploads/food/nasi lemak.jpg" alt="Food Image" class="food-image">
                            </div> 

                            <div class="food-info-container">
                                <div class="card-header-row">
                                    <span class="item-code">#FR-2931</span>
                                    <span class="urgent-badge badge-normal"></ion-icon> Expires in 24h</span>
                                </div>

                                <h3 class="listing-title">Bulk Salad Components</h3>
                                
                                <div class="location-row">
                                    <ion-icon name="location-outline"></ion-icon>
                                    <span class="location">Main Dining Commons</span>
                                </div>

                                <div class="meta-details">
                                    <span class="detail-text">Perishable • 50 lbs •</span>
                                    <span class="diet-tag">Vegan</span>
                                </div>

                                <div class="card-divider"></div>

                                <div class="action-buttons">
                                    <button class="approve-button">Approve</button>
                                    <button class="reject-button">Reject</button>
                                    <button class="flag-icon-btn"><ion-icon name="flag-outline"></ion-icon></button>
                                </div>
                            </div>
                            
                        </article>
                    </li>

                    <li class="moderation-list-item">
                        <article class="listing-card">
                            <div class="food-image-container">
                                <img src="../../uploads/food/nasi lemak.jpg" alt="Food Image" class="food-image">
                            </div> 

                            <div class="food-info-container">
                                <div class="card-header-row">
                                    <span class="item-code">#FR-2931</span>
                                    <span class="urgent-badge badge-normal"></ion-icon> Expires in 24h</span>
                                </div>

                                <h3 class="listing-title">Bulk Salad Components</h3>
                                
                                <div class="location-row">
                                    <ion-icon name="location-outline"></ion-icon>
                                    <span class="location">Main Dining Commons</span>
                                </div>

                                <div class="meta-details">
                                    <span class="detail-text">Perishable • 50 lbs •</span>
                                    <span class="diet-tag">Vegan</span>
                                </div>

                                <div class="card-divider"></div>

                                <div class="action-buttons">
                                    <button class="approve-button">Approve</button>
                                    <button class="reject-button">Reject</button>
                                    <button class="flag-icon-btn"><ion-icon name="flag-outline"></ion-icon></button>
                                </div>
                            </div>
                            
                        </article>
                    </li>
                </ul>
            </div>

        </div>
    </div>
    
</body>
</html>