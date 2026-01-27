<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering explicitly to ensure we can clean it later
if (ob_get_level() == 0) ob_start();

$host = '192.168.3.83';
$db_name = 'student_ui_portal';
$username = 'root';
$password = 'password';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Disable strict group by mode for this session to handle complex CASE groupings easier
    $conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
} catch (PDOException $e) {
    ob_clean(); // Clean buffer before outputting error
    echo json_encode(["error" => "DB Connection Failed: " . $e->getMessage()]);
    exit();
}

// --- Helper to fetch names from IDs ---
function getDBName($conn, $table, $id)
{
    if (empty($id) || !is_numeric($id)) return $id; // Return as-is if empty or not an ID
    try {
        // Validate table name to prevent SQL injection since it's dynamic
        $allowedTables = ['departments', 'faculties', 'halls'];
        if (!in_array($table, $allowedTables)) return $id;

        $stmt = $conn->prepare("SELECT name FROM `$table` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['name'] : $id;
    } catch (Exception $e) {
        return $id;
    }
}

function getColors($count)
{
    // Monochromatic Blue Palette (Dark to Light and varied saturation)
    $colors = [
        '#172554',
        '#1e3a8a',
        '#1e40af',
        '#1d4ed8',
        '#2563eb',
        '#3b82f6',
        '#60a5fa',
        '#93c5fd',
        '#bfdbfe',
        '#dbeafe',
        '#082f49',
        '#0c4a6e',
        '#075985',
        '#0369a1',
        '#0284c7',
        '#0ea5e9',
        '#38bdf8',
        '#7dd3fc',
        '#bae6fd',
        '#e0f2fe',
        '#1e1b4b',
        '#312e81',
        '#3730a3',
        '#4338ca',
        '#4f46e5',
        '#6366f1',
        '#818cf8',
        '#a5b4fc',
        '#c7d2fe',
        '#e0e7ff',
        '#0f172a',
        '#1e293b',
        '#334155',
        '#475569',
        '#64748b',
        '#94a3b8',
        '#cbd5e1',
        '#e2e8f0',
        '#f1f5f9',
        '#f8fafc',
        '#002060',
        '#0047b3',
        '#1a75ff',
        '#80b3ff',
        '#0040ff'
    ];
    return array_slice($colors, 0, $count);
}

function getPrintMeta($userId = 'Guest')
{
    return [
        "school_name" => "University of Ibadan",
        "school_logo" => "https://via.placeholder.com/150x150.png?text=UI",
        "address" => "1 Knowledge Drive, Academic City, Lagos, Nigeria",
        "generated_by" => "User ID: " . $userId,
        "generated_at" => date("F j, Y, g:i a"),
        "disclaimer" => "Official Report - Internal Use Only"
    ];
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$userId = $input['uid'] ?? $_GET['uid'] ?? 'Guest';

// --- NEW: Universal Table Export (CSV, XLSX, PDF) ---
if ($action === 'download_table_export') {
    $format = $input['format'] ?? 'pdf';
    $reportData = $input['data'] ?? [];

    $payload = [
        "data" => $reportData['data'] ?? [],
        "title" => $reportData['title'] ?? 'Table Export',
        "print_meta" => $reportData['print_meta'] ?? getPrintMeta($userId)
    ];

    // --- CRITICAL FIX FOR XLSX ---
    // Clean buffer to remove stray characters/whitespace
    if (ob_get_length()) ob_clean();

    // 1. Set Headers for Download
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="export.csv"');
    } elseif ($format === 'xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="export.xlsx"');
        header('Cache-Control: max-age=0');
    } else {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="export.pdf"');
    }

    // 2. Call Python Script
    $command = "python universal_export.py --format " . escapeshellarg($format);

    $descriptors = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        fpassthru($pipes[1]);
        fclose($pipes[1]);

        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        if ($errors) {
            error_log("Python Export Error: " . $errors);
        }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to start export process."]);
    }
    exit();
}

