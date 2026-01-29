<?php
// dashboard_sidebar.php - Sidebar navigation

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user info from session with fallbacks
$username = $_SESSION['username'] ?? $_SESSION['UserID'] ?? 'User';
$full_name = $_SESSION['full_name'] ?? ($_SESSION['Firstname'] ?? '') . ' ' . ($_SESSION['Lastname'] ?? '');
$department = $_SESSION['department'] ?? $_SESSION['Department'] ?? 'Not assigned';
$user_role = $_SESSION['user_role'] ?? $_SESSION['UserAccess'] ?? 'User';
$position = $_SESSION['position'] ?? 'Not assigned';

// Get profile picture from session (set in login.php)
$profile_pic = $_SESSION['profile_pic'] ?? null;

// Default to 'User' if no role is set
if (empty($user_role)) {
    $user_role = 'User';
}

// Determine badge color based on role
$badge_color = 'info';
if (strtolower($user_role) === 'admin' || strtolower($user_role) === 'administrator') {
    $badge_color = 'danger';
} elseif (strtolower($user_role) === 'supervisor' || strtolower($user_role) === 'manager') {
    $badge_color = 'warning';
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="d-block text-center mb-3">
            <img src="images/logo.png" alt="Romblon Logo" class="sidebar-logo" onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iODAiIGhlaWdodD0iODAiIHJ4PSIxMCIgZmlsbD0iIzA5MzI0QSIvPjx0ZXh0IHg9IjQwIiB5PSI0NSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpZGU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+UEdSPC90ZXh0Pjwvc3ZnPg=='">
        </a>
        <h4 class="text-center mb-0 text-white">Romblon DMTS</h4>
        <p class="text-center text-light small">Document Tracking System</p>
    </div>
    
    <div class="sidebar-user text-center py-3">
        <div class="user-avatar mb-2">
            <?php if (!empty($profile_pic)): ?>
                <!-- Display profile picture from session -->
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                     alt="Profile Picture" 
                     class="profile-pic rounded-circle"
                     onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIzMCIgY3k9IjMwIiByPSIzMCIgZmlsbD0iIzMwRDVDOCIvPjx0ZXh0IHg9IjMwIiB5PSIzNSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE4IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+PD9waHAgZWNobyBzdWJzdHIodHJpbSgkZnVsbF9uYW1lKSwgMCwgMSk7ID8+PC90ZXh0Pjwvc3ZnPg=='; this.classList.add('avatar-fallback');">
            <?php else: ?>
                <!-- Fallback to initials if no profile picture -->
                <div class="avatar-fallback rounded-circle">
                    <?php 
                    $initials = '';
                    if (!empty($full_name)) {
                        $names = explode(' ', $full_name);
                        if (count($names) > 0) {
                            $initials = strtoupper(substr($names[0], 0, 1));
                            if (count($names) > 1) {
                                $initials .= strtoupper(substr($names[count($names)-1], 0, 1));
                            }
                        }
                    }
                    ?>
                    <span class="initials"><?php echo htmlspecialchars($initials); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <h6 class="mb-1 text-white"><?php echo htmlspecialchars($full_name); ?></h6>
        <p class="small text-light mb-0">
            <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($department); ?>
        </p>
        <?php if (!empty($position) && $position !== 'Not assigned'): ?>
        <p class="small text-light mb-1">
            <i class="fas fa-briefcase me-1"></i> <?php echo htmlspecialchars($position); ?>
        </p>
        <?php endif; ?>
        <span class="badge bg-<?php echo $badge_color; ?> mt-1">
            <?php echo htmlspecialchars($user_role); ?>
        </span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            
            <!-- OBR Links -->
            <li class="nav-item">
                <a href="create-obr.php" class="nav-link <?php echo ($current_page == 'create-obr.php') ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle me-2"></i>
                    Create OBR
                </a>
            </li>
            
            <li class="nav-item">
                <a href="track-obr.php" class="nav-link <?php echo ($current_page == 'track-obr.php') ? 'active' : ''; ?>">
                    <i class="fas fa-qrcode me-2"></i>
                    Track Document
                </a>
            </li>
            
            <!-- Documents Menu -->
            <li class="nav-item">
                <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#documents-collapse">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    Documents
                    <i class="fas fa-chevron-right float-end mt-1"></i>
                </a>
                <div class="collapse" id="documents-collapse">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a href="my-obrs.php" class="nav-link <?php echo ($current_page == 'my-obrs.php') ? 'active' : ''; ?>">My OBRs</a>
                        </li>
                        <li class="nav-item">
                            <a href="received-obrs.php" class="nav-link <?php echo ($current_page == 'received-obrs.php') ? 'active' : ''; ?>">Received Documents</a>
                        </li>
                        <li class="nav-item">
                            <a href="pending-obrs.php" class="nav-link <?php echo ($current_page == 'pending-obrs.php') ? 'active' : ''; ?>">Pending Actions</a>
                        </li>
                        <li class="nav-item">
                            <a href="view-obr.php" class="nav-link <?php echo ($current_page == 'view-obr.php') ? 'active' : ''; ?>">View OBR</a>
                        </li>
                        <li class="nav-item">
                            <a href="print-obr.php" class="nav-link <?php echo ($current_page == 'print-obr.php') ? 'active' : ''; ?>">Print OBR</a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Reports Menu -->
            <li class="nav-item">
                <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#reports-collapse">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                    <i class="fas fa-chevron-right float-end mt-1"></i>
                </a>
                <div class="collapse" id="reports-collapse">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a href="reports-daily.php" class="nav-link <?php echo ($current_page == 'reports-daily.php') ? 'active' : ''; ?>">Daily Report</a>
                        </li>
                        <li class="nav-item">
                            <a href="reports-monthly.php" class="nav-link <?php echo ($current_page == 'reports-monthly.php') ? 'active' : ''; ?>">Monthly Report</a>
                        </li>
                        <li class="nav-item">
                            <a href="reports-office.php" class="nav-link <?php echo ($current_page == 'reports-office.php') ? 'active' : ''; ?>">Office Report</a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Admin Menu (Only for Admin Users) -->
            <?php if (strtolower($user_role) === 'admin' || strtolower($user_role) === 'administrator'): ?>
            <li class="nav-item">
                <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#admin-collapse">
                    <i class="fas fa-cogs me-2"></i>
                    Administration
                    <i class="fas fa-chevron-right float-end mt-1"></i>
                </a>
                <div class="collapse" id="admin-collapse">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a href="admin-users.php" class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">User Management</a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-signatories.php" class="nav-link <?php echo ($current_page == 'signatories.php') ? 'active' : ''; ?>">Signatory Management</a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-departments.php" class="nav-link <?php echo ($current_page == 'admin-departments.php') ? 'active' : ''; ?>">Department Setup</a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-audit.php" class="nav-link <?php echo ($current_page == 'admin-audit.php') ? 'active' : ''; ?>">Audit Logs</a>
                        </li>
                    </ul>
                </div>
            </li>
            <?php endif; ?>
            
            <!-- Divider -->
            <hr class="my-2">
            
            <!-- User Section -->
            <li class="nav-item">
                <a href="profile.php" class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle me-2"></i>
                    My Profile
                </a>
            </li>
            
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-cog me-2"></i>
                    Settings
                </a>
            </li>
            
            <!-- Logout -->
            <li class="nav-item">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer text-center py-3">
        <p class="small text-light mb-0">
            <i class="fas fa-info-circle me-1"></i>
            System Status: <span class="text-success">Online</span>
        </p>
        <p class="small text-light">
            <i class="fas fa-database me-1"></i>
            Last Sync: Today
        </p>
    </div>
</div>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 250px;
    background: linear-gradient(180deg, #2c3e50 0%, #1a1a2e 100%);
    color: white;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
    padding: 20px 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-logo {
    width: 80px;
    height: 80px;
    object-fit: contain;
    background: white;
    padding: 5px;
    border-radius: 50%;
}

.sidebar-user {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    text-align: center;
}

.user-avatar {
    display: inline-block;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    padding: 10px;
    margin-bottom: 10px;
    position: relative;
    width: 80px;
    height: 80px;
}

/* Profile picture styles */
.user-avatar .profile-pic {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.2);
}

/* Fallback avatar styles */
.user-avatar .avatar-fallback {
    width: 60px;
    height: 60px;
    background: #30D5C8;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgba(255, 255, 255, 0.2);
}

.user-avatar .initials {
    font-size: 18px;
    font-weight: bold;
    color: white;
    text-transform: uppercase;
}

.nav-link {
    color: rgba(255,255,255,0.8);
    padding: 12px 20px;
    border-left: 3px solid transparent;
    transition: all 0.3s;
}

.nav-link:hover {
    color: white;
    background: rgba(255,255,255,0.1);
    border-left-color: #0d6efd;
}

.nav-link.active {
    color: white;
    background: rgba(13, 110, 253, 0.2);
    border-left-color: #0d6efd;
}

.nav-link i {
    width: 20px;
    text-align: center;
}

.nav-link .fa-chevron-right {
    transition: transform 0.3s;
}

.nav-link[aria-expanded="true"] .fa-chevron-right {
    transform: rotate(90deg);
}

.collapse .nav-link {
    padding: 8px 20px 8px 40px;
    font-size: 0.9rem;
}

.collapse .nav-link.active {
    background: rgba(13, 110, 253, 0.15);
}

.sidebar-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
}

/* Mobile responsive */
@media (max-width: 992px) {
    .sidebar {
        width: 0;
        transition: width 0.3s;
    }
    
    .sidebar.show {
        width: 250px;
    }
    
    .sidebar-toggle {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        background: #2c3e50;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 5px;
    }
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.5);
}
</style>

<!-- Mobile Toggle Button (only shows on small screens) -->
<button class="sidebar-toggle d-md-none">
    <i class="fas fa-bars"></i>
</button>

<script>
// Mobile sidebar toggle
$(document).ready(function() {
    $('.sidebar-toggle').click(function() {
        $('.sidebar').toggleClass('show');
    });
    
    // Close sidebar when clicking outside on mobile
    $(document).click(function(event) {
        if ($(window).width() < 768) {
            if (!$(event.target).closest('.sidebar').length && 
                !$(event.target).closest('.sidebar-toggle').length &&
                $('.sidebar').hasClass('show')) {
                $('.sidebar').removeClass('show');
            }
        }
    });
});
</script>