<?php
session_start();

require_once 'db_connect.php';
require_once 'send_email.php';
require_once 'send_sms.php';   // Include your SMS script

$message = "";
$error = "";
$role = $_GET['role'] ?? $_POST['role'] ?? 'bunk_owner';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $mobile = trim($_POST['mobile']);
    $city = trim($_POST['city']);
    $address = trim($_POST['address']);

    // Server-Side Password Regex Validation
    $pattern = "/^(?=.*\d)(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]).{6,}$/";

    if (!preg_match($pattern, $password)) {
        $message = "Invalid password format. Must meet strength criteria.";
    } else {
        // Secure Password Hashing
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("INSERT INTO bunk_owners (name, email, password, mobile, city, address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $hashed_password, $mobile, $city, $address);

        if ($stmt->execute()) {
            // 1. Send Welcome Email upon successful bunk owner registration
            $subject = "Welcome to EV Recharge Network - Station Owner Portal";
            $body = "Hi <b>$name</b>,<br><br>Your Bunk Owner account has been successfully created! You can now log in to manage your charging station.";
            sendEmail($email, $subject, $body);

            // 2. Send Welcome SMS upon successful bunk owner registration
            if (!empty($mobile) && function_exists('sendSMSNotification')) {
                sendSMSNotification($mobile, $name, "EV Recharge Network", "Station Owner Registration");
            }

            $message = "Bunk Registered Successfully! <a href='bunk_login.php'>Login Here</a>";
        } else {
            $message = "Error: Email might already exist.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bunk Manager Registration</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/validation.js"></script>
</head>
<body>

<div class="form-card">
    <h2>Bunk Owner Registration</h2>
    <?php if(!empty($message)) echo "<div class='success-banner'>$message</div>"; ?>

    <form method="POST" action="bunk_register.php" onsubmit="return validateRegistrationForm()">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" id="password" name="password" required>
            <div id="password-error" class="error-msg"></div>
        </div>
        <div class="form-group">
            <label>Mobile Number</label>
            <input type="text" name="mobile" required>
        </div>
        <div class="form-group">
            <label>City</label>
            <input type="text" name="city" required>
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn-submit">Register Bunk</button>
    </form>
</div>

</body>
</html>