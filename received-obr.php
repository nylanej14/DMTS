<?php
// File: C:\inetpub\wwwroot\portal\received-obr.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Received Documents - Romblon Document Tracking</title>
    <?php include 'dashboard_sidebar.php'; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'dashboard_sidebar.php'; ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1>Received Documents</h1>
                <p class="text-muted">This page will display all documents received by your office.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This page is under construction. Will be completed in Session 6.
                </div>
            </main>
        </div>
    </div>
</body>
</html>