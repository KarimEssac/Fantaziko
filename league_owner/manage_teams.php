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

// Get league ID from URL
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
}

// Handle AJAX requests - MUST come before any HTML output
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_teams') {
    header('Content-Type: application/json');
    
    // Check access permissions
    if ($league_not_found) {
        echo json_encode(['error' => 'League not found']);
        exit();
    }
    
    if ($not_owner) {
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    if ($not_activated) {
        echo json_encode(['error' => 'League not activated']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                lt.*,
                (SELECT COUNT(*) FROM league_players WHERE team_id = lt.id) as player_count
            FROM league_teams lt
            WHERE lt.league_id = ?
            ORDER BY lt.team_score DESC, lt.team_name
        ");
        $stmt->execute([$league_id]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($teams);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_team' && isset($_GET['team_id'])) {
    header('Content-Type: application/json');
    
    // Check access permissions
    if ($league_not_found || $not_owner || $not_activated) {
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT lt.*
            FROM league_teams lt
            WHERE lt.id = ? AND lt.league_id = ?
        ");
        $stmt->execute([$_GET['team_id'], $league_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($team) {
            echo json_encode($team);
        } else {
            echo json_encode(['error' => 'Team not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_standings') {
    header('Content-Type: application/json');
    
    // Check access permissions
    if ($league_not_found || $not_owner || $not_activated) {
        echo json_encode(['error' => 'Access denied']);
        exit();
    }
    
    try {
        // Get all teams with their match statistics
        $stmt = $pdo->prepare("
            SELECT 
                lt.id,
                lt.team_name,
                lt.team_score as points,
                COALESCE(COUNT(DISTINCT CASE 
                    WHEN m.match_id IS NOT NULL THEN m.match_id 
                    ELSE NULL 
                END), 0) as played,
                COALESCE(SUM(CASE 
                    WHEN (m.team1_id = lt.id AND m.team1_score > m.team2_score) OR 
                         (m.team2_id = lt.id AND m.team2_score > m.team1_score) 
                    THEN 1 ELSE 0 
                END), 0) as won,
                COALESCE(SUM(CASE 
                    WHEN (m.team1_id = lt.id OR m.team2_id = lt.id) AND m.team1_score = m.team2_score 
                    THEN 1 ELSE 0 
                END), 0) as draw,
                COALESCE(SUM(CASE 
                    WHEN (m.team1_id = lt.id AND m.team1_score < m.team2_score) OR 
                         (m.team2_id = lt.id AND m.team2_score < m.team1_score) 
                    THEN 1 ELSE 0 
                END), 0) as lost,
                COALESCE(SUM(CASE 
                    WHEN m.team1_id = lt.id THEN m.team1_score 
                    WHEN m.team2_id = lt.id THEN m.team2_score 
                    ELSE 0 
                END), 0) as goals_for,
                COALESCE(SUM(CASE 
                    WHEN m.team1_id = lt.id THEN m.team2_score 
                    WHEN m.team2_id = lt.id THEN m.team1_score 
                    ELSE 0 
                END), 0) as goals_against
            FROM league_teams lt
            LEFT JOIN matches m ON (m.team1_id = lt.id OR m.team2_id = lt.id) 
                AND m.league_id = lt.league_id
            WHERE lt.league_id = ?
            GROUP BY lt.id, lt.team_name, lt.team_score
            ORDER BY 
                lt.team_score DESC,
                (COALESCE(SUM(CASE 
                    WHEN m.team1_id = lt.id THEN m.team1_score 
                    WHEN m.team2_id = lt.id THEN m.team2_score 
                    ELSE 0 
                END), 0) - COALESCE(SUM(CASE 
                    WHEN m.team1_id = lt.id THEN m.team2_score 
                    WHEN m.team2_id = lt.id THEN m.team1_score 
                    ELSE 0 
                END), 0)) DESC,
                lt.team_name
        ");
        $stmt->execute([$league_id]);
        $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure all numeric fields are properly set
        foreach ($standings as &$team) {
            $team['played'] = intval($team['played']);
            $team['won'] = intval($team['won']);
            $team['draw'] = intval($team['draw']);
            $team['lost'] = intval($team['lost']);
            $team['goals_for'] = intval($team['goals_for']);
            $team['goals_against'] = intval($team['goals_against']);
            $team['points'] = intval($team['points']);
        }
        
        echo json_encode($standings);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$not_owner && !$not_activated && !$league_not_found) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_team':
                    $stmt = $pdo->prepare("
                        INSERT INTO league_teams (league_id, team_name, team_score)
                        VALUES (?, ?, 0)
                    ");
                    $stmt->execute([
                        $league_id,
                        $_POST['team_name']
                    ]);
                    
                    // Update num_of_teams in leagues table
                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET num_of_teams = (SELECT COUNT(*) FROM league_teams WHERE league_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$league_id, $league_id]);
                    
                    $success_message = "Team added successfully!";
                    break;
                    
                case 'update_team':
                    // Verify team belongs to this league
                    $stmt = $pdo->prepare("SELECT league_id FROM league_teams WHERE id = ?");
                    $stmt->execute([$_POST['team_id']]);
                    $team_league = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($team_league && $team_league['league_id'] == $league_id) {
                        $stmt = $pdo->prepare("
                            UPDATE league_teams 
                            SET team_name = ?, team_score = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $_POST['team_name'],
                            $_POST['team_score'],
                            $_POST['team_id']
                        ]);
                        $success_message = "Team updated successfully!";
                    } else {
                        $error_message = "Invalid team or league mismatch.";
                    }
                    break;
                    
                case 'delete_team':
                    // Verify team belongs to this league
                    $stmt = $pdo->prepare("SELECT league_id FROM league_teams WHERE id = ?");
                    $stmt->execute([$_POST['team_id']]);
                    $team_league = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($team_league && $team_league['league_id'] == $league_id) {
                        $stmt = $pdo->prepare("DELETE FROM league_teams WHERE id = ?");
                        $stmt->execute([$_POST['team_id']]);
                        
                        // Update num_of_teams in leagues table
                        $stmt = $pdo->prepare("
                            UPDATE leagues 
                            SET num_of_teams = (SELECT COUNT(*) FROM league_teams WHERE league_id = ?)
                            WHERE id = ?
                        ");
                        $stmt->execute([$league_id, $league_id]);
                        
                        $success_message = "Team deleted successfully!";
                    } else {
                        $error_message = "Invalid team or league mismatch.";
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Management - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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

        /* Not Owner/Activated Pages */
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

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header-left {
            flex: 1;
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

        /* Buttons */
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
            font-size: 0.9rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        body.dark-mode .alert-success {
            background: rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        body.dark-mode .alert-error {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
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
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .team-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
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
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(10, 146, 215, 0.6);
        }

        .team-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
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
            flex-shrink: 0;
        }

        .team-info {
            flex: 1;
            min-width: 0;
        }

        .team-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .team-id {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .team-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .team-stat {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
        }

        body.dark-mode .team-stat {
            background: rgba(10, 20, 35, 0.5);
        }

        .team-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gradient-end);
            margin-bottom: 0.3rem;
        }

        .team-stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .team-actions {
            display: flex;
            gap: 0.8rem;
        }

        /* Empty State */
        .empty-state {
            background: var(--card-bg);
            border: 2px dashed var(--border-color);
            border-radius: 20px;
            padding: 4rem 2rem;
            text-align: center;
        }

        body.dark-mode .empty-state {
            background: rgba(20, 30, 48, 0.3);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--text-secondary);
            opacity: 0.5;
            margin-bottom: 1.5rem;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
        }

        .empty-state-text {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
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
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow: hidden;
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
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.3rem;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
            max-height: calc(85vh - 150px);
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Form Styles */
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
            font-size: 1rem;
            font-family: 'Roboto', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        body.dark-mode .form-control {
            background: rgba(10, 20, 35, 0.5);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .info-box {
            background: rgba(10, 146, 215, 0.1);
            border-left: 4px solid var(--gradient-end);
            padding: 1rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        body.dark-mode .info-box {
            background: rgba(10, 146, 215, 0.2);
        }

        .info-box-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            padding: 1rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        body.dark-mode .warning-box {
            background: rgba(239, 68, 68, 0.2);
        }

        .warning-box-title {
            font-weight: 700;
            color: var(--error);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-box-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .warning-box ul {
            margin: 0.5rem 0 0 1.5rem;
            line-height: 1.8;
        }

        /* Loading State */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(10, 146, 215, 0.3);
            border-radius: 50%;
            border-top-color: var(--gradient-end);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Standings Table */
        .standings-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        body.dark-mode .standings-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .table-container {
            overflow-x: auto;
        }

        .standings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .standings-table thead {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .standings-table th {
            padding: 1rem;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .standings-table .pos-col {
            width: 50px;
            text-align: center;
        }

        .standings-table .team-col {
            text-align: left;
            padding-left: 1.5rem;
        }

        .standings-table .stat-col {
            width: 60px;
        }

        .standings-table .pts-col {
            width: 80px;
            font-weight: 700;
        }

        .standings-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .standings-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.05);
        }

        body.dark-mode .standings-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.1);
        }

        .standings-table tbody tr:last-child {
            border-bottom: none;
        }

        .standings-table td {
            padding: 1rem;
            text-align: center;
            font-size: 0.95rem;
            color: var(--text-primary);
        }

        .standings-table .team-col {
            font-weight: 600;
            text-align: left;
        }

        .position-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--bg-secondary);
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        body.dark-mode .position-badge {
            background: rgba(10, 20, 35, 0.5);
        }

        .position-badge.top-3 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }

        .gd-positive {
            color: var(--success);
            font-weight: 600;
        }

        .gd-negative {
            color: var(--error);
            font-weight: 600;
        }

        .gd-neutral {
            color: var(--text-secondary);
        }

        .pts-value {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--gradient-end);
        }

        .standings-legend {
            padding: 1.5rem 2rem;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            justify-content: center;
        }

        body.dark-mode .standings-legend {
            background: rgba(10, 20, 35, 0.3);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .legend-label {
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .teams-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .page-subtitle {
                font-size: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .teams-grid {
                grid-template-columns: 1fr;
            }

            .team-actions {
                flex-direction: column;
            }

            .modal-content {
                width: 95%;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .team-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>
    <?php endif; ?>

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
            <!-- Teams Management Content -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2 class="page-title">Teams Management</h2>
                    <p class="page-subtitle">Manage all teams in your league</p>
                </div>
                <button class="btn btn-gradient" onclick="openAddTeamModal()">
                    <i class="fas fa-plus"></i>
                    Add New Team
                </button>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-value" id="totalTeams">0</div>
                    <div class="stat-label">Total Teams</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value" id="totalPlayers">0</div>
                    <div class="stat-label">Total Players</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-value" id="topScore">0</div>
                    <div class="stat-label">Highest Score</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value" id="avgScore">0</div>
                    <div class="stat-label">Average Score</div>
                </div>
            </div>

            <!-- Section Divider -->
            <div style="margin: 3rem 0 2rem; position: relative;">
                <div style="height: 2px; background: linear-gradient(90deg, transparent, var(--border-color), transparent);"></div>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--bg-primary); padding: 0 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-shield-alt" style="color: var(--gradient-end);"></i>
                        Your Teams
                    </h3>
                </div>
            </div>

            <!-- Teams Grid -->
            <div id="teamsContainer">
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <div class="loading-spinner" style="width: 40px; height: 40px; border-width: 4px; margin: 0 auto 1rem;"></div>
                    <p>Loading teams...</p>
                </div>
            </div>

            <!-- Standings Section -->
            <div style="margin: 3rem 0 2rem; position: relative;">
                <div style="height: 2px; background: linear-gradient(90deg, transparent, var(--border-color), transparent);"></div>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--bg-primary); padding: 0 1.5rem;">
                    <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-trophy" style="color: var(--gradient-end);"></i>
                        League Standings
                    </h3>
                </div>
            </div>

            <!-- Standings Table -->
            <div class="standings-card">
                <div class="table-container">
                    <table class="standings-table">
                        <thead>
                            <tr>
                                <th class="pos-col">#</th>
                                <th class="team-col">Team</th>
                                <th class="stat-col">P</th>
                                <th class="stat-col">W</th>
                                <th class="stat-col">D</th>
                                <th class="stat-col">L</th>
                                <th class="stat-col">GF</th>
                                <th class="stat-col">GA</th>
                                <th class="stat-col">GD</th>
                                <th class="pts-col">PTS</th>
                            </tr>
                        </thead>
                        <tbody id="standingsTableBody">
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                    <div class="loading-spinner" style="width: 30px; height: 30px; border-width: 3px; margin: 0 auto 0.5rem;"></div>
                                    Loading standings...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="standings-legend">
                    <div class="legend-item">
                        <span class="legend-label">P:</span> Played
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">W:</span> Won
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">D:</span> Draw
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">L:</span> Lost
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">GF:</span> Goals For
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">GA:</span> Goals Against
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">GD:</span> Goal Difference
                    </div>
                    <div class="legend-item">
                        <span class="legend-label">PTS:</span> Points
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/footer.php'; ?>
    <?php endif; ?>

    <!-- Add Team Modal -->
    <div id="addTeamModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Team</h3>
                <button class="modal-close" onclick="closeAddTeamModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="addTeamForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_team">
                    
                    <div class="info-box">
                        <p class="info-box-text">
                            <i class="fas fa-info-circle"></i>
                            Add a new team to your league. The team score will start at 0 and will be automatically updated based on match results.
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Team Name <span style="color: var(--error);">*</span>
                        </label>
                        <input type="text" name="team_name" class="form-control" placeholder="Enter team name" required>
                        <p class="form-help">Choose a unique and descriptive name for the team</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddTeamModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-plus"></i>
                        Add Team
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div id="editTeamModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Team</h3>
                <button class="modal-close" onclick="closeEditTeamModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="editTeamForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_team">
                    <input type="hidden" name="team_id" id="editTeamId">
                    
                    <div class="info-box">
                        <p class="info-box-text">
                            <i class="fas fa-edit"></i>
                            Update team information. Be careful when manually adjusting team scores as they are typically calculated automatically.
                        </p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Team Name <span style="color: var(--error);">*</span>
                        </label>
                        <input type="text" name="team_name" id="editTeamName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Team Score
                        </label>
                        <input type="number" name="team_score" id="editTeamScore" class="form-control" step="1" value="0">
                        <p class="form-help">⚠️ Manual score changes should be made carefully</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditTeamModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Team Modal -->
    <div id="deleteTeamModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Team</h3>
                <button class="modal-close" onclick="closeDeleteTeamModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="deleteTeamForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_team">
                    <input type="hidden" name="team_id" id="deleteTeamId">
                    
                    <div class="warning-box">
                        <div class="warning-box-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Warning: This Action Cannot Be Undone
                        </div>
                        <div class="warning-box-text">
                            Are you sure you want to delete the team "<strong id="deleteTeamName"></strong>"?
                            <br><br>
                            <strong>This will:</strong>
                            <ul>
                                <li>Permanently remove the team from the league</li>
                                <li>Delete all associated data</li>
                                <li>Cannot be reversed</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteTeamModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Delete Team
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Load teams on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
            loadTeams();
            loadStandings();
            <?php endif; ?>
        });

        function loadTeams() {
            const leagueId = '<?php echo $league_id; ?>';
            
            fetch('?ajax=get_teams&id=' + leagueId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Teams data:', data); // Debug log
                    
                    if (data.error) {
                        showError(data.error);
                        return;
                    }
                    
                    displayTeams(data);
                    updateStats(data);
                })
                .catch(error => {
                    console.error('Error loading teams:', error);
                    showError('Failed to load teams. Please refresh the page.');
                });
        }

        function loadStandings() {
            const leagueId = '<?php echo $league_id; ?>';
            
            fetch('?ajax=get_standings&id=' + leagueId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Standings data:', data); // Debug log
                    
                    if (data.error) {
                        showStandingsError(data.error);
                        return;
                    }
                    
                    displayStandings(data);
                })
                .catch(error => {
                    console.error('Error loading standings:', error);
                    showStandingsError('Failed to load standings');
                });
        }

        function displayStandings(standings) {
            const tbody = document.getElementById('standingsTableBody');
            
            if (standings.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <i class="fas fa-trophy" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                            No match data available yet
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            standings.forEach((team, index) => {
                const position = index + 1;
                const positionClass = position <= 3 ? 'top-3' : '';
                const goalsFor = parseInt(team.goals_for) || 0;
                const goalsAgainst = parseInt(team.goals_against) || 0;
                const goalDiff = goalsFor - goalsAgainst;
                const gdClass = goalDiff > 0 ? 'gd-positive' : goalDiff < 0 ? 'gd-negative' : 'gd-neutral';
                const gdSign = goalDiff > 0 ? '+' : '';
                
                html += `
                    <tr>
                        <td><span class="position-badge ${positionClass}">${position}</span></td>
                        <td class="team-col">${escapeHtml(team.team_name)}</td>
                        <td>${parseInt(team.played) || 0}</td>
                        <td>${parseInt(team.won) || 0}</td>
                        <td>${parseInt(team.draw) || 0}</td>
                        <td>${parseInt(team.lost) || 0}</td>
                        <td>${goalsFor}</td>
                        <td>${goalsAgainst}</td>
                        <td class="${gdClass}">${gdSign}${goalDiff}</td>
                        <td><span class="pts-value">${parseInt(team.points) || 0}</span></td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function showStandingsError(message) {
            const tbody = document.getElementById('standingsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align: center; padding: 2rem; color: var(--error);">
                        <i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        ${escapeHtml(message)}
                    </td>
                </tr>
            `;
        }

        function displayTeams(teams) {
            const container = document.getElementById('teamsContainer');
            
            if (teams.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="empty-state-title">No Teams Yet</h3>
                        <p class="empty-state-text">Get started by adding your first team to the league</p>
                        <button class="btn btn-gradient" onclick="openAddTeamModal()">
                            <i class="fas fa-plus"></i>
                            Add Your First Team
                        </button>
                    </div>
                `;
                return;
            }

            let html = '<div class="teams-grid">';
            
            teams.forEach(team => {
                html += `
                    <div class="team-card">
                        <div class="team-header">
                            <div class="team-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="team-info">
                                <h3 class="team-name">${escapeHtml(team.team_name)}</h3>
                            </div>
                        </div>
                        
                        <div class="team-stats">
                            <div class="team-stat">
                                <div class="team-stat-value">${team.team_score}</div>
                                <div class="team-stat-label">Score</div>
                            </div>
                            <div class="team-stat">
                                <div class="team-stat-value">${team.player_count}</div>
                                <div class="team-stat-label">Players</div>
                            </div>
                        </div>
                        
                        <div class="team-actions">
                            <button class="btn btn-secondary btn-sm" onclick="editTeam(${team.id})" style="flex: 1;">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteTeam(${team.id}, '${escapeHtml(team.team_name).replace(/'/g, "\\'")}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function updateStats(teams) {
            const totalTeams = teams.length;
            let totalPlayers = 0;
            let topScore = 0;
            let totalScore = 0;

            teams.forEach(team => {
                totalPlayers += parseInt(team.player_count) || 0;
                const score = parseInt(team.team_score) || 0;
                topScore = Math.max(topScore, score);
                totalScore += score;
            });

            const avgScore = totalTeams > 0 ? Math.round(totalScore / totalTeams) : 0;

            document.getElementById('totalTeams').textContent = totalTeams;
            document.getElementById('totalPlayers').textContent = totalPlayers;
            document.getElementById('topScore').textContent = topScore;
            document.getElementById('avgScore').textContent = avgScore;
        }

        function openAddTeamModal() {
            document.getElementById('addTeamForm').reset();
            document.getElementById('addTeamModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddTeamModal() {
            document.getElementById('addTeamModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function editTeam(teamId) {
            const leagueId = '<?php echo $league_id; ?>';
            
            fetch(`?ajax=get_team&team_id=${teamId}&id=${leagueId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Team data:', data); // Debug log
                    
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    document.getElementById('editTeamId').value = data.id;
                    document.getElementById('editTeamName').value = data.team_name;
                    document.getElementById('editTeamScore').value = data.team_score || 0;
                    
                    document.getElementById('editTeamModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    console.error('Error loading team:', error);
                    alert('Failed to load team data. Please try again.');
                });
        }

        function closeEditTeamModal() {
            document.getElementById('editTeamModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function deleteTeam(teamId, teamName) {
            document.getElementById('deleteTeamId').value = teamId;
            document.getElementById('deleteTeamName').textContent = teamName;
            document.getElementById('deleteTeamModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteTeamModal() {
            document.getElementById('deleteTeamModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function showError(message) {
            const container = document.getElementById('teamsContainer');
            container.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: var(--error);">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p style="font-size: 1.1rem;">${escapeHtml(message)}</p>
                </div>
            `;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });

        // Reload teams after form submission
        document.getElementById('addTeamForm').addEventListener('submit', function() {
            setTimeout(() => {
                loadTeams();
                loadStandings();
            }, 100);
        });

        document.getElementById('editTeamForm').addEventListener('submit', function() {
            setTimeout(() => {
                loadTeams();
                loadStandings();
            }, 100);
        });

        document.getElementById('deleteTeamForm').addEventListener('submit', function() {
            setTimeout(() => {
                loadTeams();
                loadStandings();
            }, 100);
        });
    </script>
</body>
</html>