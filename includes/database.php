<?php
// File: C:\inetpub\wwwroot\portal\includes\database.php
class Database {
    private static $connections = [];
    
    public static function getConnection($database = 'PGR_DMTS') {
        if (!isset(self::$connections[$database])) {
            $serverName = DB_SERVER;
            $username = DB_USERNAME;
            $password = DB_PASSWORD;
            
            $dbName = '';
            switch ($database) {
                case 'MASTERLIST':
                    $dbName = DB_MASTERLIST;
                    break;
                case 'OPAD_HRMIS':
                    $dbName = DB_OPAD_HRMIS;
                    break;
                case 'PGR_DMTS':
                default:
                    $dbName = DB_PGR_DMTS;
                    break;
            }
            
            $connectionInfo = array(
                "Database" => $dbName,
                "UID" => $username,
                "PWD" => $password,
                "CharacterSet" => "UTF-8",
                "ReturnDatesAsStrings" => true,
                "TrustServerCertificate" => true
            );
            
            $conn = sqlsrv_connect($serverName, $connectionInfo);
            
            if (!$conn) {
                die("Connection failed for database {$dbName}: " . print_r(sqlsrv_errors(), true));
            }
            
            self::$connections[$database] = $conn;
        }
        
        return self::$connections[$database];
    }
    
    public static function closeAll() {
        foreach (self::$connections as $conn) {
            sqlsrv_close($conn);
        }
        self::$connections = [];
    }
}
?>