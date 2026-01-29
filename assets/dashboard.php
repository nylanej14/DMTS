<?php
// dashboard.php - Main Dashboard for Document Management & Tracking System

// ============================================
// SESSION & AUTHENTICATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// dashboard.php - Add this right after session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session variables are set properly
if (!isset($_SESSION['full_name']) && isset($_SESSION['Firstname']) && isset($_SESSION['Lastname'])) {
    $_SESSION['full_name'] = $_SESSION['Firstname'] . ' ' . $_SESSION['Lastname'];
}

if (!isset($_SESSION['department']) && isset($_SESSION['Department'])) {
    $_SESSION['department'] = $_SESSION['Department'];
}

if (!isset($_SESSION['user_role']) && isset($_SESSION['UserAccess'])) {
    $_SESSION['user_role'] = $_SESSION['UserAccess'];
}

if (!isset($_SESSION['username']) && isset($_SESSION['UserID'])) {
    $_SESSION['username'] = $_SESSION['UserID'];
}

// Get user info from session
$userInfo = [
    'username' => $_SESSION['username'] ?? '',
    'full_name' => $_SESSION['full_name'] ?? '',
    'user_role' => $_SESSION['user_role'] ?? 'User',
    'access_level' => $_SESSION['access_level'] ?? '',
    'department' => $_SESSION['department'] ?? 'Not Assigned',
    'position' => $_SESSION['position'] ?? 'Not Assigned'
];

$isAdmin = ($userInfo['user_role'] === 'Admin' || $userInfo['user_role'] === 'Administrator');

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Redirect to login.php for proper logout logging
    header('Location: login.php?action=logout');
    exit;
}

// Database connection for stats
$serverName = "10.0.0.4\PGR2022";
$connectionOptions = array(
    "Database" => "PGR-DMTS",
    "Uid" => "PRG_AdminUser",
    "PWD" => "PRG!ESXiAdminProd#2025$",
    "CharacterSet" => "UTF-8",
    "TrustServerCertificate" => true,
    "Encrypt" => false
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

// Get OBR statistics
$totalObr = 0;
$pendingObr = 0;
$completedObr = 0;
$myObr = 0;

if ($conn) {
    // Get total OBR count
    $sql = "SELECT COUNT(*) as total FROM OBR";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $totalObr = $row['total'];
    }
    
    // Get pending OBR count
    $sql = "SELECT COUNT(*) as pending FROM OBR WHERE Status IN ('Draft', 'Pending')";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $pendingObr = $row['pending'];
    }
    
    // Get completed OBR count
    $sql = "SELECT COUNT(*) as completed FROM OBR WHERE Status = 'Completed'";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $completedObr = $row['completed'];
    }
    
    // Get my OBR count
    $sql = "SELECT COUNT(*) as myobr FROM OBR WHERE CreatedBy = ?";
    $params = array($userInfo['full_name']);
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $myObr = $row['myobr'];
    }
    
    sqlsrv_close($conn);
}

