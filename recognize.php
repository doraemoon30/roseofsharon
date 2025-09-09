<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio'])) {
    $file = $_FILES['audio']['tmp_name'];
    $filename = 'uploads/audio.wav';
    move_uploaded_file($file, $filename);

    $command = escapeshellcmd("python recognize.py $filename");
    $output = shell_exec($command);
    $result = json_decode($output, true);

    if ($result && isset($result['text'])) {
        echo json_encode(['success' => true, 'text' => $result['text']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not recognize audio']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No audio uploaded']);
}
?>