// --- NEW: Mixed Batch PDF Download ---
if ($action === 'download_batch_pdf') {
    if (!isset($input['items']) || !is_array($input['items'])) {
        echo json_encode(["error" => "No items provided for batch download"]);
        exit();
    }

    $payload = [
        "meta" => getPrintMeta($userId),
        "items" => $input['items']
    ];

    $command = 'python generate_batch_report.py';
    $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
    $process = proc_open($command, $descriptors, $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $ret = proc_close($process);
        $timestamp = date("Y-m-d_H-i-s");
        $filename = "batch_report_{$userId}_{$timestamp}.pdf";

        if ($ret === 0) {
            $b64 = base64_encode($output);
            echo json_encode(["success" => true, "data" => "data:application/pdf;base64," . $b64, "filename" => $filename]);
        } else {
            echo json_encode(["error" => "Python Error: " . $error]);
        }
    } else {
        echo json_encode(["error" => "Failed to spawn python process"]);
    }
    exit();
}

// --- STUDENT LIST ---
if ($action === 'generate_student_list') {
    $zone = $input['zone'] ?? '';
    $state = $input['state'] ?? '';
    $gender = $input['gender'] ?? '';
    $level = $input['level'] ?? '';
    $department = $input['department'] ?? '';
    $faculty = $input['faculty'] ?? '';
    $hall = $input['hall'] ?? '';

    $whereConditions = [];
    $params = [];

    // --- Title Logic with Name Fetching ---
    $titleParts = [];
    if (!empty($level)) $titleParts[] = "$level Level";
    if (!empty($gender)) $titleParts[] = "$gender";
    $titleParts[] = "Students";

    // Fetch names instead of showing IDs
    if (!empty($department)) {
        $deptName = getDBName($conn, 'departments', $department);
        $titleParts[] = "in Dept $deptName";
    }
    if (!empty($faculty)) {
        $facName = getDBName($conn, 'faculties', $faculty);
        $titleParts[] = "in Faculty $facName";
    }
    if (!empty($hall)) {
        $hallName = getDBName($conn, 'halls', $hall);
        $titleParts[] = "in Hall $hallName";
    }

    if (!empty($state)) {
        $titleParts[] = "from $state";
    } elseif (!empty($zone)) {
        $titleParts[] = "from $zone";
    }

    $listTitle = implode(" ", $titleParts);
    if ($listTitle === "Students") $listTitle = "All Students Directory";

    if (!empty($zone)) {
        switch ($zone) {
            case 'South West':
                $states = "'Lagos', 'Ogun', 'Oyo', 'Osun', 'Ondo', 'Ekiti'";
                break;
            case 'South East':
                $states = "'Abia', 'Anambra', 'Ebonyi', 'Enugu', 'Imo'";
                break;
            case 'South South':
                $states = "'Akwa Ibom', 'Bayelsa', 'Cross River', 'Delta', 'Edo', 'Rivers'";
                break;
            case 'North Central':
                $states = "'Benue', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau', 'FCT', 'Abuja'";
                break;
            case 'North East':
                $states = "'Adamawa', 'Bauchi', 'Borno', 'Gombe', 'Taraba', 'Yobe'";
                break;
            case 'North West':
                $states = "'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Sokoto', 'Zamfara'";
                break;
            default:
                $states = "";
        }
        if ($states) $whereConditions[] = "p.state_of_origin IN ($states)";
    }

    if (!empty($state)) {
        $whereConditions[] = "p.state_of_origin = ?";
        $params[] = $state;
    }
    if (!empty($gender)) {
        $whereConditions[] = "p.gender = ?";
        $params[] = $gender;
    }
    if (!empty($level)) {
        $whereConditions[] = "p.level_id = ?";
        $params[] = $level;
    }
    if (!empty($department)) {
        $whereConditions[] = "p.department_id = ?";
        $params[] = $department;
    }
    if (!empty($faculty)) {
        $whereConditions[] = "d.faculty_id = ?";
        $params[] = $faculty;
    }
    if (!empty($hall)) {
        $whereConditions[] = "p.hall_id = ?";
        $params[] = $hall;
    }

    $whereSQL = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $userTableExists = false;
    try {
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        if ($result->rowCount() > 0) $userTableExists = true;
    } catch (Exception $e) {
    }

    // Construct Query with JOIN to Departments AND Halls
    // Use DISTINCT to prevent duplicates from joins
    $baseQuery = "SELECT DISTINCT p.user_id, p.gender, p.level_id, p.state_of_origin, d.name AS department, h.name AS hall, p.institutional_email FROM profiles p LEFT JOIN departments d ON p.department_id = d.id LEFT JOIN halls h ON p.hall_id = h.id";

    if ($userTableExists) {
        $query = "$baseQuery LEFT JOIN users u ON p.user_id = u.id $whereSQL ORDER BY p.level_id ASC, p.state_of_origin ASC";
    } else {
        $query = "$baseQuery $whereSQL ORDER BY p.level_id ASC, p.state_of_origin ASC";
    }

    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            "id" => "gen-" . time(),
            "title" => $listTitle,
            "type" => "table",
            "success" => true,
            "is_chart" => false,
            "count" => count($data),
            "data" => $data,
            "print_meta" => getPrintMeta($userId)
        ]);
    } catch (Exception $e) {
        echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
    }
    exit();
}

