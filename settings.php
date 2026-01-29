<?php
// settings.php - System Settings

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is Admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.html');
    exit;
}

if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Administrator') {
    header('Location: dashboard.php');
    exit;
}

$userInfo = [
    'username' => $_SESSION['username'] ?? '',
    'full_name' => $_SESSION['full_name'] ?? '',
    'user_role' => $_SESSION['user_role'] ?? 'User'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Document Management & Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; }
        @media (max-width: 992px) { .main-content { margin-left: 0; } }
        .content-section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 15px rgba(11,35,50,0.1); margin-bottom: 30px; }
    </style>
</head>
<body>
    <?php include 'dashboard_sidebar.php'; ?>
    <main class="main-content">
        <div class="content-section">
            <h1 class="h3 mb-4">General Settings</h1>
            <p>System settings page is under development.</p>
        </div>
    </main>
</body>
</html>