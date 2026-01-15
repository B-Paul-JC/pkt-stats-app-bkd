<?php
require_once 'Database.php';

class DataFetcher
{
    private $conn;
    private $mappings; // Store mappings as a property

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();

        // Initialize Source of Truth for Options
        $this->mappings = [
            'hall' => [
                'All' => '*',
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
                'All' => '*',
                'Computer Science' => 1,
                'Botany' => 2,
                'Medicine' => 3,
                'History' => 4,
                'Mathematics' => 5,
                'Physics' => 12,
                'Agric' => 20
                // Add all departments here if possible
            ],
            'level' => [
                '100 Level',
                '200 Level',
                '300 Level',
                '400 Level',
                '500 Level'
            ],
            'gender' => ['Male', 'Female'],
            'programmetype' => ['Full Time', 'Part Time'],
            'stateoforigin' => [
                "Abia",
                "Adamawa",
                "Akwa Ibom",
                "Anambra",
                "Bauchi",
                "Bayelsa",
                "Benue",
                "Borno",
                "Cross River",
                "Delta",
                "Ebonyi",
                "Edo",
                "Ekiti",
                "Enugu",
                "Gombe",
                "Imo",
                "Jigawa",
                "Kaduna",
                "Kano",
                "Katsina",
                "Kebbi",
                "Kogi",
                "Kwara",
                "Lagos",
                "Nasarawa",
                "Niger",
                "Ogun",
                "Ondo",
                "Osun",
                "Oyo",
                "Plateau",
                "Rivers",
                "Sokoto",
                "Taraba",
                "Yobe",
                "Zamfara",
                "FCT"
            ]
        ];
    }

    // New: Helper to get all options for "All Selected" check
    public function getOptions($key)
    {
        // Map frontend config keys to mapping keys
        $map = [
            'hallofresidence' => 'hall',
            'department' => 'department',
            'level' => 'level',
            'gender' => 'gender',
            'stateoforigin' => 'stateoforigin',
            'programmetype' => 'programmetype'
        ];

        $lookup = $map[$key] ?? $key;

        if (isset($this->mappings[$lookup])) {
            // If it's an associative array (Hall/Dept), return keys (Names)
            // If indexed (Gender/State), return values
            $sample = $this->mappings[$lookup];
            if (array_keys($sample) !== range(0, count($sample) - 1)) {
                return array_keys($sample);
            }
            return $sample;
        }
        return [];
    }

    public function getChartData($config)
    {
        // 1. Determine Grouping (X-Axis)
        $groupBy = 'department_id';
        $labelMapType = 'department';

        // Handle potential array in selectedDataTypes
        $types = $config['selectedDataTypes'] ?? [];
        if (!is_array($types)) $types = [$types];

        if (in_array("GRADE", $types) || in_array("LEVEL", $types)) {
            $groupBy = 'level_id';
            $labelMapType = 'level';
        } elseif (in_array("GENDER", $types)) {
            $groupBy = 'gender';
            $labelMapType = 'none';
        } elseif (in_array("STATE", $types)) {
            $groupBy = 'state';
            $labelMapType = 'none';
        }

        // 2. Build Query
        $sql = "SELECT $groupBy as label, COUNT(*) as value FROM profiles WHERE 1=1";
        $params = [];

        // --- ROBUST FILTERING (Handles Arrays & Strings) ---

        $this->applyFilter($sql, $params, 'state', $config['stateoforigin'] ?? null);
        $this->applyFilter($sql, $params, 'gender', $config['gender'] ?? null);
        $this->applyFilter($sql, $params, 'programme_type', $config['programmetype'] ?? null);

        // Mapped Filters (Convert Name -> ID)
        $this->applyFilter($sql, $params, 'level_id', $config['level'] ?? null, 'level');
        $this->applyFilter($sql, $params, 'hall_id', $config['hallofresidence'] ?? null, 'hall');
        $this->applyFilter($sql, $params, 'department_id', $config['department'] ?? null, 'department');

        // 3. Grouping & Ordering
        $sql .= " GROUP BY $groupBy";

        if ($groupBy === 'level_id') {
            $sql .= " ORDER BY level_id ASC";
        } else {
            $sql .= " ORDER BY value DESC";
        }

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("SQL Error: " . $e->getMessage());
            $rows = [];
        }

        // Fallback for Empty Data
        if (empty($rows)) {
            return [
                'Labels' => ['No Data Found'],
                'Count' => [0]
            ];
        }

        // 4. Format Output
        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $label = $row['label'];
            if ($labelMapType !== 'none') {
                $label = $this->resolveName($labelMapType, $row['label']);
            }
            $labels[] = $label ? (string)$label : 'Unknown';
            $values[] = (int)$row['value'];
        }

        return [
            'Labels' => $labels,
            'Count' => $values
        ];
    }

    private function applyFilter(&$sql, &$params, $column, $value, $mapType = null)
    {
        if (empty($value)) return;
        if ($value === 'Any') return;
        if (is_array($value) && (empty($value) || in_array('Any', $value))) return;

        $items = is_array($value) ? $value : [$value];
        $cleanItems = [];

        foreach ($items as $item) {
            $val = $item;

            if ($mapType === 'level') {
                $val = (int) filter_var($item, FILTER_SANITIZE_NUMBER_INT);
                if ($val <= 0) continue;
            } elseif ($mapType) {
                // Use the new property for resolution
                $val = $this->mappings[$mapType][$item] ?? null;
                if (!$val) continue;
            }

            $cleanItems[] = $val;
        }

        if (empty($cleanItems)) return;

        $placeholders = implode(',', array_fill(0, count($cleanItems), '?'));
        $sql .= " AND $column IN ($placeholders)";

        foreach ($cleanItems as $p) {
            $params[] = $p;
        }
    }

    // Kept private helper for internal use (if needed for reverse lookup)
    private function resolveName($type, $id)
    {
        if ($type === 'level') return $id . " Level";

        if ($type === 'department') {
            // Flip the array to look up Name by ID
            $flip = array_flip($this->mappings['department']);
            return $flip[$id] ?? "ID: $id";
        }
        return "ID: $id";
    }
}
