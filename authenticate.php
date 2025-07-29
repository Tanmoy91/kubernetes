<?php
session_start();
$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    if ($user['password'] === $password) {  // For real app: use password_verify()
        $_SESSION['user_id'] = $user['id'];
        header("Location: attendance.php");
    } else {
        echo "Wrong password!";
    }
} else {
    echo "Email not found!";
}
?>
