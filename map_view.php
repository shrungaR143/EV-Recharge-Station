<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php?role=user");
    exit();
}

// Database Connection (Adjust database credentials if needed)
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "ev_recharge_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle New Bunk Creation (Automatically geocoding via Nominatim/OSM or accepting input coordinates)
$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bunk'])) {
    $bunk_name = trim($_POST['bunk_name']);
    $address = trim($_POST['address']);
    $latitude = trim($_POST['latitude']);
    $longitude = trim($_POST['longitude']);

    // If latitude/longitude are empty, try to auto-fetch coordinates using OpenStreetMap Nominatim based on the address
    if (empty($latitude) || empty($longitude)) {
        $encoded_address = urlencode($address);
        $nominatim_url = "https://nominatim.openstreetmap.org/search?q={$encoded_address}&format=json&limit=1";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $nominatim_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'EVRechargeApp/1.0');
        $output = curl_exec($ch);
        curl_close($ch);

        $geo_data = json_decode($output, true);
        if (!empty($geo_data)) {
            $latitude = $geo_data[0]['lat'];
            $longitude = $geo_data[0]['lon'];
        }
    }

    if (!empty($bunk_name) && !empty($address) && !empty($latitude) && !empty($longitude)) {
        // Check if bunk_owners table has a matching record for the current user, or insert one dynamically using standard user columns
        $current_user_id = $_SESSION['user_id'];
        $check_owner = $conn->prepare("SELECT id FROM bunk_owners WHERE id = ?");
        $check_owner->bind_param("i", $current_user_id);
        $check_owner->execute();
        $owner_result = $check_owner->get_result();
        
        if ($owner_result->num_rows === 0) {
            // Automatically seed/register the current user into bunk_owners using dynamic column selection fallback
            $user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $user_query->bind_param("i", $current_user_id);
            $user_query->execute();
            $user_data = $user_query->get_result()->fetch_assoc();
            $user_query->close();

            if ($user_data) {
                $name_val = $user_data['name'] ?? ($user_data['full_name'] ?? ($user_data['user_name'] ?? 'Owner'));
                $email_val = $user_data['email'] ?? '';

                $insert_owner = $conn->prepare("INSERT INTO bunk_owners (id, name, email) VALUES (?, ?, ?)");
                $insert_owner->bind_param("iss", $current_user_id, $name_val, $email_val);
                @$insert_owner->execute();
                $insert_owner->close();
            }
        }
        $check_owner->close();

        // Insert including owner_id set to current session user_id
        $stmt = $conn->prepare("INSERT INTO bunks (bunk_name, address, latitude, longitude, owner_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddi", $bunk_name, $address, $latitude, $longitude, $current_user_id);
        if ($stmt->execute()) {
            $success_msg = "New charging bunk added and pinned successfully!";
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error_msg = "Please provide valid address details or coordinates so the map can pin it automatically.";
    }
}

// Fetch charging bunks/stations with valid coordinates
$sql = "SELECT * FROM bunks WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$result = $conn->query($sql);

