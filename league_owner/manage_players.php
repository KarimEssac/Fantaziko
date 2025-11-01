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
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_teams' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, team_name, team_score
                FROM league_teams
                WHERE league_id = ?
                ORDER BY team_name
            ");
            $stmt->execute([$_GET['league_id']]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($teams);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_team_players' && isset($_GET['team_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    lt.team_name
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.team_id = ?
                ORDER BY 
                    FIELD(lp.player_role, 'GK', 'DEF', 'MID', 'ATT'),
                    lp.player_name
            ");
            $stmt->execute([$_GET['team_id']]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($players);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_player' && isset($_GET['player_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    lt.team_name,
                    l.name as league_name,
                    l.system as league_system,
                    l.positions as league_positions
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN leagues l ON lp.league_id = l.id
                WHERE lp.player_id = ?
            ");
            $stmt->execute([$_GET['player_id']]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($player) {
                echo json_encode($player);
            } else {
                echo json_encode(['error' => 'Player not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_player_stats' && isset($_GET['player_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    lt.team_name,
                    l.name as league_name,
                    l.system as league_system,
                    l.positions as league_positions
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN leagues l ON lp.league_id = l.id
                WHERE lp.player_id = ?
            ");
            $stmt->execute([$_GET['player_id']]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$player) {
                echo json_encode(['error' => 'Player not found']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
            $stmt->execute([$player['league_id']]);
            $rules = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN scorer = ? THEN 1 END) as goals,
                    COUNT(CASE WHEN assister = ? THEN 1 END) as assists,
                    COUNT(CASE WHEN bonus = ? THEN 1 END) as bonus_count,
                    COUNT(CASE WHEN minus = ? THEN 1 END) as minus_count,
                    SUM(CASE WHEN scorer = ? THEN bonus_points ELSE 0 END) as bonus_from_goals,
                    SUM(CASE WHEN assister = ? THEN bonus_points ELSE 0 END) as bonus_from_assists,
                    SUM(CASE WHEN bonus = ? THEN bonus_points ELSE 0 END) as bonus_from_bonus,
                    SUM(CASE WHEN minus = ? THEN minus_points ELSE 0 END) as minus_from_minus,
                    SUM(yellow_card) as yellow_cards,
                    SUM(red_card) as red_cards,
                    COUNT(CASE WHEN saved_penalty_gk = ? THEN 1 END) as penalties_saved,
                    COUNT(CASE WHEN missed_penalty_player = ? THEN 1 END) as penalties_missed
                FROM matches_points
                WHERE scorer = ? OR assister = ? OR bonus = ? OR minus = ? 
                   OR saved_penalty_gk = ? OR missed_penalty_player = ?
            ");
            $stmt->execute([
                $_GET['player_id'], $_GET['player_id'], $_GET['player_id'], $_GET['player_id'],
                $_GET['player_id'], $_GET['player_id'], $_GET['player_id'], $_GET['player_id'],
                $_GET['player_id'], $_GET['player_id'],
                $_GET['player_id'], $_GET['player_id'], $_GET['player_id'], $_GET['player_id'],
                $_GET['player_id'], $_GET['player_id']
            ]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            $cleanSheets = 0;
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT m.match_id) as clean_sheets
                FROM matches m
                INNER JOIN league_players lp ON lp.team_id IN (m.team1_id, m.team2_id)
                WHERE lp.player_id = ?
                AND (
                    (lp.team_id = m.team1_id AND m.team2_score = 0) OR
                    (lp.team_id = m.team2_id AND m.team1_score = 0)
                )
            ");
            $stmt->execute([$_GET['player_id']]);
            $csResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $cleanSheets = $csResult['clean_sheets'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as lineup_count
                FROM contributor_players
                WHERE league_id = ?
                AND player_id = ?
            ");
            $stmt->execute([$player['league_id'], $_GET['player_id']]);
            $lineupResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $lineupCount = $lineupResult['lineup_count'] ?? 0;
            $totalPoints = $player['total_points'] ?? 0;
            $response = [
                'player' => $player,
                'rules' => $rules,
                'lineup_count' => $lineupCount,
                'stats' => [
                    'goals' => $stats['goals'] ?? 0,
                    'assists' => $stats['assists'] ?? 0,
                    'clean_sheets' => $cleanSheets,
                    'penalties_saved' => $stats['penalties_saved'] ?? 0,
                    'penalties_missed' => $stats['penalties_missed'] ?? 0,
                    'yellow_cards' => $stats['yellow_cards'] ?? 0,
                    'red_cards' => $stats['red_cards'] ?? 0,
                    'bonus_count' => $stats['bonus_count'] ?? 0,
                    'minus_count' => $stats['minus_count'] ?? 0,
                    'total_points' => $totalPoints
                ]
            ];
            
            echo json_encode($response);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$league_not_found && !$not_owner && !$not_activated) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_player':
                    $player_role = $league['positions'] === 'positionless' ? null : $_POST['player_role'];
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO league_players (league_id, team_id, player_name, player_role, player_price)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $league_id,
                        $_POST['team_id'],
                        $_POST['player_name'],
                        $player_role,
                        $_POST['player_price']
                    ]);

                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET num_of_players = (SELECT COUNT(*) FROM league_players WHERE league_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$league_id, $league_id]);
                    
                    $success_message = "Player added successfully!";
                    break;
                    
                case 'update_player':
                    $player_role = $league['positions'] === 'positionless' ? null : $_POST['player_role'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE league_players 
                        SET player_name = ?, player_role = ?, player_price = ?, team_id = ?
                        WHERE player_id = ?
                    ");
                    $stmt->execute([
                        $_POST['player_name'],
                        $player_role,
                        $_POST['player_price'],
                        $_POST['team_id'],
                        $_POST['player_id']
                    ]);
                    $success_message = "Player updated successfully!";
                    break;
                    
                case 'delete_player':
                    $stmt = $pdo->prepare("DELETE FROM league_players WHERE player_id = ?");
                    $stmt->execute([$_POST['player_id']]);
                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET num_of_players = (SELECT COUNT(*) FROM league_players WHERE league_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$league_id, $league_id]);
                    
                    $success_message = "Player deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

if (!$league_not_found && !$not_owner && !$not_activated) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, team_name, team_score
            FROM league_teams
            WHERE league_id = ?
            ORDER BY team_name
        ");
        $stmt->execute([$league_id]);
        $teams_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        $teams_list = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Players - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            color: var(--error);
        }

        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .team-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .team-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .team-card::before {
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

        .team-card:hover::before {
            opacity: 1;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(10, 146, 215, 0.6);
        }

        .team-card.selected {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.15), rgba(10, 146, 215, 0.15));
            border-color: var(--gradient-end);
        }

        body.dark-mode .team-card.selected {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.25), rgba(10, 146, 215, 0.25));
        }

        .team-icon {
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

        .team-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .team-score {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .content-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        body.dark-mode .content-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--gradient-end);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
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
        }

        .btn-secondary:hover {
            background: var(--gradient-end);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .data-table tbody tr {
            transition: all 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.05);
        }

        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-gk {
            background: rgba(255, 193, 7, 0.2);
            color: #f59e0b;
        }

        .badge-def {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .badge-mid {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .badge-att {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

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
            max-width: 600px;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Roboto', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gradient-end);
            background: var(--card-bg);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        body.dark-mode .stat-card {
            background: rgba(10, 20, 35, 0.5);
        }

        .stat-card:hover {
            border-color: var(--gradient-end);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.2);
        }

        .stat-card-icon {
            font-size: 2rem;
            margin-bottom: 0.8rem;
            display: block;
        }

        .stat-card-title {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gradient-end);
        }

        .player-profile {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
            border-radius: 20px;
            margin-bottom: 2rem;
        }

        .player-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .player-info {
            flex: 1;
        }

        .player-name-large {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .player-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .total-points-card {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .total-points-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .total-points-value {
            font-size: 3.5rem;
            font-weight: 900;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
            border-left: 4px solid var(--gradient-end);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .info-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gradient-end);
            margin-bottom: 0.5rem;
        }

        .info-card-text {
            font-size: 0.9rem;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
        }

        .tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--gradient-end);
        }

        .tab.active {
            color: var(--gradient-end);
            border-bottom-color: var(--gradient-end);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

            .teams-grid {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .player-profile {
                flex-direction: column;
                text-align: center;
            }

            .stats-container {
                grid-template-columns: 1fr;
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
        <div class="loading-text">Loading Players Management...</div>
    </div>
    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <?php include 'includes/header.php'; ?>

    <div class="main-content">
        <?php if ($league_not_found): ?>
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
            <div class="page-header">
                <h2 class="page-title">Manage Players</h2>
                <p class="page-subtitle">Select a team to view and manage its players</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div id="teamsView">
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            Select a Team
                        </h3>
                    </div>

                    <?php if (!empty($teams_list)): ?>
                        <div class="teams-grid">
                            <?php foreach ($teams_list as $team): ?>
                                <div class="team-card" onclick="selectTeam(<?php echo $team['id']; ?>, '<?php echo addslashes($team['team_name']); ?>')">
                                    <div class="team-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                    <div class="team-score">Score: <?php echo number_format($team['team_score']); ?> pts</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shield-alt"></i>
                            <div class="empty-state-title">No Teams Found</div>
                            <p>Please add teams to your league first before managing players.</p>
                            <a href="manage_teams.php?id=<?php echo $league_id; ?>" class="btn btn-gradient" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i>
                                Go to Manage Teams
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="playersView" style="display: none;">
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i>
                            <span id="teamNameDisplay"></span>
                        </h3>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button class="btn btn-gradient" onclick="openAddPlayerModal()">
                                <i class="fas fa-plus"></i>
                                Add Player
                            </button>
                            <button class="btn btn-secondary" onclick="backToTeams()">
                                <i class="fas fa-arrow-left"></i>
                                Back to Teams
                            </button>
                        </div>
                    </div>

                    <div class="tabs">
                        <button class="tab active" onclick="switchTab('players-list')">Players List</button>
                        <button id="statsTab" class="tab" style="display: none;" onclick="switchTab('player-stats')">Player Statistics</button>
                    </div>

                    <div id="players-list" class="tab-content active">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <?php if ($league['positions'] === 'positions'): ?>
                                        <th>Position</th>
                                        <?php endif; ?>
                                        <?php if ($league['system'] === 'Budget'): ?>
                                        <th>Price</th>
                                        <?php endif; ?>
                                        <th>Points</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="playersTableBody">
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 2rem;">
                                            Loading players...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="player-stats" class="tab-content">
                        <div id="playerStatsContent"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Player Modal -->
    <div id="addPlayerModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeAddPlayerModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">Add New Player</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_player">
                <input type="hidden" name="team_id" id="addPlayerTeamId">
                
                <div class="form-group">
                    <label class="form-label" for="addPlayerName">
                        <i class="fas fa-user"></i> Player Name
                    </label>
                    <input type="text" class="form-control" id="addPlayerName" name="player_name" required placeholder="Enter player name">
                </div>
                
                <?php if ($league['positions'] === 'positions'): ?>
                <div class="form-group">
                    <label class="form-label" for="addPlayerRole">
                        <i class="fas fa-map-marker-alt"></i> Position
                    </label>
                    <select class="form-control" id="addPlayerRole" name="player_role" required>
                        <option value="">Select Position</option>
                        <option value="GK">Goalkeeper</option>
                        <option value="DEF">Defender</option>
                        <option value="MID">Midfielder</option>
                        <option value="ATT">Attacker</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="player_role" value="">
                <?php endif; ?>
                
                <?php if ($league['system'] === 'Budget'): ?>
                <div class="form-group">
                    <label class="form-label" for="addPlayerPrice">
                        <i class="fas fa-dollar-sign"></i> Price
                    </label>
                    <input type="number" step="0.01" class="form-control" id="addPlayerPrice" name="player_price" required placeholder="0.00">
                    <div style="margin-top: 0.8rem; padding: 0.8rem; background: rgba(241, 161, 85, 0.1); border-left: 3px solid #F1A155; border-radius: 8px;">
                        <p style="font-size: 0.9rem; color: var(--text-primary); margin: 0;">
                            <i class="fas fa-info-circle" style="color: #F1A155;"></i>
                            <strong>Budget Info:</strong> Please know that each contributor's budget is $100 and nothing else.
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="player_price" value="0">
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-check"></i>
                        Add Player
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddPlayerModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Player Modal -->
    <div id="editPlayerModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeEditPlayerModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">Edit Player</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_player">
                <input type="hidden" name="player_id" id="editPlayerId">
                <input type="hidden" name="team_id" id="editPlayerTeamId">
                
                <div class="form-group">
                    <label class="form-label" for="editPlayerName">
                        <i class="fas fa-user"></i> Player Name
                    </label>
                    <input type="text" class="form-control" id="editPlayerName" name="player_name" required>
                </div>
                
                <?php if ($league['positions'] === 'positions'): ?>
                <div class="form-group">
                    <label class="form-label" for="editPlayerRole">
                        <i class="fas fa-map-marker-alt"></i> Position
                    </label>
                    <select class="form-control" id="editPlayerRole" name="player_role" required>
                        <option value="">Select Position</option>
                        <option value="GK">Goalkeeper</option>
                        <option value="DEF">Defender</option>
                        <option value="MID">Midfielder</option>
                        <option value="ATT">Attacker</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="player_role" id="editPlayerRoleHidden">
                <?php endif; ?>
                
                <?php if ($league['system'] === 'Budget'): ?>
                <div class="form-group">
                    <label class="form-label" for="editPlayerPrice">
                        <i class="fas fa-dollar-sign"></i> Price
                    </label>
                    <input type="number" step="0.01" class="form-control" id="editPlayerPrice" name="player_price" required>
                </div>
                <?php else: ?>
                <input type="hidden" name="player_price" id="editPlayerPriceHidden">
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-check"></i>
                        Update Player
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditPlayerModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Player Modal -->
    <div id="deletePlayerModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeDeletePlayerModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">Delete Player</h2>
            </div>
            <div style="margin-bottom: 2rem;">
                <p style="font-size: 1.1rem; color: var(--text-primary);">
                    Are you sure you want to delete <strong id="deletePlayerName"></strong>?
                </p>
                <p style="color: var(--text-secondary); margin-top: 1rem;">
                    This action cannot be undone.
                </p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_player">
                <input type="hidden" name="player_id" id="deletePlayerId">
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Delete Player
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeletePlayerModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
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
        let currentTeamId = null;
        let currentTeamName = '';
        let currentPlayerId = null;
        const leagueId = <?php echo json_encode($league_id); ?>;
        const leagueSystem = <?php echo json_encode($league['system'] ?? 'No Limits'); ?>;
        const leaguePositions = <?php echo json_encode($league['positions'] ?? 'positions'); ?>;

        function selectTeam(teamId, teamName) {
            currentTeamId = teamId;
            currentTeamName = teamName;
            
            document.getElementById('teamsView').style.display = 'none';
            document.getElementById('playersView').style.display = 'block';
            document.getElementById('teamNameDisplay').textContent = teamName;
            
            loadTeamPlayers(teamId);
        }

        function backToTeams() {
            document.getElementById('playersView').style.display = 'none';
            document.getElementById('teamsView').style.display = 'block';
            document.getElementById('statsTab').style.display = 'none';
            switchTab('players-list');
            currentTeamId = null;
            currentPlayerId = null;
        }

        function loadTeamPlayers(teamId) {
            const tbody = document.getElementById('playersTableBody');
            let colspan = 3; 
            if (leaguePositions === 'positions') colspan++;
            if (leagueSystem === 'Budget') colspan++;
            
            tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; padding: 2rem;">Loading players...</td></tr>`;
            
            console.log('Loading players for team:', teamId);
            console.log('League ID:', leagueId);
            
            const url = `manage_players.php?id=${leagueId}&ajax=get_team_players&team_id=${teamId}`;
            console.log('Fetching URL:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const players = JSON.parse(text);
                        console.log('Parsed players:', players);
                        
                        if (players.error) {
                            tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; padding: 2rem; color: var(--error);">Error: ${players.error}</td></tr>`;
                            return;
                        }
                    let colspan = 3; 
                    if (leaguePositions === 'positions') colspan++;
                    if (leagueSystem === 'Budget') colspan++;
                    
                    if (players.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="${colspan}">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <div class="empty-state-title">No Players Found</div>
                                        <p>Add your first player to this team.</p>
                                    </div>
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    tbody.innerHTML = '';
                    players.forEach(player => {
                        const tr = document.createElement('tr');
                        let html = `<td><strong>${escapeHtml(player.player_name)}</strong></td>`;
                        
                        if (leaguePositions === 'positions') {
                            html += `<td>${getRoleBadge(player.player_role)}</td>`;
                        }
                        
                        if (leagueSystem === 'Budget') {
                            html += `<td>${parseFloat(player.player_price || 0).toFixed(2)}</td>`;
                        }
                        
                        html += `
                            <td><strong>${player.total_points || 0}</strong></td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button class="btn btn-secondary btn-sm" onclick="viewPlayerStats(${player.player_id})">
                                        <i class="fas fa-chart-bar"></i> Stats
                                    </button>
                                    <button class="btn btn-gradient btn-sm" onclick="editPlayer(${player.player_id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deletePlayer(${player.player_id}, '${escapeHtml(player.player_name)}')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        `;
                        
                        tr.innerHTML = html;
                        tbody.appendChild(tr);
                    });
                    } catch(e) {
                        console.error('JSON Parse Error:', e);
                        tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; padding: 2rem; color: var(--error);">Error parsing response: ${e.message}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    let colspan = 3;
                    if (leaguePositions === 'positions') colspan++;
                    if (leagueSystem === 'Budget') colspan++;
                    tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; padding: 2rem; color: var(--error);">Error loading players: ${error.message}</td></tr>`;
                });
        }

        function getRoleBadge(role) {
            if (!role) {
                return '<span class="badge" style="background: rgba(128, 128, 128, 0.2); color: #888;"><i class="fas fa-user"></i> No Position</span>';
            }
            const badges = {
                'GK': '<span class="badge badge-gk"><i class="fas fa-hand-paper"></i> Goalkeeper</span>',
                'DEF': '<span class="badge badge-def"><i class="fas fa-shield-alt"></i> Defender</span>',
                'MID': '<span class="badge badge-mid"><i class="fas fa-running"></i> Midfielder</span>',
                'ATT': '<span class="badge badge-att"><i class="fas fa-futbol"></i> Attacker</span>'
            };
            return badges[role] || '<span class="badge">' + role + '</span>';
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openAddPlayerModal() {
            document.getElementById('addPlayerTeamId').value = currentTeamId;
            document.getElementById('addPlayerName').value = '';
            if (leaguePositions === 'positions') {
                document.getElementById('addPlayerRole').value = '';
            }
            if (leagueSystem === 'Budget') {
                document.getElementById('addPlayerPrice').value = '';
            }
            document.getElementById('addPlayerModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddPlayerModal() {
            document.getElementById('addPlayerModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function editPlayer(playerId) {
            fetch(`manage_players.php?id=${leagueId}&ajax=get_player&player_id=${playerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    document.getElementById('editPlayerId').value = data.player_id;
                    document.getElementById('editPlayerTeamId').value = data.team_id;
                    document.getElementById('editPlayerName').value = data.player_name;
                    
                    if (leaguePositions === 'positions') {
                        document.getElementById('editPlayerRole').value = data.player_role || '';
                    } else {
                        document.getElementById('editPlayerRoleHidden').value = data.player_role || '';
                    }
                    
                    if (leagueSystem === 'Budget') {
                        document.getElementById('editPlayerPrice').value = data.player_price || '';
                    } else {
                        document.getElementById('editPlayerPriceHidden').value = data.player_price || '0';
                    }
                    
                    document.getElementById('editPlayerModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    console.error(error);
                    alert('Error loading player data');
                });
        }

        function closeEditPlayerModal() {
            document.getElementById('editPlayerModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function deletePlayer(playerId, playerName) {
            document.getElementById('deletePlayerId').value = playerId;
            document.getElementById('deletePlayerName').textContent = playerName;
            document.getElementById('deletePlayerModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDeletePlayerModal() {
            document.getElementById('deletePlayerModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            document.querySelector(`.tab[onclick="switchTab('${tabId}')"]`).classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            if (tabId === 'players-list') {
                loadTeamPlayers(currentTeamId);
            }
        }

        function viewPlayerStats(playerId) {
            currentPlayerId = playerId;
            document.getElementById('statsTab').style.display = 'block';
            switchTab('player-stats');
            loadPlayerStats(playerId);
        }

        function loadPlayerStats(playerId) {
            const content = document.getElementById('playerStatsContent');
            content.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-secondary);">Loading statistics...</div>';
            
            fetch(`manage_players.php?id=${leagueId}&ajax=get_player_stats&player_id=${playerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        content.innerHTML = `<div style="text-align: center; padding: 3rem; color: var(--error);">Error: ${data.error}</div>`;
                        return;
                    }
                    
                    const player = data.player;
                    const stats = data.stats;
                    const rules = data.rules;
                    const lineupCount = data.lineup_count || 0;
                    const isPositionless = player.league_positions === 'positionless';
                    
                    let statsHtml = `
                        <div class="player-profile">
                            <div class="player-avatar">
                                ${player.player_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="player-info">
                                <div class="player-name-large">${escapeHtml(player.player_name)}</div>
                                <div class="player-meta">
                                    ${!isPositionless && player.player_role ? '<span>' + getRoleText(player.player_role) + '</span>' : ''}
                                    <span><i class="fas fa-shield-alt"></i> ${escapeHtml(player.team_name || 'No Team')}</span>
                                    <span><i class="fas fa-users"></i> Owned by ${lineupCount} contributor${lineupCount !== 1 ? 's' : ''}</span>
                                    ${leagueSystem === 'Budget' ? '<span><i class="fas fa-dollar-sign"></i> ' + parseFloat(player.player_price || 0).toFixed(2) + '</span>' : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="total-points-card">
                            <div class="total-points-label"><i class="fas fa-trophy"></i> Total Fantasy Points</div>
                            <div class="total-points-value">${stats.total_points}</div>
                        </div>
                        
                        <div class="stats-container">
                    `;
                    const goalPoints = isPositionless ? calculatePositionlessGoalPoints(stats.goals, rules) : calculateGoalPoints(player.player_role, stats.goals, rules);
                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">⚽</span>
                            <div class="stat-card-title">Goals Scored</div>
                            <div class="stat-card-value">${stats.goals}</div>
                            <div style="font-size: 0.85rem; color: var(--success); margin-top: 0.5rem;">+${goalPoints} pts</div>
                        </div>
                    `;

                    const assistPoints = isPositionless ? calculatePositionlessAssistPoints(stats.assists, rules) : calculateAssistPoints(player.player_role, stats.assists, rules);
                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">🎯</span>
                            <div class="stat-card-title">Assists</div>
                            <div class="stat-card-value">${stats.assists}</div>
                            <div style="font-size: 0.85rem; color: var(--success); margin-top: 0.5rem;">+${assistPoints} pts</div>
                        </div>
                    `;

                    if (isPositionless || player.player_role === 'GK' || player.player_role === 'DEF') {
                        const csPoints = isPositionless ? calculatePositionlessCleanSheetPoints(stats.clean_sheets, rules) : calculateCleanSheetPoints(player.player_role, stats.clean_sheets, rules);
                        statsHtml += `
                            <div class="stat-card">
                                <span class="stat-card-icon">🛡️</span>
                                <div class="stat-card-title">Clean Sheets</div>
                                <div class="stat-card-value">${stats.clean_sheets}</div>
                                <div style="font-size: 0.85rem; color: var(--success); margin-top: 0.5rem;">+${csPoints} pts</div>
                            </div>
                        `;
                    }

                    if (isPositionless || player.player_role === 'GK') {
                        const penSavePoints = (stats.penalties_saved * (rules?.gk_save_penalty || 0));
                        statsHtml += `
                            <div class="stat-card">
                                <span class="stat-card-icon">🧤</span>
                                <div class="stat-card-title">Penalties Saved</div>
                                <div class="stat-card-value">${stats.penalties_saved}</div>
                                <div style="font-size: 0.85rem; color: var(--success); margin-top: 0.5rem;">+${penSavePoints} pts</div>
                            </div>
                        `;
                    }

                    const penMissPoints = (stats.penalties_missed * (rules?.miss_penalty || 0));
                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">❌</span>
                            <div class="stat-card-title">Penalties Missed</div>
                            <div class="stat-card-value">${stats.penalties_missed}</div>
                            <div style="font-size: 0.85rem; color: var(--error); margin-top: 0.5rem;">${penMissPoints} pts</div>
                        </div>
                    `;

                    const yellowPoints = (stats.yellow_cards * (rules?.yellow_card || 0));
                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">🟨</span>
                            <div class="stat-card-title">Yellow Cards</div>
                            <div class="stat-card-value">${stats.yellow_cards}</div>
                            <div style="font-size: 0.85rem; color: var(--error); margin-top: 0.5rem;">${yellowPoints} pts</div>
                        </div>
                    `;

                    const redPoints = (stats.red_cards * (rules?.red_card || 0));
                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">🟥</span>
                            <div class="stat-card-title">Red Cards</div>
                            <div class="stat-card-value">${stats.red_cards}</div>
                            <div style="font-size: 0.85rem; color: var(--error); margin-top: 0.5rem;">${redPoints} pts</div>
                        </div>
                    `;

                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">⭐</span>
                            <div class="stat-card-title">Bonus Events</div>
                            <div class="stat-card-value">${stats.bonus_count}</div>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">Performance bonuses</div>
                        </div>
                    `;

                    statsHtml += `
                        <div class="stat-card">
                            <span class="stat-card-icon">⚠️</span>
                            <div class="stat-card-title">Minus Events</div>
                            <div class="stat-card-value">${stats.minus_count}</div>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">Performance penalties</div>
                        </div>
                    `;
                    
                    statsHtml += `</div>`;
                    if (rules) {
                        statsHtml += `
                            <div class="info-card">
                                <div class="info-card-title"><i class="fas fa-cog"></i> League Scoring Rules</div>
                                <div class="info-card-text">
                        `;
                        
                        if (isPositionless) {
                            statsHtml += `
                                <strong>Positionless League - All Actions Available:</strong><br>
                                • Goal (GK): +${rules.gk_score || 0} points<br>
                                • Goal (DEF): +${rules.def_score || 0} points<br>
                                • Goal (MID): +${rules.mid_score || 0} points<br>
                                • Goal (ATT): +${rules.for_score || 0} points<br>
                                • Assist (GK): +${rules.gk_assist || 0} points<br>
                                • Assist (DEF): +${rules.def_assist || 0} points<br>
                                • Assist (MID): +${rules.mid_assist || 0} points<br>
                                • Assist (ATT): +${rules.for_assist || 0} points<br>
                                • Clean Sheet (GK): +${rules.gk_clean_sheet || 0} points<br>
                                • Clean Sheet (DEF): +${rules.def_clean_sheet || 0} points<br>
                                • Penalty Saved: +${rules.gk_save_penalty || 0} points<br>
                                • Penalty Missed: ${rules.miss_penalty || 0} points<br>
                                • Yellow Card: ${rules.yellow_card || 0} points<br>
                                • Red Card: ${rules.red_card || 0} points
                            `;
                        } else if (player.player_role) {
                            statsHtml += `<strong>For ${getRoleText(player.player_role)}:</strong><br>`;
                            
                            if (player.player_role === 'GK') {
                                statsHtml += `
                                    • Goal: +${rules.gk_score || 0} points<br>
                                    • Assist: +${rules.gk_assist || 0} points<br>
                                    • Clean Sheet: +${rules.gk_clean_sheet || 0} points<br>
                                    • Penalty Saved: +${rules.gk_save_penalty || 0} points<br>
                                `;
                            } else if (player.player_role === 'DEF') {
                                statsHtml += `
                                    • Goal: +${rules.def_score || 0} points<br>
                                    • Assist: +${rules.def_assist || 0} points<br>
                                    • Clean Sheet: +${rules.def_clean_sheet || 0} points<br>
                                `;
                            } else if (player.player_role === 'MID') {
                                statsHtml += `
                                    • Goal: +${rules.mid_score || 0} points<br>
                                    • Assist: +${rules.mid_assist || 0} points<br>
                                `;
                            } else if (player.player_role === 'ATT') {
                                statsHtml += `
                                    • Goal: +${rules.for_score || 0} points<br>
                                    • Assist: +${rules.for_assist || 0} points<br>
                                `;
                            }
                            
                            statsHtml += `
                                    • Penalty Missed: ${rules.miss_penalty || 0} points<br>
                                    • Yellow Card: ${rules.yellow_card || 0} points<br>
                                    • Red Card: ${rules.red_card || 0} points
                            `;
                        }
                        
                        statsHtml += `
                                </div>
                            </div>
                        `;
                    }
                    
                    content.innerHTML = statsHtml;
                })
                .catch(error => {
                    console.error(error);
                    content.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--error);">Error loading player statistics</div>';
                });
        }

        function calculateGoalPoints(role, goals, rules) {
            if (!rules) return 0;
            switch(role) {
                case 'GK': return goals * (rules.gk_score || 0);
                case 'DEF': return goals * (rules.def_score || 0);
                case 'MID': return goals * (rules.mid_score || 0);
                case 'ATT': return goals * (rules.for_score || 0);
                default: return 0;
            }
        }

        function calculateAssistPoints(role, assists, rules) {
            if (!rules) return 0;
            switch(role) {
                case 'GK': return assists * (rules.gk_assist || 0);
                case 'DEF': return assists * (rules.def_assist || 0);
                case 'MID': return assists * (rules.mid_assist || 0);
                case 'ATT': return assists * (rules.for_assist || 0);
                default: return 0;
            }
        }

        function calculateCleanSheetPoints(role, cleanSheets, rules) {
            if (!rules) return 0;
            switch(role) {
                case 'GK': return cleanSheets * (rules.gk_clean_sheet || 0);
                case 'DEF': return cleanSheets * (rules.def_clean_sheet || 0);
                default: return 0;
            }
        }

        function calculatePositionlessGoalPoints(goals, rules) {
            if (!rules) return 0;
            const gkPoints = goals * (rules.gk_score || 0);
            const defPoints = goals * (rules.def_score || 0);
            const midPoints = goals * (rules.mid_score || 0);
            const attPoints = goals * (rules.for_score || 0);
            return Math.max(gkPoints, defPoints, midPoints, attPoints);
        }

        function calculatePositionlessAssistPoints(assists, rules) {
            if (!rules) return 0;
            const gkPoints = assists * (rules.gk_assist || 0);
            const defPoints = assists * (rules.def_assist || 0);
            const midPoints = assists * (rules.mid_assist || 0);
            const attPoints = assists * (rules.for_assist || 0);
            return Math.max(gkPoints, defPoints, midPoints, attPoints);
        }

        function calculatePositionlessCleanSheetPoints(cleanSheets, rules) {
            if (!rules) return 0;
            const gkPoints = cleanSheets * (rules.gk_clean_sheet || 0);
            const defPoints = cleanSheets * (rules.def_clean_sheet || 0);
            return Math.max(gkPoints, defPoints);
        }

        function getRoleText(role) {
            const roles = {
                'GK': '🟡 Goalkeeper',
                'DEF': '🟢 Defender',
                'MID': '🔵 Midfielder',
                'ATT': '🔴 Attacker'
            };
            return roles[role] || role;
        }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>