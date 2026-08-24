<?php
session_start();
require_once 'db_connect.php';
require_once 'send_email.php';
require_once 'send_sms.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $bunk_id = intval($_POST['bunk_id']);
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $payment_id = $_POST['payment_id'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);

    // Fetch user details
    $u_stmt = $conn->prepare("SELECT name, email, mobile FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $user = $u_stmt->get_result()->fetch_assoc();
    
    if ($user && isset($user['mobile'])) {
        $user['phone'] = $user['mobile'];
    }

    // Fetch bunk details
    $b_stmt = $conn->prepare("SELECT bunk_name FROM bunks WHERE id = ?");
    $b_stmt->bind_param("i", $bunk_id);
    $b_stmt->execute();
    $bunk = $b_stmt->get_result()->fetch_assoc();

    // Check if payment ID is missing or empty
    if (empty($payment_id)) {
        if ($user && $bunk) {
            $subject = "Payment Failed - EV Recharge Network";
            $body = "Hi <b>{$user['name']}</b>,<br><br>Unfortunately, your payment and booking attempt for <b>{$bunk['bunk_name']}</b> on $booking_date at $booking_time has failed. Please try again.";
            sendEmail($user['email'], $subject, $body);

            if (!empty($user['phone']) && function_exists('sendSMSNotification')) {
                sendSMSNotification($user['phone'], $user['name'], $bunk['bunk_name'], "Payment Failed");
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'Payment failed or was canceled. Notification sent.']);
        exit();
    }

    // Insert booking with 'Pending' status so owner/admin can review it before approval
    $stmt = $conn->prepare("INSERT INTO bookings (user_id, bunk_id, booking_date, booking_time, status, payment_id, amount, payment_status) VALUES (?, ?, ?, ?, 'Pending', ?, ?, 'Paid')");
    $stmt->bind_param("iisssd", $user_id, $bunk_id, $booking_date, $booking_time, $payment_id, $amount);

    if ($stmt->execute()) {
        // Decrement free slot counter
        $conn->query("UPDATE bunks SET free_slots = GREATEST(0, free_slots - 1) WHERE id = $bunk_id");

        // Send Success Email Alert
        if ($user && $bunk) {
            if (function_exists('sendBookingConfirmation')) {
                sendBookingConfirmation(
                    $user['email'],
                    $user['name'],
                    $bunk['bunk_name'],
                    $booking_date,
                    $booking_time,
                    $payment_id,
                    $amount
                );
            } else {
                $subject = "Booking Received - EV Recharge Network";
                $body = "Hi <b>{$user['name']}</b>,<br><br>Your payment was successful! Your booking request at <b>{$bunk['bunk_name']}</b> for $booking_date at $booking_time is now pending owner approval. Payment ID: $payment_id";
                sendEmail($user['email'], $subject, $body);
            }

            // Send Success SMS Alert
            if (!empty($user['phone']) && function_exists('sendSMSNotification')) {
                sendSMSNotification(
                    $user['phone'],
                    $user['name'],
                    $bunk['bunk_name'],
                    $booking_date . ' ' . $booking_time
                );
            }
        }

        echo json_encode([
            'status' => 'success', 
            'message' => 'Payment Successful! Booking submitted and is awaiting owner approval.'
        ]);
    } else {
        error_log("Booking Insert SQL Error: " . $stmt->error);

        if ($user && $bunk) {
            $subject = "Booking Error - EV Recharge Network";
            $body = "Hi <b>{$user['name']}</b>,<br><br>Your payment went through, but we encountered an error saving your booking for <b>{$bunk['bunk_name']}</b>. Please contact support.";
            sendEmail($user['email'], $subject, $body);
        }

        echo json_encode(['status' => 'error', 'message' => 'Failed to process booking after payment. Support notified. Details: ' . $stmt->error]);
    }
    $stmt->close();
}
?>