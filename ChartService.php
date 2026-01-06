<?php

class ChartService {
    private $pythonPath;
    private $scriptPath;
    private $storageDir;
    private $secretKey;
    private $baseUrl;

    /**
     * @param string $storageDir Absolute path to where files should be stored (private)
     * @param string $baseUrl URL to the download.php script (e.g., 'https://site.com/download.php')
     * @param string $secretKey Secret key for signing URLs
     */
    public function __construct($storageDir, $baseUrl, $secretKey) {
        $this->pythonPath = 'python3'; // Or 'python' or '/usr/bin/python3'
        $this->scriptPath = __DIR__ . '/chart_generator.py';
        $this->storageDir = rtrim($storageDir, '/');
        $this->baseUrl = $baseUrl;
        $this->secretKey = $secretKey;

        if (!file_exists($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function generateCharts(array $requests) {
        // 1. Garbage Collection (Clean old files > 1 hour)
        $this->cleanup();

        // 2. Prepare Payload
        $jobId = uniqid('job_');
        $payload = [
            'job_id' => $jobId,
            'output_dir' => $this->storageDir,
            'requests' => $requests
        ];

        // 3. Write to temp JSON file
        $tempJson = tempnam(sys_get_temp_dir(), 'chart_req_');
        file_put_contents($tempJson, json_encode($payload));

        // 4. Execute Python Script
        // 2>&1 redirects stderr to stdout so we can capture python errors
        $command = escapeshellcmd("{$this->pythonPath} {$this->scriptPath} {$tempJson}") . " 2>&1";
        $output = shell_exec($command);
        
        // Remove temp file
        unlink($tempJson);

        // 5. Parse Result
        $result = json_decode($output, true);

        if (!$result || isset($result['error'])) {
            throw new Exception("Python Error: " . ($result['error'] ?? $output));
        }

        // 6. Generate Signed Links
        $links = [
            'pdf_report' => $this->signUrl($result['pdf']),
            'images' => []
        ];

        foreach ($result['images'] as $img) {
            $links['images'][$img['id']] = $this->signUrl($img['path']);
        }

        return $links;
    }

    private function signUrl($filename) {
        if (!$filename) return null;
        
        $expires = time() + 3600; // 1 Hour
        $dataToSign = $filename . $expires;
        $signature = hash_hmac('sha256', $dataToSign, $this->secretKey);

        $query = http_build_query([
            'file' => $filename,
            'expires' => $expires,
            'sig' => $signature
        ]);

        return $this->baseUrl . '?' . $query;
    }

    private function cleanup() {
        // Simple garbage collection: scans directory for files older than 1 hour
        // For high traffic sites, move this to a cron job.
        $files = glob($this->storageDir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 3600) {
                    unlink($file);
                }
            }
        }
    }
}
?>