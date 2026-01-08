<?php
require_once __DIR__ . '/ChartService.php';

// Configuration
// In a real app, load these from environment variables
$storagePath = __DIR__ . '/../backend/storage/';
$downloadUrl = '/download.php'; // Adjust to your actual domain
$secretKey = 'YOUR_SUPER_SECRET_KEY';

$service = new ChartService($storagePath, $downloadUrl, $secretKey);

$generatedLinks = null;
$error = null;

// --- Simulating a User Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sample Data
    $salesData = [
        'Month' => ['Jan', 'Feb', 'Mar', 'Apr'],
        'Revenue' => [12000, 15000, 11000, 18000],
        'Costs' => [8000, 9000, 8500, 10000]
    ];

    $requests = [
        [
            'data' => $salesData,
            'type' => 'bar',
            'key_col' => 'Month',
            'axis' => 'x',
            'title' => 'Monthly Revenue vs Costs',
            'filename' => 'revenue_bar'
        ],
        [
            'data' => $salesData,
            'type' => 'bar',
            'key_col' => 'Month',
            'axis' => 'y',
            'title' => 'Revenue (Horizontal View)',
            'filename' => 'revenue_horiz'
        ]
    ];

    try {
        $generatedLinks = $service->generateCharts($requests);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Chart Generator</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .card { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .error { color: red; background: #ffe6e6; padding: 10px; border-radius: 5px; }
        img.preview { max-width: 100%; height: auto; border: 1px solid #ccc; margin-top: 10px; }
    </style>
</head>
<body>

    <h1>Chart Generator</h1>
    <p>Click the button below to generate charts via Python, store them securely, and get temporary access links.</p>

    <form method="POST">
        <button type="submit" class="btn" style="cursor: pointer;">Generate Charts</button>
    </form>

    <hr>

    <?php if ($error): ?>
        <div class="error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($generatedLinks): ?>
        <div class="results">
            <h2>Generation Successful</h2>
            <p><em>These links are valid for 1 hour.</em></p>

            <div class="card">
                <h3>Full PDF Report</h3>
                <a href="<?= $generatedLinks['pdf_report'] ?>" class="btn">Download PDF</a>
            </div>

            <h3>Individual Images</h3>
            <?php foreach ($generatedLinks['images'] as $name => $link): ?>
                <div class="card">
                    <h4><?= htmlspecialchars($name) ?></h4>
                    <!-- We can use the secure link directly in the img src! -->
                    <img src="<?= $link ?>" class="preview" alt="Chart Preview">
                    <br><br>
                    <a href="<?= $link ?>" download>Download Image</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</body>
</html>