<?php
session_start();
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View OBR - Document Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'dashboard_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="container-fluid py-4">
            <h1 class="h3 mb-4">View OBR Document</h1>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Enter tracking number to view OBR details.
                <form class="mt-3">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Enter tracking number (e.g., PGR260128-TREAS-001)">
                        <button class="btn btn-primary" type="submit">View OBR</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>