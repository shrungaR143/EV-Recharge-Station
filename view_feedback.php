<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

$owner_id = $_SESSION['user_id'];
$is_owner = ($_SESSION['role'] ?? '') === 'bunk_owner' || ($_SESSION['role'] ?? '') === 'owner';

// Query feedback joined with users and bunks
if ($is_owner) {
    $sql = "SELECT f.*, u.name as user_name, b.bunk_name 
            FROM feedback f 
            JOIN bunks b ON f.bunk_id = b.id 
            LEFT JOIN users u ON f.user_id = u.id 
            WHERE b.owner_id = ? 
            ORDER BY f.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Admin or general list fallback
    $sql = "SELECT f.*, u.name as user_name, b.bunk_name 
            FROM feedback f 
            JOIN bunks b ON f.bunk_id = b.id 
            LEFT JOIN users u ON f.user_id = u.id 
            ORDER BY f.id DESC";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Station Reviews - EV Network</title>
    
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
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .main-container {
            width: 90%;
            max-width: 1000px;
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

        .feedback-grid {
            display: grid;
            gap: 20px;
        }

        .feedback-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-3d);
            transition: transform 0.2s;
        }

        .feedback-card:hover {
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .reviewer-name {
            font-weight: 700;
            font-size: 16px;
            color: #ffffff;
        }

        .bunk-badge {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .stars {
            color: #f59e0b;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .feedback-text {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .feedback-date {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 50px 20px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="javascript:history.back()" class="brand-logo">⚡ EV Station Feedback</a>
        <a href="javascript:history.back()" class="btn-back">← Back</a>
    </header>

    <main class="main-container">
        <div class="page-header">
            <h1>User Feedback & Reviews</h1>
            <p>Customer feedback and ratings for your charging stations.</p>
        </div>

        <div class="feedback-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $rating = (int)($row['rating'] ?? 5);
                    $comment = $row['comments'] ?? $row['feedback'] ?? '';
                    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                ?>
                    <div class="feedback-card">
                        <div class="card-header">
                            <div class="reviewer-name">👤 <?php echo htmlspecialchars($row['user_name'] ?? 'Driver'); ?></div>
                            <span class="bunk-badge">⚡ <?php echo htmlspecialchars($row['bunk_name'] ?? 'Charging Bunk'); ?></span>
                        </div>
                        <div class="stars"><?php echo $stars; ?> (<?php echo $rating; ?>/5)</div>
                        <p class="feedback-text">"<?php echo htmlspecialchars($comment); ?>"</p>
                        <div class="feedback-date">📅 Submitted: <?php echo htmlspecialchars($row['created_at'] ?? 'Recently'); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Reviews Found</h3>
                    <p>There are no feedback submissions for your stations yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>