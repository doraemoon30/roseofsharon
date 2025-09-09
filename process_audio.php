<?php
if ($_FILES['audio']['error'] === 0) {
    $targetPath = 'uploads/' . uniqid() . '.wav';
    move_uploaded_file($_FILES['audio']['tmp_name'], $targetPath);

    $lang = $_POST['lang'] ?? 'en'; // 'en' or 'tl'
    $command = escapeshellcmd("python3 recognize.py " . escapeshellarg($targetPath) . " " . escapeshellarg($lang));
    $output = shell_exec($command);

    echo trim($output); // Send recognized text back to JS
}
?>
