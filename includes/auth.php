<?php
require_once __DIR__ . '/config.php';

class Auth {
    private $db;
    private $database;

    public function __construct($connection) {
        $this->db = $connection;
        // Use the global database instance
        $this->database = $GLOBALS['database'];
    }

    public function login($username, $password) {
        // Switch to HRMIS database for authentication
        if (!$this->database->switchToHRMIS()) {
            error_log("Failed to switch to HRMIS database");
            return false;
        }

        try {
            // Execute stored procedure from HRMIS database
            $sql = "EXEC dbo.UsersProfileLoad_AssemblyLogin @Username = ?, @Password = ?";
            $params = array($username, $password);
            $stmt = sqlsrv_query($this->db, $sql, $params);

            if ($stmt === false) {
                error_log("Stored procedure error: " . print_r(sqlsrv_errors(), true));
                // Switch back to GawadReg database
                $this->database->switchToGawadReg();
                return false;
            }

            // Try to fetch the result
            $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if (!$user) {
                // Switch back to GawadReg database
                $this->database->switchToGawadReg();
                return false;
            }
            
            // Build full name from components
            $FirstName = $user['FirstName'] ?? '';
            $MiddleName = $user['MiddleName'] ?? '';
            $Surname = $user['Surname'] ?? '';
            $fullName = trim($FirstName . ' ' . $MiddleName . ' ' . $Surname);
            
            // Determine user role
            $userRole = 'User';
            if (!empty($user['AccesLevel'])) {
                $accessLevel = strtolower(trim($user['AccesLevel']));
                if ($accessLevel === 'admin' || $accessLevel === 'administrator') {
                    $userRole = 'Administrator';
                }
            }

            // Switch back to GawadReg database before setting session and logging activity
            $this->database->switchToGawadReg();

            // Set session variables
            $_SESSION['user_id'] = $user['UsersProfileId'] ?? ($user['Username'] ?? $username);
            $_SESSION['username'] = $user['Username'] ?? $username;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['user_role'] = $userRole;
            $_SESSION['access_level'] = $user['AccesLevel'] ?? '';

            // Log login activity in local database
            $this->logLoginActivity($user['Username'] ?? $username, $fullName, $userRole);

            sqlsrv_free_stmt($stmt);
            return true;
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->database->switchToGawadReg();
            return false;
        }
    }

    private function logLoginActivity($username, $fullName, $role) {
        try {
            // First check if login_activity table exists
            $checkTable = "IF OBJECT_ID('login_activity', 'U') IS NOT NULL SELECT 1 ELSE SELECT 0";
            $stmt = sqlsrv_query($this->db, $checkTable);
            
            if ($stmt) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
                $tableExists = $row[0] ?? 0;
                sqlsrv_free_stmt($stmt);
                
                if ($tableExists) {
                    $sql = "INSERT INTO login_activity (username, full_name, role, ip_address, login_time) 
                            VALUES (?, ?, ?, ?, GETDATE())";
                    $params = array($username, $fullName, $role, $_SERVER['REMOTE_ADDR'] ?? 'Unknown');
                    $stmt = sqlsrv_query($this->db, $sql, $params);
                    
                    if ($stmt === false) {
                        error_log("Failed to log login activity: " . print_r(sqlsrv_errors(), true));
                    } else {
                        sqlsrv_free_stmt($stmt);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error logging login activity: " . $e->getMessage());
        }
    }

    public function logout() {
        if (isset($_SESSION['username'])) {
            $this->logLogoutActivity($_SESSION['username']);
        }
        session_destroy();
    }

    private function logLogoutActivity($username) {
        try {
            // Check if login_activity table exists
            $checkTable = "IF OBJECT_ID('login_activity', 'U') IS NOT NULL SELECT 1 ELSE SELECT 0";
            $stmt = sqlsrv_query($this->db, $checkTable);
            
            if ($stmt) {
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
                $tableExists = $row[0] ?? 0;
                sqlsrv_free_stmt($stmt);
                
                if ($tableExists) {
                    $sql = "UPDATE login_activity SET logout_time = GETDATE() 
                            WHERE username = ? AND logout_time IS NULL 
                            ORDER BY login_time DESC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY";
                    $stmt = sqlsrv_query($this->db, $sql, array($username));
                    
                    if ($stmt === false) {
                        error_log("Failed to log logout activity: " . print_r(sqlsrv_errors(), true));
                    } else {
                        sqlsrv_free_stmt($stmt);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error logging logout activity: " . $e->getMessage());
        }
    }
}
?>