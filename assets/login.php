<?php
// login.php - Consolidated Authentication Page

// ============================================
// CONFIGURATION & SESSION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ============================================
// DATABASE CLASS (Updated for multiple databases)
// ============================================
class Database {
    private $serverName = "10.0.0.4\PGR2022";
    private $connectionOptions;
    private $conn;
    private $currentDB = "";

    public function __construct($database = "PGR_ID_System") {
        $this->connectionOptions = array(
            "Database" => $database,
            "Uid" => "PRG_AdminUser",
            "PWD" => "PRG!ESXiAdminProd#2025$",
            "CharacterSet" => "UTF-8",
            "TrustServerCertificate" => true,
            "Encrypt" => false
        );
        
        $this->conn = sqlsrv_connect($this->serverName, $this->connectionOptions);
        if (!$this->conn) {
            die("Connection failed: " . print_r(sqlsrv_errors(), true));
        }
        $this->currentDB = $database;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function switchDatabase($database) {
        $sql = "USE [$database]";
        $stmt = sqlsrv_query($this->conn, $sql);
        if ($stmt) {
            $this->currentDB = $database;
            sqlsrv_free_stmt($stmt);
            return true;
        }
        return false;
    }

    public function getCurrentDB() {
        return $this->currentDB;
    }
    
    public function close() {
        sqlsrv_close($this->conn);
    }
}

// ============================================
// AUTHENTICATION CLASS (Updated with Profile Picture)
// ============================================
class Auth {
    private $database;

    public function __construct() {
        $this->database = new Database("PGR_ID_System");
    }

    public function login($username, $password) {
        // First authenticate against HRMIS
        if (!$this->database->switchDatabase("OPAD-HRMIS")) {
            error_log("Failed to switch to HRMIS database");
            return false;
        }

        // Execute stored procedure from HRMIS database
        $sql = "EXEC dbo.UsersProfileLoad_AssemblyLogin ?, ?";
        $params = array($username, $password);
        $conn = $this->database->getConnection();
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            error_log("Stored procedure error: " . print_r(sqlsrv_errors(), true));
            $this->database->switchDatabase("PGR_ID_System");
            return false;
        }

        if (sqlsrv_has_rows($stmt)) {
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            // Build full name from components
            $FirstName = $user['FirstName'] ?? '';
            $MiddleName = $user['MiddleName'] ?? '';
            $Surname = $user['Surname'] ?? '';
            $fullName = trim($FirstName . ' ' . $MiddleName . ' ' . $Surname);
            $hrmisUsername = $user['Username'] ?? $username;
            
            // Fetch profile picture from HRMIS database
            $profile_pic_src = $this->getProfilePicture($hrmisUsername);
            
            // Now check PGR-DMTS database for user access and status
            $dmtsUser = $this->checkDMTSUserAccess($hrmisUsername);
            
            if ($dmtsUser === false) {
                // User not in DMTS - create pending user
                $this->createPendingDMTSUser(
                    $hrmisUsername,
                    $FirstName,
                    $MiddleName,
                    $Surname,
                    $user['Department'] ?? '',
                    $user['Position'] ?? ''
                );
                
                // Switch back to main database
                $this->database->switchDatabase("PGR_ID_System");
                
                // Log the pending registration attempt
                $this->logLoginActivity($hrmisUsername, $fullName, 'Pending');
                
                return 'pending'; // Special return code for pending approval
            }
            
            // Check if user is active
            if ($dmtsUser['UserStatus'] !== 'Active') {
                // User exists but is not active (Pending, Inactive, etc.)
                $this->database->switchDatabase("PGR_ID_System");
                $this->logLoginActivity($hrmisUsername, $fullName, $dmtsUser['UserStatus']);
                
                if ($dmtsUser['UserStatus'] === 'Pending') {
                    return 'pending';
                } else if ($dmtsUser['UserStatus'] === 'Inactive') {
                    return 'inactive';
                } else {
                    return false;
                }
            }
            
            // Switch back to main database before setting session and logging activity
            $this->database->switchDatabase("PGR_ID_System");

            // Set session variables
            $_SESSION['user_id'] = $hrmisUsername;
            $_SESSION['username'] = $hrmisUsername;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['user_role'] = $dmtsUser['UserAccess']; // From PGR-DMTS
            $_SESSION['access_level'] = $user['AccesLevel'] ?? '';
            $_SESSION['department'] = $user['Department'] ?? '';
            $_SESSION['position'] = $user['Position'] ?? '';
            $_SESSION['logged_in'] = true;
            
            // Store profile picture in session if available
            if ($profile_pic_src) {
                $_SESSION['profile_pic'] = $profile_pic_src;
            }

            // Log login activity in local database
            $this->logLoginActivity($hrmisUsername, $fullName, $dmtsUser['UserAccess']);

            return true;
        }
        
        // Switch back to main database if login fails
        $this->database->switchDatabase("PGR_ID_System");
        return false;
    }

