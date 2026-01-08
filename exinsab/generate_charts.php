<?php
// --- CORS HANDLING START ---
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
// --- CORS HANDLING END ---

header("Content-Type: application/json");

require_once __DIR__ . '/../backend/ChartService.php';

// CONFIGURATION
$storagePath = __DIR__ . '/../backend/storage/';
// Ensure this matches your local setup
$downloadUrl = 'http://insab.test/download.php'; 
$secretKey = 'YOUR_SUPER_SECRET_KEY';

try {
    // 1. Get JSON Input (Frontend only sends Config now)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    // Extract Config from Frontend
    $chartType = $input['chartType'] ?? 'bar';
    $title = $input['title'] ?? 'Generated Report';

    // 2. "FETCH" DATA FROM DATABASE (Hardcoded Simulation)
    // In a real app, you would run a SQL query here.
    // Structure: Key => Array of values
    $dbData = [
        'Labels'   => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        'Revenue'  => [12500, 15000, 11200, 18500, 21000, 19500],
        'Expenses' => [8000, 9500, 8200, 10500, 11000, 9800],
        'Profit'   => [4500, 5500, 3000, 8000, 10000, 9700]
    ];
    $keyCol = 'Labels'; // The column to use as the X-axis

    // 3. Construct Payload for Python
    // We create two requests: one for the chart, one for the table.
    $timestamp = time();
    
    $pythonRequests = [
        // Request 1: Visualization
        [
            'data' => $dbData,
            'type' => $chartType,
            'key_col' => $keyCol,
            'axis' => 'x',
            'title' => $title . " (Visual)",
            'filename' => 'chart_' . $timestamp
        ],
        // Request 2: Statistical Table
        [
            'data' => $dbData,
            'type' => 'table',
            'key_col' => $keyCol,
            'axis' => 'x',
            'title' => $title . " (Data Table)",
            'filename' => 'table_' . $timestamp
        ]
    ];

    // 4. Initialize Service & Generate
    $service = new ChartService($storagePath, $downloadUrl, $secretKey);
    $links = $service->generateCharts($pythonRequests);

    // 5. Return JSON
    echo json_encode([
        'success' => true,
        'data' => $links
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>