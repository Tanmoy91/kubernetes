<?php
session_start();

// Database connection
$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");

// Check DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect and sanitize inputs
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // For real use: if (password_verify($password, $user['password']))
    if ($user['password'] === $password) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: attendance.php");
        exit();
    } else {
        echo "Wrong password!";
    }
} else {
    echo "Email not found!";
}

$stmt->close();
$conn->close();
?>
