<?php
session_start();
require_once 'db_connect.php';

// Enable error reporting for database debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $status = 'Pending'; // Default ticket status

    if (!empty($subject) && !empty($message)) {
        try {
            $stmt = $conn->prepare("INSERT INTO contact_support (user_id, subject, message, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isss", $user_id, $subject, $message, $status);
            
            if ($stmt->execute()) {
                $stmt->close();
                // Redirect back to dashboard opening the contact modal with success message
                header("Location: user_dashboard.php?msg=sent");
                exit();
            }
            $stmt->close();
        } catch (Exception $e) {
            die("Database Error: " . $e->getMessage());
        }
    } else {
        header("Location: user_dashboard.php?error=empty");
        exit();
    }
} else {
    // If accessed directly via URL, send them back to the dashboard
    header("Location: user_dashboard.php");
    exit();
}
?>