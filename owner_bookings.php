<?php
session_start();
require_once 'db_connect.php';

// Allow 'bunk', 'owner', or 'admin' roles to prevent login redirection loops
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['bunk', 'owner', 'admin'])) {
    header("Location: login.php?role=bunk");
    exit();
}

$owner_id = $_SESSION['user_id'];

// Handle Actions: Approve, Reject, Complete Session
if (isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id']);
    $bunk_id = intval($_POST['bunk_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Approved' WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['alert_msg'] = "Booking approved successfully!";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['alert_msg'] = "Unable to approve booking (already processed or invalid).";
            $_SESSION['alert_type'] = "warning";
        }
        $stmt->close();
    } 
    elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'Rejected' WHERE id = ? AND status = 'Pending'");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $conn->query("UPDATE bunks SET free_slots = free_slots + 1 WHERE id = $bunk_id");
            $_SESSION['alert_msg'] = "Booking rejected and slot restored.";
            $_SESSION['alert_type'] = "warning";
        } else {
            $_SESSION['alert_msg'] = "Unable to reject booking.";
            $_SESSION['alert_type'] = "warning";
        }
        $stmt->close();
    }
    elseif ($action === 'complete_session') {
        $update_stmt = $conn->prepare("UPDATE bookings SET status = 'Completed' WHERE id = ? AND status = 'Approved'");
        $update_stmt->bind_param("i", $booking_id);
        
        if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
            $conn->query("UPDATE bunks SET free_slots = free_slots + 1 WHERE id = $bunk_id");
            $_SESSION['alert_msg'] = "Charging session marked as Completed! 1 slot freed up.";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['alert_msg'] = "Session must be approved before completion or is already completed.";
            $_SESSION['alert_type'] = "warning";
        }
        $update_stmt->close();
    }
    
    header("Location: owner_bookings.php");
    exit();
}

// Fetch bookings belonging ONLY to stations owned by this logged-in manager
$query = "SELECT b.*, 
                 k.bunk_name, k.area, k.address,
                 u.name AS user_name, u.email AS user_email
          FROM bookings b
          INNER JOIN bunks k ON b.bunk_id = k.id
          LEFT JOIN users u ON b.user_id = u.id
          WHERE k.owner_id = ?
          ORDER BY b.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Bookings Dashboard - EV Bunk Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: #1e293b;
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-green: #10b981;
            --accent-blue: #0ea5e9;
            --accent-red: #ef4444;
            --accent-yellow: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation Header matching screenshot */
        .navbar {
            background: var(--bg-main);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
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
            font-weight: 500;
            border: 1px solid var(--card-border);
            transition: background 0.2s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        /* Main Container matching the screenshot card box */
        .container {
            width: 92%;
            max-width: 1350px;
            margin: 10px auto 40px auto;
            background: var(--card-bg);
            padding: 35px;
            border-radius: 14px;
            border: 1px solid var(--card-border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            flex: 1;
        }

        .container h2 {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .container > p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 25px;
        }

        /* Alerts */
        .alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13.5px;
            border: 1px solid var(--card-border);
        }
        .alert-success { background-color: rgba(16, 185, 129, 0.15); color: #34d399; }
        .alert-warning { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }

        /* Table Design matching exact reference style */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 14px 16px;
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
        }

        th {
            background-color: var(--accent-green);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border: none;
        }

        tbody tr {
            background-color: rgba(15, 23, 42, 0.3);
            transition: background 0.2s;
        }

        tbody tr:hover {
            background-color: rgba(15, 23, 42, 0.6);
        }

        td {
            color: var(--text-secondary);
        }

        td strong {
            color: var(--text-primary);
        }

        /* Professional Status Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }
        .badge-pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .badge-approved {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-completed {
            background-color: rgba(14, 165, 233, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(14, 165, 233, 0.3);
        }
        .badge-rejected {
            background-color: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Action Buttons */
        .action-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-action {
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s;
        }

        .btn-approve {
            background-color: var(--accent-green);
            color: white;
        }
        .btn-approve:hover { opacity: 0.9; }

        .btn-reject {
            background-color: var(--accent-red);
            color: white;
        }
        .btn-reject:hover { opacity: 0.9; }

        .btn-complete {
            background-color: var(--accent-blue);
            color: white;
        }
        .btn-complete:hover { opacity: 0.9; }

        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 15px;
            }
            .navbar {
                padding: 15px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <header class="navbar">
        <a href="bunk_dashboard.php" class="brand-logo">⚡ EV Bunk Portal</a>
        <a href="bunk_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </header>

    <div class="container">
        <h2>Station Bookings Management</h2>
        <p>Review customer reservations, approve or reject incoming requests, and mark active charging sessions as completed.</p>

        <!-- Session Flash Banners -->
        <?php if (isset($_SESSION['alert_msg'])): ?>
            <div class="alert alert-<?php echo $_SESSION['alert_type']; ?>">
                <?php 
                    echo htmlspecialchars($_SESSION['alert_msg']); 
                    unset($_SESSION['alert_msg']); 
                    unset($_SESSION['alert_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Station Name</th>
                        <th>Customer Details</th>
                        <th>Scheduled Slot</th>
                        <th>Payment ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action Controls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#EV-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['bunk_name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['area']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['user_name'] ?? 'User'); ?></strong><br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['user_email'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    📅 <?php echo htmlspecialchars($row['booking_date']); ?><br>
                                    ⏰ <?php echo htmlspecialchars($row['booking_time']); ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['payment_id'] ?? 'N/A'); ?></code></td>
                                <td>₹<?php echo htmlspecialchars(number_format($row['amount'] ?? 100, 2)); ?></td>
                                <td>
                                    <?php 
                                        $status = strtolower($row['status'] ?? 'pending');
                                        $badgeClass = match($status) {
                                            'approved' => 'badge-approved',
                                            'completed' => 'badge-completed',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending'
                                        };
                                        $displayStatus = ucfirst($row['status'] ?? 'Pending');
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($displayStatus); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <?php if ($status === 'pending'): ?>
                                            <!-- Approve Form -->
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="bunk_id" value="<?php echo $row['bunk_id']; ?>">
                                                <button type="submit" class="btn-action btn-approve" title="Approve booking">Approve</button>
                                            </form>
                                            <!-- Reject Form -->
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to reject this booking?');">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="bunk_id" value="<?php echo $row['bunk_id']; ?>">
                                                <button type="submit" class="btn-action btn-reject" title="Reject booking">Reject</button>
                                            </form>

                                        <?php elseif ($status === 'approved'): ?>
                                            <!-- Complete Session Form -->
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Mark this charging session as finished? This will free up 1 slot at your station.');">
                                                <input type="hidden" name="action" value="complete_session">
                                                <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="bunk_id" value="<?php echo $row['bunk_id']; ?>">
                                                <button type="submit" class="btn-action btn-complete">Complete</button>
                                            </form>

                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 13px;">No action needed</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                No bookings found for your station(s) yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>