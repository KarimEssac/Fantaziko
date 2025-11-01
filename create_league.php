<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leagues'");
$next_league_id = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {

        $league_name = trim($_POST['league_name']);
        $num_of_players = intval($_POST['num_of_players']);
        $num_of_teams = intval($_POST['num_of_teams']);
        $system = $_POST['system'];
        $positions = $_POST['positions'];

        if ($positions === 'positionless') {
            $universal_score = intval($_POST['universal_score']);
            $universal_assist = intval($_POST['universal_assist']);
            $universal_clean_sheet = intval($_POST['universal_clean_sheet']);
            $gk_save_penalty = intval($_POST['gk_save_penalty']);
            $miss_penalty = intval($_POST['miss_penalty']);
            $yellow_card = intval($_POST['yellow_card']);
            $red_card = intval($_POST['red_card']);
            $gk_score = $universal_score;
            $gk_assist = $universal_assist;
            $gk_clean_sheet = $universal_clean_sheet;
            $def_score = $universal_score;
            $def_assist = $universal_assist;
            $def_clean_sheet = $universal_clean_sheet;
            $mid_score = $universal_score;
            $mid_assist = $universal_assist;
            $for_score = $universal_score;
            $for_assist = $universal_assist;
        } else {
            $gk_save_penalty = intval($_POST['gk_save_penalty']);
            $gk_score = intval($_POST['gk_score']);
            $gk_assist = intval($_POST['gk_assist']);
            $gk_clean_sheet = intval($_POST['gk_clean_sheet']);
            $def_clean_sheet = intval($_POST['def_clean_sheet']);
            $def_assist = intval($_POST['def_assist']);
            $def_score = intval($_POST['def_score']);
            $mid_assist = intval($_POST['mid_assist']);
            $mid_score = intval($_POST['mid_score']);
            $miss_penalty = intval($_POST['miss_penalty']);
            $for_score = intval($_POST['for_score']);
            $for_assist = intval($_POST['for_assist']);
            $yellow_card = intval($_POST['yellow_card']);
            $red_card = intval($_POST['red_card']);
        }
        
        $triple_captain = intval($_POST['triple_captain']);
        $bench_boost = intval($_POST['bench_boost']);
        $wild_card = intval($_POST['wild_card']);
        $total_players = $num_of_teams * $num_of_players;
        $price = $total_players * 10;
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO leagues (name, owner, num_of_players, num_of_teams, system, positions,
                               triple_captain, bench_boost, wild_card, price, activated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $league_name, $user_id, $num_of_players, $num_of_teams, $system, $positions,
            $triple_captain, $bench_boost, $wild_card, $price
        ]);
        
        $league_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("
            INSERT INTO league_roles (league_id, gk_save_penalty, gk_score, gk_assist, 
                                    gk_clean_sheet, def_clean_sheet, def_assist, def_score,
                                    mid_assist, mid_score, miss_penalty, for_score, 
                                    for_assist, yellow_card, red_card)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $league_id, $gk_save_penalty, $gk_score, $gk_assist, $gk_clean_sheet,
            $def_clean_sheet, $def_assist, $def_score, $mid_assist, $mid_score,
            $miss_penalty, $for_score, $for_assist, $yellow_card, $red_card
        ]);
        $stmt = $pdo->prepare("
            INSERT INTO league_contributors (user_id, league_id, role, total_score)
            VALUES (?, ?, 'Admin', 0)
        ");
        $stmt->execute([$user_id, $league_id]);
        $token = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $pdo->prepare("INSERT INTO league_tokens (league_id, token) VALUES (?, ?)");
        $stmt->execute([$league_id, $token]);
        $pdo->commit();
        header("Location: main.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error creating league: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create League - Fantazina</title>
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
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeInUp 0.6s ease;
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
        
        .page-header h1 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }
        
        .page-header .gradient-text {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-header p {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--border-color);
            border-radius: 10px;
            margin-bottom: 3rem;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            transition: width 0.4s ease;
            box-shadow: 0 0 10px rgba(10, 146, 215, 0.5);
        }
        
        .form-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        body.dark-mode .form-container {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }
        
        .form-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .form-step.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .step-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .step-description {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.8rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem 1.3rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }
        
        .form-group input[type="number"] {
            appearance: textfield;
        }
        
        .form-group input[type="number"]::-webkit-inner-spin-button,
        .form-group input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        
        .system-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .system-option {
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: var(--bg-primary);
        }
        
        .system-option:hover {
            border-color: var(--gradient-end);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.2);
        }
        
        .system-option input[type="radio"] {
            display: none;
        }
        
        .system-option input[type="radio"]:checked + .system-content {
            color: var(--gradient-end);
        }
        
        .system-option input[type="radio"]:checked ~ .system-option {
            border-color: var(--gradient-end);
            background: rgba(10, 146, 215, 0.05);
        }
        
        .system-option.selected {
            border-color: var(--gradient-end);
            background: rgba(10, 146, 215, 0.05);
        }
        
        .system-content i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .system-option.selected .system-content {
            color: var(--gradient-end);
        }
        
        .system-content h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .system-content p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .role-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        body.dark-mode .role-section {
            background: rgba(10, 20, 35, 0.5);
        }
        
        .role-section h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gradient-end);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .role-section h4 i {
            font-size: 1.3rem;
        }
        
        .price-display {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.3);
        }
        
        .price-display h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }
        
        .price-display .price {
            font-size: 3rem;
            font-weight: 900;
        }
        
        .price-display p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }
        
        .form-navigation {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 2.5rem;
        }
        
        .btn-nav {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-prev {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-prev:hover {
            background: var(--card-hover);
            border-color: var(--text-secondary);
            color: var(--text-primary);
            transform: translateX(-3px);
        }
        
        .btn-next,
        .btn-submit {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
        }
        
        .btn-next:hover,
        .btn-submit:hover {
            transform: translateX(3px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
            padding: 1rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .info-box {
            background: rgba(10, 146, 215, 0.1);
            border: 1px solid rgba(10, 146, 215, 0.3);
            color: var(--text-primary);
            padding: 1rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
        }
        
        .info-box i {
            color: var(--gradient-end);
            margin-top: 0.2rem;
        }
        
        @media (max-width: 768px) {
            nav {
                padding: 1rem 3%;
            }
            
            .logo-container img {
                height: 35px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .theme-toggle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .main-container {
                padding: 120px 3% 40px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .form-container {
                padding: 2rem 1.5rem;
            }
            
            .step-title {
                font-size: 1.5rem;
            }
            
            .form-row,
            .form-row-3,
            .system-options {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 0.7rem 1.5rem;
                font-size: 0.9rem;
            }
            
            .btn-nav {
                padding: 0.9rem 1.8rem;
                font-size: 0.95rem;
            }
            
            .price-display .price {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
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
            <a href="main.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Create Your <span class="gradient-text">Fantasy League</span></h1>
            <p>Set up your custom league in just a few steps</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width: 33.33%"></div>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="createLeagueForm">
                <!-- Step 1: Basic Information -->
                <div class="form-step active" data-step="1">
                    <h3 class="step-title">Basic Information</h3>
                    <p class="step-description">Let's start with the fundamentals of your league</p>

                    <div class="form-group">
                        <label for="league_name">
                            <i class="fas fa-trophy"></i> League Name
                        </label>
                        <input type="text" id="league_name" name="league_name" 
                               placeholder="Enter your league name" required 
                               maxlength="100">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="num_of_teams">
                                <i class="fas fa-shield-alt"></i> Number of Teams
                            </label>
                            <input type="number" id="num_of_teams" name="num_of_teams" 
                                   placeholder="e.g., 8" required min="2" max="50" value="8">
                        </div>

                        <div class="form-group">
                            <label for="num_of_players">
                                <i class="fas fa-users"></i> Players per Team
                            </label>
                            <input type="number" id="num_of_players" name="num_of_players" 
                                   placeholder="e.g., 11" required min="1" max="30" value="11">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-gamepad"></i> League System
                        </label>
                        <div class="system-options">
                            <label class="system-option" data-system="Budget">
                                <input type="radio" name="system" value="Budget" required>
                                <div class="system-content">
                                    <i class="fas fa-coins"></i>
                                    <h4>Budget Mode</h4>
                                    <p>Players have prices and you manage a budget</p>
                                </div>
                            </label>

                            <label class="system-option" data-system="No Limits">
                                <input type="radio" name="system" value="No Limits" checked required>
                                <div class="system-content">
                                    <i class="fas fa-infinity"></i>
                                    <h4>No Limits</h4>
                                    <p>Pick any players without budget constraints</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-users-cog"></i> Player Positions
                        </label>
                        <div class="system-options">
                            <label class="system-option" data-positions="positions">
                                <input type="radio" name="positions" value="positions" checked required>
                                <div class="system-content">
                                    <i class="fas fa-chess"></i>
                                    <h4>With Positions</h4>
                                    <p>Players assigned specific roles: GK, DEF, MID, ATT</p>
                                </div>
                            </label>

                            <label class="system-option" data-positions="positionless">
                                <input type="radio" name="positions" value="positionless" required>
                                <div class="system-content">
                                    <i class="fas fa-users"></i>
                                    <h4>Positionless</h4>
                                    <p>All players score equally regardless of position</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <div></div>
                        <button type="button" class="btn-nav btn-next" onclick="nextStep()">
                            Next Step <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Scoring Rules -->
                <div class="form-step" data-step="2">
                    <h3 class="step-title">Scoring Rules & Power-ups</h3>
                    <p class="step-description">Customize how players earn points in your league</p>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label style="display: flex; align-items: center; gap: 0.8rem; cursor: pointer; padding: 1rem; background: var(--bg-secondary); border-radius: 12px; border: 2px solid var(--border-color); transition: all 0.3s ease;">
                            <input type="checkbox" id="useDefaultPoints" style="width: 20px; height: 20px; cursor: pointer;">
                            <div>
                                <strong style="font-size: 1.05rem; color: var(--text-primary);">
                                    <i class="fas fa-magic"></i> Use Premier League Default Points System
                                </strong>
                                <p style="font-size: 0.9rem; color: var(--text-secondary); margin: 0.3rem 0 0 0;">
                                    Automatically fill with standard Fantasy Premier League scoring rules
                                </p>
                            </div>
                        </label>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <span>Set point values for different actions. Use negative numbers for penalties.</span>
                    </div>

                    <!-- Positions Mode -->
                    <div id="positionsMode">
                        <!-- Goalkeeper -->
                        <div class="role-section">
                            <h4><i class="fas fa-hand-paper"></i> Goalkeeper (GK)</h4>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label for="gk_score">Goals</label>
                                    <input type="number" id="gk_score" name="gk_score" placeholder="e.g., 10">
                                </div>
                                <div class="form-group">
                                    <label for="gk_assist">Assists</label>
                                    <input type="number" id="gk_assist" name="gk_assist" placeholder="e.g., 3">
                                </div>
                                <div class="form-group">
                                    <label for="gk_save_penalty">Saved Penalty</label>
                                    <input type="number" id="gk_save_penalty" name="gk_save_penalty" placeholder="e.g., 5">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="gk_clean_sheet">Clean Sheet</label>
                                <input type="number" id="gk_clean_sheet" name="gk_clean_sheet" placeholder="e.g., 4">
                            </div>
                        </div>

                        <!-- Defender -->
                        <div class="role-section">
                            <h4><i class="fas fa-shield-alt"></i> Defender (DEF)</h4>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label for="def_score">Goals</label>
                                    <input type="number" id="def_score" name="def_score" placeholder="e.g., 6">
                                </div>
                                <div class="form-group">
                                    <label for="def_assist">Assists</label>
                                    <input type="number" id="def_assist" name="def_assist" placeholder="e.g., 3">
                                </div>
                                <div class="form-group">
                                    <label for="def_clean_sheet">Clean Sheet</label>
                                    <input type="number" id="def_clean_sheet" name="def_clean_sheet" placeholder="e.g., 4">
                                </div>
                            </div>
                        </div>

                        <!-- Midfielder -->
                        <div class="role-section">
                            <h4><i class="fas fa-running"></i> Midfielder (MID)</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="mid_score">Goals</label>
                                    <input type="number" id="mid_score" name="mid_score" placeholder="e.g., 5">
                                </div>
                                <div class="form-group">
                                    <label for="mid_assist">Assists</label>
                                    <input type="number" id="mid_assist" name="mid_assist" placeholder="e.g., 3">
                                </div>
                            </div>
                        </div>

                        <!-- Forward -->
                        <div class="role-section">
                            <h4><i class="fas fa-futbol"></i> Forward (ATT)</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="for_score">Goals</label>
                                    <input type="number" id="for_score" name="for_score" placeholder="e.g., 4">
                                </div>
                                <div class="form-group">
                                    <label for="for_assist">Assists</label>
                                    <input type="number" id="for_assist" name="for_assist" placeholder="e.g., 3">
                                </div>
                            </div>
                        </div>

                        <!-- Penalties & Cards -->
                        <div class="role-section">
                            <h4><i class="fas fa-exclamation-triangle"></i> Penalties & Cards</h4>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label for="miss_penalty">Missed Penalty</label>
                                    <input type="number" id="miss_penalty" name="miss_penalty" placeholder="e.g., -2">
                                </div>
                                <div class="form-group">
                                    <label for="yellow_card">Yellow Card</label>
                                    <input type="number" id="yellow_card" name="yellow_card" placeholder="e.g., -1">
                                </div>
                                <div class="form-group">
                                    <label for="red_card">Red Card</label>
                                    <input type="number" id="red_card" name="red_card" placeholder="e.g., -3">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Positionless Mode -->
                    <div id="positionlessMode" style="display: none;">
                        <div class="role-section">
                            <h4><i class="fas fa-star"></i> Universal Scoring</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="universal_score">Goal Scored</label>
                                    <input type="number" id="universal_score" name="universal_score" placeholder="e.g., 5">
                                </div>
                                <div class="form-group">
                                    <label for="universal_assist">Assist</label>
                                    <input type="number" id="universal_assist" name="universal_assist" placeholder="e.g., 3">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="universal_clean_sheet">Clean Sheet</label>
                                <input type="number" id="universal_clean_sheet" name="universal_clean_sheet" placeholder="e.g., 4">
                                <small style="color: var(--text-secondary); display: block; margin-top: 0.3rem;">
                                    <i class="fas fa-info-circle"></i> Only for Goalkeepers and Defenders
                                </small>
                            </div>
                        </div>

                        <div class="role-section">
                            <h4><i class="fas fa-exclamation-triangle"></i> Special Actions</h4>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label for="gk_save_penalty_positionless">Saved Penalty (GK)</label>
                                    <input type="number" id="gk_save_penalty_positionless" name="gk_save_penalty" placeholder="e.g., 5">
                                </div>
                                <div class="form-group">
                                    <label for="miss_penalty_positionless">Missed Penalty</label>
                                    <input type="number" id="miss_penalty_positionless" name="miss_penalty" placeholder="e.g., -2">
                                </div>
                            </div>
                        </div>

                        <div class="role-section">
                            <h4><i class="fas fa-exclamation-triangle"></i> Disciplinary</h4>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="yellow_card_positionless">Yellow Card</label>
                                    <input type="number" id="yellow_card_positionless" name="yellow_card" placeholder="e.g., -1">
                                </div>
                                <div class="form-group">
                                    <label for="red_card_positionless">Red Card</label>
                                    <input type="number" id="red_card_positionless" name="red_card" placeholder="e.g., -3">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Power-ups -->
                    <div class="role-section">
                        <h4><i class="fas fa-magic"></i> Power-ups (Per Season)</h4>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label for="triple_captain">
                                    <i class="fas fa-user-astronaut"></i> Triple Captain
                                </label>
                                <input type="number" id="triple_captain" name="triple_captain" 
                                       placeholder="e.g., 1" min="0" max="10" required>
                            </div>
                            <div class="form-group">
                                <label for="bench_boost">
                                    <i class="fas fa-rocket"></i> Bench Boost
                                </label>
                                <input type="number" id="bench_boost" name="bench_boost" 
                                       placeholder="e.g., 1" min="0" max="10" required>
                            </div>
                            <div class="form-group">
                                <label for="wild_card">
                                    <i class="fas fa-exchange-alt"></i> Wild Card
                                </label>
                                <input type="number" id="wild_card" name="wild_card" 
                                       placeholder="e.g., 2" min="0" max="10" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn-nav btn-prev" onclick="prevStep()">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn-nav btn-next" onclick="nextStep()">
                            Next Step <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Price & Submit -->
                <div class="form-step" data-step="3">
                    <h3 class="step-title">Payment Instructions</h3>
                    <p class="step-description">Complete your league setup with payment details</p>

                    <div class="price-display">
                        <h3>League Entry Fee</h3>
                        <div class="price" id="finalPrice">0 EGP</div>
                        <p id="priceBreakdown">Calculated based on total players</p>
                    </div>

                    <div class="role-section" style="margin-bottom: 1.5rem;">
                        <h4><i class="fas fa-money-bill-wave"></i> Payment Instructions</h4>
                        <div style="padding: 1rem 0;">
                            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                                <div style="min-width: 35px; height: 35px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">1</div>
                                <div>
                                    <strong style="display: block; margin-bottom: 0.3rem; color: var(--text-primary);">Pay through Instapay</strong>
                                    <span style="color: var(--text-secondary); font-size: 0.95rem;">Use your bank's Instapay service to transfer the fee</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                                <div style="min-width: 35px; height: 35px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">2</div>
                                <div style="flex: 1;">
                                    <strong style="display: block; margin-bottom: 0.3rem; color: var(--text-primary);">Transfer to account</strong>
                                    <div style="background: var(--bg-secondary); padding: 0.8rem 1rem; border-radius: 8px; font-family: monospace; font-size: 1rem; color: var(--gradient-end); font-weight: 600; margin-top: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
                                        <span>karim.essac@instapay</span>
                                        <i class="fas fa-copy" style="cursor: pointer; opacity: 0.7; transition: opacity 0.3s;" onclick="copyToClipboard('karim.essac@instapay', this)" title="Copy account"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                                <div style="min-width: 35px; height: 35px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">3</div>
                                <div>
                                    <strong style="display: block; margin-bottom: 0.3rem; color: var(--text-primary);">Expand "Add Reason to Transfer"</strong>
                                    <span style="color: var(--text-secondary); font-size: 0.95rem;">Click to expand the additional details section</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                                <div style="min-width: 35px; height: 35px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">4</div>
                                <div>
                                    <strong style="display: block; margin-bottom: 0.3rem; color: var(--text-primary);">Choose "Bill Payments"</strong>
                                    <span style="color: var(--text-secondary); font-size: 0.95rem;">Select this option from the transfer reason dropdown</span>
                                </div>
                            </div>
                            
                            <div style="display: flex; align-items: flex-start; gap: 1rem;">
                                <div style="min-width: 35px; height: 35px; background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem;">5</div>
                                <div style="flex: 1;">
                                    <strong style="display: block; margin-bottom: 0.3rem; color: var(--text-primary);">In the notes, write:</strong>
                                    <div style="background: var(--bg-secondary); padding: 0.8rem 1rem; border-radius: 8px; font-family: monospace; font-size: 0.95rem; color: var(--text-primary); margin-top: 0.5rem; display: flex; align-items: center; justify-content: space-between; line-height: 1.6;">
                                        <span id="paymentNote">League: <span id="leagueNameDisplay">[Your League Name]</span>, ID: <?php echo $next_league_id; ?></span>
                                        <i class="fas fa-copy" style="cursor: pointer; opacity: 0.7; transition: opacity 0.3s; margin-left: 1rem;" onclick="copyPaymentNote(this)" title="Copy note"></i>
                                    </div>
                                    <small style="color: var(--text-secondary); display: block; margin-top: 0.5rem;">
                                        <i class="fas fa-exclamation-circle"></i> This helps us identify your league quickly
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-lightbulb"></i>
                        <div>
                            <strong>What happens next?</strong><br>
                            After submitting, your league will be created in setup mode. Once we confirm your payment, we'll activate your league and you can start adding teams and players!
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn-nav btn-prev" onclick="prevStep()">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="submit" class="btn-nav btn-submit">
                            <i class="fas fa-check-circle"></i> Create League
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            
            if (savedTheme === 'light') {
                body.classList.remove('dark-mode');
            } else {
                body.classList.add('dark-mode');
            }
        })();
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');

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
        let currentStep = 1;
        const totalSteps = 3;

        function updateProgress() {
            const progress = (currentStep / totalSteps) * 100;
            document.getElementById('progressFill').style.width = progress + '%';
        }

        function showStep(step) {
            document.querySelectorAll('.form-step').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`[data-step="${step}"]`).classList.add('active');
            updateProgress();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (step === 3) {
                updatePrice();
            }
        }

        function nextStep() {
            const currentStepEl = document.querySelector(`[data-step="${currentStep}"]`);
            const inputs = currentStepEl.querySelectorAll('input[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value || (input.type === 'radio' && !currentStepEl.querySelector(`input[name="${input.name}"]:checked`))) {
                    isValid = false;
                    input.focus();
                    return;
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields before proceeding.');
                return;
            }

            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }
        document.querySelectorAll('.system-option').forEach(option => {
            option.addEventListener('click', function() {
                const radioInput = this.querySelector('input[type="radio"]');
                const radioName = radioInput.name;
                document.querySelectorAll(`input[name="${radioName}"]`).forEach(input => {
                    input.closest('.system-option').classList.remove('selected');
                });
                this.classList.add('selected');
                radioInput.checked = true;
                if (radioName === 'positions') {
                    togglePositionMode(radioInput.value);
                }
            });
        });
        document.querySelector('input[name="system"]:checked').closest('.system-option').classList.add('selected');
        document.querySelector('input[name="positions"]:checked').closest('.system-option').classList.add('selected');
        function togglePositionMode(mode) {
            const positionsMode = document.getElementById('positionsMode');
            const positionlessMode = document.getElementById('positionlessMode');
            
            if (mode === 'positionless') {
                positionsMode.style.display = 'none';
                positionlessMode.style.display = 'block';
                positionsMode.querySelectorAll('input').forEach(input => {
                    input.removeAttribute('required');
                });
                positionlessMode.querySelectorAll('input').forEach(input => {
                    input.setAttribute('required', 'required');
                });
            } else {
                positionsMode.style.display = 'block';
                positionlessMode.style.display = 'none';
                positionsMode.querySelectorAll('input').forEach(input => {
                    input.setAttribute('required', 'required');
                });
                positionlessMode.querySelectorAll('input').forEach(input => {
                    input.removeAttribute('required');
                });
            }
        }

        const initialPositionMode = document.querySelector('input[name="positions"]:checked').value;
        togglePositionMode(initialPositionMode);
        const useDefaultPoints = document.getElementById('useDefaultPoints');
        
        useDefaultPoints.addEventListener('change', function() {
            if (this.checked) {
                applyDefaultPoints();
            } else {
                clearAllPoints();
            }
        });

        function applyDefaultPoints() {
            const positionMode = document.querySelector('input[name="positions"]:checked').value;
            
            if (positionMode === 'positions') {
                document.getElementById('gk_score').value = 10;
                document.getElementById('gk_assist').value = 3;
                document.getElementById('gk_save_penalty').value = 5;
                document.getElementById('gk_clean_sheet').value = 4;
                
                document.getElementById('def_score').value = 6;
                document.getElementById('def_assist').value = 3;
                document.getElementById('def_clean_sheet').value = 4;
                
                document.getElementById('mid_score').value = 5;
                document.getElementById('mid_assist').value = 3;
                
                document.getElementById('for_score').value = 4;
                document.getElementById('for_assist').value = 3;
                
                document.getElementById('miss_penalty').value = -2;
                document.getElementById('yellow_card').value = -1;
                document.getElementById('red_card').value = -3;
            } else {
                document.getElementById('universal_score').value = 5;
                document.getElementById('universal_assist').value = 3;
                document.getElementById('universal_clean_sheet').value = 4;
                document.getElementById('gk_save_penalty_positionless').value = 5;
                document.getElementById('miss_penalty_positionless').value = -2;
                document.getElementById('yellow_card_positionless').value = -1;
                document.getElementById('red_card_positionless').value = -3;
            }
            document.getElementById('triple_captain').value = 1;
            document.getElementById('bench_boost').value = 1;
            document.getElementById('wild_card').value = 1;
        }

        function clearAllPoints() {
            document.getElementById('gk_score').value = '';
            document.getElementById('gk_assist').value = '';
            document.getElementById('gk_save_penalty').value = '';
            document.getElementById('gk_clean_sheet').value = '';
            document.getElementById('def_score').value = '';
            document.getElementById('def_assist').value = '';
            document.getElementById('def_clean_sheet').value = '';
            document.getElementById('mid_score').value = '';
            document.getElementById('mid_assist').value = '';
            document.getElementById('for_score').value = '';
            document.getElementById('for_assist').value = '';
            document.getElementById('miss_penalty').value = '';
            document.getElementById('yellow_card').value = '';
            document.getElementById('red_card').value = '';
            document.getElementById('universal_score').value = '';
            document.getElementById('universal_assist').value = '';
            document.getElementById('universal_clean_sheet').value = '';
            document.getElementById('gk_save_penalty_positionless').value = '';
            document.getElementById('miss_penalty_positionless').value = '';
            document.getElementById('yellow_card_positionless').value = '';
            document.getElementById('red_card_positionless').value = '';
            document.getElementById('triple_captain').value = '';
            document.getElementById('bench_boost').value = '';
            document.getElementById('wild_card').value = '';
        }
        document.getElementById('league_name').addEventListener('input', function() {
            const leagueName = this.value || '[Your League Name]';
            document.getElementById('leagueNameDisplay').textContent = leagueName;
        });
        function copyToClipboard(text, icon) {
            navigator.clipboard.writeText(text).then(() => {
                const originalIcon = icon.className;
                icon.className = 'fas fa-check';
                icon.style.opacity = '1';
                icon.style.color = '#10b981';
                
                setTimeout(() => {
                    icon.className = originalIcon;
                    icon.style.opacity = '0.7';
                    icon.style.color = '';
                }, 2000);
            });
        }

        function copyPaymentNote(icon) {
            const leagueName = document.getElementById('league_name').value || '[Your League Name]';
            const leagueId = '<?php echo $next_league_id; ?>';
            const note = `League: ${leagueName}, ID: ${leagueId}`;
            
            navigator.clipboard.writeText(note).then(() => {
                const originalIcon = icon.className;
                icon.className = 'fas fa-check';
                icon.style.opacity = '1';
                icon.style.color = '#10b981';
                
                setTimeout(() => {
                    icon.className = originalIcon;
                    icon.style.opacity = '0.7';
                    icon.style.color = '';
                }, 2000);
            });
        }

        function updatePrice() {
            const numTeams = parseInt(document.getElementById('num_of_teams').value) || 0;
            const numPlayers = parseInt(document.getElementById('num_of_players').value) || 0;
            const totalPlayers = numTeams * numPlayers;
            const price = totalPlayers * 10;

            document.getElementById('finalPrice').textContent = price.toLocaleString() + ' EGP';
            document.getElementById('priceBreakdown').textContent = 
                `${numTeams} teams × ${numPlayers} players × 10 EGP = ${price.toLocaleString()} EGP`;
        }
        document.getElementById('num_of_teams').addEventListener('input', updatePrice);
        document.getElementById('num_of_players').addEventListener('input', updatePrice);
        document.getElementById('createLeagueForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating League...';
        });
        updateProgress();
    </script>
</body>
</html>