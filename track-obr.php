<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/auth.php';

$trackingNo = $_GET['tracking'] ?? '';
$scanInput = $_POST['scan_input'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Document - Romblon DMTS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'dashboard_sidebar.php'; ?>
    
    <div class="main-content p-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Track Document</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Scan Document</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="scan_input" class="form-label">Scan QR/Barcode or Enter Tracking Number</label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="scan_input" 
                                       name="scan_input" 
                                       value="<?php echo htmlspecialchars($scanInput ?: $trackingNo); ?>"
                                       placeholder="Scan or type tracking number..."
                                       autofocus
                                       required>
                                <div class="form-text">Use QR/barcode scanner or manually enter the tracking number</div>
                            </div>
                            
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-success w-100" onclick="markAsIncoming()">
                                        <i class="fas fa-sign-in-alt me-2"></i> Mark as Incoming
                                    </button>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-warning w-100" onclick="markAsOutgoing()">
                                        <i class="fas fa-sign-out-alt me-2"></i> Mark as Outgoing
                                    </button>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Track Document
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if ($trackingNo || $scanInput): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Document Information</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $searchNo = $scanInput ?: $trackingNo;
                        try {
                            $sql = "SELECT * FROM OBR WHERE TrackingNumber = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$searchNo]);
                            $obr = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($obr): ?>
                                <p><strong>Tracking Number:</strong> <?php echo $obr['TrackingNumber']; ?></p>
                                <p><strong>Payee:</strong> <?php echo htmlspecialchars($obr['Payee']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($obr['Department']); ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-info"><?php echo $obr['Status']; ?></span></p>
                                <p><strong>Amount:</strong> ₱ <?php echo number_format($obr['TotalAmount'], 2); ?></p>
                                
                                <a href="view-obr.php?id=<?php echo $obr['OBRID']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i> View Full Details
                                </a>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Document not found with tracking number: <?php echo htmlspecialchars($searchNo); ?>
                                </div>
                            <?php endif;
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Scans</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $userDept = $_SESSION['Department'] ?? '';
                            $sql = "SELECT TOP 10 * FROM DocumentTracking 
                                    WHERE FromOffice = ? OR ToOffice = ? 
                                    ORDER BY Timestamp DESC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$userDept, $userDept]);
                            $recentScans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($recentScans)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No recent scans in your department</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recentScans as $scan): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <?php echo $scan['Action']; ?>: <?php echo $scan['TrackingNumber']; ?>
                                            </h6>
                                            <small><?php echo date('H:i', strtotime($scan['Timestamp'])); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            From: <?php echo $scan['FromOffice']; ?> 
                                            → To: <?php echo $scan['ToOffice']; ?>
                                        </p>
                                        <small class="text-muted">
                                            By: <?php echo $scan['UserName']; ?>
                                            <?php if ($scan['DurationHours']): ?>| Duration: <?php echo $scan['DurationHours']; ?> hrs<?php endif; ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif;
                        } catch (Exception $e) {
                            echo '<div class="alert alert-danger">Error loading recent scans</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-focus for scanner input
    document.getElementById('scan_input').focus();
    
    // Clear input on ESC key
    document.getElementById('scan_input').addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
        }
    });
    
    function markAsIncoming() {
        let trackingNo = document.getElementById('scan_input').value;
        if (!trackingNo) {
            alert('Please enter or scan a tracking number first');
            return;
        }
        
        if (confirm('Mark document as INCOMING to your department?')) {
            // This will be implemented in Session 5
            alert('Incoming scan feature will be available in the next update');
        }
    }
    
    function markAsOutgoing() {
        let trackingNo = document.getElementById('scan_input').value;
        if (!trackingNo) {
            alert('Please enter or scan a tracking number first');
            return;
        }
        
        if (confirm('Mark document as OUTGOING from your department?')) {
            // This will be implemented in Session 5
            alert('Outgoing scan feature will be available in the next update');
        }
    }
    </script>
</body>
</html>