<?php
session_start();
require_once 'db_connect.php';

// Verify session for bunk owner using 'bunk_owners' table columns (id, name, email, mobile, etc.)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bunk') {
    header("Location: login.php?role=bunk");
    exit();
}

// Fetch current bunk owner details dynamically from the 'bunk_owners' table
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM bunk_owners WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Map details matching exact phpMyAdmin column names: name, email, mobile, city, address
$owner_name = $current_user['name'] ?? $_SESSION['bunk_name'] ?? 'Station Owner';
$owner_email = $current_user['email'] ?? $_SESSION['email'] ?? '';
$owner_mobile = $current_user['mobile'] ?? 'No Phone';
$owner_city = $current_user['city'] ?? '';
$owner_address = $current_user['address'] ?? '';

// Calculate total stations/bunks managed by this owner using owner_id or user_id in the bunks table
$total_bunks = 0;
$stats_stmt = $conn->prepare("SELECT COUNT(*) as total_bunks FROM bunks WHERE owner_id = ?");
if (!$stats_stmt) {
    $stats_stmt = $conn->prepare("SELECT COUNT(*) as total_bunks FROM bunks WHERE user_id = ?");
}
if ($stats_stmt) {
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats_res = $stats_stmt->get_result()->fetch_assoc();
    $total_bunks = $stats_res['total_bunks'] ?? 0;
    $stats_stmt->close();
}

// Fetch incoming user support/chat messages using the correct 'users' table columns (id, name, email, phone)
$support_query = "SELECT c.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile 
                  FROM contact_support c 
                  JOIN users u ON c.user_id = u.id 
                  ORDER BY c.created_at DESC";
