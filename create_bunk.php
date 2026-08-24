<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bunk') {
    header("Location: login.php?role=bunk");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $owner_id = $_SESSION['user_id'];
    $bunk_name = trim($_POST['bunk_name']);
    $area = trim($_POST['area']);
    $address = trim($_POST['address']);
    $battery_info = trim($_POST['battery_info']);
    $total_slots = intval($_POST['total_slots']);
    $free_slots = intval($_POST['free_slots']);

    $stmt = $conn->prepare("INSERT INTO bunks (owner_id, bunk_name, area, address, battery_info, total_slots, free_slots) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssii", $owner_id, $bunk_name, $area, $address, $battery_info, $total_slots, $free_slots);

    if ($stmt->execute()) {
        $bunk_id = $stmt->insert_id;
        $message = "Bunk created successfully! <a href='update_map.php?bunk_id=$bunk_id'>Set Map Location Now</a>";
    } else {
        $message = "Error creating bunk: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Bunk</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="form-card" style="width: 450px;">
    <h2>Add EV Bunk Station</h2>
    <?php if (!empty($message)) echo "<div class='success-banner'>$message</div>"; ?>

    <form method="POST" action="create_bunk.php">
        <div class="form-group">
            <label>Bunk Name</label>
            <input type="text" name="bunk_name" placeholder="e.g. Shell EV Fast Charge" required>
        </div>
        <div class="form-group">
            <label>Area / City</label>
            <input type="text" name="area" placeholder="e.g. Indiranagar, Bengaluru" required>
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="2" required></textarea>
        </div>
        <div class="form-group">
            <label>Available Battery / Charger Types</label>
            <input type="text" name="battery_info" placeholder="e.g. 50kW CCS2, Type 2 AC" required>
        </div>
        <div class="form-group">
            <label>Total Slots</label>
            <input type="number" name="total_slots" min="1" required>
        </div>
        <div class="form-group">
            <label>Free Slots Available Now</label>
            <input type="number" name="free_slots" min="0" required>
        </div>
        <button type="submit" class="btn-submit">Save Bunk Station</button>
    </form>
    <p style="text-align:center; margin-top:10px;"><a href="bunk_dashboard.php">Back to Dashboard</a></p>
</div>

</body>
</html>