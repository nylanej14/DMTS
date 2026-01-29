<?php
// File: C:\inetpub\wwwroot\portal\api\signatory.php
require_once '../config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

class SignatoryAPI {
    private $conn;
    
    public function __construct() {
        $this->conn = Database::getConnection('PGR_DMTS');
    }
    
    public function getGovernor() {
        $sql = "SELECT TOP 1 [SigID], [Honorific], [LastName], [FirstName], [MiddleName], 
                       [ExtName], [Department], [Position], [Degree] 
                FROM [dbo].[Signatory] 
                WHERE UPPER([Position]) LIKE '%GOVERNOR%' 
                ORDER BY [SigID] DESC";
        
        $stmt = sqlsrv_query($this->conn, $sql);
        
        if ($stmt === false) {
            throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
        }
        
        $governor = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        
        if ($governor) {
            $governor['fullName'] = $this->formatName($governor);
            $governor['middleInitial'] = !empty($governor['MiddleName']) ? 
                substr($governor['MiddleName'], 0, 1) . '.' : '';
            return $governor;
        }
        
        return null;
    }
    
    public function getDepartmentHead($department) {
        $sql = "SELECT TOP 1 [SigID], [Honorific], [LastName], [FirstName], [MiddleName], 
                       [ExtName], [Department], [Position], [Degree] 
                FROM [dbo].[Signatory] 
                WHERE [Department] = ? 
                ORDER BY [SigID] DESC";
        
        $params = [$department];
        $stmt = sqlsrv_query($this->conn, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
        }
        
        $deptHead = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        
        if ($deptHead) {
            $deptHead['fullName'] = $this->formatName($deptHead);
            $deptHead['middleInitial'] = !empty($deptHead['MiddleName']) ? 
                substr($deptHead['MiddleName'], 0, 1) . '.' : '';
            return $deptHead;
        }
        
        return $this->getDefaultSignatory();
    }
    
    private function getDefaultSignatory() {
        $sql = "SELECT TOP 1 [SigID], [Honorific], [LastName], [FirstName], [MiddleName], 
                       [ExtName], [Department], [Position], [Degree] 
                FROM [dbo].[Signatory] 
                WHERE ([Position] LIKE '%Head%' OR [Position] LIKE '%Director%' OR [Position] LIKE '%Chief%')
                ORDER BY [SigID] DESC";
        
        $stmt = sqlsrv_query($this->conn, $sql);
        
        if ($stmt === false) {
            throw new Exception("Query failed: " . print_r(sqlsrv_errors(), true));
        }
        
        $default = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        
        if ($default) {
            $default['fullName'] = $this->formatName($default);
            $default['middleInitial'] = !empty($default['MiddleName']) ? 
                substr($default['MiddleName'], 0, 1) . '.' : '';
            return $default;
        }
        
        return null;
    }
    
    private function formatName($signatory) {
        $name = '';
        
        if (!empty($signatory['Honorific'])) {
            $name .= $signatory['Honorific'] . ' ';
        }
        
        $name .= $signatory['FirstName'] . ' ';
        
        if (!empty($signatory['MiddleName'])) {
            $middleInitial = substr($signatory['MiddleName'], 0, 1);
            $name .= $middleInitial . '. ';
        }
        
        $name .= $signatory['LastName'];
        
        if (!empty($signatory['ExtName'])) {
            $name .= ' ' . $signatory['ExtName'];
        }
        
        if (!empty($signatory['Degree'])) {
            $name .= ', ' . $signatory['Degree'];
        }
        
        return $name;
    }
}

try {
    $api = new SignatoryAPI();
    $action = isset($_GET['action']) ? $_GET['action'] : 'governor';
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    
    switch ($action) {
        case 'governor':
            $data = $api->getGovernor();
            echo json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'department':
            if (empty($department)) {
                throw new Exception("Department parameter is required");
            }
            
            $data = $api->getDepartmentHead($department);
            echo json_encode([
                'success' => true,
                'data' => $data,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;
            
        default:
            throw new Exception("Invalid action parameter");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>