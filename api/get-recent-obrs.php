<?php
session_start();
require_once '../includes/database.php'; // Your existing database connection

header('Content-Type: application/json');

try {
    // Get user info from session
    $userID = $_SESSION['UserID'] ?? $_SESSION['username'] ?? '';
    $userDept = $_SESSION['Department'] ?? $_SESSION['department'] ?? '';
    $isAdmin = isset($_SESSION['UserAccess']) && $_SESSION['UserAccess'] === 'Admin';
    
    if (!$userID) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    if ($isAdmin) {
        // Admin sees all recent OBRs
        $sql = "SELECT TOP 5 * FROM OBR ORDER BY DateCreated DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        // User sees their own OBRs
        $sql = "SELECT TOP 5 * FROM OBR WHERE CreatedBy = ? ORDER BY DateCreated DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userID]);
    }
    
    $obrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $obrs]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>