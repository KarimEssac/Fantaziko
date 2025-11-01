<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$league_id = $_GET['id'] ?? '';

if (empty($league_id)) {
    header("Location: ../main.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT l.*, lt.token 
    FROM leagues l
    LEFT JOIN league_tokens lt ON l.id = lt.league_id
    WHERE l.id = ?
");
$stmt->execute([$league_id]);
$league = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$league) {
    $league_not_found = true;
    $not_owner = false;
    $not_activated = false;
} else {
    $league_not_found = false;
    $league_token = $league['token'] ?? '';
    if ($league['owner'] != $user_id && $league['other_owner'] != $user_id) {
        $not_owner = true;
        $not_activated = false;
    } else {
        $not_owner = false;
        if (!$league['activated']) {
            $not_activated = true;
        } else {
            $not_activated = false;
        }
    }

    if (!$not_owner && !$not_activated) {
        $stmt = $pdo->prepare("
            SELECT a.username, lc.role, lc.total_score,
                   RANK() OVER (ORDER BY lc.total_score DESC) as ranking
            FROM league_contributors lc
            INNER JOIN accounts a ON lc.user_id = a.id
            WHERE lc.league_id = ?
            ORDER BY lc.total_score DESC
            LIMIT 10
        ");
        $stmt->execute([$league_id]);
        $top_contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT lp.player_name, lp.player_role, lt.team_name,
                   COUNT(mp.scorer) as goals,
                   RANK() OVER (ORDER BY COUNT(mp.scorer) DESC) as ranking
            FROM league_players lp
            LEFT JOIN matches_points mp ON lp.player_id = mp.scorer
            LEFT JOIN league_teams lt ON lp.team_id = lt.id
            WHERE lp.league_id = ?
            GROUP BY lp.player_id
            HAVING goals > 0
            ORDER BY goals DESC
            LIMIT 10
        ");
        $stmt->execute([$league_id]);
        $top_scorers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT lp.player_name, lp.player_role, lt.team_name,
                   COUNT(mp.assister) as assists,
                   RANK() OVER (ORDER BY COUNT(mp.assister) DESC) as ranking
            FROM league_players lp
            LEFT JOIN matches_points mp ON lp.player_id = mp.assister
            LEFT JOIN league_teams lt ON lp.team_id = lt.id
            WHERE lp.league_id = ?
            GROUP BY lp.player_id
            HAVING assists > 0
            ORDER BY assists DESC
            LIMIT 10
        ");
        $stmt->execute([$league_id]);
        $top_assisters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT lp.player_name, lp.player_role, lt.team_name,
                   COUNT(DISTINCT m.match_id) as clean_sheets,
                   RANK() OVER (ORDER BY COUNT(DISTINCT m.match_id) DESC) as ranking
            FROM league_players lp
            LEFT JOIN league_teams lt ON lp.team_id = lt.id
            LEFT JOIN matches m ON (m.team1_id = lt.id OR m.team2_id = lt.id)
            WHERE lp.league_id = ? 
            AND lp.player_role IN ('GK', 'DEF')
            AND (
                (m.team1_id = lt.id AND m.team2_score = 0) OR
                (m.team2_id = lt.id AND m.team1_score = 0)
            )
            GROUP BY lp.player_id
            HAVING clean_sheets > 0
            ORDER BY clean_sheets DESC
            LIMIT 10
        ");
        $stmt->execute([$league_id]);
        $clean_sheet_leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT lt.team_name,
                   SUM(CASE 
                       WHEN m.team1_id = lt.id THEN m.team1_score
                       WHEN m.team2_id = lt.id THEN m.team2_score
                       ELSE 0
                   END) as goals_scored,
                   RANK() OVER (ORDER BY SUM(CASE 
                       WHEN m.team1_id = lt.id THEN m.team1_score
                       WHEN m.team2_id = lt.id THEN m.team2_score
                       ELSE 0
                   END) DESC) as ranking
            FROM league_teams lt
            LEFT JOIN matches m ON (m.team1_id = lt.id OR m.team2_id = lt.id)
            WHERE lt.league_id = ?
            GROUP BY lt.id
            HAVING goals_scored > 0
            ORDER BY goals_scored DESC
            LIMIT 5
        ");
        $stmt->execute([$league_id]);
        $top_attacking_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT lt.team_name,
                   SUM(CASE 
                       WHEN m.team1_id = lt.id THEN m.team2_score
                       WHEN m.team2_id = lt.id THEN m.team1_score
                       ELSE 0
                   END) as goals_conceded,
                   COUNT(DISTINCT m.match_id) as matches_played,
                   RANK() OVER (ORDER BY SUM(CASE 
                       WHEN m.team1_id = lt.id THEN m.team2_score
                       WHEN m.team2_id = lt.id THEN m.team1_score
                       ELSE 0
                   END) ASC) as ranking
            FROM league_teams lt
            LEFT JOIN matches m ON (m.team1_id = lt.id OR m.team2_id = lt.id)
            WHERE lt.league_id = ?
            GROUP BY lt.id
            HAVING matches_played > 0
            ORDER BY goals_conceded ASC
            LIMIT 5
        ");
        $stmt->execute([$league_id]);
        $top_defensive_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT team_name, team_score,
                   RANK() OVER (ORDER BY team_score DESC) as ranking
            FROM league_teams
            WHERE league_id = ?
            ORDER BY team_score DESC
            LIMIT 10
        ");
        $stmt->execute([$league_id]);
        $team_standings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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
            --gold: #FFD700;
            --silver: #C0C0C0;
            --bronze: #CD7F32;
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

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            position: relative;
            z-index: 1;
        }
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

        .page-header {
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
        }

        .section-header {
            margin: 3rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title i {
            color: var(--gradient-end);
            font-size: 1.5rem;
        }

        .section-line {
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, var(--gradient-end), transparent);
        }

        .leaderboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .leaderboard-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .leaderboard-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .leaderboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .leaderboard-card:hover::before {
            opacity: 1;
        }

        .leaderboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(10, 146, 215, 0.6);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .card-title i {
            color: var(--gradient-end);
            font-size: 1.3rem;
        }

        .card-badge {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .leaderboard-item {
            background: rgba(10, 20, 35, 0.5);
        }

        .leaderboard-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .leaderboard-item:hover {
            background: rgba(10, 146, 215, 0.1);
            transform: translateX(5px);
        }

        .leaderboard-item:hover::before {
            opacity: 1;
        }

        .leaderboard-item.top-1 {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(255, 215, 0, 0.05));
            border: 2px solid rgba(255, 215, 0, 0.3);
        }

        body.dark-mode .leaderboard-item.top-1 {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 215, 0, 0.1));
        }

        .leaderboard-item.top-2 {
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.15), rgba(192, 192, 192, 0.05));
            border: 2px solid rgba(192, 192, 192, 0.3);
        }

        body.dark-mode .leaderboard-item.top-2 {
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.2), rgba(192, 192, 192, 0.1));
        }

        .leaderboard-item.top-3 {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.15), rgba(205, 127, 50, 0.05));
            border: 2px solid rgba(205, 127, 50, 0.3);
        }

        body.dark-mode .leaderboard-item.top-3 {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.2), rgba(205, 127, 50, 0.1));
        }

        .item-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .rank-badge {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 900;
            font-size: 1.1rem;
            min-width: 45px;
            box-shadow: 0 4px 15px rgba(10, 146, 215, 0.3);
        }

        .rank-badge.gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }

        .rank-badge.silver {
            background: linear-gradient(135deg, #E8E8E8, #A8A8A8);
            box-shadow: 0 4px 15px rgba(192, 192, 192, 0.4);
        }

        .rank-badge.bronze {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            box-shadow: 0 4px 15px rgba(205, 127, 50, 0.4);
        }

        .item-info {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.05rem;
        }

        .item-detail {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .item-score {
            font-weight: 700;
            font-size: 1.3rem;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            white-space: nowrap;
        }

        .item-score.gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .item-score.silver {
            background: linear-gradient(135deg, #E8E8E8, #A8A8A8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .item-score.bronze {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1.1rem;
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

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .leaderboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.4rem;
            }

            .leaderboard-grid {
                grid-template-columns: 1fr;
            }

            .leaderboard-card {
                padding: 1.5rem;
            }

            .item-left {
                gap: 0.8rem;
            }

            .rank-badge {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .item-name {
                font-size: 0.95rem;
            }

            .item-score {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.6rem;
            }

            .section-header {
                margin: 2rem 0 1rem;
            }

            .item-detail {
                font-size: 0.8rem;
            }
        }
        .loading-spinner-overlay {
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
        
        .loading-spinner-overlay.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .spinner-large {
            width: 80px;
            height: 80px;
            border: 6px solid var(--border-color);
            border-top: 6px solid var(--gradient-end);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
        }
        
        body.dark-mode .loading-logo img {
            content: url('../assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .loading-logo img {
            content: url('../assets/images/logo.png');
        }

    </style>
</head>
<body>
    <div class="loading-spinner-overlay" id="loadingSpinner">
        <div class="loading-logo">
            <img src="../assets/images/logo white outline.png" alt="Fantazina Logo">
        </div>
        <div class="spinner-large"></div>
        <div class="loading-text">Loading Leaderboard...</div>
    </div>
    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>
    
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
                    <p class="not-owner-text">You don't have permission to access this league's leaderboard. Only the league owner can view this page.</p>
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
            <!-- Leaderboard Content -->
            <div class="page-header">
                <h2 class="page-title">
                    <i class="fas fa-trophy"></i>
                    Leaderboard
                </h2>
                <p class="page-subtitle">Top performers and team statistics</p>
            </div>

            <!-- Contributors Section -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-medal"></i>
                    Top Contributors
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="leaderboard-grid">
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-star"></i>
                            Overall Rankings
                        </h4>
                        <span class="card-badge"><?php echo count($top_contributors); ?> Contributors</span>
                    </div>
                    <?php if (!empty($top_contributors)): ?>
                        <?php foreach ($top_contributors as $index => $contributor): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-crown"></i>
                                        <?php else: ?>
                                            <?php echo $index + 1; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($contributor['username']); ?></div>
                                        <div class="item-detail"><?php echo htmlspecialchars($contributor['role']); ?></div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo number_format($contributor['total_score']); ?> pts</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No contributors data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Players Section -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-futbol"></i>
                    Player Statistics
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="leaderboard-grid">
                <!-- Top Scorers -->
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-fire"></i>
                            Top Scorers
                        </h4>
                        <span class="card-badge">Goals</span>
                    </div>
                    <?php if (!empty($top_scorers)): ?>
                        <?php foreach ($top_scorers as $index => $scorer): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($scorer['player_name']); ?></div>
                                        <div class="item-detail"><?php echo htmlspecialchars($scorer['team_name'] ?? 'No Team'); ?> • <?php echo htmlspecialchars($scorer['player_role'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo $scorer['goals']; ?> <i class="fas fa-futbol"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-futbol"></i>
                            <p>No goals scored yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top Assisters -->
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-hands-helping"></i>
                            Top Assisters
                        </h4>
                        <span class="card-badge">Assists</span>
                    </div>
                    <?php if (!empty($top_assisters)): ?>
                        <?php foreach ($top_assisters as $index => $assister): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($assister['player_name']); ?></div>
                                        <div class="item-detail"><?php echo htmlspecialchars($assister['team_name'] ?? 'No Team'); ?> • <?php echo htmlspecialchars($assister['player_role'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo $assister['assists']; ?> <i class="fas fa-hands-helping"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-hands-helping"></i>
                            <p>No assists recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Clean Sheet Leaders -->
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            Clean Sheet Leaders
                        </h4>
                        <span class="card-badge">GK & DEF</span>
                    </div>
                    <?php if (!empty($clean_sheet_leaders)): ?>
                        <?php foreach ($clean_sheet_leaders as $index => $player): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($player['player_name']); ?></div>
                                        <div class="item-detail"><?php echo htmlspecialchars($player['team_name'] ?? 'No Team'); ?> • <?php echo htmlspecialchars($player['player_role'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo $player['clean_sheets']; ?> <i class="fas fa-shield-alt"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <p>No clean sheets recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teams Section -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-flag"></i>
                    Team Statistics
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="leaderboard-grid">
                <!-- Overall Team Standings -->
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Overall Standings
                        </h4>
                        <span class="card-badge">Total Points</span>
                    </div>
                    <?php if (!empty($team_standings)): ?>
                        <?php foreach ($team_standings as $index => $team): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php if ($index < 3): ?>
                                            <i class="fas fa-trophy"></i>
                                        <?php else: ?>
                                            <?php echo $index + 1; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                        <div class="item-detail">Fantasy League Team</div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo number_format($team['team_score']); ?> pts</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-flag"></i>
                            <p>No team standings available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Best Attack -->
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-crosshairs"></i>
                            Best Attack
                        </h4>
                        <span class="card-badge">Goals Scored</span>
                    </div>
                    <?php if (!empty($top_attacking_teams)): ?>
                        <?php foreach ($top_attacking_teams as $index => $team): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                        <div class="item-detail">Attacking Prowess</div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo $team['goals_scored']; ?> <i class="fas fa-bullseye"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-crosshairs"></i>
                            <p>No attacking data available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Best Defense -->
                <div class="leaderboard-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-lock"></i>
                            Best Defense
                        </h4>
                        <span class="card-badge">Least Conceded</span>
                    </div>
                    <?php if (!empty($top_defensive_teams)): ?>
                        <?php foreach ($top_defensive_teams as $index => $team): ?>
                            <div class="leaderboard-item <?php 
                                if ($index === 0) echo 'top-1';
                                elseif ($index === 1) echo 'top-2';
                                elseif ($index === 2) echo 'top-3';
                            ?>">
                                <div class="item-left">
                                    <div class="rank-badge <?php 
                                        if ($index === 0) echo 'gold';
                                        elseif ($index === 1) echo 'silver';
                                        elseif ($index === 2) echo 'bronze';
                                    ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="item-info">
                                        <div class="item-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                        <div class="item-detail">Defensive Wall • <?php echo $team['matches_played']; ?> matches</div>
                                    </div>
                                </div>
                                <div class="item-score <?php 
                                    if ($index === 0) echo 'gold';
                                    elseif ($index === 1) echo 'silver';
                                    elseif ($index === 2) echo 'bronze';
                                ?>"><?php echo $team['goals_conceded']; ?> <i class="fas fa-shield"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-lock"></i>
                            <p>No defensive data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/footer.php'; ?>
    <?php endif; ?>
    <script>
        window.addEventListener('load', function() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            setTimeout(() => {
                loadingSpinner.classList.add('hidden');
            }, 500);
        });

    </script>
</body>

</html>