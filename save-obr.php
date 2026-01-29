<?php
// File: C:\inetpub\wwwroot\portal\api\save-obr.php
require_once '../config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

try {
    $conn = Database::getConnection('PGR_DMTS');
    
    // Begin transaction
    sqlsrv_begin_transaction($conn);
    
    // Generate tracking number
    $trackingNumber = generateTrackingNumber($conn, $data['Department']);
    
    // Prepare OBR data
    $sql = "INSERT INTO [dbo].[OBR] (
                [TrackingNumber],
                [Payee],
                [Department],
                [Address],
                [ResCenter],
                [Particulars],
                [FPP],
                [AccCode],
                [Amount],
                [CertifiedA],
                [CertifiedB],
                [Datetime],
                [Createdby],
                [Status],
                [Remarks],
                [GovSigID],
                [DeptSigID],
                [IsCertifiedA],
                [IsCertifiedB],
                [CreatedDate]
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
    
    $params = [
        $trackingNumber,
        $data['Payee'],
        $data['Department'],
        $data['Address'],
        $data['ResCenter'],
        $data['Particulars'],
        $data['FPP'],
        $data['AccCode'],
        $data['Amount'],
        $data['CertifiedA'],
        $data['CertifiedB'],
        $data['Datetime'],
        $data['Createdby'],
        $data['Status'],
        $data['Remarks'],
        $data['GovSigID'],
        $data['DeptSigID'],
        $data['CertifiedA'],
        $data['CertifiedB']
    ];
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Failed to insert OBR: " . print_r(sqlsrv_errors(), true));
    }
    
    // Get the new OBRID
    $obrID = getLastInsertId($conn);
    
    // Insert line items
    if (!empty($data['LineItems'])) {
        $lineItems = json_decode($data['LineItems'], true);
        $lineNumber = 1;
        
        foreach ($lineItems as $item) {
            $sql = "INSERT INTO [dbo].[OBRDetails] (
                        [OBRID],
                        [ResCenter],
                        [FPP],
                        [AccCode],
                        [Amount],
                        [Particulars],
                        [LineNumber]
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $obrID,
                $item['ResCenter'],
                $item['FPP'],
                $item['AccCode'],
                $item['Amount'],
                $item['Particulars'],
                $lineNumber++
            ];
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Failed to insert line item: " . print_r(sqlsrv_errors(), true));
            }
        }
    }
    
    // Insert attachments
    if (!empty($data['Attachments'])) {
        $attachments = json_decode($data['Attachments'], true);
        
        foreach ($attachments as $attachment) {
            // Decode base64 data
            $fileData = base64_decode($attachment['FileData']);
            
            $sql = "INSERT INTO [dbo].[Attachments] (
                        [OBRID],
                        [FileName],
                        [FileType],
                        [FileData],
                        [FileSize],
                        [Description],
                        [Category],
                        [UploadedBy]
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $obrID,
                $attachment['FileName'],
                $attachment['FileType'],
                $fileData,
                $attachment['FileSize'],
                $attachment['Description'],
                $attachment['Category'],
                $data['Createdby']
            ];
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if ($stmt === false) {
                throw new Exception("Failed to insert attachment: " . print_r(sqlsrv_errors(), true));
            }
        }
    }
    
    // Create initial tracking record
    $sql = "INSERT INTO [dbo].[DocumentTracking] (
                [OBRID],
                [TrackingNumber],
                [FromOffice],
                [ToOffice],
                [Action],
                [Remarks],
                [Status],
                [UserID],
                [UserName]
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $obrID,
        $trackingNumber,
        $data['Department'],
        $data['Department'],
        'Created',
        'Document created and saved as ' . $data['Status'],
        $data['Status'],
        $data['Createdby'],
        $data['Createdby'] // In real scenario, get user name from Users table
    ];
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Failed to insert tracking record: " . print_r(sqlsrv_errors(), true));
    }
    
    // Commit transaction
    sqlsrv_commit($conn);
    
    echo json_encode([
        'success' => true,
        'obrID' => $obrID,
        'trackingNumber' => $trackingNumber,
        'message' => 'OBR created successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn) {
        sqlsrv_rollback($conn);
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateTrackingNumber($conn, $department) {
    // Get department code
    $deptCode = '';
    $sql = "SELECT TOP 1 [OfficeCode] FROM [MasterList].[dbo].[OfficeMainCat] 
            WHERE [OfficeName] = ?";
    $params = [$department];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $deptCode = $row['OfficeCode'];
    }
    
    // Generate tracking number: OBR-YYYY-MM-DEPT-SEQ
    $year = date('Y');
    $month = date('m');
    $prefix = $deptCode ? "OBR-{$deptCode}" : "OBR";
    
    // Get next sequence for this month
    $sql = "SELECT ISNULL(MAX(CAST(SUBSTRING([TrackingNumber], LEN([TrackingNumber]) - 4, 5) AS INT)), 0) + 1 as next_seq
            FROM [dbo].[OBR] 
            WHERE [TrackingNumber] LIKE ? 
            AND YEAR([CreatedDate]) = ? 
            AND MONTH([CreatedDate]) = ?";
    
    $searchPattern = $prefix . '-' . $year . '-' . $month . '-%';
    $params = [$searchPattern, $year, $month];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    $sequence = '00001';
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sequence = str_pad($row['next_seq'], 5, '0', STR_PAD_LEFT);
    }
    
    return $prefix . '-' . $year . '-' . $month . '-' . $sequence;
}

function getLastInsertId($conn) {
    $sql = "SELECT SCOPE_IDENTITY() as id";
    $stmt = sqlsrv_query($conn, $sql);
    
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['id'];
    }
    
    return null;
}
?>