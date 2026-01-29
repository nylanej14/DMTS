<?php
// tracking.php
// Public page - no session required

// Get tracking number from URL
$trackingNumber = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';

if (empty($trackingNumber)) {
    die('No tracking number provided');
}

// Database connection
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

if (!$conn) {
    die('Database connection failed');
}

// Fetch OBR and its tracking history
$sql = "SELECT OBR.*, 
               (SELECT TOP 1 Status FROM DocumentTracking WHERE OBRID = OBR.OBRID ORDER BY Timestamp DESC) as CurrentStatus,
               (SELECT COUNT(*) FROM DocumentTracking WHERE OBRID = OBR.OBRID) as HistoryCount
        FROM OBR 
        WHERE TrackingNumber = ?";
$params = array($trackingNumber);
$stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt || !sqlsrv_has_rows($stmt)) {
    $error = 'Tracking number not found in the system.';
} else {
    $obr = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);
    
    // Fetch tracking history
    $historySql = "SELECT * FROM DocumentTracking WHERE OBRID = ? ORDER BY Timestamp DESC";
    $historyStmt = sqlsrv_query($conn, $historySql, array($obr['OBRID']));
    $trackingHistory = [];
    if ($historyStmt) {
        while ($row = sqlsrv_fetch_array($historyStmt, SQLSRV_FETCH_ASSOC)) {
            $trackingHistory[] = $row;
        }
        sqlsrv_free_stmt($historyStmt);
    }
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track OBR - <?php echo htmlspecialchars($trackingNumber); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding-top: 20px; }
        .tracking-header { background: linear-gradient(135deg, #09324A 0%, #0A4A6F 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; }
        .status-badge { font-size: 1rem; padding: 8px 15px; border-radius: 20px; }
        .status-draft { background: #6c757d; }
        .status-submitted { background: #0d6efd; }
        .status-pending { background: #ffc107; color: black; }
        .status-approved { background: #198754; }
        .status-rejected { background: #dc3545; }
        .status-completed { background: #20c997; }
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item::before { content: ''; position: absolute; left: -23px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: #0d6efd; border: 2px solid white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="tracking-header text-center">
            <h1><i class="fas fa-search-location"></i> Document Tracking System</h1>
            <p class="lead">Provincial Government of Romblon</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> OBR Tracking</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger text-center">
                                <h4><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></h4>
                                <p>Please check the tracking number and try again.</p>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="javascript:history.back()" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Go Back
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Tracking Info -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Document Information</h5>
                                    <table class="table table-bordered">
                                        <tr><th width="40%">OBR Number</th><td><strong><?php echo htmlspecialchars($obr['TrackingNumber']); ?></strong></td></tr>
                                        <tr><th>Payee</th><td><?php echo htmlspecialchars($obr['Payee']); ?></td></tr>
                                        <tr><th>Department</th><td><?php echo htmlspecialchars($obr['Department']); ?></td></tr>
                                        <tr><th>Date Created</th><td><?php echo $obr['Datetime']->format('F j, Y, g:i a'); ?></td></tr>
                                        <tr><th>Total Amount</th><td>₱ <?php echo number_format($obr['TotalAmount'], 2); ?></td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6 text-center">
                                    <h5>Current Status</h5>
                                    <div class="display-4 my-3">
                                        <span class="status-badge status-<?php echo strtolower($obr['CurrentStatus'] ?? 'draft'); ?>">
                                            <?php echo $obr['CurrentStatus'] ?? 'Draft'; ?>
                                        </span>
                                    </div>
                                    <p class="text-muted">Last updated: 
                                        <?php echo !empty($trackingHistory) ? $trackingHistory[0]['Timestamp']->format('M j, Y, g:i a') : 'N/A'; ?>
                                    </p>
                                    
                                    <!-- QR Code Display -->
                                    <?php
                                    // Generate QR code for this tracking page
                                    $currentURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                                    if (file_exists('vendor/phpqrcode/qrlib.php')) {
                                        require_once 'vendor/phpqrcode/qrlib.php';
                                        $qrFile = 'temp/track_qr_' . $trackingNumber . '.png';
                                        QRcode::png($currentURL, $qrFile, QR_ECLEVEL_L, 6);
                                        echo '<img src="' . $qrFile . '" width="120" class="mt-2 border" alt="QR Code">';
                                        echo '<p class="small text-muted mt-1">Scan to revisit this page</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Tracking History -->
                            <h5><i class="fas fa-history"></i> Tracking History</h5>
                            <?php if (!empty($trackingHistory)): ?>
                                <div class="timeline">
                                    <?php foreach ($trackingHistory as $history): ?>
                                        <div class="timeline-item">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <strong><?php echo $history['Timestamp']->format('M j, Y, g:i a'); ?></strong>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <span class="badge bg-info"><?php echo $history['Status']; ?></span>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <i class="fas fa-user"></i> 
                                                            <?php echo htmlspecialchars($history['ActionByName'] ?? 'System'); ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php if ($history['FromOffice']): ?>
                                                                    From: <?php echo htmlspecialchars($history['FromOffice']); ?>
                                                                <?php endif; ?>
                                                                <?php if ($history['ToOffice']): ?>
                                                                    → To: <?php echo htmlspecialchars($history['ToOffice']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <?php if ($history['Remarks']): ?>
                                                                <small><i class="fas fa-comment"></i> <?php echo htmlspecialchars($history['Remarks']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No tracking history available yet.
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <button onclick="window.print()" class="btn btn-secondary">
                                    <i class="fas fa-print"></i> Print Tracking Info
                                </button>
                                <a href="print-obr.php?id=<?php echo $obr['OBRID']; ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-file-invoice-dollar"></i> View OBR Document
                                </a>
                                <a href="index.html" class="btn btn-outline-primary">
                                    <i class="fas fa-home"></i> Return to Home
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer text-center text-muted">
                        <small>
                            <i class="fas fa-lock"></i> This tracking information is provided by the 
                            Romblon Provincial Government Document Management & Tracking System (PGR-DMTS).
                            <br>For inquiries, please contact the concerned department.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
<?php
// Cleanup temporary QR file
if (isset($qrFile) && file_exists($qrFile)) {
    unlink($qrFile);
}
?>