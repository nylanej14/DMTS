<?php
session_start();
require_once 'includes/auth.php'; // Your existing authentication
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My OBRs - Document Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'dashboard_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="container-fluid py-4">
            <h1 class="h3 mb-4">My OBR Documents</h1>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                OBR listing will be available in the next update.
                <a href="create-obr.php" class="alert-link">Create a new OBR</a> or 
                <a href="track-obr.php" class="alert-link">Track existing documents</a>.
            </div>
        </div>
    </main>
</body>
</html>