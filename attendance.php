<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection with error handling
$conn = new mysqli("mysql.lamp.svc.cluster.local", "root", "password", "testdb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Get user details with error handling
$user_query = $conn->prepare("SELECT name, email, department, position FROM users WHERE id = ?");
if (!$user_query) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$user_query->bind_param("i", $user_id);
if (!$user_query->execute()) {
    die("Execute failed: (" . $user_query->errno . ") " . $user_query->error);
}

$user_result = $user_query->get_result();
if (!$user_result) {
    die("Get result failed: (" . $user_query->errno . ") " . $user_query->error);
}

$user = $user_result->fetch_assoc();
if (!$user) {
    die("User not found");
}

// Get attendance history (last 30 days)
$history_query = $conn->prepare("SELECT date, on_leave, check_in_time FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 30");
$history_query->bind_param("i", $user_id);
$history_query->execute();
$history_result = $history_query->get_result();

// Get attendance stats
$stats_query = $conn->prepare("SELECT 
    SUM(CASE WHEN on_leave = 0 THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN on_leave = 1 THEN 1 ELSE 0 END) as leave_days,
    COUNT(*) as total_days
    FROM attendance WHERE user_id = ? AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()");
$stats_query->bind_param("i", $user_id);
$stats_query->execute();
$stats_result = $stats_query->get_result();
$stats = $stats_result->fetch_assoc();

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $is_leave = isset($_POST['on_leave']) ? 1 : 0;
    $check_in_time = date('H:i:s');

    // Check if already marked today
    $check = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = CURDATE()");
    $check->bind_param("i", $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        // Insert attendance
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, on_leave, date, check_in_time) VALUES (?, ?, CURDATE(), ?)");
        $stmt->bind_param("iis", $user_id, $is_leave, $check_in_time);
        $stmt->execute();

        if ($is_leave) {
            $message = "Leave recorded successfully!";
        } else {
            $message = "Attendance marked successfully at " . date('h:i A', strtotime($check_in_time));
        }
        
        // Refresh data after submission
        $history_query->execute();
        $history_result = $history_query->get_result();
        $stats_query->execute();
        $stats_result = $stats_query->get_result();
        $stats = $stats_result->fetch_assoc();
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
            --gray: #6c757d;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.18);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .header-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
        }
        
        .header-gradient::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
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
            transition: transform 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
        }
        
        .attendance-form {
            padding: 2rem;
        }
        
        .form-check-input {
            width: 1.5em;
            height: 1.5em;
            margin-top: 0.1em;
            cursor: pointer;
        }
        
        .form-check-label {
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .form-check-label:hover {
            color: var(--primary);
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .present-stat {
            color: var(--success);
        }
        
        .leave-stat {
            color: var(--warning);
        }
        
        .total-stat {
            color: var(--primary);
        }
        
        .attendance-history {
            margin-top: 2rem;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .history-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
            transform: translateX(5px);
        }
        
        .history-date {
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .history-date i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .history-status {
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .status-present {
            color: var(--success);
        }
        
        .status-leave {
            color: var(--warning);
        }
        
        .history-time {
            font-size: 0.85rem;
            color: var(--gray);
            margin-left: 0.5rem;
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
        
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 2rem 0;
            margin-top: auto;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }
        
        .footer-column h5 {
            font-weight: 600;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }
        
        .footer-column h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -5px;
            width: 40px;
            height: 2px;
            background: white;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.5rem;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-links a {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-3px);
        }
        
        .copyright {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Animation classes */
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .header-gradient {
                padding: 1.5rem;
            }
            
            .attendance-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="glass-card">
            <div class="header-gradient text-center">
                <div class="user-avatar pulse">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <h3 class="mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($user['position']) ?> • <?= htmlspecialchars($user['department']) ?></p>
            </div>
            
            <div class="attendance-form">
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-value present-stat"><?= $stats['present_days'] ?? 0 ?></div>
                        <div class="stat-label">Present Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value leave-stat"><?= $stats['leave_days'] ?? 0 ?></div>
                        <div class="stat-label">Leave Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value total-stat"><?= $stats['total_days'] ?? 0 ?></div>
                        <div class="stat-label">Total Days</div>
                    </div>
                </div>
                
                <h4 class="text-center mb-4">Mark Today's Attendance</h4>
                
                <form method="post" id="attendanceForm">
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="on_leave" id="onLeave">
                        <label class="form-check-label" for="onLeave">
                            <i class="fas fa-umbrella-beach me-2"></i> I'm on leave today
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                        <i class="fas fa-check-circle me-2"></i> Submit Attendance
                    </button>
                    
                    <a href="logout.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </form>
                
                <div class="attendance-history">
                    <div class="history-header">
                        <h5><i class="fas fa-history me-2"></i>Attendance History</h5>
                        <small class="text-muted">Last 30 days</small>
                    </div>
                    <div class="list-group" id="historyList">
                        <?php if ($history_result->num_rows > 0): ?>
                            <?php while($row = $history_result->fetch_assoc()): ?>
                                <div class="history-item">
                                    <span class="history-date">
                                        <i class="fas fa-calendar-day"></i>
                                        <?= date('D, M j', strtotime($row['date'])) ?>
                                    </span>
                                    <span class="history-status <?= $row['on_leave'] ? 'status-leave' : 'status-present' ?>">
                                        <?= $row['on_leave'] ? 'On Leave' : 'Present' ?>
                                        <?php if (!$row['on_leave'] && !empty($row['check_in_time'])): ?>
                                            <span class="history-time">at <?= date('h:i A', strtotime($row['check_in_time'])) ?></span>
                                        <?php endif; ?>
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
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h5>Inadev</h5>
                    <p>The sky isn't the limit.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Services</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h5>Resources</h5>
                    <ul class="footer-links">
                        <li><a href="#">Employee Handbook</a></li>
                        <li><a href="#">HR Portal</a></li>
                        <li><a href="#">IT Support</a></li>
                        <li><a href="#">Training</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h5>Contact</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Unit 901, Tower 1, Godrej Waterside Sector V, Salt Lake City | West Bengal - 700091e</li>
                        <li><i class="fas fa-phone me-2"></i> +91 33 6606 4343</li>
                        <li><i class="fas fa-envelope me-2"></i> info@inadev.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> Inadev India Pvt. Ltd. All rights reserved.
            </div>
        </div>
    </footer>
    
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
        
        // Add animation to submit button
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.addEventListener('click', function() {
            this.classList.add('pulse');
            setTimeout(() => {
                this.classList.remove('pulse');
            }, 2000);
        });
        
        // Add shake animation to form if trying to submit without selection
        const attendanceForm = document.getElementById('attendanceForm');
        attendanceForm.addEventListener('submit', function(e) {
            const onLeave = document.getElementById('onLeave');
            if (!onLeave.checked) {
                // Add visual confirmation for present marking
                submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Marking Present...';
                submitBtn.classList.add('btn-success');
            } else {
                submitBtn.innerHTML = '<i class="fas fa-umbrella-beach me-2"></i> Marking Leave...';
                submitBtn.classList.add('btn-warning');
            }
        });
        
        // Add hover effect to history items
        const historyItems = document.querySelectorAll('.history-item');
        historyItems.forEach(item => {
            item.addEventListener('click', function() {
                this.classList.add('shake');
                setTimeout(() => {
                    this.classList.remove('shake');
                }, 500);
            });
        });
        
        // Real-time clock for fun (could be used in future enhancements)
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
            // Could display this somewhere in the UI
        }
        
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>
