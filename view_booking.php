<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bunk') {
    header("Location: login.php?role=bunk");
    exit();
}

$owner_id = $_SESSION['user_id'];

// Handle Status Updates
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $action = $_GET['action'] === 'approve' ? 'Approved' : 'Rejected';

    // Get Bunk ID associated with this booking
    $b_query = $conn->query("SELECT bunk_id FROM bookings WHERE id = $booking_id");
    $booking_data = $b_query->fetch_assoc();
    $bunk_id = $booking_data['bunk_id'];

    // Update status
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $action, $booking_id);
    $stmt->execute();

    // If approved, decrement free slot count by 1
    if ($action === 'Approved') {
        $conn->query("UPDATE bunks SET free_slots = GREATEST(0, free_slots - 1) WHERE id = $bunk_id");
    }

    header("Location: view_booking.php");
    exit();
}

$query = "SELECT b.id, u.name as user_name, u.mobile, k.bunk_name, b.booking_date, b.booking_time, b.status 
          FROM bookings b 
          JOIN users u ON b.user_id = u.id 
          JOIN bunks k ON b.bunk_id = k.id 
          WHERE k.owner_id = $owner_id 
          ORDER BY b.id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage User Bookings</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container { width: 90%; max-width: 900px; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #28a745; color: white; }
        .btn-approve { background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
        .btn-reject { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container">
    <h2>User Booking Requests</h2>

    <table>
        <thead>
            <tr>
                <th>User Name</th>
                <th>Mobile</th>
                <th>Bunk Name</th>
                <th>Date & Time</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                <td><?php echo htmlspecialchars($row['bunk_name']); ?></td>
                <td><?php echo $row['booking_date'] . " " . $row['booking_time']; ?></td>
                <td><strong><?php echo $row['status']; ?></strong></td>
                <td>
                    <?php if($row['status'] === 'Pending'): ?>
                        <a href="view_booking.php?action=approve&booking_id=<?php echo $row['id']; ?>" class="btn-approve">Approve</a>
                        <a href="view_booking.php?action=reject&booking_id=<?php echo $row['id']; ?>" class="btn-reject">Reject</a>
                    <?php else: ?>
                        Completed
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <p style="margin-top:15px;"><a href="bunk_dashboard.php">Back to Dashboard</a></p>
</div>

</body>
</html>