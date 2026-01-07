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
        // WINDOWS/LARAGON FIX: 
        // 1. Use 'python' instead of 'python3' for Windows.
        // 2. If that fails, paste the result of 'where python' here, e.g., 'C:\\Python39\\python.exe'
        $this->pythonPath = 'python'; 
        
        $this->scriptPath = __DIR__ . '/chart_generator.py';
        
        // Use realpath to resolve any relative path weirdness (./../)
        // This ensures we have a clean absolute path like C:\laragon\www\proj\backend\storage
        $this->storageDir = realpath(rtrim($storageDir, '/\\'));
        
        $this->baseUrl = $baseUrl;
        $this->secretKey = $secretKey;

        // Create storage dir if realpath failed (dir doesn't exist)
        if (!$this->storageDir) {
            $this->storageDir = rtrim($storageDir, '/\\');
            if (!file_exists($this->storageDir)) {
                mkdir($this->storageDir, 0755, true);
                $this->storageDir = realpath($this->storageDir);
            }
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
        // FIX: Create temp file in the STORAGE directory, not the system temp dir.
        // This avoids paths like "C:\Users\UI User\..." which cause space issues.
        $tempJson = tempnam($this->storageDir, 'chart_req_');
        
        // Ensure we have the .json extension for clarity, though not strictly required
        $jsonPathWithExt = $tempJson . '.json';
        rename($tempJson, $jsonPathWithExt);
        $tempJson = $jsonPathWithExt;

        file_put_contents($tempJson, json_encode($payload));

        // 4. Execute Python Script
        // escapeshellarg() wraps paths in quotes: "C:\path to\file"
        $cmdPath = escapeshellarg($this->scriptPath);
        $jsonPath = escapeshellarg($tempJson);
        
        // 2>&1 redirects stderr to stdout so we can capture python errors
        $command = "{$this->pythonPath} {$cmdPath} {$jsonPath} 2>&1";

        // WINDOWS FIX: If on Windows, wrap the entire command in quotes to prevent 
        // cmd.exe from stripping the inner quotes of the arguments.
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = 'cmd /V:ON /C "' . $command . '"';
        }
        
        $output = shell_exec($command);
        
        // Remove temp file
        if (file_exists($tempJson)) {
            unlink($tempJson);
        }

        // 5. Parse Result
        $result = json_decode($output, true);

        // Debugging: If JSON decode fails, show the raw output from Python
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Check if output is empty
            if (empty($output)) {
                 throw new Exception("Python returned no output. Check if 'python' command works in terminal.");
            }
            throw new Exception("Python execution failed. Raw Output: " . $output);
        }

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
        
        // CONCEALMENT: Pack data into a single token
        // Format inside token: Base64(filename)|Expiry|Signature
        // 1. Base64 encode filename to ensure it doesn't contain the '|' delimiter
        $b64Filename = base64_encode($filename);
        
        // 2. Create Payload
        $payload = $b64Filename . '|' . $expires;
        
        // 3. Generate Signature on the payload
        $signature = hash_hmac('sha256', $payload, $this->secretKey);
        
        // 4. Wrap everything in a single Base64 string for the URL
        $token = base64_encode($payload . '|' . $signature);

        return $this->baseUrl . '?token=' . urlencode($token);
    }

    private function cleanup() {
        // Simple garbage collection: scans directory for files older than 1 hour
        // For high traffic sites, move this to a cron job.
        $files = glob($this->storageDir . '/*');
        $now = time();
        
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($now - filemtime($file) >= 3600) {
                        unlink($file);
                    }
                }
            }
        }
    }
}
?>