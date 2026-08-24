<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?role=user");
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

$message = "";
$error = "";

// Fetch station options
$bunks_result = $conn->query("SELECT id, bunk_name, area FROM bunks ORDER BY bunk_name ASC");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $bunk_id = $_POST['bunk_id'] ?? 0;
    $rating = $_POST['rating'] ?? 5;
    $comments = trim($_POST['comments'] ?? '');

    if (empty($bunk_id) || empty($comments)) {
        $error = "Please select a charging station and write your feedback.";
    } else {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, bunk_id, rating, comments, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iiis", $user_id, $bunk_id, $rating, $comments);
            if ($stmt->execute()) {
                $message = "Thank you! Your feedback has been posted successfully.";
            } else {
                $error = "Failed to post feedback. Please try again.";
            }
            $stmt->close();
        } else {
            // Fallback for alternate column name 'feedback'
            $stmt = $conn->prepare("INSERT INTO feedback (user_id, bunk_id, rating, feedback) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiis", $user_id, $bunk_id, $rating, $comments);
                if ($stmt->execute()) {
                    $message = "Thank you! Your feedback has been posted successfully.";
                } else {
                    $error = "Failed to post feedback. Please try again.";
                }
                $stmt->close();
            } else {
                $error = "Database error. Please check table schema.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Feedback - EV Network</title>
    
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
            --accent-green-glow: rgba(16, 185, 129, 0.35);
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
            max-width: 600px;
            margin: 40px auto;
            flex: 1;
        }

        .card-form {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--shadow-3d);
        }

        .page-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 24px;
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

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--accent-green);
        }

        select.form-control option {
            background: #1e293b;
            color: #ffffff;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 110px;
        }

        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 28px;
            color: #475569;
            cursor: pointer;
            transition: color 0.2s;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #f59e0b;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            box-shadow: 0 0 15px var(--accent-green-glow);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="user_dashboard.php" class="brand-logo">⚡ EV Station Finder</a>
        <a href="user_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </header>

    <main class="main-container">
        <div class="card-form">
            <div class="page-header">
                <h1>Post Feedback</h1>
                <p>Rate charging speed, cleanliness, and overall station experience.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="post_feedback.php" method="POST">
                <div class="form-group">
                    <label class="form-label" for="bunk_id">Select Charging Station</label>
                    <select name="bunk_id" id="bunk_id" class="form-control" required>
                        <option value="">-- Select a Charging Bunk --</option>
                        <?php if ($bunks_result && $bunks_result->num_rows > 0): ?>
                            <?php while ($bunk = $bunks_result->fetch_assoc()): ?>
                                <option value="<?php echo $bunk['id']; ?>">
                                    <?php echo htmlspecialchars($bunk['bunk_name']); ?> (<?php echo htmlspecialchars($bunk['city'] ?? 'Local'); ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" checked><label for="star5">★</label>
                        <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                        <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                        <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                        <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="comments">Your Feedback / Review</label>
                    <textarea name="comments" id="comments" class="form-control" placeholder="Write your review here..." required></textarea>
                </div>

                <button type="submit" class="btn-submit">Submit Feedback</button>
            </form>
        </div>
    </main>

</body>
</html>