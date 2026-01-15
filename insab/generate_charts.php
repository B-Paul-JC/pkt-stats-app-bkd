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
$downloadUrl = 'http://192.168.3.83/exinsab/insab/download.php'; // Adjust to your domain
$secretKey = 'YOUR_SUPER_SECRET_KEY';

try {
    // 1. Get Configuration from React
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Invalid JSON input");

    // 2. Fetch Data from Database
    $fetcher = new DataFetcher();
    $chartData = $fetcher->getChartData($input);

    // 3. Generate Dynamic Title (Robust Array Handling)
    $title = $input['title'] ?? '';

    // Capture new fields
    $uid = $input['uid'] ?? 'Unknown User';
    $description = $input['description'] ?? '';

    if (empty(trim($title))) {
        $criteria = [];

        // Helper: Format val as string, even if array
        $formatVal = function ($key, $val) use ($fetcher) {
            if (empty($val) || $val === 'Any') return null;

            if (is_array($val)) {
                $val = array_filter($val, fn($v) => $v !== 'Any');
                if (empty($val)) return null;

                $allOptions = $fetcher->getOptions($key);
                if (!empty($allOptions) && count($val) >= count($allOptions)) {
                    $label = ucfirst($key);
                    if ($key === 'hallofresidence') $label = 'Halls';
                    if ($key === 'stateoforigin') $label = 'States';
                    if ($key === 'programmetype') $label = 'Programmes';
                    if ($key === 'level') $label = 'Levels';
                    return "All " . $label;
                }
                return implode("/", $val);
            }
            return $val;
        };

        // Hierarchy logic
        $dept = $formatVal('department', $input['department'] ?? null);
        $fac = $formatVal('faculty', $input['faculty'] ?? null);

        if ($dept) {
            $criteria[] = $dept;
        } elseif ($fac) {
            $criteria[] = $fac;
        }

        if ($v = $formatVal('hallofresidence', $input['hallofresidence'] ?? null)) $criteria[] = $v;
        if ($v = $formatVal('level', $input['level'] ?? null)) $criteria[] = $v;
        if ($v = $formatVal('gender', $input['gender'] ?? null)) $criteria[] = $v;

        $stateVal = $formatVal('stateoforigin', $input['stateoforigin'] ?? null);
        if ($stateVal) {
            if (strpos($stateVal, 'All') === 0) {
                $criteria[] = $stateVal;
            } else {
                $criteria[] = $stateVal . " State";
            }
        }

        if ($v = $formatVal('programmetype', $input['programmetype'] ?? null)) $criteria[] = $v;

        if (!empty($criteria)) {
            $title = "Report: " . implode(", ", $criteria);
        } else {
            $title = "General Statistics Report";
        }
    }

    // 4. Prepare Payload for Python
    $keyCol = 'Labels';
    $timestamp = time();

    // Support Multiple Chart Types
    $requestedTypes = $input['chartType'] ?? 'bar';
    if (!is_array($requestedTypes)) $requestedTypes = [$requestedTypes];
    $requestedTypes = array_unique(array_filter($requestedTypes));
    if (empty($requestedTypes)) $requestedTypes = ['bar'];

    $pythonRequests = [];

    // Loop 1: Visual Requests
    foreach ($requestedTypes as $type) {
        $pythonRequests[] = [
            'data' => $chartData,
            'type' => $type,
            'key_col' => $keyCol,
            'axis' => 'x',
            'title' => $title . " (" . ucfirst($type) . " View)",
            'filename' => 'chart_' . $type . '_' . $timestamp,
            // Pass metadata to Python
            'uid' => $uid,
            'description' => $description
        ];
    }

    // Loop 2: Data Table (Always Included)
    $pythonRequests[] = [
        'data' => $chartData,
        'type' => 'table',
        'key_col' => $keyCol,
        'axis' => 'x',
        'title' => $title . " - Statistical Data",
        'filename' => 'table_' . $timestamp,
        // Pass metadata to Python
        'uid' => $uid,
        'description' => $description
    ];

    // 5. Generate Files
    $service = new ChartService($storagePath, $downloadUrl, $secretKey);
    $links = $service->generateCharts($pythonRequests);

    // 6. Return Links AND Raw Data
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