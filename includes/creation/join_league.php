<?php
session_start();
require_once 'config/db.php';

// Get token from URL
$token = trim($_GET['token'] ?? '');

// Store token in session for after signup redirect
if (!empty($token)) {
    $_SESSION['pending_league_token'] = $token;
}

// If user is not logged in, redirect to signup
if (!isset($_SESSION['user_id'])) {
    header("Location: signup.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Retrieve token from session if not in URL (after signup redirect)
if (empty($token) && isset($_SESSION['pending_league_token'])) {
    $token = $_SESSION['pending_league_token'];
}

$league = null;
$error = '';
$already_member = false;

// Validate token and fetch league details
if (!empty($token)) {
    if (strlen($token) !== 8) {
        $error = 'Invalid token format. Token must be 8 characters.';
    } else {
        // Find league by token
        $stmt = $pdo->prepare("
            SELECT l.*, lt.token, a.username as owner_name 
            FROM league_tokens lt
            INNER JOIN leagues l ON lt.league_id = l.id
            INNER JOIN accounts a ON l.owner = a.id
            WHERE lt.token = ?
        ");
        $stmt->execute([$token]);
        $league = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$league) {
            $error = 'League not found. The invitation link may be invalid or expired.';
        } else {
            // Check if user is already in the league
            $stmt = $pdo->prepare("SELECT * FROM league_contributors WHERE user_id = ? AND league_id = ?");
            $stmt->execute([$user_id, $league['id']]);
            if ($stmt->fetch()) {
                $already_member = true;
            }
            
            // Check if user is the owner
            if ($league['owner'] == $user_id || $league['other_owner'] == $user_id) {
                $already_member = true;
                $error = 'You are the owner of this league.';
            }
            
            // Get contributor count
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM league_contributors WHERE league_id = ?");
            $stmt->execute([$league['id']]);
            $contributor_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
    }
} else {
    $error = 'No league token provided.';
}

// Handle join league request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_league'])) {
    $league_id = intval($_POST['league_id'] ?? 0);
    
    if ($league_id > 0 && !$already_member) {
        try {
            $stmt = $pdo->prepare("INSERT INTO league_contributors (user_id, league_id, role, total_score) VALUES (?, ?, 'Contributor', 0)");
            $stmt->execute([$user_id, $league_id]);
            
            // Clear the pending token from session
            unset($_SESSION['pending_league_token']);
            
            // Redirect to main dashboard
            header("Location: main.php");
            exit();
        } catch (PDOException $e) {
            $error = 'Failed to join league. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join League - Fantazina</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fc;
            --text-primary: #000000;
            --text-secondary: #666666;
            --border-color: rgba(0, 0, 0, 0.1);
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-hover: rgba(255, 255, 255, 1);
            --nav-bg: rgba(255, 255, 255, 0.95);
            --gradient-start: #1D60AC;
            --gradient-end: #0A92D7;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 10px 30px rgba(10, 146, 215, 0.2);
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        
        body.dark-mode {
            --bg-primary: #000000;
            --bg-secondary: #0a0a0a;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
            --border-color: rgba(255, 255, 255, 0.1);
            --card-bg: rgba(20, 20, 20, 0.95);
            --card-hover: rgba(30, 30, 30, 0.95);
            --nav-bg: rgba(0, 0, 0, 0.95);
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 10px 30px rgba(10, 146, 215, 0.3);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                repeating-linear-gradient(0deg, var(--border-color) 0px, transparent 1px, transparent 40px, var(--border-color) 41px),
                repeating-linear-gradient(90deg, var(--border-color) 0px, transparent 1px, transparent 40px, var(--border-color) 41px);
            opacity: 0.3;
            pointer-events: none;
            z-index: 0;
        }
        
        body.dark-mode::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at center, rgba(29, 96, 172, 0.15), transparent);
            pointer-events: none;
            z-index: 0;
        }
        
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: var(--nav-bg);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        
        .logo-container img {
            height: 50px;
            width: auto;
        }
        
        body.dark-mode .logo-container img {
            content: url('assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .logo-container img {
            content: url('assets/images/logo.png');
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        body.dark-mode .logo-text {
            background: none;
            -webkit-text-fill-color: white;
            color: white;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.6rem 1.2rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .user-info:hover {
            background: var(--card-hover);
            border-color: var(--gradient-end);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        
        .theme-toggle {
            background: transparent;
            border: 2px solid var(--text-primary);
            color: var(--text-primary);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .theme-toggle:hover {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-color: transparent;
            transform: rotate(180deg);
        }
        
        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            border: none;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--text-primary);
        }
        
        .btn-outline:hover {
            background: var(--text-primary);
            color: var(--bg-primary);
            transform: translateY(-2px);
        }
        
        .main-container {
            padding: 140px 5% 60px;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .join-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 3rem;
            box-shadow: var(--shadow);
            width: 100%;
            animation: fadeInUp 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        body.dark-mode .join-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .join-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .join-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .join-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .join-header h1 {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .join-header .gradient-text {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .join-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .league-details {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        body.dark-mode .league-details {
            background: rgba(10, 20, 35, 0.5);
            border-color: rgba(10, 146, 215, 0.3);
        }
        
        .league-name {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .league-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        body.dark-mode .info-box {
            background: rgba(20, 30, 48, 0.6);
            border-color: rgba(10, 146, 215, 0.2);
        }
        
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.2);
            border-color: var(--gradient-end);
        }
        
        .info-box-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }
        
        .info-box-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .info-box-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .league-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gradient-end);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.3rem;
        }
        
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1rem;
            animation: fadeInUp 0.4s ease;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.3);
            color: var(--error);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid rgba(245, 158, 11, 0.3);
            color: var(--warning);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .action-buttons .btn {
            flex: 1;
        }
        
        .loading-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .loading-spinner.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .spinner {
            width: 80px;
            height: 80px;
            border: 6px solid var(--border-color);
            border-top: 6px solid var(--gradient-end);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            margin-top: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .loading-logo {
            margin-bottom: 2rem;
        }
        
        .loading-logo img {
            height: 80px;
            width: auto;
            animation: logoPluse 2s ease-in-out infinite;
        }
        
        @keyframes logoPluse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
        }
        
        body.dark-mode .loading-logo img {
            content: url('assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .loading-logo img {
            content: url('assets/images/logo.png');
        }
        
        @media (max-width: 768px) {
            nav {
                padding: 1rem 3%;
            }
            
            .logo-container {
                gap: 0.5rem;
            }
            
            .logo-container img {
                height: 35px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .user-info {
                padding: 0.5rem 1rem;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .user-name {
                font-size: 0.85rem;
            }
            
            .theme-toggle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .main-container {
                padding: 120px 3% 40px;
            }
            
            .join-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
            
            .join-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
            
            .join-header h1 {
                font-size: 2rem;
            }
            
            .join-header p {
                font-size: 1rem;
            }
            
            .league-name {
                font-size: 1.5rem;
            }
            
            .league-info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .league-stats {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                padding: 0.7rem 1.5rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .logo-text {
                display: none;
            }
            
            .join-header h1 {
                font-size: 1.7rem;
            }
            
            .league-details {
                padding: 1.5rem;
            }
            
            .info-box {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="loading-logo">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
        </div>
        <div class="spinner"></div>
        <div class="loading-text">Loading...</div>
    </div>

    <!-- Navigation -->
    <nav>
        <a href="main.php" class="logo-container">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
            <span class="logo-text">FANTAZINA</span>
        </a>
        <div class="nav-right">
            <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <div class="join-card">
            <?php if ($error): ?>
                <div class="join-header">
                    <div class="join-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h1>League <span class="gradient-text">Not Found</span></h1>
                    <p>We couldn't find the league you're looking for</p>
                </div>
                
                <div class="alert alert-error">
                    <i class="fas fa-times-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                
                <div class="action-buttons">
                    <a href="main.php" class="btn btn-gradient">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                
            <?php elseif ($already_member): ?>
                <div class="join-header">
                    <div class="join-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1>Already <span class="gradient-text">a Member</span></h1>
                    <p>You're already part of this league!</p>
                </div>
                
                <div class="league-details">
                    <h2 class="league-name"><?php echo htmlspecialchars($league['name']); ?></h2>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <span>You are already a member of this league or you own it.</span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="league_dashboard.php?id=<?php echo $league['id']; ?>" class="btn btn-gradient">
                        <i class="fas fa-sign-in-alt"></i> Go to League
                    </a>
                    <a href="main.php" class="btn btn-outline">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
                
            <?php elseif ($league): ?>
                <div class="join-header">
                    <div class="join-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h1>Join <span class="gradient-text">League</span></h1>
                    <p>You've been invited to join an exciting fantasy league!</p>
                </div>
                
                <div class="league-details">
                    <h2 class="league-name">
                        <i class="fas fa-trophy" style="color: var(--gradient-end); margin-right: 0.5rem;"></i>
                        <?php echo htmlspecialchars($league['name']); ?>
                    </h2>
                    
                    <div class="league-info-grid">
                        <div class="info-box">
                            <div class="info-box-icon">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="info-box-label">League Owner</div>
                            <div class="info-box-value"><?php echo htmlspecialchars($league['owner_name']); ?></div>
                        </div>
                        
                        <div class="info-box">
                            <div class="info-box-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="info-box-label">Members</div>
                            <div class="info-box-value"><?php echo $contributor_count ?? 0; ?> Contributors</div>
                        </div>
                        
                        <div class="info-box">
                            <div class="info-box-icon">
                                <i class="fas fa-gamepad"></i>
                            </div>
                            <div class="info-box-label">System</div>
                            <div class="info-box-value"><?php echo htmlspecialchars($league['system']); ?></div>
                        </div>
                    </div>
                    
                    <div class="league-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $league['num_of_teams']; ?></div>
                            <div class="stat-label">Teams</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $league['num_of_players']; ?></div>
                            <div class="stat-label">Players</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">Round <?php echo $league['round']; ?></div>
                            <div class="stat-label">Current Round</div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Everything looks good! Click the button below to join this league.</span>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="league_id" value="<?php echo $league['id']; ?>">
                    <div class="action-buttons">
                        <a href="main.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" name="join_league" class="btn btn-gradient">
                            <i class="fas fa-sign-in-alt"></i> Join League Now
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme handling - Load saved theme immediately
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            
            if (savedTheme === 'light') {
                body.classList.remove('dark-mode');
            } else {
                body.classList.add('dark-mode');
            }
        })();

        // Loading spinner
        window.addEventListener('load', function() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            setTimeout(() => {
                loadingSpinner.classList.add('hidden');
            }, 500);
        });

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');

        // Set initial icon based on current theme
        if (body.classList.contains('dark-mode')) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
        
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        // Form submission handling with loading state
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Joining...';
                }
            });
        }

        // Add animation to cards on load
        window.addEventListener('load', function() {
            const joinCard = document.querySelector('.join-card');
            if (joinCard) {
                joinCard.style.opacity = '0';
                joinCard.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    joinCard.style.transition = 'all 0.6s ease';
                    joinCard.style.opacity = '1';
                    joinCard.style.transform = 'translateY(0)';
                }, 100);
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add hover effect to info boxes
        document.querySelectorAll('.info-box').forEach(box => {
            box.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            box.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.backgroundColor = 'rgba(255, 255, 255, 0.5)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s ease-out';
                ripple.style.pointerEvents = 'none';
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Handle back button navigation
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        // Add keyboard navigation support
        document.addEventListener('keydown', function(e) {
            // Escape key to go back
            if (e.key === 'Escape') {
                const cancelBtn = document.querySelector('.btn-outline[href="main.php"]');
                if (cancelBtn) {
                    window.location.href = 'main.php';
                }
            }
            
            // Enter key to submit form (if focused on page)
            if (e.key === 'Enter' && !e.target.matches('input, textarea, button')) {
                const submitBtn = document.querySelector('button[name="join_league"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
        });

        // Add focus visible for accessibility
        document.querySelectorAll('.btn, .theme-toggle').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.outline = '2px solid var(--gradient-end)';
                this.style.outlineOffset = '2px';
            });
            
            element.addEventListener('blur', function() {
                this.style.outline = 'none';
            });
        });

        // Console welcome message
        console.log('%c🏆 Welcome to Fantazina! 🏆', 'font-size: 20px; font-weight: bold; color: #0A92D7;');
        console.log('%cJoin the league and start your fantasy football journey!', 'font-size: 14px; color: #1D60AC;');
    </script>
</body>
</html>