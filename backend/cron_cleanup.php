<?php
// backend/cron_cleanup.php

// 1. Configuration
// Using absolute path as requested for reliability in Laragon
$storageDir = 'C:\laragon\www\exinsab\backend\storage';
$retentionPeriod = 3600; // 1 Hour in seconds

echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup...\n";

// 2. Validation
if (!is_dir($storageDir)) {
    // Attempt to normalize path in case of slash issues
    $storageDir = str_replace('\\', '/', $storageDir);
    if (!is_dir($storageDir)) {
        fwrite(STDERR, "Error: Storage directory '$storageDir' not found.\n");
        exit(1);
    }
}

// 3. Cleanup Logic
$count = 0;
$files = new DirectoryIterator($storageDir);
$now = time();

foreach ($files as $fileInfo) {
    if ($fileInfo->isFile()) {
        $filePath = $fileInfo->getRealPath();
        $fileAge = $now - $fileInfo->getMTime();

        // Check if file is older than retention period
        if ($fileAge > $retentionPeriod) {
            // Check extension to ensure we only delete generated assets (safety check)
            $ext = strtolower($fileInfo->getExtension());
            if (in_array($ext, ['png', 'pdf', 'json'])) {
                if (unlink($filePath)) {
                    echo "Deleted: " . $fileInfo->getFilename() . " (Age: " . round($fileAge/60) . " mins)\n";
                    $count++;
                } else {
                    fwrite(STDERR, "Failed to delete: " . $fileInfo->getFilename() . "\n");
                }
            }
        }
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Cleanup finished. Removed $count files.\n";
?>