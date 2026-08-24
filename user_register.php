<?php
session_start();
require_once 'db_connect.php';
require_once 'send_email.php'; // Include your email script
require_once 'send_sms.php';   // Include your SMS script

$message = "";
$error = "";
$role = $_GET['role'] ?? $_POST['role'] ?? 'user';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if (empty($name) || empty($email) || empty($password) || empty($city)) {
        $error = "Please fill in all mandatory fields including city.";
    } else {
        // Determine table based on role (using bunk_owner instead of bunks)
        $is_owner = ($role === 'bunk_owner' || $role === 'owner');
        $target_table = $is_owner ? "bunk_owner" : "users";

        // Check if email already exists in the correct table
        $check = $conn->prepare("SELECT id FROM $target_table WHERE email = ?");
        if ($check) {
            $check->bind_param("s", $email);
            $check->execute();
            $check_res = $check->get_result();

            if ($check_res && $check_res->num_rows > 0) {
                $error = "This email is already registered. Please login.";
            } else {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

                // Insert into correct table matching schema
                if ($is_owner) {
                    $stmt = $conn->prepare("INSERT INTO bunk_owner (name, email, phone, city, password) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssss", $name, $email, $phone, $city, $hashed_pass);
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (name, email, mobile, city, password) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssss", $name, $email, $phone, $city, $hashed_pass);
                    }
                }
                
                if (isset($stmt) && $stmt && $stmt->execute()) {
                    // 1. Send Welcome Email upon successful registration
                    $subject = "Welcome to EV Recharge Network";
                    $body = "Hi <b>$name</b>,<br><br>Your account has been successfully created! You can now log in and manage your EV charging activities.";
                    sendEmail($email, $subject, $body);

                    // 2. Send Welcome SMS upon successful registration
                    if (!empty($phone) && function_exists('sendSMSNotification')) {
                        sendSMSNotification($phone, $name, "EV Charging Network", "Account Registration");
                    }

                    $redirect_page = $is_owner ? "bunk_login.php" : "user_login.php";
                    header("Location: {$redirect_page}?registered=1");
                    exit();
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
            $check->close();
        } else {
            $error = "Database query error. Please check table structure.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - EV Charging Network</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.85);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-green: #10b981;
            --accent-green-glow: rgba(16, 185, 129, 0.35);
            --shadow-3d: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 480px;
        }

        .auth-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 36px;
            box-shadow: var(--shadow-3d);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-logo {
            font-size: 32px;
            margin-bottom: 8px;
            display: inline-block;
        }

        .brand-header h1 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-header p {
            color: var(--text-secondary);
            font-size: 13.5px;
            margin-top: 4px;
        }

        .role-tabs {
            display: flex;
            background: rgba(15, 23, 42, 0.6);
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 24px;
            border: 1px solid var(--card-border);
        }

        .role-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
        }

        .role-btn.active {
            background: var(--accent-green);
            color: white;
            box-shadow: 0 2px 10px var(--accent-green-glow);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-green);
        }

.btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            box-shadow: 0 0 15px var(--accent-green-glow);
            transform: translateY(-1px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13.5px;
            color: var(--text-secondary);
        }

        .auth-footer a {
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-header">
                <div class="brand-logo">⚡</div>
                <h1>Create Account</h1>
                <p>Join the EV charging network</p>
            </div>

            <!-- Role Selector Tabs -->
            <div class="role-tabs">
                <a href="?role=user" class="role-btn <?php echo ($role === 'user') ? 'active' : ''; ?>">🚗 EV Driver</a>
                <a href="?role=bunk_owner" class="role-btn <?php echo ($role === 'bunk_owner' || $role === 'owner') ? 'active' : ''; ?>">🔌 Station Owner</a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="" method="POST">
            
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

                <div class="form-group">
                    <label class="form-label" for="name"><?php echo ($role === 'bunk_owner' || $role === 'owner') ? 'Station / Owner Name' : 'Full Name'; ?></label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="<?php echo ($role === 'bunk_owner' || $role === 'owner') ? 'GreenCharge Hub' : 'John Doe'; ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="form-control" placeholder="+91 9876543210">
                </div>

                <div class="form-group">
                    <label class="form-label" for="city">City</label>
                    <input type="text" name="city" id="city" class="form-control" placeholder="Enter your city" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Create a strong password" required>
                </div>

                <button type="submit" class="btn-submit">Create Account</button>
            </form>

            <div class="auth-footer">
                Already registered? <a href="<?php echo ($role === 'bunk_owner' || $role === 'owner') ? 'bunk_login.php' : 'user_login.php'; ?>">Sign in here</a>
            </div>
        </div>
    </div>

</body>
</html>