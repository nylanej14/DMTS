<?php
// File: C:\inetpub\wwwroot\portal\includes\db_masterlist.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test if we can include config.php
if (file_exists('../config.php')) {
    require_once '../config.php';
} else {
    // If config.php doesn't exist, use default credentials
    define('DB_SERVER', 'localhost');
    define('DB_MASTERLIST', 'MasterList');
    define('DB_USERNAME', 'sa');  // Default SQL Server username
    define('DB_PASSWORD', '');    // Default SQL Server password
}

class MasterListDB {
    private $conn;
    
    public function __construct() {
        $serverName = DB_SERVER;
        $connectionInfo = array(
            "Database" => DB_MASTERLIST,
            "UID" => DB_USERNAME,
            "PWD" => DB_PASSWORD,
            "CharacterSet" => "UTF-8",
            "ReturnDatesAsStrings" => true
        );
        
        $this->conn = sqlsrv_connect($serverName, $connectionInfo);
        
        if (!$this->conn) {
            // Try alternative: Windows Authentication without TrustedConnection
            $connectionInfo = array(
                "Database" => DB_MASTERLIST,
                "CharacterSet" => "UTF-8",
                "ReturnDatesAsStrings" => true
            );
            
            $this->conn = sqlsrv_connect($serverName, $connectionInfo);
            
            if (!$this->conn) {
                $this->sendError("MasterList Connection failed: " . print_r(sqlsrv_errors(), true));
                exit;
            }
        }
    }
    
    public function getDepartments() {
        try {
            $sql = "SELECT [OfficeSysID], [OfficeName], [OfficeAb], [OfficeCode] 
                    FROM [dbo].[OfficeMainCat] 
                    ORDER BY [OfficeName]";
            
            $stmt = sqlsrv_query($this->conn, $sql);
            
            if ($stmt === false) {
                return $this->sendError("Query failed: " . print_r(sqlsrv_errors(), true));
            }
            
            $departments = array();
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $departments[] = array(
                    'id' => $row['OfficeSysID'],
                    'name' => $row['OfficeName'],
                    'abbreviation' => $row['OfficeAb'],
                    'code' => $row['OfficeCode']
                );
            }
            
            sqlsrv_free_stmt($stmt);
            
            if (empty($departments)) {
                // Try alternative query
                $sql2 = "SELECT TOP 10 * FROM [dbo].[OfficeMainCat]";
                $stmt2 = sqlsrv_query($this->conn, $sql2);
                if ($stmt2) {
                    $columns = array();
                    while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
                        $columns = array_keys($row);
                        break;
                    }
                    sqlsrv_free_stmt($stmt2);
                    return $this->sendError("Query returned empty. Columns available: " . implode(", ", $columns));
                }
            }
            
            return $this->sendSuccess($departments);
        } catch (Exception $e) {
            return $this->sendError("Exception: " . $e->getMessage());
        }
    }
    
    // ... Keep other methods as they were ...
    
    private function sendSuccess($data) {
        return json_encode(array(
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ), JSON_PRETTY_PRINT);
    }
    
    private function sendError($message) {
        return json_encode(array(
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ), JSON_PRETTY_PRINT);
    }
    
    public function __destruct() {
        if ($this->conn) {
            sqlsrv_close($this->conn);
        }
    }
}

// Check if we're being called directly
if (isset($_SERVER['REQUEST_METHOD'])) {
    $db = new MasterListDB();
    $action = isset($_GET['action']) ? $_GET['action'] : 'departments';
    
    switch ($action) {
        case 'departments':
            echo $db->getDepartments();
            break;
        case 'account-codes':
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            echo $db->getAccountCodes($search);
            break;
        case 'responsibility-centers':
            echo $db->getResponsibilityCenters();
            break;
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
    }
}
?>