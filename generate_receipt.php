<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?role=user");
    exit();
}

if (!isset($_GET['booking_id'])) {
    die("Invalid request. Booking ID missing.");
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

// Fetch booking details along with user & station info (excluding missing u.phone)
$query = "SELECT b.*, 
                 COALESCE(k.bunk_name, 'Station Unavailable') AS bunk_name, 
                 COALESCE(k.address, '-') AS address, 
                 COALESCE(k.area, '-') AS area, 
                 k.battery_info, 
                 u.name as user_name, 
                 u.email as user_email 
          FROM bookings b 
          LEFT JOIN bunks k ON b.bunk_id = k.id 
          LEFT JOIN users u ON b.user_id = u.id 
          WHERE b.id = ? AND b.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Booking record not found or access denied.");
}

$booking = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Receipt #<?php echo htmlspecialchars($booking['id']); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 40px 0;
            color: #333;
        }
        .receipt-card {
            background: #ffffff;
            max-width: 650px;
            margin: 0 auto;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #e1e8ed;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .brand {
            font-size: 22px;
            font-weight: bold;
            color: #007bff;
        }
        .receipt-title {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .details-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .details-grid td {
            padding: 10px 5px;
            border-bottom: 1px solid #f1f1f1;
            font-size: 14px;
        }
        .details-grid td.label {
            color: #6c757d;
            font-weight: 600;
            width: 40%;
        }
        .details-grid td.value {
            color: #212529;
            font-weight: 500;
        }
        .summary-box {
            background: #eef6ff;
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .summary-box .amount-title {
            font-size: 15px;
            color: #0056b3;
            font-weight: 600;
        }
        .summary-box .amount-value {
            font-size: 22px;
            color: #0056b3;
            font-weight: bold;
        }
        .footer-note {
            text-align: center;
            font-size: 12px;
            color: #8c98a4;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .action-buttons {
            text-align: center;
            margin-top: 25px;
        }
        .btn {
            padding: 10px 22px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            display: inline-block;
            margin: 0 5px;
        }
        .btn-print {
            background-color: #007bff;
            color: white;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
        }

        /* Hide buttons when saving to PDF */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt-card {
                box-shadow: none;
                border: none;
                max-width: 100%;
                padding: 0;
            }
            .action-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="receipt-card">
    <div class="header-section">
        <div>
            <div class="brand">⚡ EV Recharge Network</div>
            <div style="font-size: 12px; color: #555; margin-top: 4px;">Charging Slot Booking Receipt</div>
        </div>
        <div class="receipt-title">
            <div><strong>Receipt No:</strong> #EV-<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <div><strong>Date:</strong> <?php echo date('d M Y'); ?></div>
        </div>
    </div>

    <table class="details-grid">
        <tr>
            <td class="label">Customer Name:</td>
            <td class="value"><?php echo htmlspecialchars($booking['user_name'] ?? 'User'); ?></td>
        </tr>
        <tr>
            <td class="label">Customer Email:</td>
            <td class="value"><?php echo htmlspecialchars($booking['user_email'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <td class="label">Station Name:</td>
            <td class="value"><strong><?php echo htmlspecialchars($booking['bunk_name']); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Station Location:</td>
            <td class="value"><?php echo htmlspecialchars($booking['address'] . ', ' . $booking['area']); ?></td>
        </tr>
        <tr>
            <td class="label">Charger Details:</td>
            <td class="value"><?php echo htmlspecialchars($booking['battery_info'] ?? 'Standard EV Fast Charging'); ?></td>
        </tr>
        <tr>
            <td class="label">Scheduled Slot:</td>
            <td class="value">📅 <?php echo htmlspecialchars($booking['booking_date']); ?> | ⏰ <?php echo htmlspecialchars($booking['booking_time']); ?></td>
        </tr>
        <tr>
            <td class="label">Payment Transaction ID:</td>
            <td class="value"><code><?php echo htmlspecialchars($booking['payment_id'] ?? 'N/A'); ?></code></td>
        </tr>
        <tr>
            <td class="label">Booking & Payment Status:</td>
            <td class="value">
                <span class="badge-success"><?php echo htmlspecialchars($booking['status']); ?> & <?php echo htmlspecialchars($booking['payment_status'] ?? 'Paid'); ?></span>
            </td>
        </tr>
    </table>

    <div class="summary-box">
        <div class="amount-title">Total Amount Paid</div>
        <div class="amount-value">₹<?php echo htmlspecialchars(number_format($booking['amount'] ?? 100, 2)); ?></div>
    </div>

    <div class="footer-note">
        Thank you for choosing EV Recharge Network. Please show this receipt at the charging station upon arrival.<br>
        This is a computer-generated receipt and requires no physical signature.
    </div>

    <div class="action-buttons">
        <button onclick="window.print()" class="btn btn-print">📄 Save as PDF / Print</button>
        <a href="my_requests.php" class="btn btn-back">Back to My Requests</a>
    </div>
</div>

</body>
</html>