    private function getProfilePicture($username) {
        try {
            $sql = "SELECT ProfilePicThumdnail 
                   FROM UsersProfile 
                   WHERE Username = ?";
            
            $conn = $this->database->getConnection();
            $stmt = sqlsrv_query($conn, $sql, array($username));

            if ($stmt && sqlsrv_has_rows($stmt)) {
                $pic_user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                
                // Handle profile picture
                if (!empty($pic_user['ProfilePicThumdnail'])) {
                    $image_data = $pic_user['ProfilePicThumdnail'];
                    
                    // Handle both stream and direct data
                    if (is_resource($image_data)) {
                        $binary_data = stream_get_contents($image_data);
                    } else {
                        $binary_data = $image_data;
                    }
                    
                    // Create data URL if we have valid data
                    if (!empty($binary_data) && strlen($binary_data) > 100) {
                        // Try to detect image type
                        $header = substr($binary_data, 0, 8);
                        
                        if (strpos($header, "\xFF\xD8\xFF") === 0) {
                            // JPEG
                            return 'data:image/jpeg;base64,' . base64_encode($binary_data);
                        } elseif (strpos($header, "\x89PNG\r\n\x1A\n") === 0) {
                            // PNG
                            return 'data:image/png;base64,' . base64_encode($binary_data);
                        } elseif (strpos($header, "GIF8") === 0) {
                            // GIF
                            return 'data:image/gif;base64,' . base64_encode($binary_data);
                        } else {
                            // Default to JPEG
                            return 'data:image/jpeg;base64,' . base64_encode($binary_data);
                        }
                    }
                }
            }
            
            sqlsrv_free_stmt($stmt);
            return null;
            
        } catch (Exception $e) {
            error_log("Error loading profile picture: " . $e->getMessage());
            return null;
        }
    }

    private function checkDMTSUserAccess($hrmisUsername) {
        // Connect to PGR-DMTS database
        $dmtsDB = new Database("PGR-DMTS");
        $conn = $dmtsDB->getConnection();
        
        $sql = "SELECT UserAccess, UserStatus FROM Users WHERE HRMIS_Username = ?";
        $params = array($hrmisUsername);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $dmtsDB->close();
            return [
                'UserAccess' => $row['UserAccess'],
                'UserStatus' => $row['UserStatus']
            ];
        }
        
        $dmtsDB->close();
        return false;
    }

    private function createPendingDMTSUser($hrmisUsername, $firstName, $middleName, $lastName, $department, $position) {
        // Connect to PGR-DMTS database
        $dmtsDB = new Database("PGR-DMTS");
        $conn = $dmtsDB->getConnection();
        
        // Check if user already exists
        $checkSql = "SELECT UserID FROM Users WHERE HRMIS_Username = ?";
        $checkStmt = sqlsrv_query($conn, $checkSql, array($hrmisUsername));
        
        if ($checkStmt && sqlsrv_has_rows($checkStmt)) {
            // User already exists, just return
            sqlsrv_free_stmt($checkStmt);
            $dmtsDB->close();
            return true;
        }
        sqlsrv_free_stmt($checkStmt);
        
        // Insert new user with Pending status
        $sql = "INSERT INTO Users 
                (HRMIS_Username, Firstname, Lastname, MiddleName, 
                 Department, Position, UserAccess, UserStatus, 
                 DateCreated, CreatedBy) 
                VALUES (?, ?, ?, ?, ?, ?, 'User', 'Pending', GETDATE(), 'System')";
        $params = array($hrmisUsername, $firstName, $lastName, $middleName, 
                       $department, $position);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt) {
            sqlsrv_free_stmt($stmt);
        }
        
        $dmtsDB->close();
        return true;
    }

    private function logLoginActivity($username, $fullName, $role) {
        // Switch back to PGR_ID_System for logging
        $this->database->switchDatabase("PGR_ID_System");
        
        $conn = $this->database->getConnection();
        $sql = "INSERT INTO login_activity (username, full_name, role, ip_address, login_time) 
                VALUES (?, ?, ?, ?, GETDATE())";
        $params = array($username, $fullName, $role, $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            error_log("Failed to log login activity: " . print_r(sqlsrv_errors(), true));
        } else {
            sqlsrv_free_stmt($stmt);
        }
    }

    public function logout() {
        if (isset($_SESSION['username'])) {
            $this->logLogoutActivity($_SESSION['username']);
        }
        // Clear profile picture from session
        if (isset($_SESSION['profile_pic'])) {
            unset($_SESSION['profile_pic']);
        }
        session_destroy();
    }

    private function logLogoutActivity($username) {
        $conn = $this->database->getConnection();
        $sql = "UPDATE login_activity SET logout_time = GETDATE() 
                WHERE username = ? AND logout_time IS NULL 
                ORDER BY login_time DESC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY";
        $stmt = sqlsrv_query($conn, $sql, array($username));
        
        if ($stmt === false) {
            error_log("Failed to log logout activity: " . print_r(sqlsrv_errors(), true));
        } else {
            sqlsrv_free_stmt($stmt);
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getUserInfo() {
        if ($this->isLoggedIn()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'user_role' => $_SESSION['user_role'],
                'access_level' => $_SESSION['access_level'],
                'profile_pic' => $_SESSION['profile_pic'] ?? null
            ];
        }
        return null;
    }
}

