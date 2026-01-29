<?php
// File: C:\inetpub\wwwroot\portal\api\responsibility-centers.php
require_once '../config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

try {
    $conn = Database::getConnection('MASTERLIST');
    
    // Get valid responsibility centers (OfficeCode)
    $sql = "SELECT DISTINCT 
                [OfficeCode],
                [OfficeName]
            FROM [dbo].[OfficeMainCat] 
            WHERE [OfficeCode] IS NOT NULL 
                AND [OfficeCode] != ''
                AND [OfficeCode] NOT IN ('-', '--')
                AND [OfficeName] IS NOT NULL
            ORDER BY [OfficeCode]";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
    }
    
    $centers = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $centers[] = [
            'code' => $row['OfficeCode'],
            'name' => $row['OfficeName']
        ];
    }
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $centers,
        'count' => count($centers),
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