<?php
session_start();
require_once '../includes/database.php';

header('Content-Type: application/json');

try {
    $userID = $_SESSION['UserID'] ?? null;
    $userDepartment = $_SESSION['Department'] ?? null;
    $userAccess = $_SESSION['UserAccess'] ?? 'User';
    
    if (!$userID) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    $sql = "";
    
    if ($userAccess === 'Admin') {
        // Admin sees all OBRs
        $sql = "SELECT 
                    OBR.OBRID,
                    OBR.TrackingNumber,
                    OBR.Payee,
                    OBR.PayeeAddress,
                    OBR.DateCreated,
                    OBR.TotalAmount,
                    OBR.Status,
                    OBR.CreatedBy,
                    OBR.Department,
                    dt.Status AS CurrentStatus,
                    dt.ToOffice AS CurrentOffice
                FROM OBR
                LEFT JOIN (
                    SELECT OBRID, Status, ToOffice, ROW_NUMBER() OVER (PARTITION BY OBRID ORDER BY Timestamp DESC) AS rn
                    FROM DocumentTracking
                ) dt ON OBR.OBRID = dt.OBRID AND dt.rn = 1
                ORDER BY OBR.DateCreated DESC";
        $stmt = $conn->prepare($sql);
    } else {
        // User sees their created OBRs + OBRs in their department
        $sql = "SELECT 
                    OBR.OBRID,
                    OBR.TrackingNumber,
                    OBR.Payee,
                    OBR.PayeeAddress,
                    OBR.DateCreated,
                    OBR.TotalAmount,
                    OBR.Status,
                    OBR.CreatedBy,
                    OBR.Department,
                    dt.Status AS CurrentStatus,
                    dt.ToOffice AS CurrentOffice
                FROM OBR
                LEFT JOIN (
                    SELECT OBRID, Status, ToOffice, ROW_NUMBER() OVER (PARTITION BY OBRID ORDER BY Timestamp DESC) AS rn
                    FROM DocumentTracking
                ) dt ON OBR.OBRID = dt.OBRID AND dt.rn = 1
                WHERE OBR.CreatedBy = ? 
                   OR (dt.ToOffice = ? AND dt.Status != 'Completed')
                ORDER BY OBR.DateCreated DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userID, $userDepartment]);
    }
    
    $obrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $obrs]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>