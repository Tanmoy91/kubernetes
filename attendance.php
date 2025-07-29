<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("INSERT INTO attendance (user_id) VALUES (?)");
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo "Attendance marked successfully!";
?>
<a href="logout.php">Logout</a>
