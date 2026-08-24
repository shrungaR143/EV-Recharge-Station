<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?role=user");
    exit();
}

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "ev_recharge_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Query bookings/requests for logged-in user
// Adjust table/column names if your schema uses 'bookings', 'requests', or different key names
$sql = "SELECT r.*, b.bunk_name, b.area FROM bookings r JOIN bunks b ON r.bunk_id = b.id WHERE r.user_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Fallback query if requests table uses a direct relation or simpler structure
    $sql = "SELECT * FROM bookings WHERE user_id = ? ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings & Receipts - EV Network</title>
    
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

        /* Top Header Navbar */
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

        .btn-back {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--card-border);
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Main Container */
        .main-container {
            width: 90%;
            max-width: 1200px;
            margin: 35px auto;
            flex: 1;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 6px;
        }

        /* Booking Cards Stack */
        .booking-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .booking-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-3d);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            transition: transform 0.2s, border-color 0.2s;
        }

        .booking-card:hover {
            transform: translateY(-3px);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .booking-details {
            flex: 1;
            min-width: 260px;
        }

        .station-name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .booking-meta {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved, .status-accepted, .status-completed, .status-paid {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .status-rejected, .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .booking-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-receipt {
            background: linear-gradient(135deg, var(--accent-blue), #0284c7);
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
            transition: all 0.2s;
        }

        .btn-receipt:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.5);
        }

        .empty-state {
            text-align: center;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 60px 20px;
            box-shadow: var(--shadow-3d);
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-browse {
            background: var(--accent-green);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
        }

        @media (max-width: 640px) {
            .booking-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .booking-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="user_dashboard.php" class="brand-logo">
            ⚡ EV Station Finder
        </a>
        <a href="user_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </header>

    <main class="main-container">
        <div class="page-header">
            <h1>My Bookings & Receipts</h1>
            <p>Track your active slot requests, status approvals, and download payment receipts.</p>
        </div>

        <div class="booking-list">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $status = strtolower($row['status'] ?? 'pending');
                    $statusClass = 'status-' . $status;
                    $reqId = $row['id'] ?? $row['request_id'] ?? '';
                ?>
                    <div class="booking-card">
                        <div class="booking-details">
                            <div class="station-name">
                                ⚡ <?php echo htmlspecialchars($row['bunk_name'] ?? 'EV Charging Bunk #' . ($row['bunk_id'] ?? '')); ?>
                            </div>
                            <div class="booking-meta">📍 <?php echo htmlspecialchars($row['address'] ?? 'Location details recorded'); ?></div>
                            <div class="booking-meta">📅 Date: <?php echo htmlspecialchars($row['booking_date'] ?? $row['date'] ?? 'N/A'); ?> | ⏰ Time: <?php echo htmlspecialchars($row['booking_time'] ?? $row['time'] ?? 'N/A'); ?></div>
                            <div class="booking-meta">💳 Amount: ₹<?php echo htmlspecialchars($row['amount'] ?? $row['cost'] ?? '0'); ?></div>
                        </div>

                        <div class="booking-actions">
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </span>

                            <?php if ($status === 'approved' || $status === 'accepted' || $status === 'completed' || $status === 'paid'): ?>
                                <a href="generate_receipt.php?booking_id=<?php echo $row['id']; ?>" class="btn-receipt"> 📄Download Receipt</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Bookings Found</h3>
                    <p>You haven't reserved any charging slots yet.</p>
                    <a href="find_bunk.php" class="btn-browse">Search Charging Bunks</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>