// ============================================
// HTML OUTPUT
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Document Management & Tracking System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <style>
        :root {
            --tiffany: #A7E4D5;
            --turq: #30D5C8;
            --navy: #09324A;
            --light-navy: #0A4A6F;
            --sidebar-width: 260px;
            --header-height: 70px;
            --gradient-bg: linear-gradient(135deg, var(--tiffany) 0%, #bff0ea 30%, var(--turq) 100%);
            --gradient-dark: linear-gradient(135deg, var(--navy) 0%, #0A4A6F 100%);
            --gradient-card: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            --shadow: 0 5px 15px rgba(11, 35, 50, 0.1);
            --shadow-hover: 0 10px 25px rgba(11, 35, 50, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8f9fa;
            color: var(--navy);
            overflow-x: hidden;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Header */
        .header {
            background: white;
            padding: 15px 0;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--navy);
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-header {
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-logout {
            background: transparent;
            color: var(--navy);
            border: 2px solid var(--navy);
        }
        
        .btn-logout:hover {
            background: var(--navy);
            color: white;
        }
        
        .btn-new {
            background: var(--navy);
            color: white;
            border: 2px solid var(--navy);
        }
        
        .btn-new:hover {
            background: var(--light-navy);
            border-color: var(--light-navy);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-top: 4px solid var(--turq);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--turq);
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--navy);
            margin: 0;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--navy);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-btn:hover {
            border-color: var(--tiffany);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .action-icon {
            font-size: 2rem;
            color: var(--turq);
            margin-bottom: 10px;
        }
        
        .action-label {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Welcome Message */
        .welcome-card {
            background: linear-gradient(135deg, var(--navy) 0%, var(--light-navy) 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .welcome-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .welcome-text {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        /* Admin-specific styles */
        .admin-only {
            border-left: 4px solid #dc3545;
        }
        
        .list-group-item {
            border: none;
            border-bottom: 1px solid #eee;
            padding: 12px 15px;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-menu {
            border-radius: 10px;
            box-shadow: var(--shadow);
            border: none;
        }
        
        .dropdown-item {
            padding: 8px 15px;
        }
        
        .dropdown-header {
            font-weight: 600;
            color: var(--navy);
        }
        
        /* OBR Specific Styles */
        .obr-status-badge {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-pending { background: #ffc107; color: black; }
        .status-approved { background: #198754; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-completed { background: #0d6efd; color: white; }
        
        /* NEW: OBR Quick Access Styles */
        .obr-quick-access {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .obr-quick-btn {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px 10px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: var(--navy);
        }
        
        .obr-quick-btn:hover {
            border-color: var(--turq);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .obr-quick-icon {
            font-size: 1.5rem;
            color: var(--turq);
            margin-bottom: 8px;
        }
        
        .obr-quick-label {
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* In dashboard.php - Add to existing styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding-top: 70px; /* Make room for mobile toggle button */
            }
        }

    </style>
</head>
<body>
    <!-- Include Dashboard Sidebar -->
    <?php include 'dashboard_sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h1 class="welcome-title">Welcome, <?php echo htmlspecialchars(explode(' ', $userInfo['full_name'])[0]); ?>!</h1>
            <p class="welcome-text">
                Welcome to the Document Management & Tracking System. 
                <?php echo $isAdmin ? 'As an administrator, you have full access to manage documents, users, and system settings.' : 'You can submit, track, and manage your documents here.'; ?>
            </p>
            <div class="d-flex gap-3">
                <button class="btn btn-light" onclick="window.location.href='create-obr.php'">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Create OBR
                </button>
                <button class="btn btn-outline-light" onclick="window.location.href='track-obr.php'">
                    <i class="fas fa-search-location me-2"></i>Track Document
                </button>
            </div>
        </div>

        <!-- Header -->
        <header class="header">
            <div>
                <h1 class="page-title">Document Management Dashboard</h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?>
                    | <i class="fas fa-user me-1 ms-2"></i> <?php echo htmlspecialchars($userInfo['department']); ?>
                </p>
            </div>
            
            <div class="header-actions">
                <button class="btn btn-new" onclick="window.location.href='create-obr.php'">
                    <i class="fas fa-file-invoice-dollar"></i> New OBR
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger"><?php echo $pendingObr; ?></span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="my-obrs.php"><small>You have <?php echo $myObr; ?> OBR document(s)</small></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="track-obr.php"><small>Track your documents</small></a></li>
                        <li><a class="dropdown-item" href="create-obr.php"><small>Create new OBR</small></a></li>
                    </ul>
                </div>
                
                <a href="track-obr.php" class="btn btn-outline-primary">
                    <i class="fas fa-search-location"></i> Track Document
                </a>
                
                <a href="?action=logout" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value"><?php echo $totalObr; ?></div>
                <div class="stat-label">Total OBR Documents</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $pendingObr; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $completedObr; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-value"><?php echo $myObr; ?></div>
                <div class="stat-label">My OBR Documents</div>
            </div>
        </div>

        <!-- NEW: OBR Quick Access Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-bolt me-2"></i>OBR Quick Access
                </h2>
            </div>
            
            <div class="obr-quick-access">
                <a href="create-obr.php" class="obr-quick-btn">
                    <div class="obr-quick-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="obr-quick-label">Create OBR</div>
                </a>
                
                <a href="track-obr.php" class="obr-quick-btn">
                    <div class="obr-quick-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="obr-quick-label">Track Document</div>
                </a>
                
                <a href="my-obrs.php" class="obr-quick-btn">
                    <div class="obr-quick-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="obr-quick-label">My OBRs</div>
                </a>
                
                <a href="view-obr.php" class="obr-quick-btn">
                    <div class="obr-quick-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="obr-quick-label">View OBR</div>
                </a>
                
                <a href="print-obr.php" class="obr-quick-btn">
                    <div class="obr-quick-icon">
                        <i class="fas fa-print"></i>
                    </div>
                    <div class="obr-quick-label">Print OBR</div>
                </a>
                
                <a href="received-obrs.php" class="obr-quick-btn">
                    <div class="obr-quick-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="obr-quick-label">Received Docs</div>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-lg-8">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h2>
                        <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='quick_actions.php'">
                            View All
                        </button>
                    </div>
                    
                    <div class="quick-actions">
                        <div class="action-btn" onclick="window.location.href='create-obr.php'">
                            <div class="action-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="action-label">Create OBR</div>
                            <small class="text-muted">Obligation Request</small>
                        </div>
                        
                        <div class="action-btn" onclick="window.location.href='my-obrs.php'">
                            <div class="action-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="action-label">My OBRs</div>
                            <small class="text-muted">View your documents</small>
                        </div>
                        
                        <div class="action-btn" onclick="window.location.href='track-obr.php'">
                            <div class="action-icon">
                                <i class="fas fa-search-location"></i>
                            </div>
                            <div class="action-label">Track Document</div>
                            <small class="text-muted">Public tracking</small>
                        </div>
                        
                        <div class="action-btn" onclick="window.location.href='documents.php'">
                            <div class="action-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="action-label">All Documents</div>
                            <small class="text-muted">System documents</small>
                        </div>
                        
                        <div class="action-btn" onclick="window.location.href='reports.php'">
                            <div class="action-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="action-label">Reports</div>
                            <small class="text-muted">Analytics & stats</small>
                        </div>
                        
                        <div class="action-btn" onclick="window.location.href='profile.php'">
                            <div class="action-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <div class="action-label">My Profile</div>
                            <small class="text-muted">Update information</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent OBR Activity -->
            <div class="col-lg-4">
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-history me-2"></i>Recent OBR Activity
                        </h2>
                        <button class="btn btn-sm btn-outline-primary" onclick="window.location.href='my-obrs.php'">
                            View All
                        </button>
                    </div>
                    
                    <div class="list-group">
                        <?php
                        // Get recent OBRs for this user
                        if ($conn = sqlsrv_connect($serverName, $connectionOptions)) {
                            $sql = "SELECT TOP 3 * FROM OBR WHERE CreatedBy = ? ORDER BY Datetime DESC";
                            $params = array($userInfo['full_name']);
                            $stmt = sqlsrv_query($conn, $sql, $params);
                            
                            if ($stmt && sqlsrv_has_rows($stmt)) {
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $statusClass = 'status-' . strtolower($row['Status']);
                                    echo '
                                    <a href="view-obr.php?id=' . $row['OBRID'] . '" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">' . htmlspecialchars($row['Payee']) . '</h6>
                                            <small>' . $row['Datetime']->format('M j') . '</small>
                                        </div>
                                        <p class="mb-1">' . htmlspecialchars(substr($row['Particulars'], 0, 50)) . '...</p>
                                        <span class="badge obr-status-badge ' . $statusClass . '">' . $row['Status'] . '</span>
                                        <small class="text-muted">' . htmlspecialchars($row['TrackingNumber']) . '</small>
                                    </a>';
                                }
                            } else {
                                echo '<div class="text-center py-3">
                                    <i class="fas fa-inbox fa-2x mb-2 text-muted"></i>
                                    <p class="text-muted mb-0">No OBR documents yet</p>
                                    <a href="create-obr.php" class="btn btn-sm btn-primary mt-2">Create Your First OBR</a>
                                </div>';
                            }
                            sqlsrv_close($conn);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Dashboard (Visible only to admins) -->
        <?php if ($isAdmin): ?>
        <div class="content-section mt-4 admin-only">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-user-shield me-2"></i>Administrator Panel
                </h2>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Administrator Access:</strong> You have full system access. 
                        Use the navigation menu to access user management, system settings, and advanced reports.
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-users text-primary me-2"></i>
                                User Management
                            </h5>
                            <p class="card-text">Manage system users, roles, and permissions.</p>
                            <a href="admin-users.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-arrow-right me-1"></i> Go to Users
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-cog text-success me-2"></i>
                                System Settings
                            </h5>
                            <p class="card-text">Configure system preferences and options.</p>
                            <a href="settings.php" class="btn btn-success btn-sm">
                                <i class="fas fa-arrow-right me-1"></i> Go to Settings
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-signature text-warning me-2"></i>
                                Signatories
                            </h5>
                            <p class="card-text">Manage document signatories and approval flow.</p>
                            <a href="admin-signatories.php" class="btn btn-warning btn-sm">
                                <i class="fas fa-arrow-right me-1"></i> Go to Signatories
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-file-invoice-dollar text-info me-2"></i>
                                OBR Reports
                            </h5>
                            <p class="card-text">View OBR statistics and analytics.</p>
                            <a href="obr_reports.php" class="btn btn-info btn-sm">
                                <i class="fas fa-arrow-right me-1"></i> OBR Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
        $(document).ready(function() {
            // Handle responsive menu toggle
            $(window).resize(function() {
                if ($(window).width() < 992) {
                    $('.sidebar').removeClass('active');
                } else {
                    $('.sidebar').addClass('active');
                }
            });
            
            // Trigger initial resize check
            $(window).trigger('resize');
            
            // Handle notifications badge click
            $('.dropdown-toggle').on('click', function() {
                // In a real app, you would mark notifications as read here
                // For now, just hide the badge
                $('.badge').fadeOut();
            });
            
            // Auto-refresh dashboard every 60 seconds
            setInterval(function() {
                // Reload OBR stats
                $.ajax({
                    url: 'get_obr_stats.php',
                    method: 'GET',
                    success: function(data) {
                        if (data.success) {
                            // Update stats cards
                            $('.stat-card:nth-child(1) .stat-value').text(data.total);
                            $('.stat-card:nth-child(2) .stat-value').text(data.pending);
                            $('.stat-card:nth-child(3) .stat-value').text(data.completed);
                            $('.stat-card:nth-child(4) .stat-value').text(data.my);
                            
                            // Update notification badge
                            $('.badge.bg-danger').text(data.pending);
                        }
                    }
                });
            }, 60000); // 60 seconds
            
            // NEW: OBR Quick Access Animation
            $('.obr-quick-btn').hover(
                function() {
                    $(this).find('.obr-quick-icon').css('transform', 'scale(1.2)');
                },
                function() {
                    $(this).find('.obr-quick-icon').css('transform', 'scale(1)');
                }
            );
        });
    </script>
</body>
</html>