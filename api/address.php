<?php
// File: C:\inetpub\wwwroot\portal\api\address.php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple caching function
function getCachedData($cacheKey, $callback, $cacheDuration = 86400) {
    $cacheDir = '../cache/';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    $cacheFile = $cacheDir . $cacheKey . '.json';
    
    // Check cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
        $cachedData = file_get_contents($cacheFile);
        return $cachedData;
    }
    
    // Get fresh data
    $data = $callback();
    if ($data) {
        file_put_contents($cacheFile, $data);
    }
    return $data;
}

function fetchPCGCData($url) {
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Try multiple methods
    $methods = [
        function() use ($url, $context) {
            return @file_get_contents($url, false, $context);
        },
        function() use ($url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        }
    ];
    
    foreach ($methods as $method) {
        $result = $method();
        if ($result !== false) {
            return $result;
        }
    }
    
    return false;
}

// Handle API requests
$action = isset($_GET['action']) ? $_GET['action'] : 'provinces';

try {
    switch ($action) {
        case 'provinces':
            $data = getCachedData('provinces', function() {
                $url = 'https://psgc.gitlab.io/api/provinces/';
                $json = fetchPCGCData($url);
                if ($json) {
                    $data = json_decode($json, true);
                    if (is_array($data)) {
                        // Filter for relevant provinces (Romblon and nearby)
                        $relevantProvinces = [];
                        foreach ($data as $province) {
                            if (isset($province['name']) && isset($province['code'])) {
                                $provinceName = strtoupper($province['name']);
                                if (in_array($provinceName, ['ROMBLON', 'QUEZON', 'MARINDUQUE', 'PALAWAN', 'BATANGAS', 'LAGUNA'])) {
                                    $relevantProvinces[] = [
                                        'code' => $province['code'],
                                        'name' => $province['name'],
                                        'regionCode' => $province['regionCode'] ?? '',
                                        'islandGroupCode' => $province['islandGroupCode'] ?? ''
                                    ];
                                }
                            }
                        }
                        return json_encode($relevantProvinces);
                    }
                }
                // Return empty array if no data
                return json_encode([]);
            });
            
            if ($data) {
                $provinces = json_decode($data, true);
                echo json_encode([
                    'success' => true,
                    'data' => $provinces,
                    'count' => count($provinces),
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_PRETTY_PRINT);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch provinces',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_PRETTY_PRINT);
            }
            break;
            
        case 'municipalities':
            $provinceCode = isset($_GET['provinceCode']) ? $_GET['provinceCode'] : '';
            if (!$provinceCode) {
                throw new Exception('Province code is required');
            }
            
            $data = getCachedData("municipalities_{$provinceCode}", function() use ($provinceCode) {
                $url = "https://psgc.gitlab.io/api/provinces/{$provinceCode}/municipalities/";
                $json = fetchPCGCData($url);
                if ($json) {
                    $data = json_decode($json, true);
                    if (is_array($data)) {
                        $municipalities = [];
                        foreach ($data as $municipality) {
                            if (isset($municipality['name']) && isset($municipality['code'])) {
                                $municipalities[] = [
                                    'code' => $municipality['code'],
                                    'name' => $municipality['name']
                                ];
                            }
                        }
                        return json_encode($municipalities);
                    }
                }
                return json_encode([]);
            });
            
            if ($data) {
                $municipalities = json_decode($data, true);
                echo json_encode([
                    'success' => true,
                    'data' => $municipalities,
                    'count' => count($municipalities),
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_PRETTY_PRINT);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to fetch municipalities',
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_PRETTY_PRINT);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>