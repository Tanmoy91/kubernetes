<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");
$user_id = $_SESSION['user_id'];

// Get user details for display
$user_query = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

// Get attendance history (last 7 days)
$history_query = $conn->prepare("SELECT date, on_leave FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 7");
$history_query->bind_param("i", $user_id);
$history_query->execute();
$history_result = $history_query->get_result();

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
            $message = "Leave recorded successfully!";
        } else {
            $message = "Attendance marked successfully!";
        }
        
        // Refresh history after submission
        $history_query->execute();
        $history_result = $history_query->get_result();
    } else {
        $message = "You have already marked your attendance today.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Portal | Inadev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --warning: #f72585;
            --info: #7209b7;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.18);
            overflow: hidden;
        }
        
        .header-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--primary);
            font-weight: bold;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .attendance-form {
            padding: 2rem;
        }
        
        .form-check-input {
            width: 1.5em;
            height: 1.5em;
            margin-top: 0.1em;
        }
        
        .form-check-label {
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border: none;
            padding: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline-secondary {
            border-color: var(--primary);
            color: var(--primary);
            transition: all 0.3s ease;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .attendance-history {
            margin-top: 2rem;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s ease;
        }
        
        .history-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .history-date {
            font-weight: 500;
        }
        
        .history-status {
            font-weight: 600;
        }
        
        .status-present {
            color: var(--success);
        }
        
        .status-leave {
            color: var(--warning);
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.5s forwards, fadeOut 0.5s 3s forwards;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
            transition: all 0.3s ease;
            z-index: 99;
        }
        
        .floating-btn:hover {
            transform: scale(1.1);
            background: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="glass-card">
            <div class="header-gradient text-center">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <h3 class="mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($user['email']) ?></p>
            </div>
            
            <div class="attendance-form">
                <h4 class="text-center mb-4">Daily Attendance</h4>
                
                <form method="post">
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="on_leave" id="onLeave">
                        <label class="form-check-label" for="onLeave">
                            <i class="fas fa-umbrella-beach me-2"></i> I'm on leave today
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="fas fa-check-circle me-2"></i> Submit Attendance
                    </button>
                    
                    <a href="logout.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </form>
                
                <div class="attendance-history mt-4">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
                    <div class="list-group">
                        <?php if ($history_result->num_rows > 0): ?>
                            <?php while($row = $history_result->fetch_assoc()): ?>
                                <div class="history-item">
                                    <span class="history-date">
                                        <?= date('D, M j', strtotime($row['date'])) ?>
                                    </span>
                                    <span class="history-status <?= $row['on_leave'] ? 'status-leave' : 'status-present' ?>">
                                        <?= $row['on_leave'] ? 'On Leave' : 'Present' ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted">
                                No attendance records found
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="notification">
            <div class="alert alert-<?= strpos($message, 'successfully') !== false ? 'success' : 'warning' ?> shadow-lg">
                <div class="d-flex align-items-center">
                    <i class="fas <?= strpos($message, 'successfully') !== false ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> me-2"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <a href="#" class="floating-btn" data-bs-toggle="tooltip" title="Need help?">
        <i class="fas fa-question"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Auto-dismiss notification after 3 seconds
        setTimeout(() => {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 3000);
    </script>
</body>
</html>
