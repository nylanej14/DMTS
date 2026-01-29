<?php
// users.php - User Management for Document Management & Tracking System

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

// Get pending user count for the badge
$pendingCount = 0;
$conn = null;
try {
    // Database connection for PGR-DMTS
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
    if ($conn) {
        $sql = "SELECT COUNT(*) as count FROM Users WHERE UserStatus = 'Pending'";
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $pendingCount = $row['count'];
        }
        if ($stmt) sqlsrv_free_stmt($stmt);
    }
} catch (Exception $e) {
    error_log("Error getting pending count: " . $e->getMessage());
}
if ($conn) sqlsrv_close($conn);

// Database connection function for PGR-DMTS
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
            
        } elseif ($action === 'save_user') {
            // Save user to PGR-DMTS database using stored procedure
            $userID = $_POST['user_id'] ?? 0;
            $hrmisUsername = $_POST['hrmis_username'] ?? '';
            $firstName = $_POST['first_name'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            $middleName = $_POST['middle_name'] ?? '';
            $extName = $_POST['ext_name'] ?? '';
            $department = $_POST['department'] ?? '';
            $position = $_POST['position'] ?? '';
            $userAccess = $_POST['user_access'] ?? 'User';
            $userStatus = $_POST['user_status'] ?? 'Active';
            
            if (empty($hrmisUsername) || empty($firstName) || empty($lastName)) {
                echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Use stored procedure for save/update
                if ($userID > 0) {
                    // Check if stored procedure exists, otherwise use direct SQL
                    $sql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                            WHERE ROUTINE_NAME = 'sp_Users_Update' AND ROUTINE_TYPE = 'PROCEDURE'";
                    $stmt = sqlsrv_query($conn, $sql);
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    
                    if ($row && $row['proc_exists'] > 0) {
                        // Use stored procedure
                        $sql = "{CALL sp_Users_Update(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
                        $params = array(
                            array($userID, SQLSRV_PARAM_IN),
                            array($hrmisUsername, SQLSRV_PARAM_IN),
                            array($firstName, SQLSRV_PARAM_IN),
                            array($lastName, SQLSRV_PARAM_IN),
                            array($middleName, SQLSRV_PARAM_IN),
                            array($extName, SQLSRV_PARAM_IN),
                            array($department, SQLSRV_PARAM_IN),
                            array($position, SQLSRV_PARAM_IN),
                            array($userAccess, SQLSRV_PARAM_IN),
                            array($userStatus, SQLSRV_PARAM_IN),
                            array($_SESSION['username'], SQLSRV_PARAM_IN)
                        );
                    } else {
                        // Fallback to direct SQL
                        $sql = "UPDATE Users SET 
                                HRMIS_Username = ?, 
                                Firstname = ?, 
                                Lastname = ?, 
                                MiddleName = ?, 
                                ExtName = ?,
                                Department = ?, 
                                Position = ?, 
                                UserAccess = ?, 
                                UserStatus = ?,
                                DateModified = GETDATE(),
                                ModifiedBy = ?
                                WHERE UserID = ?";
                        
                        $params = array(
                            $hrmisUsername, $firstName, $lastName, $middleName, $extName,
                            $department, $position, $userAccess, $userStatus, 
                            $_SESSION['username'], $userID
                        );
                    }
                } else {
                    // Check if stored procedure exists
                    $sql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                            WHERE ROUTINE_NAME = 'sp_Users_Insert' AND ROUTINE_TYPE = 'PROCEDURE'";
                    $stmt = sqlsrv_query($conn, $sql);
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    
                    if ($row && $row['proc_exists'] > 0) {
                        // Use stored procedure
                        $sql = "{CALL sp_Users_Insert(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
                        $params = array(
                            array($hrmisUsername, SQLSRV_PARAM_IN),
                            array($firstName, SQLSRV_PARAM_IN),
                            array($lastName, SQLSRV_PARAM_IN),
                            array($middleName, SQLSRV_PARAM_IN),
                            array($extName, SQLSRV_PARAM_IN),
                            array($department, SQLSRV_PARAM_IN),
                            array($position, SQLSRV_PARAM_IN),
                            array($userAccess, SQLSRV_PARAM_IN),
                            array($userStatus, SQLSRV_PARAM_IN),
                            array($_SESSION['username'], SQLSRV_PARAM_IN)
                        );
                    } else {
                        // Fallback to direct SQL
                        $sql = "INSERT INTO Users 
                                (HRMIS_Username, Firstname, Lastname, MiddleName, ExtName, 
                                Department, Position, UserAccess, UserStatus, DateCreated, CreatedBy) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)";
                        
                        $params = array(
                            $hrmisUsername, $firstName, $lastName, $middleName, $extName,
                            $department, $position, $userAccess, $userStatus, $_SESSION['username']
                        );
                    }
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'User saved successfully']);
                } else {
                    $errors = sqlsrv_errors();
                    error_log("Save user error: " . print_r($errors, true));
                    echo json_encode(['success' => false, 'message' => 'Failed to save user. Please check database.']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in save_user: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'delete_user') {
            // Delete user from PGR-DMTS database
            $userID = $_POST['user_id'] ?? 0;
            
            if ($userID <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Check if stored procedure exists
                $sql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                        WHERE ROUTINE_NAME = 'sp_Users_Delete' AND ROUTINE_TYPE = 'PROCEDURE'";
                $stmt = sqlsrv_query($conn, $sql);
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                
                if ($row && $row['proc_exists'] > 0) {
                    // Use stored procedure
                    $sql = "{CALL sp_Users_Delete(?)}";
                    $params = array($userID);
                } else {
                    // Fallback to direct SQL
                    $sql = "DELETE FROM Users WHERE UserID = ?";
                    $params = array($userID);
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                } else {
                    $errors = sqlsrv_errors();
                    error_log("Delete user error: " . print_r($errors, true));
                    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in delete_user: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'get_users') {
            // Get users from PGR-DMTS database with optional status filter
            $status = $_POST['status'] ?? '';
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Build query based on status
                if ($status && in_array($status, ['Active', 'Pending', 'Inactive'])) {
                    $sql = "SELECT UserID, HRMIS_Username, Lastname, Firstname, MiddleName, ExtName,
                            Department, Position, UserAccess, UserStatus, DateCreated,
                            CreatedBy, DateModified, ModifiedBy
                            FROM Users 
                            WHERE UserStatus = ?
                            ORDER BY Lastname, Firstname";
                    $params = array($status);
                } else {
                    $sql = "SELECT UserID, HRMIS_Username, Lastname, Firstname, MiddleName, ExtName,
                            Department, Position, UserAccess, UserStatus, DateCreated,
                            CreatedBy, DateModified, ModifiedBy
                            FROM Users 
                            ORDER BY 
                            CASE UserStatus 
                                WHEN 'Pending' THEN 1
                                WHEN 'Active' THEN 2
                                WHEN 'Inactive' THEN 3
                                ELSE 4
                            END,
                            Lastname, Firstname";
                    $params = array();
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                $users = [];
                if ($stmt) {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $users[] = [
                            'UserID' => $row['UserID'],
                            'HRMIS_Username' => $row['HRMIS_Username'],
                            'Lastname' => $row['Lastname'],
                            'Firstname' => $row['Firstname'],
                            'MiddleName' => $row['MiddleName'],
                            'ExtName' => $row['ExtName'],
                            'Department' => $row['Department'],
                            'Position' => $row['Position'],
                            'UserAccess' => $row['UserAccess'],
                            'UserStatus' => $row['UserStatus'],
                            'DateCreated' => $row['DateCreated'] ? $row['DateCreated']->format('Y-m-d H:i:s') : '',
                            'CreatedBy' => $row['CreatedBy'] ?? '',
                            'DateModified' => $row['DateModified'] ? $row['DateModified']->format('Y-m-d H:i:s') : '',
                            'ModifiedBy' => $row['ModifiedBy'] ?? ''
                        ];
                    }
                    sqlsrv_free_stmt($stmt);
                } else {
                    error_log("Get users error: " . print_r(sqlsrv_errors(), true));
                }
                
                echo json_encode(['success' => true, 'data' => $users]);
                
            } catch (Exception $e) {
                error_log("Exception in get_users: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'get_user') {
            // Get single user from PGR-DMTS database
            $userID = $_POST['user_id'] ?? 0;
            
            if ($userID <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // Check if stored procedure exists
                $sql = "SELECT COUNT(*) as proc_exists FROM INFORMATION_SCHEMA.ROUTINES 
                        WHERE ROUTINE_NAME = 'sp_Users_GetById' AND ROUTINE_TYPE = 'PROCEDURE'";
                $stmt = sqlsrv_query($conn, $sql);
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                
                if ($row && $row['proc_exists'] > 0) {
                    // Use stored procedure
                    $sql = "{CALL sp_Users_GetById(?)}";
                    $params = array($userID);
                } else {
                    // Fallback to direct SQL
                    $sql = "SELECT UserID, HRMIS_Username, Firstname, Lastname, MiddleName, ExtName,
                            Department, Position, UserAccess, UserStatus 
                            FROM Users 
                            WHERE UserID = ?";
                    $params = array($userID);
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt && sqlsrv_has_rows($stmt)) {
                    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in get_user: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
            
        } elseif ($action === 'approve_user') {
            // Approve user in PGR-DMTS database
            $userID = $_POST['user_id'] ?? 0;
            
            if ($userID <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }
            
            $conn = connectToDMTS();
            
            if (!$conn) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
            
            try {
                // First check if user exists and is pending
                $checkSql = "SELECT UserStatus FROM Users WHERE UserID = ?";
                $checkStmt = sqlsrv_query($conn, $checkSql, array($userID));
                
                if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
                    $user = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
                    
                    if ($user['UserStatus'] !== 'Pending') {
                        echo json_encode(['success' => false, 'message' => 'User is not pending approval']);
                        exit;
                    }
                    
                    // Update user to Active
                    $sql = "UPDATE Users SET 
                            UserStatus = 'Active',
                            DateModified = GETDATE(),
                            ModifiedBy = ?
                            WHERE UserID = ?";
                    
                    $params = array($_SESSION['username'], $userID);
                    $stmt = sqlsrv_query($conn, $sql, $params);
                    
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'User approved successfully']);
                    } else {
                        $errors = sqlsrv_errors();
                        error_log("Approve user error: " . print_r($errors, true));
                        echo json_encode(['success' => false, 'message' => 'Failed to approve user']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
                
            } catch (Exception $e) {
                error_log("Exception in approve_user: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            
            sqlsrv_close($conn);
            exit;
        }
    }
}

// ============================================
// HTML OUTPUT
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Document Management & Tracking System</title>
    
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
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .user-table th {
            background: var(--navy);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .user-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .user-table tr:hover {
            background-color: rgba(167, 228, 213, 0.1);
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-inactive {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .access-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .access-admin {
            background: rgba(0, 123, 255, 0.2);
            color: #007bff;
        }
        
        .access-user {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }
        
        .access-administrator {
            background: rgba(111, 66, 193, 0.2);
            color: #6f42c1;
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
        
        .btn-approve {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .btn-approve:hover {
            background: #28a745;
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
        
        /* Status Filter Tabs */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--navy);
            background-color: white;
            border-bottom: 3px solid var(--tiffany);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--navy);
            background-color: rgba(167, 228, 213, 0.1);
        }
        
        /* Responsive table */
        @media (max-width: 768px) {
            .user-table {
                display: block;
                overflow-x: auto;
            }
            
            .nav-tabs {
                flex-wrap: nowrap;
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
        
        /* Loading spinner for approve button */
        .btn-approve .fa-spinner {
            font-size: 0.8rem;
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
        echo '<h5 class="text-white">User Management</h5>';
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
                <h1 class="page-title">User Management</h1>
                <p class="text-muted mb-0">Manage system users and access levels</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal" onclick="resetForm()">
                    <i class="fas fa-plus me-2"></i>Add New User
                </button>
            </div>
        </header>
        
        <!-- Alert Messages -->
        <div id="alertContainer"></div>
        
        <!-- Status Filter Tabs -->
        <div class="row mb-4">
            <div class="col-md-12">
                <ul class="nav nav-tabs" id="userStatusTabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" data-status="all">All Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-status="Active">Active</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-status="Pending">
                            Pending Approval
                            <?php if ($pendingCount > 0): ?>
                            <span class="badge bg-warning ms-1" id="pendingTabBadge"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-status="Inactive">Inactive</a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Users List Section -->
        <div class="content-section">
            <h2 class="section-title">
                <i class="fas fa-users me-2"></i>System Users
            </h2>
            
            <div class="table-responsive">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th width="50">ID</th>
                            <th>HRMIS Username</th>
                            <th>Last Name</th>
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Extension</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th width="120">Access</th>
                            <th width="100">Status</th>
                            <th width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr>
                            <td colspan="11" class="loading">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading users...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add/Edit User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        <span id="modalTitle">Add New User</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm">
                    <div class="modal-body">
                        <input type="hidden" id="userID" name="user_id" value="0">
                        
                        <!-- Employee Search -->
                        <div class="form-group">
                            <label class="form-label required">Search Employee</label>
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
                        
                        <!-- User Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required">First Name</label>
                                    <input type="text" class="form-control" id="firstName" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required">Last Name</label>
                                    <input type="text" class="form-control" id="lastName" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middleName" name="middle_name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Extension</label>
                                    <input type="text" class="form-control" id="extName" name="ext_name" placeholder="Jr., Sr., III">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label required">HRMIS Username</label>
                                    <input type="text" class="form-control" id="hrmisUsername" name="hrmis_username" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" id="department" name="department">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Position</label>
                                    <input type="text" class="form-control" id="position" name="position">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required">User Access</label>
                                    <select class="form-select" id="userAccess" name="user_access" required>
                                        <option value="User">User</option>
                                        <option value="Admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label required">Status</label>
                                    <select class="form-select" id="userStatus" name="user_status" required>
                                        <option value="Active">Active</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveButton">
                            <i class="fas fa-save me-2"></i>Save User
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
                    <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                    <p class="fw-bold" id="deleteUserName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash me-2"></i>Delete User
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
        $(document).ready(function() {
            let currentEditUserId = null;
            let currentDeleteUserId = null;
            let currentDeleteRow = null;
            let searchTimeout = null;
            let currentStatusFilter = 'all';
            
            // Load users on page load
            loadUsers();
            
            // Status filter tabs
            $(document).on('click', '#userStatusTabs .nav-link', function(e) {
                e.preventDefault();
                
                // Update active tab
                $('#userStatusTabs .nav-link').removeClass('active');
                $(this).addClass('active');
                
                // Load users with the selected status
                currentStatusFilter = $(this).data('status');
                loadUsers(currentStatusFilter);
            });
            
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
            $('#userForm').on('submit', function(e) {
                e.preventDefault();
                saveUser();
            });
            
            // When modal is hidden, reset form
            $('#addUserModal').on('hidden.bs.modal', function() {
                resetForm();
            });
            
            // When delete modal is hidden, reset delete variables
            $('#deleteModal').on('hidden.bs.modal', function() {
                currentDeleteUserId = null;
                currentDeleteRow = null;
                $('#confirmDelete').prop('disabled', false).html('<i class="fas fa-trash me-2"></i>Delete User');
            });
            
            // Load users function
            function loadUsers(status = 'all') {
                $.ajax({
                    url: 'users.php',
                    method: 'POST',
                    data: { 
                        action: 'get_users',
                        status: status
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        $('#usersTableBody').html('<tr><td colspan="11" class="loading"><i class="fas fa-spinner fa-spin me-2"></i>Loading users...</td></tr>');
                    },
                    success: function(response) {
                        if (response.success) {
                            displayUsers(response.data);
                        } else {
                            showAlert('Failed to load users: ' + response.message, 'danger');
                            $('#usersTableBody').html('<tr><td colspan="11" class="no-data">Error loading users</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading users:', error);
                        showAlert('Failed to load users. Please try again.', 'danger');
                        $('#usersTableBody').html('<tr><td colspan="11" class="no-data">Error loading users</td></tr>');
                    }
                });
            }
            
            // Display users in table
            function displayUsers(users) {
                const tbody = $('#usersTableBody');
                
                if (!users || users.length === 0) {
                    let message = 'No users found';
                    if (currentStatusFilter === 'Pending') {
                        message = 'No pending users found';
                    } else if (currentStatusFilter === 'Active') {
                        message = 'No active users found';
                    } else if (currentStatusFilter === 'Inactive') {
                        message = 'No inactive users found';
                    }
                    tbody.html(`<tr><td colspan="11" class="no-data">${message}</td></tr>`);
                    return;
                }
                
                let html = '';
                users.forEach(user => {
                    // Get access badge class
                    let accessClass = 'access-user';
                    if (user.UserAccess === 'Admin') {
                        accessClass = 'access-admin';
                    } else if (user.UserAccess === 'Administrator') {
                        accessClass = 'access-administrator';
                    }
                    
                    // Get status badge class
                    let statusClass = user.UserStatus === 'Active' ? 'status-active' : 
                                     user.UserStatus === 'Pending' ? 'status-pending' : 'status-inactive';
                    
                    // Build action buttons
                    let actionButtons = `<div class="action-buttons">`;
                    
                    // Add Approve button ONLY for pending users
                    if (user.UserStatus === 'Pending') {
                        actionButtons += `
                            <button class="btn-action btn-approve" onclick="approveUser(${user.UserID})" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                        `;
                    }
                    
                    // Always show Edit and Delete buttons
                    actionButtons += `
                        <button class="btn-action btn-edit" onclick="editUser(${user.UserID})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" onclick="showDeleteModal(${user.UserID}, '${escapeHtml((user.Firstname || '') + ' ' + (user.Lastname || ''))}', this)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>`;
                    
                    html += `
                        <tr data-user-id="${user.UserID}" class="fade-in" data-status="${user.UserStatus}">
                            <td>${user.UserID}</td>
                            <td>${user.HRMIS_Username || ''}</td>
                            <td>${user.Lastname || ''}</td>
                            <td>${user.Firstname || ''}</td>
                            <td>${user.MiddleName || ''}</td>
                            <td>${user.ExtName || ''}</td>
                            <td>${user.Department || 'N/A'}</td>
                            <td>${user.Position || 'N/A'}</td>
                            <td>
                                <span class="access-badge ${accessClass}">
                                    ${user.UserAccess || 'User'}
                                </span>
                            </td>
                            <td id="status-cell-${user.UserID}">
                                <span class="status-badge ${statusClass}">
                                    ${user.UserStatus || 'Active'}
                                </span>
                            </td>
                            <td id="action-cell-${user.UserID}">
                                ${actionButtons}
                            </td>
                        </tr>
                    `;
                });
                
                tbody.html(html);
            }
            
            // Search employees in HRMIS
            function searchEmployees(searchTerm) {
                $.ajax({
                    url: 'users.php',
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
            
            // Save user function
            function saveUser() {
                const formData = $('#userForm').serialize();
                
                $.ajax({
                    url: 'users.php',
                    method: 'POST',
                    data: formData + '&action=save_user',
                    dataType: 'json',
                    beforeSend: function() {
                        $('#saveButton').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
                    },
                    success: function(response) {
                        if (response.success) {
                            showAlert(response.message, 'success');
                            $('#addUserModal').modal('hide');
                            resetForm();
                            loadUsers(currentStatusFilter);
                        } else {
                            showAlert(response.message, 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Save error:', error);
                        showAlert('Failed to save user. Please try again.', 'danger');
                    },
                    complete: function() {
                        $('#saveButton').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save User');
                    }
                });
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
            $('#hrmisUsername').val(employee.Username);
            $('#firstName').val(employee.FirstName);
            $('#middleName').val(employee.MiddleName || '');
            $('#lastName').val(employee.Surname);
            $('#extName').val('');
            $('#department').val(employee.Department || '');
            $('#position').val(employee.Position || '');
            
            $('#searchResults').hide().empty();
            $('#employeeSearch').val('');
        }
        
        // Reset form
        function resetForm() {
            $('#userForm')[0].reset();
            $('#userID').val('0');
            $('#modalTitle').text('Add New User');
            $('#userStatus').val('Active'); // Default status for manual creation
        }
        
        // Edit user
        function editUser(userId) {
            $.ajax({
                url: 'users.php',
                method: 'POST',
                data: { 
                    action: 'get_user',
                    user_id: userId
                },
                dataType: 'json',
                beforeSend: function() {
                    // Show loading state
                },
                success: function(response) {
                    if (response.success) {
                        const user = response.data;
                        
                        // Populate form with user data
                        $('#userID').val(user.UserID);
                        $('#hrmisUsername').val(user.HRMIS_Username);
                        $('#firstName').val(user.Firstname);
                        $('#lastName').val(user.Lastname);
                        $('#middleName').val(user.MiddleName || '');
                        $('#extName').val(user.ExtName || '');
                        $('#department').val(user.Department || '');
                        $('#position').val(user.Position || '');
                        $('#userAccess').val(user.UserAccess || 'User');
                        $('#userStatus').val(user.UserStatus || 'Active');
                        
                        // Update modal title
                        $('#modalTitle').text('Edit User');
                        
                        // Show modal
                        $('#addUserModal').modal('show');
                    } else {
                        showAlert('Failed to load user data: ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Edit user error:', error);
                    showAlert('Failed to load user data. Please try again.', 'danger');
                }
            });
        }
        
        // Show delete confirmation modal
        function showDeleteModal(userId, userName, button) {
            currentDeleteUserId = userId;
            currentDeleteRow = $(button).closest('tr');
            $('#deleteUserName').text('User: ' + userName);
            $('#deleteModal').modal('show');
        }
        
        // Approve user - UPDATED FOR INSTANT REFLECTION
        function approveUser(userId) {
            if (!confirm('Are you sure you want to approve this user? They will be able to login immediately.')) return;
            
            // Get the row element
            const row = $(`tr[data-user-id="${userId}"]`);
            const approveBtn = $(`#action-cell-${userId} .btn-approve`);
            
            $.ajax({
                url: 'users.php',
                method: 'POST',
                data: {
                    action: 'approve_user',
                    user_id: userId
                },
                dataType: 'json',
                beforeSend: function() {
                    // Show loading on the approve button
                    approveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                },
                success: function(response) {
                    if (response.success) {
                        // Update status badge immediately
                        $(`#status-cell-${userId}`).html(`
                            <span class="status-badge status-active">
                                Active
                            </span>
                        `);
                        
                        // Remove approve button
                        approveBtn.remove();
                        
                        // Update row data-status attribute
                        row.attr('data-status', 'Active');
                        
                        // Show success message
                        const userName = row.find('td:nth-child(4)').text().trim() + ' ' + row.find('td:nth-child(3)').text().trim();
                        showAlert(`User "${userName}" approved successfully!`, 'success');
                        
                        // Update pending count in the tab
                        updatePendingTabCount();
                        
                        // If we're in the Pending tab and no more pending users, show message
                        const currentStatus = $('#userStatusTabs .nav-link.active').data('status');
                        if (currentStatus === 'Pending') {
                            setTimeout(() => {
                                const pendingRows = $('tr[data-status="Pending"]');
                                if (pendingRows.length === 0) {
                                    $('#usersTableBody').html('<tr><td colspan="11" class="no-data">No pending users found</td></tr>');
                                }
                            }, 300);
                        }
                    } else {
                        showAlert(response.message, 'danger');
                        // Reset button
                        approveBtn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Approve error:', error);
                    showAlert('Failed to approve user. Please try again.', 'danger');
                    // Reset button
                    approveBtn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                }
            });
        }
        
        // Update pending tab count
        function updatePendingTabCount() {
            const pendingCount = $('tr[data-status="Pending"]').length;
            const pendingTab = $('#userStatusTabs .nav-link[data-status="Pending"]');
            let badge = pendingTab.find('.badge');
            
            if (pendingCount > 0) {
                if (badge.length === 0) {
                    pendingTab.append(`<span class="badge bg-warning ms-1" id="pendingTabBadge">${pendingCount}</span>`);
                } else {
                    badge.text(pendingCount);
                }
            } else {
                badge.remove();
            }
        }
        
        // Confirm delete
        $('#confirmDelete').on('click', function() {
            if (!currentDeleteUserId || !currentDeleteRow) return;
            
            $.ajax({
                url: 'users.php',
                method: 'POST',
                data: {
                    action: 'delete_user',
                    user_id: currentDeleteUserId
                },
                dataType: 'json',
                beforeSend: function() {
                    $('#confirmDelete').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row with animation
                        currentDeleteRow.fadeOut(300, function() {
                            $(this).remove();
                            showAlert(response.message, 'success');
                            
                            // Check if table is now empty
                            if ($('#usersTableBody tr').length === 0) {
                                $('#usersTableBody').html('<tr><td colspan="11" class="no-data">No users found</td></tr>');
                            }
                        });
                        
                        // Update pending count if needed
                        updatePendingTabCount();
                    } else {
                        showAlert(response.message, 'danger');
                    }
                    $('#deleteModal').modal('hide');
                },
                error: function(xhr, status, error) {
                    console.error('Delete error:', error);
                    showAlert('Failed to delete user. Please try again.', 'danger');
                    $('#deleteModal').modal('hide');
                },
                complete: function() {
                    currentDeleteUserId = null;
                    currentDeleteRow = null;
                    $('#confirmDelete').prop('disabled', false).html('<i class="fas fa-trash me-2"></i>Delete User');
                }
            });
        });
        
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