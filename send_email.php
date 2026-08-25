<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@gmail.com'; // Updated with your actual email
        $mail->Password   = 'password';     // Updated with your actual App Password (spaces removed)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your_email@gmail.com', 'EV Recharge Network');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendBookingConfirmation($toEmail, $userName, $bunkName, $bookingDate, $bookingTime, $paymentId, $amount) {
    $mail = new PHPMailer(true);

    try {
        // Server configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@gmail.com'; 
        $mail->Password   = 'password'; // Spaces removed
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('your_email@gmail.com', 'EV Recharge Network');
        $mail->addAddress($toEmail, $userName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Booking Confirmed - EV Charging Station Slot';
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 500px;'>
                <h2 style='color: #28a745;'>Booking & Payment Confirmed!</h2>
                <p>Hello <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p>Your EV charging slot reservation is confirmed. Here are your booking details:</p>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr><td><strong>Station Name:</strong></td><td>" . htmlspecialchars($bunkName) . "</td></tr>
                    <tr><td><strong>Date:</strong></td><td>" . htmlspecialchars($bookingDate) . "</td></tr>
                    <tr><td><strong>Time:</strong></td><td>" . htmlspecialchars($bookingTime) . "</td></tr>
                    <tr><td><strong>Payment ID:</strong></td><td>" . htmlspecialchars($paymentId) . "</td></tr>
                    <tr><td><strong>Amount Paid:</strong></td><td>₹" . htmlspecialchars($amount) . "</td></tr>
                </table>
                <br>
                <p style='color: #666; font-size: 13px;'>Thank you for using EV Recharge Network!</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