// ============================================
// MAIN REQUEST HANDLER
// ============================================

// Initialize authentication
$auth = new Auth();

// Handle AJAX login request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }
    
    $loginResult = $auth->login($username, $password);
    
    if ($loginResult === true) {
        echo json_encode([
            'success' => true, 
            'redirect' => 'dashboard.php',
            'has_profile_pic' => isset($_SESSION['profile_pic']) && !empty($_SESSION['profile_pic'])
        ]);
        exit;
    } else if ($loginResult === 'pending') {
        echo json_encode([
            'success' => false, 
            'message' => 'Your account is pending administrator approval. You will be notified once approved.',
            'status' => 'pending'
        ]);
        exit;
    } else if ($loginResult === 'inactive') {
        echo json_encode([
            'success' => false, 
            'message' => 'Your account is inactive. Please contact the system administrator.',
            'status' => 'inactive'
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: index.html');
    exit;
}

// Handle check login status
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json');
    if ($auth->isLoggedIn()) {
        echo json_encode(['logged_in' => true, 'user' => $auth->getUserInfo()]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

// If already logged in, redirect to dashboard page
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
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
    <title>Login - Document Management & Tracking System</title>
    
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
            --gradient-bg: linear-gradient(135deg, var(--tiffany) 0%, #bff0ea 30%, var(--turq) 100%);
            --gradient-dark: linear-gradient(135deg, var(--navy) 0%, #0A4A6F 100%);
            --gradient-card: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            --shadow: 0 5px 15px rgba(11, 35, 50, 0.1);
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--gradient-bg);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .login-logo {
            height: 80px;
            margin-bottom: 20px;
        }
        
        .login-title {
            color: var(--navy);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--tiffany);
            box-shadow: 0 0 0 0.2rem rgba(167, 228, 213, 0.25);
        }
        
        .btn-login {
            background: var(--navy);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: var(--light-navy);
            transform: translateY(-2px);
        }
        
        .login-footer {
            margin-top: 30px;
            color: #666;
            font-size: 0.8rem;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        
        .password-toggle {
            cursor: pointer;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-left: none;
        }
        
        .spinner-border {
            width: 1rem;
            height: 1rem;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="images/logo.png" alt="PGR Logo" class="login-logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iODAiIGhlaWdodD0iODAiIHJ4PSIxMCIgZmlsbD0iIzA5MzI0QSIvPjx0ZXh0IHg9IjQwIiB5PSI0NSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+UEdSPC90ZXh0Pjwvc3ZnPg=='">
                <h1 class="login-title">PGR-DMTS</h1>
                <p class="login-subtitle">Document Management & Tracking System</p>
            </div>
            
            <div id="alertContainer"></div>
            
            <form id="loginForm">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username" 
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required>
                        <span class="input-group-text password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <div class="login-footer">
                <p class="mb-2">
                    <i class="fas fa-info-circle me-1"></i>
                    Provincial Government of Romblon
                </p>
                <p class="mb-0 text-muted">
                    <small>DMTS v1.0 © 2024</small>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
        $(document).ready(function() {
            // Handle form submission
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                
                const username = $('#username').val().trim();
                const password = $('#password').val().trim();
                
                if (!username || !password) {
                    showAlert('Please enter both username and password', 'warning');
                    return;
                }
                
                // Disable button and show loading
                $('#loginButton').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Logging in...');
                
                // Send login request
                $.ajax({
                    url: 'login.php',
                    method: 'POST',
                    data: {
                        username: username,
                        password: password
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showAlert('Login successful! Redirecting...', 'success');
                            setTimeout(() => {
                                window.location.href = response.redirect;
                            }, 1000);
                        } else {
                            if (response.status === 'pending') {
                                showAlert(response.message, 'warning');
                            } else if (response.status === 'inactive') {
                                showAlert(response.message, 'danger');
                            } else {
                                showAlert(response.message, 'danger');
                            }
                            $('#loginButton').prop('disabled', false).html('<i class="fas fa-sign-in-alt me-2"></i>Login');
                        }
                    },
                    error: function(xhr, status, error) {
                        showAlert('Login failed. Please try again.', 'danger');
                        $('#loginButton').prop('disabled', false).html('<i class="fas fa-sign-in-alt me-2"></i>Login');
                    }
                });
            });
            
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
            
            // Enter key to submit form
            $('#password').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#loginForm').submit();
                }
            });
        });
        
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = $('#password');
            const toggleIcon = $('.password-toggle i');
            
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                toggleIcon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                toggleIcon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        }
    </script>
</body>
</html>