// --- CHART GENERATION ---
if ($action === 'generate_report' || $action === 'download_pdf') {
    $breakdown = $input['breakdown'] ?? '';
    $chartType = $input['type'] ?? 'bar';
    $title = $input['title'] ?? 'Generated Report';

    // Store filters in array for easier iteration
    // We will update values to names below
    $filters = [
        'Gender' => $input['filter_gender'] ?? '',
        'Level' => $input['filter_level'] ?? '',
        'Department' => $input['filter_department'] ?? '',
        'Faculty' => $input['filter_faculty'] ?? '',
        'Zone' => $input['filter_zone'] ?? '',
        'State' => $input['filter_state'] ?? '',
        'Hall' => $input['filter_hall'] ?? ''
    ];

    $filterGender = $filters['Gender'];
    $filterLevel = $filters['Level'];
    $filterDept = $filters['Department'];
    $filterZone = $filters['Zone'];
    $filterState = $filters['State'];
    $filterHall = $filters['Hall'];
    $filterFaculty = $filters['Faculty'];

    $isMultiSeries = false;
    $pivotColumn = '';
    $pivotAlias = 'series_key';
    if ($chartType !== 'pie') {
        if (!empty($filterLevel) && empty($filterGender)) {
            $isMultiSeries = true;
            $pivotColumn = 'gender';
        } elseif (!empty($filterGender) && empty($filterLevel)) {
            $isMultiSeries = true;
            $pivotColumn = 'level_id';
        } elseif (empty($filterLevel) && empty($filterGender)) {
            $isMultiSeries = true;
            $pivotColumn = 'level_id';
        }
    }

    // --- 1. Define Base Joins (Always available for filtering) ---
    // We LEFT JOIN departments and halls so their columns are available for WHERE and SELECT clauses
    // Aliasing: p=profiles, d=departments, h=halls
    $fromClause = "FROM profiles p 
                   LEFT JOIN departments d ON p.department_id = d.id 
                   LEFT JOIN halls h ON p.hall_id = h.id";

    $selectColumn = "";
    $groupBy = "";
    $orderBy = "";
    $limit = "";
    $whereConditions = [];
    $params = [];

    // --- 2. Determine Grouping (Breakdown) ---
    switch ($breakdown) {
        case 'gender':
            $selectColumn = "p.gender";
            $groupBy = "p.gender";
            break;
        case 'state_of_origin':
            $selectColumn = "p.state_of_origin";
            $groupBy = "p.state_of_origin";
            break;
        case 'geopolitical_zone':
            $selectColumn = "CASE WHEN p.state_of_origin IN ('Lagos', 'Ogun', 'Oyo', 'Osun', 'Ondo', 'Ekiti') THEN 'South West' WHEN p.state_of_origin IN ('Abia', 'Anambra', 'Ebonyi', 'Enugu', 'Imo') THEN 'South East' WHEN p.state_of_origin IN ('Akwa Ibom', 'Bayelsa', 'Cross River', 'Delta', 'Edo', 'Rivers') THEN 'South South' WHEN p.state_of_origin IN ('Benue', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau', 'FCT', 'Abuja') THEN 'North Central' WHEN p.state_of_origin IN ('Adamawa', 'Bauchi', 'Borno', 'Gombe', 'Taraba', 'Yobe') THEN 'North East' WHEN p.state_of_origin IN ('Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Sokoto', 'Zamfara') THEN 'North West' ELSE 'Other/International' END";
            $groupBy = "name";
            break;
        case 'level_category':
            $selectColumn = "CASE WHEN p.level_id IN (100, 200) THEN 'Lower Levels (100-200)' WHEN p.level_id IN (300, 400) THEN 'Upper Levels (300-400)' WHEN p.level_id >= 500 THEN 'Final Years (500+)' ELSE 'Other' END";
            $groupBy = "name";
            break;
        case 'religion':
            $selectColumn = "p.religion";
            $groupBy = "p.religion";
            break;
        case 'marital_status':
            $selectColumn = "p.marital_status";
            $groupBy = "p.marital_status";
            break;
        case 'mode_of_admission':
            $selectColumn = "p.mode_of_admission";
            $groupBy = "p.mode_of_admission";
            break;
        case 'lga':
            $selectColumn = "p.lga";
            $groupBy = "p.lga";
            break;
        case 'hall':
            // Select Name instead of ID, requires JOIN (handled by base JOIN)
            $selectColumn = "h.name";
            $groupBy = "h.name";
            break;
        case 'department':
            // Select Name instead of ID, requires JOIN (handled by base JOIN)
            $selectColumn = "d.name";
            $groupBy = "d.name";
            break;
        default:
            echo json_encode(["error" => "Invalid parameter"]);
            exit();
    }

    if (strpos($selectColumn, 'CASE') === false && strpos($selectColumn, '.') === false) $whereConditions[] = "$selectColumn IS NOT NULL";
    else {
        if ($breakdown === 'geopolitical_zone') $whereConditions[] = "p.state_of_origin IS NOT NULL";
        if ($breakdown === 'level_category') $whereConditions[] = "p.level_id IS NOT NULL";
    }

    // --- 3. Apply Filters (Using Aliases from Base Joins) ---
    if (!empty($filterGender)) {
        $whereConditions[] = "p.gender = ?";
        $params[] = $filterGender;
    }
    if (!empty($filterLevel)) {
        $whereConditions[] = "p.level_id = ?";
        $params[] = $filterLevel;
    }
    if (!empty($filterDept)) {
        $whereConditions[] = "p.department_id = ?";
        $params[] = $filterDept;
    }
    if (!empty($filterState)) {
        $whereConditions[] = "p.state_of_origin = ?";
        $params[] = $filterState;
    }
    if (!empty($filterHall)) {
        $whereConditions[] = "p.hall_id = ?";
        $params[] = $filterHall;
    }
    if (!empty($filterFaculty)) {
        $whereConditions[] = "d.faculty_id = ?";
        $params[] = $filterFaculty;
    }

    if (!empty($filterZone)) {
        switch ($filterZone) {
            case 'South West':
                $states = "'Lagos', 'Ogun', 'Oyo', 'Osun', 'Ondo', 'Ekiti'";
                break;
            case 'South East':
                $states = "'Abia', 'Anambra', 'Ebonyi', 'Enugu', 'Imo'";
                break;
            case 'South South':
                $states = "'Akwa Ibom', 'Bayelsa', 'Cross River', 'Delta', 'Edo', 'Rivers'";
                break;
            case 'North Central':
                $states = "'Benue', 'Kogi', 'Kwara', 'Nasarawa', 'Niger', 'Plateau', 'FCT', 'Abuja'";
                break;
            case 'North East':
                $states = "'Adamawa', 'Bauchi', 'Borno', 'Gombe', 'Taraba', 'Yobe'";
                break;
            case 'North West':
                $states = "'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Sokoto', 'Zamfara'";
                break;
            default:
                $states = "";
        }
        if ($states) $whereConditions[] = "p.state_of_origin IN ($states)";
    }

    $whereSQL = implode(" AND ", $whereConditions);

    // Disable Strict SQL Mode for complex groupings locally
    $conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

    // --- 4. Construct Final Query ---
    // Note: $pivotColumn likely needs 'p.' prefix if it's 'level_id' or 'gender'
    $pivotColSql = $pivotColumn;
    if ($pivotColumn === 'level_id') $pivotColSql = 'p.level_id';
    if ($pivotColumn === 'gender') $pivotColSql = 'p.gender';

    // Use COUNT(DISTINCT p.user_id) to avoid counting duplicate rows from joins
    if ($isMultiSeries) {
        $query = "SELECT $selectColumn as name, $pivotColSql as $pivotAlias, COUNT(DISTINCT p.user_id) as value 
                  $fromClause 
                  WHERE $whereSQL 
                  GROUP BY $groupBy, $pivotColSql $orderBy $limit";
    } else {
        $query = "SELECT $selectColumn as name, COUNT(DISTINCT p.user_id) as value 
                  $fromClause 
                  WHERE $whereSQL 
                  GROUP BY $groupBy $orderBy $limit";
    }

    try {
        $stmt = $conn->prepare($query); 
        
        // Write to a file for debugging purposes
        file_put_contents('debug_query.sql', $stmt->queryString . "\nWith Params: " . implode(", ", $params));

        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        $config = ["xKey" => "name"];
        $primaryColor = "#2563eb"; // Standard Blue

        if ($isMultiSeries) {
            $pivoted = [];
            $allSeriesKeys = [];
            foreach ($rawRows as $row) {
                $name = $row['name'];
                $sKey = $row[$pivotAlias] ?? 'Unknown';
                $val = (int)$row['value'];
                if (!isset($pivoted[$name])) $pivoted[$name] = ["name" => $name];
                $pivoted[$name][$sKey] = $val;
                $allSeriesKeys[$sKey] = true;
            }
            $data = array_values($pivoted);
            $distinctKeys = array_keys($allSeriesKeys);
            sort($distinctKeys);
            $seriesColors = getColors(count($distinctKeys));
            $seriesConfig = [];
            foreach ($distinctKeys as $index => $key) {
                $label = $pivotColumn === 'level_id' ? "Level $key" : $key;
                $seriesConfig[] = ["key" => (string)$key, "color" => $seriesColors[$index % count($seriesColors)], "name" => $label];
            }
            if ($chartType === 'line') $config['lines'] = $seriesConfig;
            elseif ($chartType === 'area') {
                foreach ($seriesConfig as &$s) {
                    $s['fill'] = true;
                    $s['fillOpacity'] = 0.6;
                }
                $config['areas'] = $seriesConfig;
            } elseif ($chartType === 'scatter') $config['scatters'] = $seriesConfig;
            else $config['bars'] = $seriesConfig;
        } else {
            $data = $rawRows;
            $colors = getColors(count($data));
            foreach ($data as $index => &$row) $row['fill'] = $colors[$index % count($colors)];
            if ($chartType === 'pie') $config['pies'] = [["key" => "value", "nameKey" => "name", "colors" => $colors]];
            elseif ($chartType === 'line') $config['lines'] = [["key" => "value", "color" => $primaryColor]];
            elseif ($chartType === 'area') $config['areas'] = [["key" => "value", "color" => $primaryColor, "fill" => true]];
            elseif ($chartType === 'scatter') $config['scatters'] = [["key" => "value", "color" => $primaryColor]];
            else $config['bars'] = [["key" => "value", "colorKey" => "fill"]];
        }

        // --- Human Readable Subtitles: Update IDs to Names ---
        $subtitleParts = [];
        // Update filter values to human-readable names where needed
        if ($filters['Department']) $filters['Department'] = getDBName($conn, 'departments', $filters['Department']);
        if ($filters['Faculty']) $filters['Faculty'] = getDBName($conn, 'faculties', $filters['Faculty']);
        if ($filters['Hall']) $filters['Hall'] = getDBName($conn, 'halls', $filters['Hall']);

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $subtitleParts[] = "$key: $value";
            }
        }
        if (empty($subtitleParts)) $subtitleParts[] = "All Records";

        $finalPayload = ["id" => "gen-" . time(), "is_chart" => true, "type" => $chartType, "title" => $title, "subtitle" => implode(" • ", $subtitleParts), "tag" => "LIVE", "width" => "full", "data" => $data, "config" => $config, "print_meta" => getPrintMeta($userId)];

        if ($action === 'download_pdf') {
            $command = 'python generate_chart.py';
            $descriptors = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
            $process = proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], json_encode($finalPayload));
                fclose($pipes[0]);
                echo stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
            }
        } else {
            echo json_encode($finalPayload);
        }
    } catch (Exception $e) {
        echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
    }
    exit();
}

// --- GET Default ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = ["user_role" => "admin", "summary_stats" => [], "widgets" => []];
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM profiles");
        $response['summary_stats']['total_students'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        $response['summary_stats']['revenue'] = 0;
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit();
}
