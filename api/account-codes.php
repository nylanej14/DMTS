<?php
// File: C:\inetpub\wwwroot\portal\api\account-codes.php
require_once '../config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

try {
    $conn = Database::getConnection('MASTERLIST');
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    if (!empty($search)) {
        $sql = "SELECT DISTINCT [AcctCodeFormatted] 
                FROM [dbo].[AcctChart2016] 
                WHERE [AcctCodeFormatted] LIKE ? 
                ORDER BY [AcctCodeFormatted]";
        $params = ["%{$search}%"];
        $stmt = sqlsrv_query($conn, $sql, $params);
    } else {
        $sql = "SELECT DISTINCT [AcctCodeFormatted] 
                FROM [dbo].[AcctChart2016] 
                WHERE [AcctCodeFormatted] IS NOT NULL 
                ORDER BY [AcctCodeFormatted]";
        $stmt = sqlsrv_query($conn, $sql);
    }
    
    if ($stmt === false) {
        throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
    }
    
    $codes = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $codes[] = $row['AcctCodeFormatted'];
    }
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $codes,
        'count' => count($codes),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>