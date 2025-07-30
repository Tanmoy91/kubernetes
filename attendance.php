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
            display: flex;
            flex-direction: column;
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
        
        .attendance-history {
            margin-top: 2rem;
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
            color: var(--dark);
            opacity: 0.7;
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
        
        /* Footer Styles */
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
            transition: all 0.3s ease;
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
                <p class="mb-0 opacity-75"><?= htmlspecialchars($user['email']) ?></p>
            </div>
            
            <div class="attendance-form">
                <h4 class="text-center mb-4">Daily Attendance</h4>
                
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
                
                <div class="attendance-history mt-4">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Attendance</h5>
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
                    <p>Innovating digital solutions for tomorrow's challenges.</p>
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
                        <li><i class="fas fa-map-marker-alt me-2"></i> 123 Tech Park, Bangalore</li>
                        <li><i class="fas fa-phone me-2"></i> +91 80 1234 5678</li>
                        <li><i class="fas fa-envelope me-2"></i> hr@inadev.com</li>
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
    </script>
</body>
</html>
