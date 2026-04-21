<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (ob_get_level() == 0) ob_start();

// Parse Input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';
$userId = $input['uid'] ?? $_GET['uid'] ?? 'Guest';

// THE ROUTER FLAG
$dataSource = $input['data_source'] ?? $_GET['data_source'] ?? 'db';

$conn = null;
if ($dataSource === 'db') {
    $host = '192.168.3.16';
    $db_name = 'staff_ui';
    $username = 'root';
    $password = '';

    try {
        $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(["error" => "DB Connection Failed: " . $e->getMessage()]);
        exit();
    }
}

function getPrintMeta($userId = 'Guest')
{
    return [
        "organization_name" => "University Staff Portal",
        "generated_by" => "User ID: " . $userId,
        "generated_at" => date("F j, Y, g:i a"),
        "disclaimer" => "Staff Records - Internal Use Only"
    ];
}

function getColors($count)
{
    $colors = ['#1e8a3a', '#1dd84e', '#3bf682', '#60faa5', '#93fdc5', '#0ee9a5', '#38f8bd', '#006020', '#00c864', '#2ee98e'];
    return array_slice($colors, 0, $count);
}

function parseCSV($filePath)
{
    $rows = [];
    if (($handle = @fopen($filePath, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        if ($headers) {
            $headers = array_map('trim', $headers);
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($headers) == count($data)) {
                    $rows[] = array_combine($headers, $data);
                }
            }
        }
        fclose($handle);
    }
    return $rows;
}

// --- STAFF LIST GENERATION ---
if ($action === 'generate_staff_list') {
    $dept = $input['department'] ?? '';
    $gender = $input['gender'] ?? '';

    if ($dataSource === 'csv') {
        $filePath = __DIR__ . '/uploads/staff_latest.csv';
        if (!file_exists($filePath)) {
            echo json_encode(["error" => "No staff CSV data found. Please upload a file."]);
            exit();
        }

        $allData = parseCSV($filePath);
        $filteredData = array_filter($allData, function ($row) use ($dept, $gender) {
            if (!empty($dept) && ($row['department'] ?? $row['staffDepartment'] ?? '') != $dept) return false;
            if (!empty($gender) && ($row['gender'] ?? $row['staffGender'] ?? '') != $gender) return false;
            return true;
        });

        echo json_encode([
            "id" => "gen-" . time(),
            "success" => true,
            "title" => "Staff Directory (CSV)",
            "count" => count($filteredData),
            "data" => array_values($filteredData),
            "print_meta" => getPrintMeta($userId)
        ]);
        exit();
    } else {
        $whereConditions = [];
        $params = [];
        if (!empty($dept)) {
            $whereConditions[] = "s.staffDepartment = ?";
            $params[] = $dept;
        }
        if (!empty($gender)) {
            $whereConditions[] = "s.staffGender = ?";
            $params[] = $gender;
        }

        $whereSQL = count($whereConditions) > 0 ? "WHERE " . implode(" AND ", $whereConditions) : "";

        $query = "SELECT s.staffNumber as staffID, s.staffFirstName, s.staffLastName, s.staffGender, 
                         d.department as department_name, f.faculty as faculty_name 
                  FROM staff s LEFT JOIN department d ON s.staffDepartment = d.department_id
                  LEFT JOIN faculty f ON d.faculty_id = f.faculty_id $whereSQL ORDER BY s.staffLastName ASC";

        try {
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                "id" => "gen-" . time(),
                "success" => true,
                "title" => "Staff Directory",
                "count" => count($data),
                "data" => $data,
                "print_meta" => getPrintMeta($userId)
            ]);
        } catch (Exception $e) {
            echo json_encode(["error" => "List generation failed: " . $e->getMessage()]);
        }
        exit();
    }
}

// --- STAFF ANALYTICS / CHART GENERATION ---
if ($action === 'generate_report') {
    $breakdown = $input['breakdown'] ?? 'department';
    $chartType = $input['type'] ?? 'bar';
    $title = $input['title'] ?? 'Staff Analytics';

    if ($dataSource === 'csv') {
        $filePath = __DIR__ . '/uploads/staff_latest.csv';
        if (!file_exists($filePath)) {
            echo json_encode(["error" => "No staff CSV data found. Please upload a file."]);
            exit();
        }

        $allData = parseCSV($filePath);
        $stats = [];
        foreach ($allData as $row) {
            $key = $row[$breakdown] ?? 'Unknown';
            if (!isset($stats[$key])) $stats[$key] = ['name' => $key, 'value' => 0];
            $stats[$key]['value']++;
        }

        $chartData = array_values($stats);
        $colors = getColors(count($chartData));
        foreach ($chartData as $index => &$row) $row['fill'] = $colors[$index % count($colors)];

        echo json_encode([
            "is_chart" => true,
            "type" => $chartType,
            "title" => $title . " (CSV)",
            "data" => $chartData,
            "config" => ["xKey" => "name", "bars" => [["key" => "value", "colorKey" => "fill"]]],
            "print_meta" => getPrintMeta($userId)
        ]);
        exit();
    } else {
        $selectColumn = "";
        $groupBy = "";
        $fromClause = "FROM staff s LEFT JOIN department d ON s.staffDepartment = d.department_id";

        switch ($breakdown) {
            case 'gender':
                $selectColumn = "s.staffGender as name";
                $groupBy = "s.staffGender";
                break;
            case 'marital_status':
                $selectColumn = "s.staffMarritalStatus as name";
                $groupBy = "s.staffMarritalStatus";
                break;
            case 'department':
                $selectColumn = "d.department as name";
                $groupBy = "d.department";
                break;
            default:
                echo json_encode(["error" => "Invalid breakdown"]);
                exit();
        }

        $query = "SELECT $selectColumn, COUNT(*) as value $fromClause GROUP BY $groupBy";

        try {
            $stmt = $conn->query($query);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $colors = getColors(count($data));
            foreach ($data as $index => &$row) $row['fill'] = $colors[$index % count($colors)];

            echo json_encode([
                "is_chart" => true,
                "type" => $chartType,
                "title" => $title,
                "data" => $data,
                "config" => ["xKey" => "name", "bars" => [["key" => "value", "colorKey" => "fill"]]],
                "print_meta" => getPrintMeta($userId)
            ]);
        } catch (Exception $e) {
            echo json_encode(["error" => "Report failed: " . $e->getMessage()]);
        }
        exit();
    }
}

// --- DEFAULT DASHBOARD VIEW ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($dataSource === 'csv') {
        $filePath = __DIR__ . '/uploads/staff_latest.csv';
        $totalStaff = 0;
        if (file_exists($filePath)) {
            $totalStaff = count(parseCSV($filePath));
        }
        echo json_encode([
            "user_role" => "admin",
            "summary_stats" => ["total_staff" => $totalStaff, "total_departments" => 0],
            "status" => "online (CSV mode)"
        ]);
    } else {
        try {
            $stats = [];
            $stats['total_staff'] = $conn->query("SELECT COUNT(*) FROM staff")->fetchColumn();
            $stats['total_departments'] = $conn->query("SELECT COUNT(*) FROM department")->fetchColumn();
            echo json_encode(["user_role" => "admin", "summary_stats" => $stats, "status" => "online"]);
        } catch (Exception $e) {
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    exit();
}
