<?php
session_start();
require_once 'db_connect.php';
require_once 'send_email.php'; // Include your email script
require_once 'send_sms.php';   // Include your SMS script

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Query users table
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Password verification
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'user';
                $_SESSION['user_name'] = $user['name'] ?? 'User';

                $userName = $_SESSION['user_name'];
                $userPhone = $user['mobile'] ?? $user['phone'] ?? '';

                // 1. Send Login Notification Email
                $subject = "New Login Alert - EV Recharge Network";
                $body = "Hi <b>$userName</b>,<br><br>We noticed a successful login to your EV Driver account. If this was you, you can safely ignore this email.";
                sendEmail($email, $subject, $body);

                // 2. Send Login Notification SMS
                if (!empty($userPhone) && function_exists('sendSMSNotification')) {
                    sendSMSNotification($userPhone, $userName, "EV Recharge Network", "Account Login Alert");
                }

                header("Location: user_dashboard.php");
                exit();
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "No user account found with this email. <a href='user_register.php' style='color: #0ea5e9;'>Register here</a>.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - EV Recharge Network</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 35px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        h2 { margin-top: 0; color: #0ea5e9; text-align: center; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #94a3b8; }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            box-sizing: border-box;
            font-size: 14px;
        }
        input:focus { outline: none; border-color: #0ea5e9; }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 18px;
            text-align: center;
        }
        .footer-links { text-align: center; font-size: 13px; color: #94a3b8; margin-top: 20px; }
        .footer-links a { color: #0ea5e9; text-decoration: none; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>🚗 Driver Login</h2>
    
    <?php if (!empty($error)): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="user_login.php">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required placeholder="driver@example.com">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn-submit">Login as Driver</button>
    </form>

    <div class="footer-links">
        Don't have an account? <a href="user_register.php">Register here</a><br><br>
        <a href="index.php">← Back to Portal Selection</a>
    </div>
</div>

</body>
</html>