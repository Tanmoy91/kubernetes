<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");
$user_id = $_SESSION['user_id'];

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $is_leave = isset($_POST['on_leave']) ? 1 : 0;

    // Check if already marked today
    $check = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURDATE()");
    $check->bind_param("i", $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        // Insert attendance
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, on_leave, date) VALUES (?, ?, CURDATE())");
        $stmt->bind_param("ii", $user_id, $is_leave);
        $stmt->execute();

        if ($is_leave) {
            $message = "✅ Leave recorded successfully!";
        } else {
            $message = "✅ Attendance marked successfully!";
        }
    } else {
        $message = "⚠️ You have already marked your attendance today.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #74ebd5, #9face6);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .card {
            width: 100%;
            max-width: 450px;
            padding: 30px;
            border: none;
            border-radius: 15px;
            background-color: #fff;
            box-shadow: 0px 12px 24px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .btn-primary {
            background-color: #5a67d8;
            border: none;
        }

        .btn-primary:hover {
            background-color: #434190;
        }

        .form-check-label {
            color: #555;
        }

        .logout-link {
            margin-top: 20px;
        }

        .alert {
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="card text-center">
        <h2>Welcome to Attendance Portal</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-check text-start mb-3">
                <input class="form-check-input" type="checkbox" name="on_leave" id="onLeave">
                <label class="form-check-label" for="onLeave">
                    I'm on leave today
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Submit Attendance</button>
        </form>

        <a href="logout.php" class="btn btn-outline-secondary w-100 logout-link">Logout</a>
    </div>
</body>
</html>
