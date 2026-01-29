<?php
// signatories.php - Signatory Management for Document Management & Tracking System

// ============================================
// SESSION & AUTHENTICATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is Admin, redirect if not
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.html');
    exit;
}

// Check for both 'Admin' and 'Administrator' roles
if ($_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Administrator') {
    header('Location: dashboard.php');
    exit;
}

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get user info from session
$userInfo = [
    'username' => $_SESSION['username'] ?? '',
    'full_name' => $_SESSION['full_name'] ?? '',
    'user_role' => $_SESSION['user_role'] ?? 'User'
];

// Database connection for PGR-DMTS
function connectToDMTS() {
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
        error_log("Database connection failed: " . print_r(sqlsrv_errors(), true));
    }
    
    return $conn;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'search_employee') {
            // Search employee in HRMIS database
            $searchTerm = $_POST['search'] ?? '';
            
            if (strlen($searchTerm) < 2) {
                echo json_encode(['success' => false, 'message' => 'Please enter at least 2 characters']);
                exit;
            }
            
            // Connect to HRMIS database
            $serverName = "10.0.0.4\PGR2022";
            $connectionOptions = array(
                "Database" => "OPAD-HRMIS",
                "Uid" => "PRG_AdminUser",
                "PWD" => "PRG!ESXiAdminProd#2025$",
                "CharacterSet" => "UTF-8",
                "TrustServerCertificate" => true,
                "Encrypt" => false
            );
            
            $conn = sqlsrv_connect($serverName, $connectionOptions);
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            // Search in HRMIS database
            $sql = "SELECT TOP 10 
                    UsersProfileId, 
                    FirstName, 
                    MiddleName, 
                    Surname,
                    Username,
                    Department,
                    Position
                    FROM dbo.UsersProfile 
                    WHERE (FirstName LIKE ? OR MiddleName LIKE ? OR Surname LIKE ? OR Username LIKE ?)
                    ORDER BY FirstName, Surname";
            
            $searchParam = "%" . $searchTerm . "%";
            $params = array($searchParam, $searchParam, $searchParam, $searchParam);
            $stmt = sqlsrv_query($conn, $sql, $params);
            
            $results = [];
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $results[] = [
                        'UsersProfileId' => $row['UsersProfileId'],
                        'FirstName' => $row['FirstName'],
                        'MiddleName' => $row['MiddleName'],
                        'Surname' => $row['Surname'],
                        'Username' => $row['Username'],
                        'Department' => $row['Department'] ?? 'Not specified',
                        'Position' => $row['Position'] ?? 'Not specified'
                    ];
                }
                sqlsrv_free_stmt($stmt);
            } else {
                error_log("Search query failed: " . print_r(sqlsrv_errors(), true));
            }
            
            sqlsrv_close($conn);
            
            echo json_encode(['success' => true, 'data' => $results]);
            exit;
            
        } elseif ($action === 'save_signatory') {
            // Save signatory to PGR-DMTS database
            $sigID = $_POST['sig_id'] ?? 0;
            $honorific = $_POST['honorific'] ?? '';
            $firstName = $_POST['first_name'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            $middleName = $_POST['middle_name'] ?? '';
            $extName = $_POST['ext_name'] ?? '';
            $department = $_POST['department'] ?? '';
            $position = $_POST['position'] ?? '';
            $degree = $_POST['degree'] ?? '';
            
            if (empty($firstName) || empty($lastName)) {
                echo json_encode(['success' => false, 'message' => 'First Name and Last Name are required']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                if ($sigID > 0) {
                    // Update existing signatory
                    // Check if stored procedure exists
                    $checkSql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                                WHERE ROUTINE_NAME = 'sp_Signatory_Update' AND ROUTINE_TYPE = 'PROCEDURE'";
                    $checkStmt = sqlsrv_query($conn, $checkSql);
                    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
                    
                    if ($row['proc_exists'] > 0) {
                        // Use stored procedure
                        $sql = "{CALL sp_Signatory_Update(?, ?, ?, ?, ?, ?, ?, ?, ?)}";
                        $params = array(
                            array($sigID, SQLSRV_PARAM_IN),
                            array($honorific, SQLSRV_PARAM_IN),
                            array($lastName, SQLSRV_PARAM_IN),
                            array($firstName, SQLSRV_PARAM_IN),
                            array($middleName, SQLSRV_PARAM_IN),
                            array($extName, SQLSRV_PARAM_IN),
                            array($department, SQLSRV_PARAM_IN),
                            array($position, SQLSRV_PARAM_IN),
                            array($degree, SQLSRV_PARAM_IN)
                        );
                    } else {
                        // Fallback to direct SQL
                        $sql = "UPDATE Signatory SET 
                                Honorific = ?, 
                                LastName = ?, 
                                FirstName = ?, 
                                MiddleName = ?, 
                                ExtName = ?,
                                Department = ?, 
                                Position = ?, 
                                Degree = ?
                                WHERE SigID = ?";
                        
                        $params = array(
                            $honorific, $lastName, $firstName, $middleName, $extName,
                            $department, $position, $degree, $sigID
                        );
                    }
                } else {
                    // Insert new signatory
                    // Check if stored procedure exists
                    $checkSql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                                WHERE ROUTINE_NAME = 'sp_Signatory_Insert' AND ROUTINE_TYPE = 'PROCEDURE'";
                    $checkStmt = sqlsrv_query($conn, $checkSql);
                    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
                    
                    if ($row['proc_exists'] > 0) {
                        // Use stored procedure
                        $sql = "{CALL sp_Signatory_Insert(?, ?, ?, ?, ?, ?, ?, ?)}";
                        $params = array(
                            array($honorific, SQLSRV_PARAM_IN),
                            array($lastName, SQLSRV_PARAM_IN),
                            array($firstName, SQLSRV_PARAM_IN),
                            array($middleName, SQLSRV_PARAM_IN),
                            array($extName, SQLSRV_PARAM_IN),
                            array($department, SQLSRV_PARAM_IN),
                            array($position, SQLSRV_PARAM_IN),
                            array($degree, SQLSRV_PARAM_IN)
                        );
                    } else {
                        // Fallback to direct SQL
                        $sql = "INSERT INTO Signatory 
                                (Honorific, LastName, FirstName, MiddleName, ExtName, 
                                Department, Position, Degree) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $params = array(
                            $honorific, $lastName, $firstName, $middleName, $extName,
                            $department, $position, $degree
                        );
                    }
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'Signatory saved successfully']);
                } else {
                    $errors = sqlsrv_errors();
                    error_log("Save signatory error: " . print_r($errors, true));
                    echo json_encode(['success' => false, 'message' => 'Failed to save signatory. Please check database.']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in save_signatory: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'delete_signatory') {
            // Delete signatory from PGR-DMTS database
            $sigID = $_POST['sig_id'] ?? 0;
            
            if ($sigID <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid signatory ID']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Check if stored procedure exists
                $checkSql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                            WHERE ROUTINE_NAME = 'sp_Signatory_Delete' AND ROUTINE_TYPE = 'PROCEDURE'";
                $checkStmt = sqlsrv_query($conn, $checkSql);
                $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
                
                if ($row['proc_exists'] > 0) {
                    // Use stored procedure
                    $sql = "{CALL sp_Signatory_Delete(?)}";
                    $params = array($sigID);
                } else {
                    // Fallback to direct SQL
                    $sql = "DELETE FROM Signatory WHERE SigID = ?";
                    $params = array($sigID);
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'Signatory deleted successfully']);
                } else {
                    $errors = sqlsrv_errors();
                    error_log("Delete signatory error: " . print_r($errors, true));
                    echo json_encode(['success' => false, 'message' => 'Failed to delete signatory']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in delete_signatory: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'get_signatories') {
            // Get all signatories from PGR-DMTS database
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Check if stored procedure exists
                $checkSql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                            WHERE ROUTINE_NAME = 'sp_Signatory_GetAll' AND ROUTINE_TYPE = 'PROCEDURE'";
                $checkStmt = sqlsrv_query($conn, $checkSql);
                $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
                
                if ($row['proc_exists'] > 0) {
                    // Use stored procedure
                    $sql = "{CALL sp_Signatory_GetAll()}";
                    $stmt = sqlsrv_query($conn, $sql);
                } else {
                    // Fallback to direct SQL
                    $sql = "SELECT SigID, Honorific, LastName, FirstName, MiddleName, ExtName,
                            Department, Position, Degree
                            FROM Signatory 
                            ORDER BY LastName, FirstName";
                    $stmt = sqlsrv_query($conn, $sql);
                }
                
                $signatories = [];
                if ($stmt) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $signatories[] = [
                            'SigID' => $row['SigID'],
                            'Honorific' => $row['Honorific'],
                            'LastName' => $row['LastName'],
                            'FirstName' => $row['FirstName'],
                            'MiddleName' => $row['MiddleName'],
                            'ExtName' => $row['ExtName'],
                            'Department' => $row['Department'],
                            'Position' => $row['Position'],
                            'Degree' => $row['Degree']
                        ];
                    }
                    sqlsrv_free_stmt($stmt);
                } else {
                    error_log("Get signatories error: " . print_r(sqlsrv_errors(), true));
                }
                
                echo json_encode(['success' => true, 'data' => $signatories]);
                
            } catch (Exception $e) {
                error_log("Exception in get_signatories: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'get_signatory') {
            // Get single signatory from PGR-DMTS database
            $sigID = $_POST['sig_id'] ?? 0;
            
            if ($sigID <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid signatory ID']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Check if stored procedure exists
                $checkSql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                            WHERE ROUTINE_NAME = 'sp_Signatory_GetById' AND ROUTINE_TYPE = 'PROCEDURE'";
                $checkStmt = sqlsrv_query($conn, $checkSql);
                $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
                
                if ($row['proc_exists'] > 0) {
                    // Use stored procedure
                    $sql = "{CALL sp_Signatory_GetById(?)}";
                    $params = array($sigID);
                } else {
                    // Fallback to direct SQL
                    $sql = "SELECT SigID, Honorific, LastName, FirstName, MiddleName, ExtName,
                            Department, Position, Degree
                            FROM Signatory 
                            WHERE SigID = ?";
                    $params = array($sigID);
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt && sqlsrv_has_rows($stmt)) {
                    $signatory = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    echo json_encode(['success' => true, 'data' => $signatory]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Signatory not found']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in get_signatory: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
        }
    }
}

// Predefined lists for dropdowns
$honorifics = [
    'Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.', 'Atty.', 'Engr.', 'Arch.',
    'Gov.', 'Hon.', 'Sir', 'Madam', 'Prof.', 'Rev.', 'Fr.', 'Sr.',
    'Br.', 'Capt.', 'Col.', 'Gen.', 'Maj.', 'Lt.', 'Cpt.', 'Adm.'
];

$degrees = [
    'MBA', 'MPA', 'CPA','MD', 'PhD', 'DBA', 'LLB', 'JD', 'LLM',
    'MEng', 'BEng', 'CE', 'ME', 'EE', 'ECE', 'Arch', 'RA',
    'RN', 'MD', 'DO', 'DDS', 'DMD', 'PharmD', 'OD', 'DC',
    'DPT', 'DVM', 'EdD', 'PsyD', 'DMin', 'ThD', 'JSD', 'SJD',
    'FPOGS', 'FPSP', 'FPSO', 'FPCS', 'FPCP', 'FPCR', 'FPRP',
    'FPSP', 'FPHA', 'FPAFP', 'BSCS', 'BSIT', 'BSBA', 'BSN',
    'BSA', 'BSArch', 'BSEE', 'BSME', 'BSCE', 'BSCpE'
];

// ============================================
// HTML OUTPUT
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signatory Management - Document Management & Tracking System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --tiffany: #A7E4D5;
            --turq: #30D5C8;
            --navy: #09324A;
            --light-navy: #0A4A6F;
            --sidebar-width: 260px;
            --gradient-dark: linear-gradient(135deg, var(--navy) 0%, #0A4A6F 100%);
            --gradient-card: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            --shadow: 0 5px 15px rgba(11, 35, 50, 0.1);
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8f9fa;
            color: var(--navy);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            padding: 15px 0;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--navy);
            margin: 0;
        }
        
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--tiffany);
        }
        
        .search-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-input {
            padding-left: 40px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--turq);
            z-index: 10;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .search-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: all 0.2s ease;
        }
        
        .search-item:hover {
            background-color: rgba(167, 228, 213, 0.2);
        }
        
        .search-item:last-child {
            border-bottom: none;
        }
        
        .employee-name {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 2px;
        }
        
        .employee-details {
            font-size: 0.85rem;
            color: #666;
        }
        
        .signatory-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .signatory-table th {
            background: var(--navy);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .signatory-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .signatory-table tr:hover {
            background-color: rgba(167, 228, 213, 0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            width: 30px;
            height: 30px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-edit {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .btn-edit:hover {
            background: #28a745;
            color: white;
        }
        
        .btn-delete {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--tiffany);
            box-shadow: 0 0 0 0.2rem rgba(167, 228, 213, 0.25);
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: var(--turq);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        /* Responsive table */
        @media (max-width: 768px) {
            .signatory-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Fade in animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        
        .signatory-name {
            font-weight: 600;
            color: var(--navy);
        }
        
        .signatory-details {
            font-size: 0.85rem;
            color: #666;
        }
        
        #namePreview {
            min-height: 50px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 8px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Include Dashboard Sidebar -->
    <?php 
    if (file_exists('dashboard_sidebar.php')) {
        include 'dashboard_sidebar.php'; 
    } else {
        // Fallback minimal sidebar
        echo '<aside class="sidebar" style="position:fixed;left:0;top:0;width:260px;height:100vh;background:#09324A;color:white;padding:20px;">';
        echo '<h5 class="text-white">Signatory Management</h5>';
        echo '<p class="text-muted">Logged in as: ' . htmlspecialchars($userInfo['full_name']) . '</p>';
        echo '<a href="dashboard.php" class="btn btn-light mt-3">Back to Dashboard</a>';
        echo '</aside>';
        echo '<style>.main-content { margin-left: 260px; }</style>';
    }
    ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div>
                <h1 class="page-title">Signatory Management</h1>
                <p class="text-muted mb-0">Manage document signatories and approvers</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSignatoryModal" onclick="resetForm()">
                    <i class="fas fa-plus me-2"></i>Add New Signatory
                </button>
            </div>
        </header>
        
        <!-- Alert Messages -->
        <div id="alertContainer"></div>
        
        <!-- Signatories List Section -->
        <div class="content-section">
            <h2 class="section-title">
                <i class="fas fa-signature me-2"></i>Document Signatories
            </h2>
            
            <div class="table-responsive">
                <table class="signatory-table">
                    <thead>
                        <tr>
                            <th width="50">ID</th>
                            <th>Honorific</th>
                            <th>Last Name</th>
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Extension</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Degree</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="signatoriesTableBody">
                        <tr>
                            <td colspan="10" class="loading">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading signatories...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add/Edit Signatory Modal -->
    <div class="modal fade" id="addSignatoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        <span id="modalTitle">Add New Signatory</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="signatoryForm">
                    <div class="modal-body">
                        <input type="hidden" id="sigID" name="sig_id" value="0">
                        
                        <!-- Employee Search -->
                        <div class="form-group">
                            <label class="form-label">Search Employee</label>
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" 
                                       class="form-control search-input" 
                                       id="employeeSearch" 
                                       placeholder="Type to search employee by name or username..."
                                       autocomplete="off">
                                <div class="search-results" id="searchResults"></div>
                            </div>
                            <small class="text-muted">Search for employee in HRMIS database. Select to auto-fill details.</small>
                        </div>
                        
                        <!-- Honorific and Name -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Honorific</label>
                                    <select class="form-select" id="honorific" name="honorific">
                                        <option value="">-- Select --</option>
                                        <?php foreach ($honorifics as $honorific): ?>
                                            <option value="<?php echo htmlspecialchars($honorific); ?>"><?php echo htmlspecialchars($honorific); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label required">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label required">First Name</label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middleName" name="middle_name">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Extension and Department -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Extension</label>
                                    <input type="text" class="form-control" id="extName" name="ext_name" placeholder="Jr., Sr., III">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Degree</label>
                                    <select class="form-select" id="degree" name="degree">
                                        <option value="">-- Select --</option>
                                        <?php foreach ($degrees as $degree): ?>
                                            <option value="<?php echo htmlspecialchars($degree); ?>"><?php echo htmlspecialchars($degree); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Position</label>
                                    <input type="text" class="form-control" id="position" name="position">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview of full name -->
                        <div class="form-group">
                            <label class="form-label">Preview of Full Name:</label>
                            <div id="namePreview" class="form-control bg-light" style="height: auto; min-height: 50px;">
                                <span class="text-muted">Full name will appear here...</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveButton">
                            <i class="fas fa-save me-2"></i>Save Signatory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this signatory? This action cannot be undone.</p>
                    <p class="fw-bold" id="deleteSignatoryName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash me-2"></i>Delete Signatory
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <script>
        // Global variables to track delete state
        let currentDeleteSigId = null;
        let currentDeleteRow = null;
        let currentEditSigId = null;
        let searchTimeout = null;
        
        $(document).ready(function() {
            // Load signatories on page load
            loadSignatories();
            
            // Employee search functionality
            $('#employeeSearch').on('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length < 2) {
                    $('#searchResults').hide().empty();
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchEmployees(searchTerm);
                }, 300);
            });
            
            // Close search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#employeeSearch, #searchResults').length) {
                    $('#searchResults').hide();
                }
            });
            
            // Handle form submission
            $('#signatoryForm').on('submit', function(e) {
                e.preventDefault();
                saveSignatory();
            });
            
            // Update name preview when name fields change
            $('#honorific, #lastName, #firstName, #middleName, #extName, #degree').on('input change', function() {
                updateNamePreview();
            });
            
            // When modal is hidden, reset form
            $('#addSignatoryModal').on('hidden.bs.modal', function() {
                resetForm();
            });
            
            // When delete modal is hidden, reset delete variables
            $('#deleteModal').on('hidden.bs.modal', function() {
                currentDeleteSigId = null;
                currentDeleteRow = null;
                $('#confirmDelete').prop('disabled', false).html('<i class="fas fa-trash me-2"></i>Delete Signatory');
            });
            
            // Handle delete confirmation
            $('#confirmDelete').on('click', function() {
                confirmDelete();
            });
            
            // Load signatories function
            function loadSignatories() {
                // Show loading state
                $('#signatoriesTableBody').html('<tr><td colspan="10" class="loading"><i class="fas fa-spinner fa-spin me-2"></i>Loading signatories...</td></tr>');
                
                $.ajax({
                    url: 'signatories.php',
                    method: 'POST',
                    data: { action: 'get_signatories' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            displaySignatories(response.data);
                        } else {
                            showAlert('Failed to load signatories: ' + response.message, 'danger');
                            $('#signatoriesTableBody').html('<tr><td colspan="10" class="no-data">Error loading signatories</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading signatories:', error);
                        showAlert('Failed to load signatories. Please try again.', 'danger');
                        $('#signatoriesTableBody').html('<tr><td colspan="10" class="no-data">Error loading signatories</td></tr>');
                    }
                });
            }
            
            // Display signatories in table
            function displaySignatories(signatories) {
                const tbody = $('#signatoriesTableBody');
                
                if (!signatories || signatories.length === 0) {
                    tbody.html('<tr><td colspan="10" class="no-data">No signatories found</td></tr>');
                    return;
                }
                
                let html = '';
                signatories.forEach(signatory => {
                    // Build full name with honorific and degree
                    let fullName = '';
                    if (signatory.Honorific) fullName += signatory.Honorific + ' ';
                    fullName += (signatory.FirstName || '') + ' ';
                    if (signatory.MiddleName) fullName += signatory.MiddleName + ' ';
                    fullName += (signatory.LastName || '');
                    if (signatory.ExtName) fullName += ', ' + signatory.ExtName;
                    if (signatory.Degree) fullName += ', ' + signatory.Degree;
                    
                    html += `
                        <tr data-sig-id="${signatory.SigID}" class="fade-in">
                            <td>${signatory.SigID}</td>
                            <td>${signatory.Honorific || ''}</td>
                            <td>${signatory.LastName || ''}</td>
                            <td>${signatory.FirstName || ''}</td>
                            <td>${signatory.MiddleName || ''}</td>
                            <td>${signatory.ExtName || ''}</td>
                            <td>${signatory.Department || 'N/A'}</td>
                            <td>${signatory.Position || 'N/A'}</td>
                            <td>${signatory.Degree || ''}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action btn-edit" onclick="editSignatory(${signatory.SigID})" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action btn-delete" onclick="showDeleteModal(${signatory.SigID}, '${escapeHtml(fullName)}', this)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.html(html);
            }
            
            // Search employees in HRMIS
            function searchEmployees(searchTerm) {
                $.ajax({
                    url: 'signatories.php',
                    method: 'POST',
                    data: { 
                        action: 'search_employee',
                        search: searchTerm
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        $('#searchResults').html('<div class="search-item"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</div>').show();
                    },
                    success: function(response) {
                        if (response.success) {
                            displaySearchResults(response.data);
                        } else {
                            $('#searchResults').html(`<div class="search-item">${response.message}</div>`).show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Search error:', error);
                        $('#searchResults').html('<div class="search-item">Search failed. Please try again.</div>').show();
                    }
                });
            }
            
            // Display search results
            function displaySearchResults(employees) {
                const resultsDiv = $('#searchResults');
                
                if (!employees || employees.length === 0) {
                    resultsDiv.html('<div class="search-item">No employees found</div>').show();
                    return;
                }
                
                let html = '';
                employees.forEach(emp => {
                    const fullName = `${emp.FirstName} ${emp.MiddleName ? emp.MiddleName + ' ' : ''}${emp.Surname}`;
                    html += `
                        <div class="search-item" onclick="selectEmployee(${JSON.stringify(emp).replace(/"/g, '&quot;')})">
                            <div class="employee-name">${fullName}</div>
                            <div class="employee-details">
                                <span>Username: ${emp.Username}</span>
                                <span> | Department: ${emp.Department}</span>
                                <span> | Position: ${emp.Position}</span>
                            </div>
                        </div>
                    `;
                });
                
                resultsDiv.html(html).show();
            }
            
            // Save signatory function
            function saveSignatory() {
                const formData = $('#signatoryForm').serialize();
                
                $.ajax({
                    url: 'signatories.php',
                    method: 'POST',
                    data: formData + '&action=save_signatory',
                    dataType: 'json',
                    beforeSend: function() {
                        $('#saveButton').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
                    },
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.message, 'success');
                            $('#addSignatoryModal').modal('hide');
                            resetForm();
                            // Force reload the signatories list
                            loadSignatories();
                        } else {
                            showAlert(response.message, 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Save error:', error);
                        showAlert('Failed to save signatory. Please try again.', 'danger');
                    },
                    complete: function() {
                        $('#saveButton').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Signatory');
                    }
                });
            }
            
            // Update name preview
            function updateNamePreview() {
                const honorific = $('#honorific').val();
                const firstName = $('#firstName').val();
                const middleName = $('#middleName').val();
                const lastName = $('#lastName').val();
                const extName = $('#extName').val();
                const degree = $('#degree').val();
                
                let fullName = '';
                if (honorific) fullName += honorific + ' ';
                if (firstName) fullName += firstName + ' ';
                if (middleName) fullName += middleName + ' ';
                if (lastName) fullName += lastName;
                if (extName) fullName += ', ' + extName;
                if (degree) fullName += ', ' + degree;
                
                if (fullName.trim()) {
                    $('#namePreview').html(fullName.trim());
                } else {
                    $('#namePreview').html('<span class="text-muted">Full name will appear here...</span>');
                }
            }
            
            // Show alert message
            function showAlert(message, type) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                $('#alertContainer').html(alertHtml);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    $('.alert').alert('close');
                }, 5000);
            }
        });
        
        // Escape HTML special characters
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Select employee from search results
        function selectEmployee(employee) {
            $('#firstName').val(employee.FirstName);
            $('#middleName').val(employee.MiddleName || '');
            $('#lastName').val(employee.Surname);
            $('#extName').val('');
            $('#department').val(employee.Department || '');
            $('#position').val(employee.Position || '');
            
            $('#searchResults').hide().empty();
            $('#employeeSearch').val('');
            
            // Update name preview
            updateNamePreview();
        }
        
        // Reset form
        function resetForm() {
            $('#signatoryForm')[0].reset();
            $('#sigID').val('0');
            $('#modalTitle').text('Add New Signatory');
            currentEditSigId = null;
            updateNamePreview();
        }
        
        // Update name preview (global function)
        function updateNamePreview() {
            const honorific = $('#honorific').val();
            const firstName = $('#firstName').val();
            const middleName = $('#middleName').val();
            const lastName = $('#lastName').val();
            const extName = $('#extName').val();
            const degree = $('#degree').val();
            
            let fullName = '';
            if (honorific) fullName += honorific + ' ';
            if (firstName) fullName += firstName + ' ';
            if (middleName) fullName += middleName + ' ';
            if (lastName) fullName += lastName;
            if (extName) fullName += ', ' + extName;
            if (degree) fullName += ', ' + degree;
            
            if (fullName.trim()) {
                $('#namePreview').html(fullName.trim());
            } else {
                $('#namePreview').html('<span class="text-muted">Full name will appear here...</span>');
            }
        }
        
        // Edit signatory
        function editSignatory(sigId) {
            $.ajax({
                url: 'signatories.php',
                method: 'POST',
                data: { 
                    action: 'get_signatory',
                    sig_id: sigId
                },
                dataType: 'json',
                beforeSend: function() {
                    // Show loading state
                },
                success: function(response) {
                    if (response.success) {
                        const signatory = response.data;
                        
                        // Populate form with signatory data
                        $('#sigID').val(signatory.SigID);
                        $('#honorific').val(signatory.Honorific || '');
                        $('#firstName').val(signatory.FirstName);
                        $('#lastName').val(signatory.LastName);
                        $('#middleName').val(signatory.MiddleName || '');
                        $('#extName').val(signatory.ExtName || '');
                        $('#department').val(signatory.Department || '');
                        $('#position').val(signatory.Position || '');
                        $('#degree').val(signatory.Degree || '');
                        
                        // Update modal title
                        $('#modalTitle').text('Edit Signatory');
                        
                        // Update name preview
                        updateNamePreview();
                        
                        // Show modal
                        $('#addSignatoryModal').modal('show');
                    } else {
                        showAlert('Failed to load signatory data: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Edit signatory error:', error);
                    showAlert('Failed to load signatory data. Please try again.', 'danger');
                }
            });
        }
        
        // Show delete confirmation modal
        function showDeleteModal(sigId, signatoryName, button) {
            currentDeleteSigId = sigId;
            currentDeleteRow = $(button).closest('tr');
            $('#deleteSignatoryName').text('Signatory: ' + signatoryName);
            $('#deleteModal').modal('show');
        }
        
        // Confirm delete function
        function confirmDelete() {
            if (!currentDeleteSigId || !currentDeleteRow) {
                console.error('Delete variables not set');
                showAlert('Delete error: Missing signatory information.', 'danger');
                return;
            }
            
            $.ajax({
                url: 'signatories.php',
                method: 'POST',
                data: {
                    action: 'delete_signatory',
                    sig_id: currentDeleteSigId
                },
                dataType: 'json',
                beforeSend: function() {
                    $('#confirmDelete').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Deleting...');
                },
                success: function(response) {
                    console.log('Delete response:', response);
                    if (response.success) {
                        // Remove the row with animation
                        currentDeleteRow.fadeOut(300, function() {
                            $(this).remove();
                            showAlert(response.message, 'success');
                            
                            // Check if table is now empty
                            if ($('#signatoriesTableBody tr').length === 0) {
                                $('#signatoriesTableBody').html('<tr><td colspan="10" class="no-data">No signatories found</td></tr>');
                            }
                        });
                    } else {
                        showAlert(response.message, 'danger');
                    }
                    $('#deleteModal').modal('hide');
                },
                error: function(xhr, status, error) {
                    console.error('Delete error:', error);
                    showAlert('Failed to delete signatory. Please try again.', 'danger');
                    $('#deleteModal').modal('hide');
                },
                complete: function() {
                    currentDeleteSigId = null;
                    currentDeleteRow = null;
                    $('#confirmDelete').prop('disabled', false).html('<i class="fas fa-trash me-2"></i>Delete Signatory');
                }
            });
        }
        
        // Helper function for showing alerts
        function showAlert(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            $('#alertContainer').html(alertHtml);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                $('.alert').alert('close');
            }, 5000);
        }
    </script>
</body>
</html>