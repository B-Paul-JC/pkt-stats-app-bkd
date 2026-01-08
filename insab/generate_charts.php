<?php
// CORS Headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
} else {
    header("Access-Control-Allow-Origin: *");
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

header("Content-Type: application/json");

require_once __DIR__ . '/../backend/ChartService.php';
require_once __DIR__ . '/../backend/db-worker/DataFetcher.php';

// CONFIGURATION
$storagePath = __DIR__ . '/../backend/storage/';
$downloadUrl = 'http://exinsab.test/insab/download.php'; // Adjust to your domain
$secretKey = 'YOUR_SUPER_SECRET_KEY';

try {
    // 1. Get Configuration from React
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Invalid JSON input");

    // 2. Fetch Data from Database
    $fetcher = new DataFetcher();
    $chartData = $fetcher->getChartData($input);

    // 3. Generate Dynamic Title (Backend Logic)
    $title = $input['title'] ?? '';
    
    // If title is empty, generate one based on active filters
    if (empty(trim($title))) {
        $criteria = [];
        // Helper to check validity
        $isValid = function($val) { 
            return !empty($val) && $val !== "All" && $val !== ""; 
        };

        // Hierarchy logic: Specific department takes precedence over faculty
        if ($isValid($input['department'] ?? null)) {
            $criteria[] = $input['department'];
        } elseif ($isValid($input['faculty'] ?? null)) {
            $criteria[] = $input['faculty'];
        }

        if ($isValid($input['hallofresidence'] ?? null)) $criteria[] = $input['hallofresidence'];
        if ($isValid($input['level'] ?? null)) $criteria[] = $input['level'];
        if ($isValid($input['gender'] ?? null)) $criteria[] = $input['gender'];
        if ($isValid($input['stateoforigin'] ?? null)) $criteria[] = $input['stateoforigin'] . " State";
        if ($isValid($input['programmetype'] ?? null)) $criteria[] = $input['programmetype'];

        if (!empty($criteria)) {
            $title = "Report: " . implode(", ", $criteria);
        } else {
            $title = "General Statistics Report";
        }
    }

    // 4. Prepare Payload for Python
    $keyCol = 'Labels';
    $timestamp = time();
    $visualType = $input['chartType'] ?: 'bar';

    $pythonRequests = [
        // Request 1: Visual Chart
        [
            'data' => $chartData,
            'type' => $visualType, 
            'key_col' => $keyCol,
            'axis' => 'x',
            'title' => $title,
            'filename' => 'chart_' . $timestamp
        ],
        // Request 2: Data Table (For PDF Report)
        [
            'data' => $chartData,
            'type' => 'table',
            'key_col' => $keyCol,
            'axis' => 'x',
            'title' => $title . " - Statistical Data",
            'filename' => 'table_' . $timestamp
        ]
    ];

    // 5. Generate Files
    $service = new ChartService($storagePath, $downloadUrl, $secretKey);
    $links = $service->generateCharts($pythonRequests);

    // 6. Return Links AND Raw Data to Frontend
    echo json_encode([
        'success' => true,
        'data' => array_merge($links, ['source_data' => $chartData])
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>