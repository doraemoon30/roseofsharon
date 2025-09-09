<?php
require 'db.php';
require 'auth_check.php';


$id = $_GET['id'];

$stmt = $conn->prepare("DELETE FROM sessions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: sessions.php");
exit();
?>
