<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check DB connection
if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

$folder = "assets/storybooks/";
$videoFiles = glob($folder . "*.mp4");

$inserted = 0;
$skipped = 0;

foreach ($videoFiles as $path) {
    $filename = basename($path);
    $rawTitle = pathinfo($filename, PATHINFO_FILENAME);
    $title = ucwords(str_replace(['_', '-'], ' ', $rawTitle));
    $description = "Auto-inserted storybook.";

    // Check for duplicates
    $check = $conn->prepare("SELECT id FROM storybooks WHERE filename = ?");
    if (!$check) {
        die("❌ Failed to prepare SELECT statement: " . $conn->error);
    }

    $check->bind_param("s", $filename);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        // Insert using created_at field
        $stmt = $conn->prepare("INSERT INTO storybooks (title, description, filename, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            die("❌ Failed to prepare INSERT statement: " . $conn->error);
        }

        $stmt->bind_param("sss", $title, $description, $filename);
        $stmt->execute();
        $inserted++;
    } else {
        $skipped++;
    }
}

echo "<h3>📚 Storybooks Scan Result</h3>";
echo "✅ Inserted <strong>$inserted</strong> new video(s) into the storybooks table.<br>";
echo "⚠️ Skipped <strong>$skipped</strong> already existing video(s).<br><br>";
echo "<a href='storybooks.php'>➡️ Go to Storybooks Page</a>";
?>
