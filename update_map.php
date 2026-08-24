<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bunk') {
    header("Location: login.php?role=bunk");
    exit();
}

$owner_id = $_SESSION['user_id'];
$message = "";

// Get owner's bunks to select which one to update
$bunk_query = $conn->query("SELECT id, bunk_name, latitude, longitude FROM bunks WHERE owner_id = $owner_id");
$bunks = $bunk_query->fetch_all(MYSQLI_ASSOC);

$selected_bunk_id = isset($_GET['bunk_id']) ? intval($_GET['bunk_id']) : ($bunks[0]['id'] ?? 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bunk_id = intval($_POST['bunk_id']);
    $lat = floatval($_POST['latitude']);
    $lng = floatval($_POST['longitude']);

    $stmt = $conn->prepare("UPDATE bunks SET latitude = ?, longitude = ? WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ddii", $lat, $lng, $bunk_id, $owner_id);

    if ($stmt->execute()) {
        $message = "Location updated successfully!";
    } else {
        $message = "Failed to update location.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Bunk Map Location</title>
    <link rel="stylesheet" href="css/style.css">
    
    <!-- FREE OpenStreetMap Leaflet CSS & JS (No Credit Card / No API Key Required) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        #map {
            width: 100%;
            height: 350px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .btn-geo {
            background-color: #17a2b8;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="form-card" style="width: 550px;">
    <h2>Update Bunk Map Location</h2>
    <?php if (!empty($message)) echo "<div class='success-banner'>$message</div>"; ?>

    <form method="GET" action="update_map.php">
        <div class="form-group">
            <label>Select Bunk</label>
            <select name="bunk_id" onchange="this.form.submit()" style="width:100%; padding:8px;">
                <?php foreach ($bunks as $b): ?>
                    <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $selected_bunk_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['bunk_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <button class="btn-geo" onclick="useCurrentLocation()">📍 Auto Detect My Location (Geo Map)</button>
    
    <!-- Interactive Map Container -->
    <div id="map"></div>

    <form method="POST" action="update_map.php">
        <input type="hidden" name="bunk_id" value="<?php echo $selected_bunk_id; ?>">
        
        <div class="form-group">
            <label>Latitude</label>
            <input type="text" id="latitude" name="latitude" readonly required>
        </div>
        <div class="form-group">
            <label>Longitude</label>
            <input type="text" id="longitude" name="longitude" readonly required>
        </div>

        <button type="submit" class="btn-submit">Save Location Coordinates</button>
    </form>
    <p style="text-align:center; margin-top:10px;"><a href="bunk_dashboard.php">Back to Dashboard</a></p>
</div>

<script>
let map, marker;
const defaultLat = 12.9716; // Bengaluru Lat
const defaultLng = 77.5946; // Bengaluru Lng

function initMap() {
    // 1. Create Leaflet Map Instance
    map = L.map('map').setView([defaultLat, defaultLng], 13);

    // 2. Load Free OpenStreetMap Tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // 3. Add Draggable Pin Marker
    marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    updateInputFields(defaultLat, defaultLng);

    // 4. Update Lat/Lng inputs whenever the pin is dragged
    marker.on('dragend', function (e) {
        const position = marker.getLatLng();
        updateInputFields(position.lat, position.lng);
    });
}

function updateInputFields(lat, lng) {
    document.getElementById("latitude").value = parseFloat(lat).toFixed(8);
    document.getElementById("longitude").value = parseFloat(lng).toFixed(8);
}

function useCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            map.setView([lat, lng], 15);
            marker.setLatLng([lat, lng]);
            updateInputFields(lat, lng);
        });
    } else {
        alert("Geolocation is not supported by your browser.");
    }
}

window.onload = initMap;
</script>

</body>
</html>