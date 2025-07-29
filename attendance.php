<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");
$user_id = $_SESSION['user_id'];

$conn->query("INSERT INTO attendance (user_id) VALUES ($user_id)");
echo "Attendance marked successfully!";

<a href="logout.php">Logout</a>
?>