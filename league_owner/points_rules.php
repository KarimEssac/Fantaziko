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
        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $roles = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$roles) {
            $stmt = $pdo->prepare("
                INSERT INTO league_roles (
                    league_id, gk_save_penalty, gk_score, gk_assist, gk_clean_sheet,
                    def_clean_sheet, def_assist, def_score,
                    mid_assist, mid_score, miss_penalty,
                    for_score, for_assist,
                    yellow_card, red_card
                ) VALUES (?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0)
            ");
            $stmt->execute([$league_id]);
            $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
            $stmt->execute([$league_id]);
            $roles = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$not_owner && !$not_activated && !$league_not_found) {
    try {
        if ($league['positions'] === 'positionless') {
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
        $stmt = $pdo->prepare("
            UPDATE league_roles SET 
                gk_save_penalty = ?, gk_score = ?, gk_assist = ?, gk_clean_sheet = ?,
                def_clean_sheet = ?, def_assist = ?, def_score = ?,
                mid_assist = ?, mid_score = ?, miss_penalty = ?,
                for_score = ?, for_assist = ?,
                yellow_card = ?, red_card = ?
            WHERE league_id = ?
        ");
        $stmt->execute([
            $gk_save_penalty, $gk_score, $gk_assist, $gk_clean_sheet,
            $def_clean_sheet, $def_assist, $def_score,
            $mid_assist, $mid_score, $miss_penalty,
            $for_score, $for_assist,
            $yellow_card, $red_card,
            $league_id
        ]);
        
        $success_message = "Points rules updated successfully!";
        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $roles = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error_message = "Error updating points rules: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Points Rules - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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

        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.4s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error);
        }

        .alert i {
            font-size: 1.5rem;
        }

        .league-info-banner {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.3);
        }

        .league-info-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .league-info-title {
            font-size: 1.8rem;
            font-weight: 900;
        }

        .league-info-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .league-info-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        .league-info-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box {
            background: rgba(10, 146, 215, 0.1);
            border: 1px solid rgba(10, 146, 215, 0.3);
            border-left: 4px solid var(--gradient-end);
            color: var(--text-primary);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .info-box i {
            color: var(--gradient-end);
            font-size: 1.5rem;
            margin-top: 0.2rem;
        }

        .info-box-content h4 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gradient-end);
        }

        .info-box-content p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .form-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        body.dark-mode .form-container {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .role-section {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        body.dark-mode .role-section {
            background: rgba(10, 20, 35, 0.5);
        }

        .role-section:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .role-section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .role-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(10, 146, 215, 0.3);
        }

        .role-section h4 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input {
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

        .form-group input:focus {
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

        .btn-gradient:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        .quick-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            padding: 0.7rem 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn:hover {
            border-color: var(--gradient-end);
            color: var(--gradient-end);
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .role-section {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .league-info-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .quick-actions {
                flex-direction: column;
            }

            .quick-action-btn {
                width: 100%;
                justify-content: center;
            }
        }
        /* Loading Spinner Overlay */
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
        <!-- Loading Spinner -->
    <div class="loading-spinner-overlay" id="loadingSpinner">
        <div class="loading-logo">
            <img src="../assets/images/logo white outline.png" alt="Fantazina Logo">
        </div>
        <div class="spinner-large"></div>
        <div class="loading-text">Loading Points Rules Management...</div>
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
                    <p class="not-owner-text">You don't have permission to access this league's settings. Only the league owner can manage points rules.</p>
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
            <!-- Page Header -->
            <div class="page-header">
                <h2 class="page-title">Points Rules</h2>
                <p class="page-subtitle">Configure how players earn points in your league</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- League Info Banner -->
            <div class="league-info-banner">
                <div class="league-info-header">
                    <h3 class="league-info-title"><?php echo htmlspecialchars($league['name']); ?></h3>
                    <div class="league-info-badge">
                        <?php echo $league['positions'] === 'positionless' ? 'Positionless Mode' : 'Positions Mode'; ?>
                    </div>
                </div>
                <div class="league-info-meta">
                    <span><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($league['system']); ?></span>
                    <span><i class="fas fa-users"></i> <?php echo $league['num_of_players']; ?> Players per Team</span>
                    <span><i class="fas fa-shield-alt"></i> <?php echo $league['num_of_teams']; ?> Teams</span>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div class="info-box-content">
                    <h4>About Points Rules</h4>
                    <p>
                        <?php if ($league['positions'] === 'positionless'): ?>
                            In <strong>positionless mode</strong>, all players earn the same points for goals, assists, and clean sheets regardless of their position. Every player is treated equally when it comes to scoring actions.
                        <?php else: ?>
                            In <strong>positions mode</strong>, point values differ based on player positions (GK, DEF, MID, ATT). Set specific point values for each position type. Use positive numbers for rewards and negative numbers for penalties.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <button type="button" class="quick-action-btn" onclick="applyPremierLeagueDefaults()">
                    <i class="fas fa-magic"></i> Premier League Defaults
                </button>
                <button type="button" class="quick-action-btn" onclick="resetAllPoints()">
                    <i class="fas fa-undo"></i> Reset All to Zero
                </button>
            </div>
            <form method="POST" id="pointsForm">
                <?php if ($league['positions'] === 'positionless'): ?>
                    <!-- Positionless Mode -->
                    <div class="form-container">
                        <div class="info-box" style="margin-bottom: 2rem;">
                            <i class="fas fa-lightbulb"></i>
                            <div class="info-box-content">
                                <h4>Positionless Mode</h4>
                                <p>
                                    All players (GK, DEF, MID, ATT) earn <strong>the same points</strong> for goals, assists, and clean sheets. There's no distinction between positions for these actions.
                                </p>
                            </div>
                        </div>

                        <!-- Universal Scoring -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4>Universal Scoring (Applies to All Players)</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="universal_score">
                                        <i class="fas fa-futbol"></i> Goal Scored
                                    </label>
                                    <input type="number" id="universal_score" name="universal_score" 
                                           value="<?php echo $roles['gk_score']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="universal_assist">
                                        <i class="fas fa-hands-helping"></i> Assist 
                                    </label>
                                    <input type="number" id="universal_assist" name="universal_assist" 
                                           value="<?php echo $roles['gk_assist']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="universal_clean_sheet">
                                        <i class="fas fa-shield-alt"></i> Clean Sheet
                                    </label>
                                    <input type="number" id="universal_clean_sheet" name="universal_clean_sheet" 
                                           value="<?php echo $roles['gk_clean_sheet']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Special Actions -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                                <h4>Special Actions</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="gk_save_penalty">
                                        <i class="fas fa-hand-paper"></i> Saved Penalty 
                                    </label>
                                    <input type="number" id="gk_save_penalty" name="gk_save_penalty" 
                                           value="<?php echo $roles['gk_save_penalty']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="miss_penalty">
                                        <i class="fas fa-times-circle"></i> Missed Penalty
                                    </label>
                                    <input type="number" id="miss_penalty" name="miss_penalty" 
                                           value="<?php echo $roles['miss_penalty']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Disciplinary -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h4>Disciplinary</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="yellow_card">
                                        <i class="fas fa-square" style="color: #fbbf24;"></i> Yellow Card
                                    </label>
                                    <input type="number" id="yellow_card" name="yellow_card" 
                                           value="<?php echo $roles['yellow_card']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="red_card">
                                        <i class="fas fa-square" style="color: #ef4444;"></i> Red Card
                                    </label>
                                    <input type="number" id="red_card" name="red_card" 
                                           value="<?php echo $roles['red_card']; ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Positions Mode -->
                    <div class="form-container">
                        <!-- Goalkeeper -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-hand-paper"></i>
                                </div>
                                <h4>Goalkeeper (GK)</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="gk_score">
                                        <i class="fas fa-futbol"></i> Goals
                                    </label>
                                    <input type="number" id="gk_score" name="gk_score" 
                                           value="<?php echo $roles['gk_score']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="gk_assist">
                                        <i class="fas fa-hands-helping"></i> Assists
                                    </label>
                                    <input type="number" id="gk_assist" name="gk_assist" 
                                           value="<?php echo $roles['gk_assist']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="gk_save_penalty">
                                        <i class="fas fa-hand-rock"></i> Saved Penalty
                                    </label>
                                    <input type="number" id="gk_save_penalty" name="gk_save_penalty" 
                                           value="<?php echo $roles['gk_save_penalty']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="gk_clean_sheet">
                                        <i class="fas fa-shield-alt"></i> Clean Sheet
                                    </label>
                                    <input type="number" id="gk_clean_sheet" name="gk_clean_sheet" 
                                           value="<?php echo $roles['gk_clean_sheet']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Defender -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h4>Defender (DEF)</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="def_score">
                                        <i class="fas fa-futbol"></i> Goals
                                    </label>
                                    <input type="number" id="def_score" name="def_score" 
                                           value="<?php echo $roles['def_score']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="def_assist">
                                        <i class="fas fa-hands-helping"></i> Assists
                                    </label>
                                    <input type="number" id="def_assist" name="def_assist" 
                                           value="<?php echo $roles['def_assist']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="def_clean_sheet">
                                        <i class="fas fa-shield-alt"></i> Clean Sheet
                                    </label>
                                    <input type="number" id="def_clean_sheet" name="def_clean_sheet" 
                                           value="<?php echo $roles['def_clean_sheet']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Midfielder -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-running"></i>
                                </div>
                                <h4>Midfielder (MID)</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="mid_score">
                                        <i class="fas fa-futbol"></i> Goals
                                    </label>
                                    <input type="number" id="mid_score" name="mid_score" 
                                           value="<?php echo $roles['mid_score']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="mid_assist">
                                        <i class="fas fa-hands-helping"></i> Assists
                                    </label>
                                    <input type="number" id="mid_assist" name="mid_assist" 
                                           value="<?php echo $roles['mid_assist']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Forward -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-futbol"></i>
                                </div>
                                <h4>Forward (ATT)</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="for_score">
                                        <i class="fas fa-futbol"></i> Goals
                                    </label>
                                    <input type="number" id="for_score" name="for_score" 
                                           value="<?php echo $roles['for_score']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="for_assist">
                                        <i class="fas fa-hands-helping"></i> Assists
                                    </label>
                                    <input type="number" id="for_assist" name="for_assist" 
                                           value="<?php echo $roles['for_assist']; ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Penalties & Cards -->
                        <div class="role-section">
                            <div class="role-section-header">
                                <div class="role-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h4>Penalties & Cards</h4>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="miss_penalty">
                                        <i class="fas fa-times-circle"></i> Missed Penalty
                                    </label>
                                    <input type="number" id="miss_penalty" name="miss_penalty" 
                                           value="<?php echo $roles['miss_penalty']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="yellow_card">
                                        <i class="fas fa-square" style="color: #fbbf24;"></i> Yellow Card
                                    </label>
                                    <input type="number" id="yellow_card" name="yellow_card" 
                                           value="<?php echo $roles['yellow_card']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="red_card">
                                        <i class="fas fa-square" style="color: #ef4444;"></i> Red Card
                                    </label>
                                    <input type="number" id="red_card" name="red_card" 
                                           value="<?php echo $roles['red_card']; ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i> Save Points Rules
                    </button>
                </div>
            </form>
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
        function applyPremierLeagueDefaults() {
            const isPositionless = <?php echo $league['positions'] === 'positionless' ? 'true' : 'false'; ?>;
            
            if (confirm('This will overwrite all current point values with Premier League defaults. Continue?')) {
                if (isPositionless) {
                    document.getElementById('universal_score').value = 5;
                    document.getElementById('universal_assist').value = 3;
                    document.getElementById('universal_clean_sheet').value = 4;
                    document.getElementById('gk_save_penalty').value = 5;
                    document.getElementById('miss_penalty').value = -2;
                    document.getElementById('yellow_card').value = -1;
                    document.getElementById('red_card').value = -3;
                } else {
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
                }
                showNotification('Premier League defaults applied successfully!', 'success');
            }
        }

        function resetAllPoints() {
            const isPositionless = <?php echo $league['positions'] === 'positionless' ? 'true' : 'false'; ?>;
            
            if (confirm('This will reset all point values to zero. Continue?')) {
                const inputs = document.querySelectorAll('#pointsForm input[type="number"]');
                inputs.forEach(input => {
                    input.value = 0;
                });
                showNotification('All points reset to zero!', 'success');
            }
        }

        function showNotification(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass}`;
            alert.innerHTML = `
                <i class="fas ${iconClass}"></i>
                <span>${message}</span>
            `;
            
            const pageHeader = document.querySelector('.page-header');
            pageHeader.insertAdjacentElement('afterend', alert);
            
            setTimeout(() => {
                alert.style.animation = 'slideOut 0.4s ease';
                setTimeout(() => alert.remove(), 400);
            }, 3000);
        }

        document.getElementById('pointsForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        });

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateY(0);
                }
                to {
                    opacity: 0;
                    transform: translateY(-10px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>