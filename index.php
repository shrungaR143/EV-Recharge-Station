<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV Recharge Station Network</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.1);
            --accent-green: #10b981;
            --accent-green-glow: rgba(16, 185, 129, 0.25);
            --accent-blue: #0ea5e9;
            --accent-blue-glow: rgba(14, 165, 233, 0.25);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .main-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 40px;
            max-width: 580px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .brand-logo {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #10b981, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 15px;
            margin-bottom: 32px;
        }

        /* Role Selector Cards */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 28px;
        }

        .role-card {
            background: var(--card-bg);
            border: 2px solid var(--card-border);
            border-radius: 14px;
            padding: 22px 16px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            user-select: none;
        }

        .role-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .role-card.active-user {
            border-color: var(--accent-blue);
            background: rgba(14, 165, 233, 0.08);
            box-shadow: 0 0 20px var(--accent-blue-glow);
        }

        .role-card.active-owner {
            border-color: var(--accent-green);
            background: rgba(16, 185, 129, 0.08);
            box-shadow: 0 0 20px var(--accent-green-glow);
        }

        .role-icon {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }

        .role-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .role-desc {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Action Buttons Area (Hidden by default) */
        .action-area {
            display: none;
            flex-direction: column;
            gap: 12px;
        }

        .btn-action {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), #0284c7);
            color: white;
            box-shadow: 0 4px 12px var(--accent-blue-glow);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            opacity: 0.95;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 480px) {
            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="main-card">
    <div class="brand-logo">⚡ EV Recharge</div>
    <p class="subtitle" id="helperText">Select your portal to continue</p>

    <!-- Interactive Role Toggle -->
    <div class="role-selector">
        <div class="role-card" id="userCard" onclick="selectRole('user')">
            <span class="role-icon">🚗</span>
            <div class="role-title">EV Driver</div>
            <div class="role-desc">Find stations & reserve slots</div>
        </div>

        <div class="role-card" id="ownerCard" onclick="selectRole('owner')">
            <span class="role-icon">🔌</span>
            <div class="role-title">Bunk Owner</div>
            <div class="role-desc">Manage stations & bookings</div>
        </div>
    </div>

    <!-- Dynamic Actions (Hidden initially, shown on click via JS) -->
    <div class="action-area" id="actionArea">
        <a href="#" id="btnLogin" class="btn-action btn-primary">Login</a>
        <a href="#" id="btnRegister" class="btn-action btn-secondary">Register</a>
    </div>
</div>

<script>
    function selectRole(role) {
        const userCard = document.getElementById('userCard');
        const ownerCard = document.getElementById('ownerCard');
        const actionArea = document.getElementById('actionArea');
        const btnLogin = document.getElementById('btnLogin');
        const btnRegister = document.getElementById('btnRegister');
        const helperText = document.getElementById('helperText');

        // Reveal action buttons block
        actionArea.style.display = 'flex';
        helperText.innerText = 'Choose an option below';

        if (role === 'user') {
            userCard.className = 'role-card active-user';
            ownerCard.className = 'role-card';

            btnLogin.href = 'user_login.php';
            btnLogin.innerText = 'Login as EV Driver';
            btnLogin.style.background = 'linear-gradient(135deg, #0ea5e9, #0284c7)';
            btnLogin.style.boxShadow = '0 4px 12px rgba(14, 165, 233, 0.25)';

            btnRegister.href = 'user_register.php';
            btnRegister.innerText = 'Register as EV Driver';
        } else {
            ownerCard.className = 'role-card active-owner';
            userCard.className = 'role-card';

            btnLogin.href = 'bunk_login.php';
            btnLogin.innerText = 'Login as Station Owner';
            btnLogin.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            btnLogin.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.25)';

            btnRegister.href = 'bunk_register.php';
            btnRegister.innerText = 'Register Charging Station';
        }
    }
</script>

</body>
</html>