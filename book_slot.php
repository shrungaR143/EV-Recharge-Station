<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?role=user");
    exit();
}

$bunk_id = isset($_GET['bunk_id']) ? intval($_GET['bunk_id']) : 0;
$user_id = $_SESSION['user_id'];
$message = "";

// Fetch Bunk details
$bunk_stmt = $conn->prepare("SELECT * FROM bunks WHERE id = ?");
$bunk_stmt->bind_param("i", $bunk_id);
$bunk_stmt->execute();
$bunk = $bunk_stmt->get_result()->fetch_assoc();

// Fetch User details for Razorpay checkout prefill
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Fixed slot reservation fee (e.g., ₹100)
$booking_fee = 100.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book & Pay - EV Recharge Slot</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Razorpay Checkout JS SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.85);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-green: #10b981;
            --accent-green-glow: rgba(16, 185, 129, 0.35);
            --accent-blue: #0ea5e9;
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
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 35px;
            width: 100%;
            max-width: 440px;
            box-shadow: var(--shadow-3d);
        }

        .form-card h2 {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
            text-align: center;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-blue);
            text-align: center;
            margin-bottom: 16px;
        }

        .station-meta {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--card-border);
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13.5px;
        }

        .station-meta span {
            color: var(--text-secondary);
        }

        .station-meta strong {
            color: var(--text-primary);
        }

        .fee-badge {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: var(--accent-green);
            margin-bottom: 20px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 8px;
            border-radius: 8px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 12px var(--accent-green-glow);
        }

        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            margin-top: 5px;
        }

        .btn-submit:hover:not(:disabled) {
            box-shadow: 0 0 16px var(--accent-green-glow);
            transform: translateY(-1px);
        }

        .btn-submit:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            cursor: not-allowed;
            box-shadow: none;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--text-primary);
        }

        .success-banner {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--accent-green);
            color: var(--accent-green);
            padding: 10px;
            border-radius: 8px;
            font-size: 13.5px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="form-card">
    <h2>Book Charging Slot</h2>
    <h3><?php echo htmlspecialchars($bunk['bunk_name'] ?? ''); ?></h3>
    
    <div class="station-meta">
        <span>Available Free Slots:</span>
        <strong><?php echo $bunk['free_slots'] ?? 0; ?></strong>
    </div>

    <div class="fee-badge">
        Reservation Fee: ₹<?php echo number_format($booking_fee, 2); ?>
    </div>

    <div id="alert-msg"></div>

    <!-- Removed standard form submit behavior to handle via JS cleanly -->
    <div id="bookingForm">
        <input type="hidden" id="bunk_id" value="<?php echo $bunk_id; ?>">
        <input type="hidden" id="amount" value="<?php echo $booking_fee; ?>">

        <div class="form-group">
            <label>Booking Date</label>
            <input type="date" id="booking_date" required min="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="form-group">
            <label>Booking Time</label>
            <input type="time" id="booking_time" required>
        </div>

        <?php if (($bunk['free_slots'] ?? 0) > 0): ?>
            <button type="button" id="payButton" class="btn-submit">Proceed to Pay ₹100 & Book</button>
        <?php else: ?>
            <button type="button" disabled class="btn-submit">No Slots Available</button>
        <?php endif; ?>
    </div>
    
    <a href="find_bunk.php" class="back-link">← Back to Stations</a>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const payBtn = document.getElementById('payButton');
    if (payBtn) {
        payBtn.addEventListener('click', payAndBook);
    }
});

function payAndBook() {
    const bookingDate = document.getElementById('booking_date').value;
    const bookingTime = document.getElementById('booking_time').value;

    if (!bookingDate || !bookingTime) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Details',
            text: 'Please select both booking date and time first!',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#10b981'
        });
        return;
    }

    // Check if Razorpay SDK loaded properly
    if (typeof Razorpay === 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'SDK Error',
            text: 'Razorpay payment gateway failed to load. Please check your internet connection or disable ad-blockers.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#ef4444'
        });
        return;
    }

    // Razorpay Configuration
    var options = {
        "key": "rzp_test_TJhgpPCbNxfz6v", 
        "amount": <?php echo intval($booking_fee * 100); ?>, 
        "currency": "INR",
        "name": "EV Recharge Network",
        "description": "Slot Booking Fee - <?php echo htmlspecialchars($bunk['bunk_name'] ?? ''); ?>",
        "handler": function (response) {
            verifyAndSaveBooking(response.razorpay_payment_id, bookingDate, bookingTime);
        },
        "prefill": {
            "name": "<?php echo htmlspecialchars($user['name'] ?? ''); ?>",
            "email": "<?php echo htmlspecialchars($user['email'] ?? ''); ?>",
            "contact": "<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>"
        },
        "theme": {
            "color": "#10b981"
        }
    };

    try {
        var rzp1 = new Razorpay(options);
        rzp1.open();
    } catch (error) {
        console.error("Razorpay error:", error);
    }
}

function verifyAndSaveBooking(paymentId, date, time) {
    const bunkId = document.getElementById('bunk_id').value;
    const amount = document.getElementById('amount').value;

    fetch('process_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            'bunk_id': bunkId,
            'booking_date': date,
            'booking_time': time,
            'payment_id': paymentId,
            'amount': amount
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('alert-msg').innerHTML = `<div class='success-banner'>${data.message}</div>`;
            setTimeout(() => { window.location.href = 'my_requests.php'; }, 2000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Booking Failed',
                text: data.message,
                background: '#1e293b',
                color: '#f8fafc',
                confirmButtonColor: '#ef4444'
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while saving your booking. Please try again.',
            background: '#1e293b',
            color: '#f8fafc',
            confirmButtonColor: '#ef4444'
        });
    });
}
</script>

</body>
</html>