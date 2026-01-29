<?php
// File: C:\inetpub\wwwroot\portal\api\save-obr.php
header('Content-Type: application/json');

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


// ... (after database connection and getting $data)
// Generate Tracking Number (Format: OBR-YYYY-XXXXX)
function generateTrackingNumber($conn, $prefix = 'OBR') {
    $year = date('Y');
    // Get the last sequence number for this year
    $sql = "SELECT MAX(CAST(SUBSTRING(TrackingNumber, 10, 5) AS INT)) as last_seq 
            FROM OBR 
            WHERE TrackingNumber LIKE ?";
    $params = array("{$prefix}-{$year}-%");
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    $lastSeq = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $lastSeq = (int)$row['last_seq'];
    }
    if ($stmt) sqlsrv_free_stmt($stmt);
    
    $newSeq = $lastSeq + 1;
    // Format sequence as 5-digit number
    return sprintf("%s-%s-%05d", $prefix, $year, $newSeq);
}

// Check if a TrackingNumber was provided (for drafts), otherwise generate one
if (empty($data['TrackingNumber']) || $data['TrackingNumber'] == '') {
    $trackingNumber = generateTrackingNumber($conn);
} else {
    $trackingNumber = $data['TrackingNumber'];
    // Ensure it matches the required format if manually entered
    // You can add validation here
}

// Increase PHP limits for this script
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

