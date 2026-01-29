<?php
// File: C:\inetpub\wwwroot\portal\api\departments.php
require_once '../config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

try {
    $conn = Database::getConnection('MASTERLIST');
    
    // Clean query - get distinct department names, filter out invalid codes
    $sql = "SELECT DISTINCT 
                [OfficeName],
                [OfficeAb],
                [OfficeCode]
            FROM [dbo].[OfficeMainCat] 
            WHERE [OfficeName] IS NOT NULL 
                AND [OfficeName] != ''
                AND ([OfficeCode] IS NULL OR [OfficeCode] NOT IN ('-', '--', ''))
            ORDER BY [OfficeName]";
    
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt === false) {
        throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
    }
    
    $departments = [];
    $counter = 1;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $departments[] = [
            'id' => $counter++, // Use sequential ID since OfficeSysID has GUIDs
            'name' => $row['OfficeName'],
            'abbreviation' => $row['OfficeAb'] ?: '',
            'code' => $row['OfficeCode'] ?: ''
        ];
    }
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $departments,
        'count' => count($departments),
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