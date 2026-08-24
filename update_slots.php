<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bunk') {
    header("Location: login.php?role=bunk");
    exit();
}

$owner_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bunk_id = intval($_POST['bunk_id']);
    $total_slots = intval($_POST['total_slots']);
    $free_slots = intval($_POST['free_slots']);

    $stmt = $conn->prepare("UPDATE bunks SET total_slots = ?, free_slots = ? WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("iiii", $total_slots, $free_slots, $bunk_id, $owner_id);

    if ($stmt->execute()) {
        $message = "Slots updated successfully!";
    } else {
        $message = "Failed to update slots.";
    }
    $stmt->close();
}

$bunk_query = $conn->query("SELECT * FROM bunks WHERE owner_id = $owner_id");
$bunks = $bunk_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bunk Slots</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="form-card" style="width: 450px;">
    <h2>Update Charging Slots</h2>
    <?php if (!empty($message)) echo "<div class='success-banner'>$message</div>"; ?>

    <?php if (count($bunks) > 0): ?>
    <form method="POST" action="update_slots.php">
        <div class="form-group">
            <label>Select Bunk</label>
            <select name="bunk_id" style="width:100%; padding:8px;" required>
                <?php foreach ($bunks as $b): ?>
                    <option value="<?php echo $b['id']; ?>">
                        <?php echo htmlspecialchars($b['bunk_name']); ?> (Current Free: <?php echo $b['free_slots']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Total Slots Capacity</label>
            <input type="number" name="total_slots" min="1" required>
        </div>

        <div class="form-group">
            <label>Available Free Slots</label>
            <input type="number" name="free_slots" min="0" required>
        </div>

        <button type="submit" class="btn-submit">Update Slot Status</button>
    </form>
    <?php else: ?>
        <p>No bunks found. <a href="create_bunk.php">Add a bunk first</a>.</p>
    <?php endif; ?>
    <p style="text-align:center; margin-top:10px;"><a href="bunk_dashboard.php">Back to Dashboard</a></p>
</div>

</body>
</html>