// Check if the request is too large
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 20 * 1024 * 1024) {
    echo json_encode([
        'success' => false,
        'error' => 'Request too large. Maximum 20MB allowed.'
    ]);
    exit;
}

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Get JSON data from request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate required fields
    $requiredFields = ['TrackingNumber', 'Payee', 'Department', 'Address', 'Datetime', 'CreatedBy', 'CreatedByName', 'Amount'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Convert datetime to SQL Server format
    $datetime = $data['Datetime'];
    // Convert from ISO 8601 format (2024-01-28T15:30) to SQL Server datetime format
    $datetime = str_replace('T', ' ', $datetime);
    if (strlen($datetime) === 16) {
        $datetime .= ':00'; // Add seconds if not present
    }
    
    // Validate datetime format
    if (!strtotime($datetime)) {
        throw new Exception('Invalid date format');
    }
    
    // Format date for SQL Server
    $sqlDatetime = date('Y-m-d H:i:s', strtotime($datetime));
    
    // Begin transaction
    sqlsrv_begin_transaction($conn);
    
    // Check if TrackingNumber already exists
    $checkSql = "SELECT COUNT(*) as count FROM OBR WHERE TrackingNumber = ?";
    $checkParams = array($data['TrackingNumber']);
    $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
    
    if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['count'] > 0) {
            throw new Exception('Tracking number already exists. Please use a different OBR number.');
        }
    }
    sqlsrv_free_stmt($checkStmt);
    
    // Insert into OBR table using OUTPUT clause to get the OBRID
    $sql = "INSERT INTO OBR (
                TrackingNumber, 
                Payee, 
                Department, 
                Address, 
                Datetime, 
                CreatedBy, 
                CreatedByName, 
                CreatedByPosition, 
                Remarks, 
                TotalAmount, 
                Status,
                DateCreated,
                DateModified
            ) 
            OUTPUT INSERTED.OBRID
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())";
    
    $params = array(
        $data['TrackingNumber'],
        $data['Payee'],
        $data['Department'],
        $data['Address'],
        $sqlDatetime, // Use formatted datetime
        $data['CreatedBy'],
        $data['CreatedByName'],
        isset($data['CreatedByPosition']) ? $data['CreatedByPosition'] : '',
        isset($data['Remarks']) ? $data['Remarks'] : '',
        $data['Amount'],
        isset($data['Status']) ? $data['Status'] : 'Draft'
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if (!$stmt) {
        $errors = sqlsrv_errors();
        error_log("SQL Error: " . print_r($errors, true));
        throw new Exception('Failed to insert OBR: ' . $errors[0]['message']);
    }
    
    // Get the inserted OBRID from OUTPUT clause
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$row || !isset($row['OBRID'])) {
        throw new Exception('Failed to get OBR ID from database.');
    }
    $obrID = $row['OBRID'];
    sqlsrv_free_stmt($stmt);
    
    // Insert line items if they exist
    if (isset($data['LineItems']) && is_array($data['LineItems'])) {
        foreach ($data['LineItems'] as $lineItem) {
            // Validate line item
            if (!isset($lineItem['ResCenter']) || !isset($lineItem['AccCode']) || 
                !isset($lineItem['Particulars']) || !isset($lineItem['Amount'])) {
                throw new Exception('Invalid line item data');
            }
            
            $sql = "INSERT INTO OBRLineItems (
                        OBRID, 
                        ResCenter, 
                        FPP, 
                        AccCode, 
                        Particulars, 
                        Amount
                    ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = array(
                $obrID,
                $lineItem['ResCenter'],
                isset($lineItem['FPP']) ? $lineItem['FPP'] : '',
                $lineItem['AccCode'],
                $lineItem['Particulars'],
                $lineItem['Amount']
            );
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if (!$stmt) {
                $errors = sqlsrv_errors();
                throw new Exception('Failed to insert line item: ' . $errors[0]['message']);
            }
            sqlsrv_free_stmt($stmt);
        }
    }
    
    // Insert attachments if they exist
    if (isset($data['Attachments']) && is_array($data['Attachments'])) {
        foreach ($data['Attachments'] as $attachment) {
            // Validate attachment
            if (!isset($attachment['FileName']) || !isset($attachment['FileData'])) {
                continue; // Skip invalid attachments
            }
            
            // Decode base64 to binary
            $fileData = base64_decode($attachment['FileData']);
            if ($fileData === false) {
                continue; // Skip if base64 decode fails
            }
            
            $sql = "INSERT INTO OBRAttachments (
                        OBRID, 
                        FileName, 
                        FileType, 
                        FileData, 
                        FileSize, 
                        Description, 
                        UploadedBy,
                        UploadDate
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
            
            $params = array(
                $obrID,
                $attachment['FileName'],
                isset($attachment['FileType']) ? $attachment['FileType'] : 'application/octet-stream',
                array($fileData, SQLSRV_PARAM_IN, SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY), SQLSRV_SQLTYPE_VARBINARY('max')),
                isset($attachment['FileSize']) ? $attachment['FileSize'] : 0,
                isset($attachment['Description']) ? $attachment['Description'] : $attachment['FileName'],
                $data['CreatedBy']
            );
            
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            if (!$stmt) {
                // Log attachment error but don't fail the whole transaction
                error_log('Failed to insert attachment: ' . print_r(sqlsrv_errors(), true));
                // Continue with other attachments
            }
            if ($stmt) {
                sqlsrv_free_stmt($stmt);
            }
        }
    }
    
    // Insert initial tracking record
    $sql = "INSERT INTO DocumentTracking (
                OBRID, 
                Status, 
                FromOffice, 
                ToOffice, 
                Remarks, 
                ActionBy, 
                ActionByName,
                Timestamp
            ) VALUES (?, 'Created', ?, ?, 'OBR created', ?, ?, GETDATE())";
    
    $params = array(
        $obrID,
        $data['Department'],
        $data['Department'],
        $data['CreatedBy'],
        $data['CreatedByName']
    );
    
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if (!$stmt) {
        error_log('Failed to insert tracking record: ' . print_r(sqlsrv_errors(), true));
        // Don't throw exception for tracking record failure
    }
    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }
    
    // Commit transaction
    sqlsrv_commit($conn);
    
    echo json_encode([
        'success' => true,
        'obrID' => $obrID,
        'trackingNumber' => $data['TrackingNumber'],
        'message' => 'OBR created successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        @sqlsrv_rollback($conn);
    }
    
    error_log('OBR Creation Error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($conn)) {
    sqlsrv_close($conn);
}
?>