$stations = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Station Map - EV Network</title>
    
    <!-- Google Fonts & Leaflet OpenStreetMap CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.9);
            --card-border: rgba(255, 255, 255, 0.12);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-green: #10b981;
            --accent-blue: #0ea5e9;
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

        /* Top Header Navigation */
        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
            color: var(--accent-green);
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-action {
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background: rgba(16, 185, 129, 0.25);
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
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Main Section Container */
        .main-container {
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .map-header h1 {
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .map-header p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Live Leaflet Map Canvas Frame */
        #map {
            width: 100%;
            height: 580px;
            border-radius: 16px;
            border: 1px solid var(--card-border);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        /* Modal Overlay for Adding New Bunks */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            display: flex; justify-content: center; align-items: center;
            z-index: 2000; opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content {
            background: rgba(30, 41, 59, 0.98);
            border: 1px solid var(--card-border);
            border-radius: 16px; padding: 30px; width: 90%; max-width: 480px;
            box-shadow: 0 20px 25px rgba(0,0,0,0.5); position: relative;
        }
        .modal-close {
            position: absolute; top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.08); border: none;
            color: var(--text-secondary); font-size: 18px; width: 32px; height: 32px;
            border-radius: 50%; cursor: pointer; display: flex; justify-content: center; align-items: center;
        }
        .modal-content h3 { font-size: 20px; font-weight: 700; margin-bottom: 6px; color: #fff; }
        .modal-content p { font-size: 13px; color: var(--text-secondary); margin-bottom: 16px; }

        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; }
        .form-group input, .form-group textarea {
            width: 100%; background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--card-border); border-radius: 8px;
            padding: 10px 14px; color: var(--text-primary); font-size: 13.5px; outline: none;
        }
        .form-group textarea { height: 75px; resize: vertical; }
        .btn-submit {
            width: 100%; background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white; border: none; padding: 12px; border-radius: 8px;
            font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 6px;
        }

        .alert { padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* Customizing Leaflet Map Popup Card */
        .leaflet-popup-content-wrapper {
            background: #1e293b !important;
            color: #f8fafc !important;
            border-radius: 12px !important;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 4px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }

        .leaflet-popup-tip { background: #1e293b !important; }
        .popup-card { padding: 8px; }
        .popup-card h3 { font-size: 16px; color: #10b981; margin-bottom: 6px; }
        .popup-card p { font-size: 12px; color: #94a3b8; margin-bottom: 8px; line-height: 1.4; }
        .popup-badge {
            display: inline-block; background: rgba(16, 185, 129, 0.2);
            color: #10b981; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-bottom: 12px;
        }
        .popup-actions { display: flex; gap: 8px; }
        .btn-pop {
            display: inline-block; text-align: center; padding: 6px 12px;
            border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; flex: 1;
        }
        .btn-directions { background: #0ea5e9; color: #ffffff; }
        .btn-book { background: #10b981; color: #ffffff; }

        @media (max-width: 768px) { #map { height: 450px; } }
    </style>
</head>
<body>

    <header class="navbar">
        <a href="user_dashboard.php" class="brand-logo">⚡ EV Station Finder</a>
        <div class="nav-actions">
            <button class="btn-action" onclick="openModal()">+ Add New Bunk</button>
            <a href="user_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </header>

    <main class="main-container">
        <div class="map-header">
            <h1>Nearby EV Charging Map</h1>
            <p>Newly added stations are automatically pinned here in real-time. Click any pin for details, directions, or booking.</p>
        </div>

        <?php if (!empty($success_msg)) echo "<div class='alert alert-success' style='max-width:400px;margin:0 auto;'>$success_msg</div>"; ?>
        <?php if (!empty($error_msg)) echo "<div class='alert alert-error' style='max-width:400px;margin:0 auto;'>$error_msg</div>"; ?>

        <div id="map"></div>
    </main>

    <!-- Modal for Adding New Bunk / Station -->
    <div id="addBunkModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3>Add New Charging Bunk</h3>
            <p>Enter details below to pin the station instantly on the map.</p>
            
            <form method="POST">
                <input type="hidden" name="add_bunk" value="1">
                <div class="form-group">
                    <label>Station / Bunk Name</label>
                    <input type="text" name="bunk_name" placeholder="e.g. EcoCharge Hub - MG Road" required>
                </div>
                <div class="form-group">
                    <label>Address / Location Details</label>
                    <textarea name="address" placeholder="e.g. Near Central Mall, Bangalore" required></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Latitude (Optional)</label>
                        <input type="text" name="latitude" placeholder="Auto if address given">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Longitude (Optional)</label>
                        <input type="text" name="longitude" placeholder="Auto if address given">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Save & Pin on Map</button>
            </form>
        </div>
    </div>

    <!-- Leaflet JS Map Library -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Convert PHP DB stations array into JavaScript object
        const stations = <?php echo json_encode($stations); ?>;

        // Default map center (Fallback: Bengaluru coordinates)
        let defaultLat = 12.9716;
        let defaultLng = 77.5946;

        if (stations.length > 0 && stations[0].latitude && stations[0].longitude) {
            defaultLat = parseFloat(stations[0].latitude);
            defaultLng = parseFloat(stations[0].longitude);
        }

        // Initialize Map
        const map = L.map('map').setView([defaultLat, defaultLng], 12);

        // Add OpenStreetMap Tile Layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Plot pins for each station found in database
        if (stations.length > 0) {
            const bounds = [];

            stations.forEach(st => {
                const lat = parseFloat(st.latitude);
                const lng = parseFloat(st.longitude);

                if (!isNaN(lat) && !isNaN(lng)) {
                    bounds.push([lat, lng]);

                    const name = st.bunk_name || st.name || "EV Charging Station";
                    const address = st.address || st.location || "Location details unavailable";
                    const bunkId = st.id || st.bunk_id || "";

                    const googleNavUrl = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                    const bookUrl = `find_bunk.php?bunk_id=${bunkId}`;

                    const popupHTML = `
                        <div class="popup-card">
                            <h3>⚡ ${name}</h3>
                            <p>📍 ${address}</p>
                            <span class="popup-badge">🔌 Status: Ready</span>
                            <div class="popup-actions">
                                <a href="${googleNavUrl}" target="_blank" class="btn-pop btn-directions">Navigate</a>
                                <a href="${bookUrl}" class="btn-pop btn-book">Book Slot</a>
                            </div>
                        </div>
                    `;

                    L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(popupHTML);
                }
            });

            // Automatically fit map zoom to show all markers including newly added ones
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        function openModal() {
            document.getElementById('addBunkModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('addBunkModal').classList.remove('active');
        }
    </script>
</body>
</html>