<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'bunk' && $_SESSION['role'] !== 'owner')) {
    header("Location: login.php?role=bunk");
    exit();
}

$owner_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM bunks WHERE owner_id = $owner_id");
$bunks = [];
while ($row = $result->fetch_assoc()) {
    $bunks[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en" ng-app="bunkApp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bunk Details - EV Bunk Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AngularJS CDN -->
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>

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
            flex-direction: column;
        }

        /* Top Navigation Header */
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* Main Container Card */
        .container {
            width: 92%;
            max-width: 1250px;
            margin: 40px auto;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            padding: 30px;
            border-radius: 16px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-3d);
            flex: 1;
        }

        .container h2 {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 20px;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Search Form Group */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group input {
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 14px 18px;
            color: var(--text-primary);
            font-size: 14.5px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 12px var(--accent-green-glow);
        }

        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--card-border);
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        tr:not(:last-child) td {
            border-bottom: 1px solid var(--card-border);
        }

        tbody tr {
            background: rgba(15, 23, 42, 0.4);
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: rgba(30, 41, 59, 0.7);
        }

        td {
            color: var(--text-secondary);
        }

        td:first-child {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-action {
            background: var(--accent-blue);
            color: white;
            padding: 7px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-action:hover {
            background: #0284c7;
            box-shadow: 0 0 10px rgba(14, 165, 233, 0.35);
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 15px;
            }
        }
    </style>
</head>

<body ng-controller="bunkController">

    <!-- Top Navbar -->
    <div class="navbar">
        <a href="bunk_dashboard.php" class="brand-logo">⚡ EV Bunk Portal</a>
        <a href="bunk_dashboard.php" class="btn-back">← Back to Dashboard</a>
    </div>

    <div class="container">
        <h2>Registered Bunk List</h2>

        <!-- Live Search Input -->
        <div class="form-group">
            <input type="text" ng-model="searchText" placeholder="🔍 Search by Bunk Name, Area, or Charger type...">
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Bunk Name</th>
                        <th>Area</th>
                        <th>Address</th>
                        <th>Battery Info</th>
                        <th>Total Slots</th>
                        <th>Free Slots</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="bunk in bunks | filter:searchText">
                        <td>{{ bunk.bunk_name }}</td>
                        <td>{{ bunk.area }}</td>
                        <td>{{ bunk.address }}</td>
                        <td>{{ bunk.battery_info }}</td>
                        <td>{{ bunk.total_slots }}</td>
                        <td><span style="color:#10b981; font-weight:700;">{{ bunk.free_slots }}</span></td>
                        <td><a href="update_map.php?bunk_id={{ bunk.id }}" class="btn-action">Map Pin</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        var app = angular.module('bunkApp', []);
        app.controller('bunkController', function($scope) {
            // Load MySQL JSON array directly into AngularJS scope
            $scope.bunks = <?php echo json_encode($bunks); ?>;
        });
    </script>

</body>
</html>