<?php
session_start();

// Database connection
$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");

// Check DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect and sanitize inputs
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

// Return to login if email or password is missing
if (empty($email) || empty($password)) {
    header("Location: login.php?error=Please enter both email and password.");
    exit();
}

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // In production, use: password_verify($password, $user['password'])
    if ($user['password'] === $password) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        header("Location: attendance.php");
        exit();
    } else {
        header("Location: login.php?error=Incorrect password.");
        exit();
    }
} else {
    header("Location: login.php?error=Email not found.");
    exit();
}

$stmt->close();
$conn->close();
?>
