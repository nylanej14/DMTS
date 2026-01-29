<?php
// File: C:\inetpub\wwwroot\portal\api\delete-obr.php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? $_SESSION['UserAccess'] ?? 'User';
$isAdmin = ($user_role === 'Admin' || $user_role === 'Administrator');

// Get OBR ID from request
$obrID = isset($_POST['obrID']) ? intval($_POST['obrID']) : 0;

if ($obrID <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid OBR ID']);
    exit();
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

try {
    // First, check if OBR exists and user has permission to delete it
    $checkSql = "SELECT OBRID, TrackingNumber, Status, CreatedBy FROM OBR WHERE OBRID = ?";
    $checkParams = array($obrID);
    $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
    
    if (!$checkStmt || !sqlsrv_has_rows($checkStmt)) {
        throw new Exception('OBR not found');
    }
    
    $obr = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($checkStmt);
    
    // Check permissions (only admin or creator can delete, and only if status is Draft)
    if (!$isAdmin && $obr['CreatedBy'] !== $user_id) {
        throw new Exception('You do not have permission to delete this OBR');
    }
    
    if ($obr['Status'] !== 'Draft') {
        throw new Exception('Only Draft OBRs can be deleted');
    }
    
    // Begin transaction
    sqlsrv_begin_transaction($conn);
    
    // Delete from OBR table (cascade should handle related records)
    $deleteSql = "DELETE FROM OBR WHERE OBRID = ?";
    $deleteParams = array($obrID);
    $deleteStmt = sqlsrv_query($conn, $deleteSql, $deleteParams);
    
    if (!$deleteStmt) {
        throw new Exception('Failed to delete OBR: ' . print_r(sqlsrv_errors(), true));
    }
    
    // Commit transaction
    sqlsrv_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'OBR ' . $obr['TrackingNumber'] . ' deleted successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn)) {
        sqlsrv_rollback($conn);
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    sqlsrv_close($conn);
}
?>