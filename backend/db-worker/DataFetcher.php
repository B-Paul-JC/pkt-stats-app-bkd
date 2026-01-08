<?php
require_once 'Database.php';

class DataFetcher {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getChartData($config) {
        // 1. Determine Grouping (X-Axis)
        // We look at 'selectedDataTypes' to decide what to group by.
        // Default to Department if nothing relevant is found.
        $groupBy = 'department_id'; 
        $labelMapType = 'department'; // For converting ID back to Name

        if (!empty($config['selectedDataTypes'])) {
            if (in_array("GRADE", $config['selectedDataTypes']) || in_array("LEVEL", $config['selectedDataTypes'])) {
                $groupBy = 'level_id';
                $labelMapType = 'level';
            } elseif (in_array("GENDER", $config['selectedDataTypes'])) {
                $groupBy = 'gender';
                $labelMapType = 'none';
            } elseif (in_array("STATE", $config['selectedDataTypes'])) {
                $groupBy = 'state';
                $labelMapType = 'none';
            }
        }

        // 2. Build Query & Filters
        $sql = "SELECT $groupBy as label, COUNT(*) as value FROM profiles WHERE 1=1";
        $params = [];

        // --- Filter Logic ---
        
        // Filter: State
        // if (!empty($config['stateoforigin']) && $config['stateoforigin'] !== 'All') {
        //     $sql .= " AND state = ?";
        //     $params[] = $config['stateoforigin'];
        // }

        // Filter: Gender
        if (!empty($config['gender']) && $config['gender'] !== 'All') {
            $sql .= " AND gender = ?";
            $params[] = $config['gender'];
        }

        // Filter: Level (Extract Integer from "100 Level")
        // if (!empty($config['level']) && $config['level'] !== 'All') {
        //     $levelInt = (int) filter_var($config['level'], FILTER_SANITIZE_NUMBER_INT);
        //     if ($levelInt > 0) {
        //         $sql .= " AND level_id = ?";
        //         $params[] = $levelInt;
        //     }
        // }

        // Filter: Programme Type
        // if (!empty($config['programmetype']) && $config['programmetype'] !== 'All') {
        //     $sql .= " AND programme_type = ?";
        //     $params[] = $config['programmetype'];
        // }

        // Filter: Hall (Map String -> ID)
        if (!empty($config['hallofresidence']) && $config['hallofresidence'] !== 'All') {
            $hallId = $this->resolveId('hall', $config['hallofresidence']);
            if ($hallId) {
                $sql .= " AND hall_id = ?";
                $params[] = $hallId;
            }
        }

        // Filter: Department (Map String -> ID)
        if (!empty($config['department']) && $config['department'] !== 'All') {
            $deptId = $this->resolveId('department', $config['department']);
            if ($deptId) {
                $sql .= " AND department_id = ?";
                $params[] = $deptId;
            }
        }

        // 3. Execute Grouping
        $sql .= " GROUP BY $groupBy ORDER BY value DESC";
        error_log("SQL: " . $sql . " | Params: " . json_encode($params) . "\n", 3, __DIR__ . '/queries.log');

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Format Output for Python Backend
        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            // Resolve Label (e.g., ID 1 -> "Computer Science")
            $label = $row['label'];
            if ($labelMapType !== 'none') {
                $label = $this->resolveName($labelMapType, $row['label']);
            }
            
            $labels[] = $label;
            $values[] = (int)$row['value'];
        }

        return [
            'Labels' => $labels,
            'Count' => $values // This key becomes the dataset label
        ];
    }

    // --- Helper: Map Frontend Strings to DB IDs ---
    // In a real app, query your 'departments' or 'halls' tables here.
    private function resolveId($type, $name) {
        $mappings = [
            'hall' => [
                'Queen Elizabeth Hall' => 1,
                'Mellanby Hall' => 2,
                'Tedder Hall' => 3,
                'Kuti Hall' => 4,
                'Sultan Bello Hall' => 5,
                'Nnamdi Azikiwe Hall' => 6,
                'Queen Idia Hall' => 7,
                'Independence Hall' => 8,
                'Obafemi Awolowo Hall' => 9,
            ],
            'department' => [
                'Computer Science' => 1,
                'Botany' => 2,
                'Medicine' => 3
            ]
        ];
        return $mappings[$type][$name] ?? null; 
    }

    // --- Helper: Map DB IDs back to Names for Chart Labels ---
    private function resolveName($type, $id) {
        if ($type === 'level') return $id . " Level";
        
        // Inverse the mapping array above for lookup
        $mappings = [
            'department' => [
                1 => 'Computer Science',
                2 => 'History', 
                3 => 'Medicine',
                5 => 'Mathematics',
                12 => 'Physics'
            ]
        ];
        return $mappings[$type][$id] ?? "ID: $id";
    }
}
?>