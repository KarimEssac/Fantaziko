<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get league ID from URL (coming from main.php)
$league_id = $_GET['id'] ?? '';

if (empty($league_id)) {
    header("Location: ../main.php");
    exit();
}

// Get league by ID
$stmt = $pdo->prepare("
    SELECT l.*, lt.token 
    FROM leagues l
    LEFT JOIN league_tokens lt ON l.id = lt.league_id
    WHERE l.id = ?
");
$stmt->execute([$league_id]);
$league = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if league doesn't exist
if (!$league) {
    $league_not_found = true;
    $not_owner = false;
    $not_activated = false;
} else {
    $league_not_found = false;
    
    // Get the league token for navigation
    $league_token = $league['token'] ?? '';

    // Check if user is the owner
    if ($league['owner'] != $user_id && $league['other_owner'] != $user_id) {
        $not_owner = true;
        $not_activated = false;
    } else {
        $not_owner = false;
        
        // Check if league is not activated
        if (!$league['activated']) {
            $not_activated = true;
        } else {
            $not_activated = false;
        }
    }
    
    // Only fetch league data if user has access and league is activated
    if (!$not_owner && !$not_activated) {
        // Get league statistics
        $league_id = $league['id'];

        // Get total contributors
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM league_contributors WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $total_contributors = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get contributors list (top 5 for display)
        $stmt = $pdo->prepare("
            SELECT a.username, lc.role, lc.total_score
            FROM league_contributors lc
            INNER JOIN accounts a ON lc.user_id = a.id
            WHERE lc.league_id = ?
            ORDER BY lc.total_score DESC
            LIMIT 5
        ");
        $stmt->execute([$league_id]);
        $top_contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get ALL contributors for modal
        $stmt = $pdo->prepare("
            SELECT a.username, lc.role, lc.total_score
            FROM league_contributors lc
            INNER JOIN accounts a ON lc.user_id = a.id
            WHERE lc.league_id = ?
            ORDER BY lc.total_score DESC
        ");
        $stmt->execute([$league_id]);
        $all_contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get team standings (ALL teams)
        $stmt = $pdo->prepare("
            SELECT team_name, team_score
            FROM league_teams
            WHERE league_id = ?
            ORDER BY team_score DESC
        ");
        $stmt->execute([$league_id]);
        $team_standings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total matches played
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM matches WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $total_matches = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get owner info
        $stmt = $pdo->prepare("SELECT username FROM accounts WHERE id = ?");
        $stmt->execute([$league['owner']]);
        $owner_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>League Settings - <?php echo htmlspecialchars($league['name']); ?></title>
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            position: relative;
            z-index: 1;
        }

        /* Not Owner Page */
        .not-owner-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .not-owner-card {
            background: var(--card-bg);
            border: 2px solid var(--error);
            border-radius: 25px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(239, 68, 68, 0.2);
        }

        body.dark-mode .not-owner-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
        }

        .not-owner-icon {
            font-size: 5rem;
            color: var(--error);
            margin-bottom: 1.5rem;
        }

        .not-owner-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--error);
            margin-bottom: 1rem;
        }

        .not-owner-text {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* League Not Activated */
        .not-activated-card {
            background: var(--card-bg);
            border: 2px solid var(--warning);
            border-radius: 25px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(245, 158, 11, 0.2);
        }

        body.dark-mode .not-activated-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
        }

        .not-activated-icon {
            font-size: 5rem;
            color: var(--warning);
            margin-bottom: 1.5rem;
        }

        .not-activated-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--warning);
            margin-bottom: 1rem;
        }

        /* League Not Found */
        .not-found-card {
            background: var(--card-bg);
            border: 2px solid var(--text-secondary);
            border-radius: 25px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(102, 102, 102, 0.2);
        }

        body.dark-mode .not-found-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
        }

        .not-found-icon {
            font-size: 5rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }

        .not-found-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        /* League Overview */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .stat-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(10, 146, 215, 0.6);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.3);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        body.dark-mode .content-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .content-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--gradient-end);
        }

        .card-badge {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Scrollable Content */
        .scrollable-content {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .scrollable-content::-webkit-scrollbar {
            width: 6px;
        }

        .scrollable-content::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .scrollable-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
        }

        body.dark-mode .scrollable-content::-webkit-scrollbar-track {
            background: rgba(10, 20, 35, 0.5);
        }

        /* List Items */
        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }

        body.dark-mode .list-item {
            background: rgba(10, 20, 35, 0.5);
        }

        .list-item:hover {
            background: rgba(10, 146, 215, 0.1);
            transform: translateX(5px);
        }

        .list-item-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .list-rank {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .list-info {
            display: flex;
            flex-direction: column;
        }

        .list-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .list-role {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .list-score {
            font-weight: 700;
            color: var(--gradient-end);
            font-size: 1.1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
        }

        .btn-gradient {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--gradient-end);
            border: 2px solid var(--gradient-end);
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: var(--gradient-end);
            color: white;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 25px;
            padding: 2.5rem;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
            position: relative;
        }

        body.dark-mode .modal-content {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            transform: rotate(90deg);
        }

        .modal-header {
            margin-bottom: 2rem;
            padding-right: 2rem;
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .modal-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .modal-list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .modal-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem;
            border-radius: 12px;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }

        body.dark-mode .modal-list-item {
            background: rgba(10, 20, 35, 0.5);
        }

        .modal-list-item:hover {
            background: rgba(10, 146, 215, 0.1);
            transform: translateX(5px);
        }

        .modal-list-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .modal-rank {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            min-width: 40px;
        }

        .modal-rank.top-3 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
        }

        .modal-info {
            display: flex;
            flex-direction: column;
        }

        .modal-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.05rem;
        }

        .modal-role {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .modal-score {
            font-weight: 700;
            color: var(--gradient-end);
            font-size: 1.2rem;
        }

        .modal-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .modal-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <!-- Contributors Modal -->
    <div class="modal-overlay" id="contributorsModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeContributorsModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">All Contributors</h2>
                <p class="modal-subtitle">Complete ranking of all league contributors</p>
            </div>
            <div class="modal-list" id="contributorsList">
                <?php if (!empty($all_contributors)): ?>
                    <?php foreach ($all_contributors as $index => $contributor): ?>
                        <div class="modal-list-item">
                            <div class="modal-list-left">
                                <div class="modal-rank <?php echo $index < 3 ? 'top-3' : ''; ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="modal-info">
                                    <div class="modal-name"><?php echo htmlspecialchars($contributor['username']); ?></div>
                                    <div class="modal-role"><?php echo htmlspecialchars($contributor['role']); ?></div>
                                </div>
                            </div>
                            <div class="modal-score"><?php echo number_format($contributor['total_score']); ?> pts</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="modal-empty">
                        <i class="fas fa-users"></i>
                        <p>No contributors yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($league_not_found): ?>
            <!-- League Not Found -->
            <div class="not-owner-container">
                <div class="not-found-card">
                    <div class="not-found-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h1 class="not-found-title">Oops, No Such League Exists</h1>
                    <p class="not-owner-text">The league you're looking for could not be found. It may have been deleted or the link is incorrect.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($not_owner): ?>
            <!-- Not Owner Page -->
            <div class="not-owner-container">
                <div class="not-owner-card">
                    <div class="not-owner-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h1 class="not-owner-title">Access Denied</h1>
                    <p class="not-owner-text">You don't have permission to access this league's settings. Only the league owner can manage league settings.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($not_activated): ?>
            <!-- League Not Activated -->
            <div class="not-owner-container">
                <div class="not-activated-card">
                    <div class="not-activated-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h1 class="not-activated-title">League Not Activated Yet</h1>
                    <p class="not-owner-text">This league is currently under review. Please wait until we review your application. You will be notified once your league is activated.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- League Overview -->
            <div class="page-header">
                <h2 class="page-title">League Overview</h2>
                <p class="page-subtitle">Manage and monitor your fantasy football league</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_contributors; ?></div>
                    <div class="stat-label">Total Contributors</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $league['num_of_teams']; ?></div>
                    <div class="stat-label">Teams</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value">Round <?php echo $league['round']; ?></div>
                    <div class="stat-label">Current Round</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <div class="stat-value"><?php echo $league['system']; ?></div>
                    <div class="stat-label">League System</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Top Contributors -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-trophy"></i>
                            Top Contributors
                        </h3>
                        <button class="btn btn-secondary" onclick="openContributorsModal()">
                            <i class="fas fa-list"></i> View All
                        </button>
                    </div>
                    <?php if (!empty($top_contributors)): ?>
                        <?php foreach ($top_contributors as $index => $contributor): ?>
                            <div class="list-item">
                                <div class="list-item-left">
                                    <div class="list-rank"><?php echo $index + 1; ?></div>
                                    <div class="list-info">
                                        <div class="list-name"><?php echo htmlspecialchars($contributor['username']); ?></div>
                                        <div class="list-role"><?php echo htmlspecialchars($contributor['role']); ?></div>
                                    </div>
                                </div>
                                <div class="list-score"><?php echo number_format($contributor['total_score']); ?> pts</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No contributors yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Team Standings -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Team Standings
                        </h3>
                        <span class="card-badge"><?php echo count($team_standings); ?> Teams</span>
                    </div>
                    <?php if (!empty($team_standings)): ?>
                        <div class="scrollable-content">
                            <?php foreach ($team_standings as $index => $team): ?>
                                <div class="list-item">
                                    <div class="list-item-left">
                                        <div class="list-rank"><?php echo $index + 1; ?></div>
                                        <div class="list-info">
                                            <div class="list-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                        </div>
                                    </div>
                                    <div class="list-score"><?php echo number_format($team['team_score']); ?> pts</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No teams added yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- League Info Card -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        League Information
                    </h3>
                </div>
                <div class="stats-grid">
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-role">League Owner</div>
                            <div class="list-name"><?php echo htmlspecialchars($owner_info['username']); ?></div>
                        </div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-role">League Type</div>
                            <div class="list-name"><?php echo htmlspecialchars($league['positions']); ?></div>
                        </div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-role">Entry Price</div>
                            <div class="list-name"><?php echo number_format($league['price'], 2); ?> EGP</div>
                        </div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-role">Power-ups Available</div>
                            <div class="list-name">
                                <?php 
                                $powerups = [];
                                if ($league['triple_captain'] > 0) $powerups[] = 'Triple Captain';
                                if ($league['bench_boost'] > 0) $powerups[] = 'Bench Boost';
                                if ($league['wild_card'] > 0) $powerups[] = 'Wild Card';
                                echo !empty($powerups) ? implode(', ', $powerups) : 'None';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/footer.php'; ?>
    <?php endif; ?>

    <script>
        // Contributors Modal Functions
        function openContributorsModal() {
            const modal = document.getElementById('contributorsModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeContributorsModal() {
            const modal = document.getElementById('contributorsModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal when clicking overlay
        document.getElementById('contributorsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeContributorsModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('contributorsModal');
                if (modal.classList.contains('active')) {
                    closeContributorsModal();
                }
            }
        });
    </script>
</body>
</html>