$support_result = $conn->query($support_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bunk Manager Portal - EV Recharge Network</title>
    <!-- Modern Typography Font -->
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

        /* Top Navigation Header */
        .navbar {
            background: rgba(15, 23, 42, 0.95);
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
            font-size: 20px;
            font-weight: 800;
            color: var(--accent-green);
            text-decoration: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
        }

        /* User Profile Dropdown Styling */
        .user-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .profile-toggle-btn {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            padding: 6px 14px 6px 6px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .profile-toggle-btn:hover {
            border-color: var(--accent-green);
        }

        .avatar-circle {
            width: 32px;
            height: 32px;
            background: var(--accent-green);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            background: #1e293b;
            border: 1px solid var(--card-border);
            border-radius: 12px;
            width: 240px;
            box-shadow: var(--shadow-3d);
            z-index: 1000;
            overflow: hidden;
            animation: fadeIn 0.2s ease;
        }

        .user-dropdown-container:hover .dropdown-menu {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-header-info {
            padding: 16px;
            border-bottom: 1px solid var(--card-border);
        }

        .dropdown-user-name {
            font-weight: 700;
            font-size: 14.5px;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .dropdown-user-email {
            font-size: 12px;
            color: var(--text-secondary);
            word-break: break-all;
            margin-bottom: 2px;
        }

        .dropdown-user-phone {
            font-size: 12px;
            color: var(--accent-green);
            font-weight: 500;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.2s ease;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--accent-green);
        }

        .dropdown-item.logout {
            color: #f87171;
            border-top: 1px solid var(--card-border);
        }

        .dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        /* Popup Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(6px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: #1e293b;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            width: 90%;
            max-width: 520px;
            padding: 30px;
            box-shadow: var(--shadow-3d);
            position: relative;
            animation: zoomIn 0.25s ease;
        }

        .modal-card-wide {
            background: #1e293b;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            width: 92%;
            max-width: 950px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            padding: 30px;
            box-shadow: var(--shadow-3d);
            position: relative;
            animation: zoomIn 0.25s ease;
        }

        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-close {
            position: absolute;
            top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            width: 32px; height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            display: flex; align-items: center; justify-content: center;
            z-index: 10;
        }

        .modal-close:hover {
            background: #ef4444;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #fff;
        }

        .modal-subtitle {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin-bottom: 22px;
        }

        /* Overview Info Box */
        .profile-overview-box {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .profile-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 14px;
        }

        .profile-row:last-child {
            border-bottom: none;
        }

        .profile-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .profile-value {
            color: #fff;
            font-weight: 600;
            text-align: right;
            word-break: break-all;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            padding: 11px 14px;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-green);
        }

        .btn-submit {
            background: var(--accent-green);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 12px;
            font-size: 15px;
            transition: opacity 0.2s ease;
        }

        .btn-submit:hover {
            opacity: 0.9;
        }

        /* Dashboard Layout */
        .main-container {
            max-width: 1250px;
            width: 92%;
            margin: 40px auto;
            flex: 1;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .dashboard-header p {
            color: var(--text-secondary);
            font-size: 14.5px;
            margin-top: 6px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .dashboard-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            padding: 28px;
            text-decoration: none;
            color: var(--text-primary);
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-3d);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, var(--card-border), transparent);
            transition: background 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-green);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.6), 0 0 15px var(--accent-green-glow);
        }

        .card-icon {
            font-size: 34px;
            margin-bottom: 16px;
            background: rgba(15, 23, 42, 0.6);
            width: 60px; height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            border: 1px solid var(--card-border);
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .card-desc {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .card-featured {
            border: 1px solid rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.95));
        }

        @media (max-width: 768px) {
            .navbar { padding: 16px 20px; }
            .main-container { width: 95%; margin: 20px auto; }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Header -->
    <header class="navbar">
        <a href="dashboard.php" class="brand-logo" style="text-decoration:none;">
            <span>⚡</span> EV Bunk Portal
        </a>
        <div class="user-info">
            <div class="user-dropdown-container">
                <div class="profile-toggle-btn">
                    <div class="avatar-circle">
                        <?php echo strtoupper(substr($owner_name, 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($owner_name); ?></span>
                    <span style="font-size: 10px; color: var(--text-secondary);">▼</span>
                </div>
                <div class="dropdown-menu">
                    <div class="dropdown-header-info">
                        <div class="dropdown-user-name"><?php echo htmlspecialchars($owner_name); ?></div>
                        <div class="dropdown-user-email"><?php echo htmlspecialchars($owner_email); ?></div>
                        <div class="dropdown-user-phone"><?php echo htmlspecialchars($owner_mobile); ?></div>
                    </div>
                    <div class="dropdown-item" onclick="openModal('profileModal')">👤 Profile</div>
                    <div class="dropdown-item" onclick="openModal('passwordModal')">⚙️ Change Password</div>
                    <div class="dropdown-item" onclick="openModal('chatsModal')">💬 Chats</div>
                    <a href="logout.php" class="dropdown-item logout">🚪 Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Popup Window: Profile Details matching bunk_owners table (id, name, email, mobile, city, address) -->
    <div id="profileModal" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal('profileModal')">&times;</button>
            <div class="modal-title">Owner Profile Details</div>
            <div class="modal-subtitle">Overview of your station manager account information.</div>
            
            <div class="profile-overview-box">
                <div class="profile-row">
                    <span class="profile-label">Full Name</span>
                    <span class="profile-value"><?php echo htmlspecialchars($owner_name); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Email Address</span>
                    <span class="profile-value"><?php echo htmlspecialchars($owner_email); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Mobile Number</span>
                    <span class="profile-value"><?php echo htmlspecialchars($owner_mobile); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">City</span>
                    <span class="profile-value"><?php echo htmlspecialchars($owner_city); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Address</span>
                    <span class="profile-value"><?php echo htmlspecialchars($owner_address); ?></span>
                </div>
                <div class="profile-row">
                    <span class="profile-label">Total Stations</span>
                    <span class="profile-value" style="color: var(--accent-green);"><?php echo $total_bunks; ?></span>
                </div>
            </div>

            <form action="update_profile.php" method="POST">
                <div class="form-group">
                    <label>Update Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($owner_name); ?>" required>
                </div>
                <div class="form-group">
                    <label>Update Mobile Number</label>
                    <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($owner_mobile); ?>">
                </div>
                <div class="form-group">
                    <label>Update City</label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($owner_city); ?>">
                </div>
                <div class="form-group">
                    <label>Update Address</label>
                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($owner_address); ?></textarea>
                </div>
                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Popup Window: Change Password -->
    <div id="passwordModal" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-close" onclick="closeModal('passwordModal')">&times;</button>
            <div class="modal-title">Change Password</div>
            <div class="modal-subtitle">Secure your account with a strong password.</div>
            <form action="update_password.php" method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn-submit">Update Password</button>
            </form>
        </div>
    </div>

    <!-- Popup Window: Chats (Customer Support Messages) -->
    <div id="chatsModal" class="modal-overlay">
        <div class="modal-card-wide">
            <button class="modal-close" onclick="closeModal('chatsModal')">&times;</button>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="font-size: 26px;">💬</div>
                <div>
                    <div class="modal-title" style="margin-bottom: 2px;">Customer Support Chats</div>
                    <div style="font-size: 13px; color: var(--text-secondary);">Real-time messages sent by users and drivers.</div>
                </div>
            </div>
            <div style="overflow-y: auto; flex: 1; border: 1px solid var(--card-border); border-radius: 10px;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; color: #f8fafc; font-size: 14px;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #94a3b8; background: rgba(15,23,42,0.6); position: sticky; top: 0;">
                            <th style="padding: 12px;">User</th>
                            <th style="padding: 12px;">Contact Info</th>
                            <th style="padding: 12px;">Subject</th>
                            <th style="padding: 12px;">Message Sent</th>
                            <th style="padding: 12px;">Date</th>
                            <th style="padding: 12px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($support_result && $support_result->num_rows > 0): ?>
                            <?php while ($row = $support_result->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 12px; font-weight: 600;"><?php echo htmlspecialchars($row['user_name']); ?></td>
                                    <td style="padding: 12px; font-size: 13px; color: #94a3b8;">
                                        <?php echo htmlspecialchars($row['user_email']); ?><br>
                                        <?php echo htmlspecialchars($row['user_mobile'] ?? 'No Phone'); ?>
                                    </td>
                                    <td style="padding: 12px; color: #38bdf8; font-weight: 500;"><?php echo htmlspecialchars($row['subject']); ?></td>
                                    <td style="padding: 12px; max-width: 280px; word-break: break-word;"><?php echo nl2br(htmlspecialchars($row['message'])); ?></td>
                                    <td style="padding: 12px; font-size: 12px; color: #94a3b8;"><?php echo $row['created_at']; ?></td>
                                    <td style="padding: 12px;">
                                        <a href="mailto:<?php echo htmlspecialchars($row['user_email']); ?>?subject=Re: <?php echo urlencode($row['subject']); ?>" 
                                           style="background: #0ea5e9; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block;">
                                            Reply
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">No chat messages received yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Container -->
    <main class="main-container">
        <div class="dashboard-header">
            <h1>Station Management Control</h1>
            <p>Select an operation below to manage your stations, slots, and live customer bookings.</p>
        </div>

        <div class="grid-container">
            <a href="create_bunk.php" class="dashboard-card">
                <div>
                    <div class="card-icon">➕</div>
                    <div class="card-title">Create New Bunk</div>
                    <div class="card-desc">Register a new EV charging station location to your account.</div>
                </div>
            </a>

            <a href="view_bunk_details.php" class="dashboard-card">
                <div>
                    <div class="card-icon">📋</div>
                    <div class="card-title">View / Search Bunks</div>
                    <div class="card-desc">Check your registered charging stations and update details.</div>
                </div>
            </a>

            <a href="update_slots.php" class="dashboard-card">
                <div>
                    <div class="card-icon">🔋</div>
                    <div class="card-title">Manage Free SlotsHeader</div>
                    <div class="card-desc">Manually update available chargers and total capacity.</div>
                </div>
            </a>

            <a href="owner_bookings.php" class="dashboard-card card-featured">
                <div>
                    <div class="card-icon">📥</div>
                    <div class="card-title">Live Station Bookings</div>
                    <div class="card-desc">Monitor active reservations, verify payments, and complete sessions.</div>
                </div>
            </a>

            <a href="update_map.php" class="dashboard-card">
                <div>
                    <div class="card-icon">📍</div>
                    <div class="card-title">Update Location Map</div>
                    <div class="card-desc">Set accurate Google Maps GPS coordinates for driver navigation.</div>
                </div>
            </a>

            <a href="view_feedback.php" class="dashboard-card">
                <div>
                    <div class="card-icon">💬</div>
                    <div class="card-title">Customer Reviews</div>
                    <div class="card-desc">Read feedback and rating reviews left by EV drivers.</div>
                </div>
            </a>
        </div>
    </main>

    <!-- JavaScript Handling Popup Windows -->
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>