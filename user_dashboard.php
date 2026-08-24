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
$active_modal = "";

// Check if redirected with a success message from contact.php
if (isset($_GET['msg']) && $_GET['msg'] === 'sent') {
    $success_msg = "Your message has been sent to support!";
    $active_modal = 'contact';
}

// Handle Form Submissions (Profile & Password)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $active_modal = 'profile';
        $name = trim($_POST['name']);
        $mobile = trim($_POST['mobile']);

        if (!empty($name) && !empty($mobile)) {
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, mobile = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $name, $mobile, $user_id);
            if ($update_stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $success_msg = "Profile updated successfully!";
            } else {
                $error_msg = "Failed to update profile.";
            }
            $update_stmt->close();
        } else {
            $error_msg = "Fields cannot be empty.";
        }
    } elseif (isset($_POST['change_password'])) {
        $active_modal = 'password';
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];

        $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $pass_data = $pass_stmt->get_result()->fetch_assoc();
        $pass_stmt->close();

        if ($pass_data && password_verify($current_pass, $pass_data['password'])) {
            $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $up_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $up_pass->bind_param("si", $new_hashed, $user_id);
            if ($up_pass->execute()) {
                $success_msg = "Password changed successfully!";
            } else {
                $error_msg = "Error updating password.";
            }
            $up_pass->close();
        } else {
            $error_msg = "Incorrect current password.";
        }
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT name, email, mobile FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch booking count for profile info
$booking_stmt = $conn->prepare("SELECT COUNT(*) as total_bookings FROM bookings WHERE user_id = ?");
$total_bookings = 0;
if ($booking_stmt) {
    $booking_stmt->bind_param("i", $user_id);
    $booking_stmt->execute();
    $res = $booking_stmt->get_result()->fetch_assoc();
    $total_bookings = $res['total_bookings'] ?? 0;
    $booking_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV User Dashboard</title>
    
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
            --accent-blue: #0ea5e9;
            --shadow-3d: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 25px 30px -5px rgba(16, 185, 129, 0.25), 0 0 20px rgba(16, 185, 129, 0.3);
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
        }

        .navbar {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 800;
            color: var(--accent-green);
            text-shadow: 0 0 12px var(--accent-green-glow);
            text-decoration: none;
        }

        .profile-menu-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            padding: 6px 14px 6px 6px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .profile-trigger:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 15px;
        }

        .profile-name-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .profile-dropdown {
            position: absolute;
            right: 0;
            top: 55px;
            width: 280px;
            background: #1e293b;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 200;
            animation: fadeIn 0.2s ease;
        }

        .profile-dropdown.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-user-info {
            padding: 18px 16px;
            background: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dropdown-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--accent-green), var(--accent-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .dropdown-user-details .d-name {
            font-weight: 700;
            font-size: 14.5px;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-user-details .d-email {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-user-details .d-mobile {
            font-size: 11.5px;
            color: var(--accent-green);
            margin-top: 1px;
        }

        .dropdown-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .dropdown-item.logout {
            color: #f87171;
            border-top: 1px solid var(--card-border);
        }

        .dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .main-container {
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
            flex: 1;
        }

        .dashboard-header {
            margin-bottom: 35px;
        }

        .dashboard-header h1 {
            font-size: clamp(24px, 4vw, 32px);
            font-weight: 800;
            color: var(--text-primary);
        }

        .dashboard-header p {
            color: var(--text-secondary);
            font-size: 15px;
            margin-top: 8px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .dashboard-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 28px;
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: var(--shadow-3d);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--accent-green), var(--accent-blue));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-hover);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        .card-icon { font-size: 38px; margin-bottom: 16px; }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #fff; }
        .card-desc { font-size: 13.5px; color: var(--text-secondary); line-height: 1.6; }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            display: flex; justify-content: center; align-items: center;
            z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid var(--card-border);
            border-radius: 16px; padding: 30px; width: 90%; max-width: 500px;
            box-shadow: var(--shadow-3d); position: relative;
            transform: translateY(20px); transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-close {
            position: absolute; top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.08); border: none;
            color: var(--text-secondary); font-size: 18px; width: 32px; height: 32px;
            border-radius: 50%; cursor: pointer; display: flex; justify-content: center; align-items: center;
        }
        .modal-content h3 { font-size: 20px; font-weight: 700; margin-bottom: 6px; color: #fff; }
        .modal-content p { font-size: 13.5px; color: var(--text-secondary); margin-bottom: 20px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; }
        .form-group input, .form-group textarea {
            width: 100%; background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--card-border); border-radius: 8px;
            padding: 10px 14px; color: var(--text-primary); font-size: 14px; outline: none;
        }
        .form-group textarea { height: 100px; resize: vertical; }
        .btn-submit {
            width: 100%; background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white; border: none; padding: 12px; border-radius: 8px;
            font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 10px;
        }
        .alert { padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .profile-info-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .profile-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13.5px;
        }
        .profile-info-row:last-child { border-bottom: none; }
        .profile-info-label { color: var(--text-secondary); font-weight: 500; }
        .profile-info-value { color: var(--text-primary); font-weight: 600; }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="dashboard.php" class="brand-logo">
            <span>⚡</span> EV Recharge Network
        </a>

        <div class="profile-menu-wrapper">
            <div class="profile-trigger" onclick="toggleDropdown(event)">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                </div>
                <span class="profile-name-label"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?> ▼</span>
            </div>

            <div id="profileDropdown" class="profile-dropdown">
                <div class="dropdown-user-info">
                    <div class="dropdown-avatar">
                        <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="dropdown-user-details">
                        <div class="d-name"><?php echo htmlspecialchars($user['name'] ?? ''); ?></div>
                        <div class="d-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                        <div class="d-mobile"><?php echo htmlspecialchars($user['mobile'] ?? 'No phone added'); ?></div>
                    </div>
                </div>
                <button class="dropdown-item" onclick="openModal('profileModal')">👤 Profile</button>
                <button class="dropdown-item" onclick="openModal('passwordModal')">⚙️ Change Password</button>
                <button class="dropdown-item" onclick="openModal('contactModal')">📞 Contact Support</button>
                <a href="logout.php" class="dropdown-item logout">🚪 Logout</a>
            </div>
        </div>
    </header>

    <main class="main-container">
        <div class="dashboard-header">
            <h1>Driver Control Panel</h1>
            <p>Locate nearby charging stations, book charging slots, and manage your account details.</p>
        </div>

        <div class="grid-container">
            <a href="find_bunk.php" class="dashboard-card">
                <div>
                    <div class="card-icon">🔌</div>
                    <div class="card-title">Find Charging Stations</div>
                    <div class="card-desc">Search nearby stations, check available slots in real-time, and reserve a charging slot.</div>
                </div>
            </a>

            <a href="my_requests.php" class="dashboard-card">
                <div>
                    <div class="card-icon">📅</div>
                    <div class="card-title">My Bookings & Receipts</div>
                    <div class="card-desc">Track active slot bookings, payment transaction details, and view receipts.</div>
                </div>
            </a>

            <a href="map_view.php" class="dashboard-card">
                <div>
                    <div class="card-icon">📍</div>
                    <div class="card-title">Interactive Station Map</div>
                    <div class="card-desc">View charging bunks geographically on Google Maps and get turn-by-turn driving directions.</div>
                </div>
            </a>

            <a href="post_feedback.php" class="dashboard-card">
                <div>
                    <div class="card-icon">⭐</div>
                    <div class="card-title">Rate & Review Bunks</div>
                    <div class="card-desc">Share feedback and rate charging stations to help fellow EV drivers in the community.</div>
                </div>
            </a>
        </div>
    </main>

    <!-- Profile Details View Modal -->
    <div id="profileModal" class="modal-overlay <?php echo ($active_modal === 'profile') ? 'active' : ''; ?>">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('profileModal')">&times;</button>
            <h3>User Profile Details</h3>
            <p>Overview of your account information and statistics.</p>
            
            <?php if ($active_modal === 'profile' && !empty($success_msg)) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
            <?php if ($active_modal === 'profile' && !empty($error_msg)) echo "<div class='alert alert-error'>$error_msg</div>"; ?>

            <div class="profile-info-box">
                <div class="profile-info-row">
                    <span class="profile-info-label">Full Name</span>
                    <span class="profile-info-value"><?php echo htmlspecialchars($user['name'] ?? ''); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">Email Address</span>
                    <span class="profile-info-value"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">Phone Number</span>
                    <span class="profile-info-value"><?php echo htmlspecialchars($user['mobile'] ?? 'Not Provided'); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">Total Bookings</span>
                    <span class="profile-info-value" style="color: var(--accent-green);"><?php echo $total_bookings; ?></span>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label>Update Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Update Mobile Number</label>
                    <input type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" required>
                </div>
                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal-overlay <?php echo ($active_modal === 'password') ? 'active' : ''; ?>">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('passwordModal')">&times;</button>
            <h3>Change Password</h3>
            <p>Secure your account with a new password.</p>
            <?php if ($active_modal === 'password' && !empty($success_msg)) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
            <?php if ($active_modal === 'password' && !empty($error_msg)) echo "<div class='alert alert-error'>$error_msg</div>"; ?>
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <button type="submit" class="btn-submit">Update Password</button>
            </form>
        </div>
    </div>

    <!-- Contact Support Modal (Action points directly to contact.php handler) -->
    <div id="contactModal" class="modal-overlay <?php echo ($active_modal === 'contact') ? 'active' : ''; ?>">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('contactModal')">&times;</button>
            <h3>Contact Support</h3>
            <p>Send a message to our support team.</p>
            <?php if ($active_modal === 'contact' && !empty($success_msg)) echo "<div class='alert alert-success'>$success_msg</div>"; ?>
            <form method="POST" action="contact.php">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" placeholder="e.g. Booking Issue" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" placeholder="Describe your issue..." required></textarea>
                </div>
                <button type="submit" class="btn-submit">Send Message</button>
            </form>
        </div>
    </div>

    <script>
        function toggleDropdown(event) {
            event.stopPropagation();
            document.getElementById('profileDropdown').classList.toggle('active');
        }

        window.addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.remove('active');
        });

        document.getElementById('profileDropdown').addEventListener('click', function(event) {
            event.stopPropagation();
        });

        function openModal(modalId) {
            document.getElementById('profileDropdown').classList.remove('active');
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
    </script>
</body>
</html>