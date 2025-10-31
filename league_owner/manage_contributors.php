<?php
session_start();
require_once '../config/db.php';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not authenticated']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    
    if ($_GET['ajax'] === 'get_contributor_team' && isset($_GET['user_id']) && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT owner, other_owner, activated FROM leagues WHERE id = ?");
            $stmt->execute([$_GET['league_id']]);
            $league_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$league_check) {
                echo json_encode(['error' => 'League not found']);
                exit();
            }
            
            if ($league_check['owner'] != $user_id && $league_check['other_owner'] != $user_id) {
                echo json_encode(['error' => 'Access denied']);
                exit();
            }
            
            if (!$league_check['activated']) {
                echo json_encode(['error' => 'League not activated']);
                exit();
            }

            $stmt = $pdo->prepare("
                SELECT 
                    lc.*,
                    a.username
                FROM league_contributors lc
                JOIN accounts a ON lc.user_id = a.id
                WHERE lc.user_id = ? AND lc.league_id = ?
            ");
            $stmt->execute([$_GET['user_id'], $_GET['league_id']]);
            $contributor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contributor) {
                echo json_encode(['error' => 'Contributor not found']);
                exit();
            }
            $stmt = $pdo->prepare("
                SELECT 
                    cp.slot_number, cp.is_benched,
                    lp.player_name, lp.player_role, lp.player_price, lp.total_points,
                    lt.team_name
                FROM contributor_players cp
                JOIN league_players lp ON cp.player_id = lp.player_id
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE cp.user_id = ? AND cp.league_id = ?
                ORDER BY cp.is_benched ASC, 
                    FIELD(lp.player_role, 'GK', 'DEF', 'MID', 'ATT'),
                    cp.slot_number ASC
            ");
            $stmt->execute([$_GET['user_id'], $_GET['league_id']]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $starting_xi = [];
            $substitutes = [];
            
            foreach ($players as $row) {
                $pos_enum = $row['player_role']; 
                $slot = $row['slot_number'];
                $is_bench = $row['is_benched'];
                
                $label_base = [
                    'GK' => 'Goalkeeper',
                    'DEF' => 'Defender',
                    'MID' => 'Midfielder',
                    'ATT' => 'Forward'
                ][$pos_enum];
                
                $position = strtolower($label_base);
                if ($pos_enum === 'ATT') {
                    $position = 'forward';
                }
                
                $label = $label_base;
                
                if ($is_bench) {
                    $label = 'Sub ' . ($pos_enum === 'GK' ? 'GK' : $label_base);
                    $position = 'sub_' . ($pos_enum === 'GK' ? 'goalkeeper' : $position);
                }
                
                if ($pos_enum !== 'GK') {
                    $label .= ' ' . $slot;
                    $position .= $slot;
                }
                
                $player = [
                    'player_name' => $row['player_name'],
                    'player_role' => $row['player_role'],
                    'player_price' => $row['player_price'],
                    'total_points' => $row['total_points'],
                    'team_name' => $row['team_name'],
                    'position' => $position,
                    'position_label' => $label
                ];
                
                if ($is_bench) {
                    $substitutes[] = $player;
                } else {
                    $starting_xi[] = $player;
                }
            }
            
            echo json_encode([
                'username' => $contributor['username'],
                'role' => $contributor['role'],
                'total_score' => $contributor['total_score'],
                'starting_xi' => $starting_xi,
                'substitutes' => $substitutes
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    echo json_encode(['error' => 'Invalid AJAX request']);
    exit();
}

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
$system_type   = $league['system'] ?? 'Standard';         
$position_type = $league['position_type'] ?? 'Standard';   

$hide_price    = ($system_type   === 'No Limits');
$hide_position = ($league['positions'] === 'positionless');
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
            SELECT 
                lc.user_id,
                lc.league_id,
                lc.role,
                lc.total_score,
                a.username,
                a.email
            FROM league_contributors lc
            INNER JOIN accounts a ON lc.user_id = a.id
            WHERE lc.league_id = ?
            ORDER BY lc.total_score DESC
        ");
        $stmt->execute([$league_id]);
        $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total contributors count
        $total_contributors = count($contributors);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Contributors - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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

        /* Error Pages */
        .error-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .error-card {
            background: var(--card-bg);
            border-radius: 25px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .error-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
        }

        .error-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }

        .error-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 1rem;
        }

        .error-text {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .error-card.not-found {
            border: 2px solid var(--text-secondary);
        }

        .error-card.not-found .error-icon {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .error-card.not-owner {
            border: 2px solid var(--error);
        }

        .error-card.not-owner .error-icon,
        .error-card.not-owner .error-title {
            color: var(--error);
        }

        .error-card.not-activated {
            border: 2px solid var(--warning);
        }

        .error-card.not-activated .error-icon,
        .error-card.not-activated .error-title {
            color: var(--warning);
        }

        /* Page Header */
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

        /* Stats Card */
        .stats-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
        }

        body.dark-mode .stats-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stats-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.3);
        }

        .stats-info {
            flex: 1;
        }

        .stats-value {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 0.3rem;
        }

        .stats-label {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Contributors Table Card */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        body.dark-mode .table-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .table-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .table-title i {
            color: var(--gradient-end);
            font-size: 1.8rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .contributors-table {
            width: 100%;
            border-collapse: collapse;
        }

        .contributors-table thead {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .contributors-table th {
            padding: 1.2rem 1.5rem;
            text-align: left;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .contributors-table td {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .contributors-table tbody tr {
            transition: all 0.3s ease;
        }

        .contributors-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.05);
        }

        .contributors-table tbody tr:last-child td {
            border-bottom: none;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .rank-1 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #FFFFFF;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }

        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0, #A8A8A8);
            color: #FFFFFF;
            box-shadow: 0 4px 15px rgba(192, 192, 192, 0.4);
        }

        .rank-3 {
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            color: #FFFFFF;
            box-shadow: 0 4px 15px rgba(205, 127, 50, 0.4);
        }

        .rank-other {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }

        .contributor-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.05rem;
        }

        .contributor-email {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        .badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-admin {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .badge-contributor {
            background: rgba(241, 161, 85, 0.2);
            color: #F1A155;
            border: 1px solid rgba(241, 161, 85, 0.3);
        }

        .score-value {
            font-size: 1.3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--gradient-end);
            border: 2px solid var(--gradient-end);
        }

        .btn-secondary:hover {
            background: var(--gradient-end);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state-text {
            font-size: 1.1rem;
        }

        /* Modal */
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
            padding: 0;
            max-width: 1000px;
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

        .modal-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 25px 25px 0 0;
        }

        .modal-header-info {
            flex: 1;
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .modal-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2.5rem;
        }

        .team-section {
            margin-bottom: 2.5rem;
        }

        .team-section:last-child {
            margin-bottom: 0;
        }

        .team-section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .team-section-title i {
            color: var(--gradient-end);
        }

        .team-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .team-table-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .team-table-wrapper::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .team-table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
        }

        .team-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }

        body.dark-mode .team-table {
            background: rgba(10, 20, 35, 0.5);
        }

        .team-table thead {
            background: rgba(29, 96, 172, 0.1);
        }

        body.dark-mode .team-table thead {
            background: rgba(10, 146, 215, 0.15);
        }

        .team-table th {
            padding: 1rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-primary);
        }

        .team-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .team-table tbody tr:last-child td {
            border-bottom: none;
        }

        .team-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.05);
        }

        .position-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .position-gk {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .position-def {
            background: rgba(29, 96, 172, 0.2);
            color: #1D60AC;
        }

        .position-mid {
            background: rgba(10, 146, 215, 0.2);
            color: #0A92D7;
        }

        .position-att {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .loading-spinner {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .loading-spinner i {
            font-size: 3rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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

            .stats-card {
                flex-direction: column;
                text-align: center;
            }

            .table-container {
                overflow-x: scroll;
            }

            .modal-content {
                width: 95%;
            }

            .modal-header {
                padding: 1.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <?php if ($league_not_found): ?>
            <div class="error-container">
                <div class="error-card not-found">
                    <div class="error-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h1 class="error-title">League Not Found</h1>
                    <p class="error-text">The league you're looking for could not be found. It may have been deleted or the link is incorrect.</p>
                    <a href="../main.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($not_owner): ?>
            <div class="error-container">
                <div class="error-card not-owner">
                    <div class="error-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h1 class="error-title">Access Denied</h1>
                    <p class="error-text">You don't have permission to access this league's settings. Only the league owner can manage contributors.</p>
                    <a href="../main.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($not_activated): ?>
            <div class="error-container">
                <div class="error-card not-activated">
                    <div class="error-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h1 class="error-title">League Not Activated Yet</h1>
                    <p class="error-text">This league is currently under review. Please wait until we review your application. You will be notified once your league is activated.</p>
                    <a href="../main.php" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="page-header">
                <h2 class="page-title">Contributors Management</h2>
                <p class="page-subtitle">View and manage league contributors and their teams</p>
            </div>

            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-info">
                    <div class="stats-value"><?php echo $total_contributors; ?></div>
                    <div class="stats-label">Total Contributors</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-trophy"></i>
                        Contributors Leaderboard
                    </h3>
                </div>
                
                <?php if (empty($contributors)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-friends"></i>
                        <p class="empty-state-text">No contributors in this league yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="contributors-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Contributor</th>
                                    <th>Role</th>
                                    <th>Total Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contributors as $index => $contributor): ?>
                                    <?php
                                    $rank = $index + 1;
                                    $rank_class = 'rank-other';
                                    $rank_display = $rank;
                                    
                                    if ($rank === 1) {
                                        $rank_class = 'rank-1';
                                        $rank_display = '🥇';
                                    } elseif ($rank === 2) {
                                        $rank_class = 'rank-2';
                                        $rank_display = '🥈';
                                    } elseif ($rank === 3) {
                                        $rank_class = 'rank-3';
                                        $rank_display = '🥉';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="rank-badge <?php echo $rank_class; ?>">
                                                <?php echo $rank_display; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contributor-name">
                                                <?php echo htmlspecialchars($contributor['username']); ?>
                                            </div>
                                            <div class="contributor-email">
                                                <?php echo htmlspecialchars($contributor['email']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $contributor['role'] === 'Admin' ? 'badge-admin' : 'badge-contributor'; ?>">
                                                <?php echo htmlspecialchars($contributor['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="score-value">
                                                <?php echo number_format($contributor['total_score']); ?> pts
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary" onclick="viewTeam(<?php echo $contributor['user_id']; ?>, <?php echo $contributor['league_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                                View Team
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/footer.php'; ?>
    <?php endif; ?>

    <!-- View Team Modal -->
    <div class="modal-overlay" id="teamModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-info">
                    <h2 class="modal-title" id="modalUsername"></h2>
                    <p class="modal-subtitle" id="modalSubtitle"></p>
                </div>
                <button class="modal-close" onclick="closeTeamModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="loadingSpinner" class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading team data...</p>
                </div>

                <div id="teamContent" style="display: none;">
                    <div class="team-section" id="startingSection">
                        <h3 class="team-section-title">
                            <i class="fas fa-futbol"></i>
                            Starting XI
                        </h3>
                        <div class="team-table-wrapper">
                            <table class="team-table">
                                <thead>
                                    <tr>
                                        <?php if (!$hide_position): ?><th>Position</th><?php endif; ?>
                                        <th>Player Name</th>
                                        <th>Team</th>
                                        <?php if (!$hide_price): ?><th>Price</th><?php endif; ?>
                                        <th>Points</th>
                                    </tr>
                                </thead>
                                <tbody id="startingXIBody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="team-section" id="subsSection">
                        <h3 class="team-section-title">
                            <i class="fas fa-users"></i>
                            Substitutes
                        </h3>
                        <div class="team-table-wrapper">
                            <table class="team-table">
                                <thead>
                                    <tr>
                                        <?php if (!$hide_position): ?><th>Position</th><?php endif; ?>
                                        <th>Player Name</th>
                                        <th>Team</th>
                                        <?php if (!$hide_price): ?><th>Price</th><?php endif; ?>
                                        <th>Points</th>
                                    </tr>
                                </thead>
                                <tbody id="substitutesBody">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="emptyTeam" class="empty-state" style="display: none;">
                        <i class="fas fa-clipboard-list"></i>
                        <p class="empty-state-text">This contributor hasn't selected their team yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewTeam(userId, leagueId) {
            const modal = document.getElementById('teamModal');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const teamContent = document.getElementById('teamContent');
            const emptyTeam = document.getElementById('emptyTeam');
            
            modal.classList.add('active');
            loadingSpinner.style.display = 'block';
            teamContent.style.display = 'none';
            emptyTeam.style.display = 'none';
            document.body.style.overflow = 'hidden';
            
            fetch(`?ajax=get_contributor_team&user_id=${userId}&league_id=${leagueId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        closeTeamModal();
                        return;
                    }
                    
                    loadingSpinner.style.display = 'none';
                    
                    document.getElementById('modalUsername').textContent = data.username + "'s Team";
                    document.getElementById('modalSubtitle').textContent = `${data.role} • ${data.total_score} points`;
                    
                    const startingXI = data.starting_xi || [];
                    const substitutes = data.substitutes || [];
                    
                    if (startingXI.length === 0 && substitutes.length === 0) {
                        emptyTeam.style.display = 'block';
                        return;
                    }
                    
                    teamContent.style.display = 'block';
                    
                    // Populate Starting XI
// Populate Starting XI
const startingBody = document.getElementById('startingXIBody');
if (startingXI.length > 0) {
    let startingHTML = '';
    startingXI.forEach(player => {
        const roleClass = getRoleClass(player.player_role);
        startingHTML += `
            <tr>
                ${<?php echo $hide_position ? 'false' : 'true'; ?> ? `<td><span class="position-badge position-${roleClass}">${player.position_label}</span></td>` : ''}
                <td><strong>${player.player_name || '<em style="color: var(--text-secondary);">Not Selected</em>'}</strong></td>
                <td>${player.team_name || '-'}</td>
                ${<?php echo $hide_price ? 'false' : 'true'; ?> ? `<td>${player.player_price ? parseFloat(player.player_price).toFixed(2) : '-'}</td>` : ''}
                <td><strong>${player.total_points || 0}</strong></td>
            </tr>
        `;
    });
    startingBody.innerHTML = startingHTML;
    document.getElementById('startingSection').style.display = 'block';
} else {
    document.getElementById('startingSection').style.display = 'none';
}

// Populate Substitutes
const subsBody = document.getElementById('substitutesBody');
if (substitutes.length > 0) {
    let subsHTML = '';
    substitutes.forEach(player => {
        const roleClass = getRoleClass(player.player_role);
        subsHTML += `
            <tr>
                ${<?php echo $hide_position ? 'false' : 'true'; ?> ? `<td><span class="position-badge position-${roleClass}">${player.position_label}</span></td>` : ''}
                <td><strong>${player.player_name || '<em style="color: var(--text-secondary);">Not Selected</em>'}</strong></td>
                <td>${player.team_name || '-'}</td>
                ${<?php echo $hide_price ? 'false' : 'true'; ?> ? `<td>${player.player_price ? parseFloat(player.player_price).toFixed(2) : '-'}</td>` : ''}
                <td><strong>${player.total_points || 0}</strong></td>
            </tr>
        `;
    });
    subsBody.innerHTML = subsHTML;
    document.getElementById('subsSection').style.display = 'block';
} else {
    document.getElementById('subsSection').style.display = 'none';
}
                })
                .catch(error => {
                    console.error(error);
                    alert('Error loading team data');
                    closeTeamModal();
                });
        }
        
        function getRoleClass(role) {
            const roleMap = {
                'GK': 'gk',
                'DEF': 'def',
                'MID': 'mid',
                'ATT': 'att'
            };
            return roleMap[role] || 'gk';
        }
        
        function closeTeamModal() {
            const modal = document.getElementById('teamModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Close modal when clicking overlay
        document.getElementById('teamModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTeamModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('teamModal');
                if (modal.classList.contains('active')) {
                    closeTeamModal();
                }
            }
        });
    </script>
</body>
</html>