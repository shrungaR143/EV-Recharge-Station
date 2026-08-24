<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?role=user");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $mobile = trim($_POST['mobile']);

    if (!empty($name) && !empty($mobile)) {
        $update_stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $name, $mobile, $user_id);
        if ($update_stmt->execute()) {
            $success_msg = "Profile updated successfully!";
        } else {
            $error_msg = "Failed to update profile. Please try again.";
        }
        $update_stmt->close();
    } else {
        $error_msg = "Fields cannot be empty.";
    }
}

// Fetch current user details
$stmt = $conn->prepare("SELECT name, email, mobile, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - EV Recharge Network</title>
    <link rel="stylesheet" href="css/style.css">
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
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .navbar {
            width: 100%;
            max-width: 600px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
        }

        .brand-logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent-green);
            text-decoration: none;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid var(--card-border);
            transition: background 0.2s;
        }

        .btn-back:hover { background: rgba(255, 255, 255, 0.12); }

        .form-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 35px;
            width: 100%;
            max-width: 600px;
            box-shadow: var(--shadow-3d);
        }

        .form-card h2 {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .form-card p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 25px;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 12px var(--accent-green-glow);
        }

        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-submit:hover {
            box-shadow: 0 0 16px var(--accent-green-glow);
        }
    </style>
</head>
<body>

    <div class="navbar">
        <a href="find_bunk.php" class="brand-logo">⚡ EV Station Finder</a>
        <a href="find_bunk.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <div class="form-card">
        <h2>Account Profile</h2>
        <p>View and update your personal account information.</p>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address (Cannot be changed)</label>
                <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
            </div>

            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Member Since</label>
                <input type="text" value="<?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?>" disabled>
            </div>

            <button type="submit" class="btn-submit">Save Changes</button>
        </form>
    </div>

</body>
</html>