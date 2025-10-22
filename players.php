<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_info' && isset($_GET['league_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                a.username as owner_name,
                a2.username as other_owner_name
            FROM leagues l
            LEFT JOIN accounts a ON l.owner = a.id
            LEFT JOIN accounts a2 ON l.other_owner = a2.id
            WHERE l.id = ?
        ");
        $stmt->execute([$_GET['league_id']]);
        $league = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($league);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Add this new endpoint RIGHT AFTER get_league_info
if ($_GET['ajax'] === 'get_league_teams' && isset($_GET['league_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                team_name,
                team_score
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
    
    if ($_GET['ajax'] === 'get_player' && isset($_GET['player_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    l.name as league_name,
                    l.activated as league_activated,
                    l.system as league_system
                FROM league_players lp
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
    
    if ($_GET['ajax'] === 'get_league_players' && isset($_GET['league_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                lp.*,
                l.name as league_name,
                lt.team_name
            FROM league_players lp
            LEFT JOIN leagues l ON lp.league_id = l.id
            LEFT JOIN league_teams lt ON lp.team_id = lt.id
            WHERE lp.league_id = ?
            ORDER BY lp.player_role, lp.player_name
        ");
        $stmt->execute([$_GET['league_id']]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($players);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

    // Get player statistics
if ($_GET['ajax'] === 'get_player_stats' && isset($_GET['player_id'])) {
    try {
        // Get player basic info
        $stmt = $pdo->prepare("
            SELECT 
                lp.*,
                lt.team_name,
                l.name as league_name,
                l.system as league_system
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
        
        // Get league scoring rules
        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
        $stmt->execute([$player['league_id']]);
        $rules = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get player statistics from matches_points
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
        
        // Get clean sheets (matches where team didn't concede)
        $cleanSheets = 0;
        if (in_array($player['player_role'], ['GK', 'DEF'])) {
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
        }
        
        // Get number of contributors who have this player in their lineup
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as lineup_count
            FROM contributor_players
            WHERE league_id = ?
            AND player_id = ?
        ");
        $stmt->execute([$player['league_id'], $_GET['player_id']]);
        $lineupResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $lineupCount = $lineupResult['lineup_count'] ?? 0;

        // Use total_points from league_players table instead of calculating
        $totalPoints = $player['total_points'] ?? 0;

        // Compile response
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_player':
    $stmt = $pdo->prepare("
        INSERT INTO league_players (league_id, team_id, player_name, player_role, player_price)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['league_id'],
        $_POST['team_id'],
        $_POST['player_name'],
        $_POST['player_role'],
        $_POST['player_price']
    ]);
    
    // Update num_of_players in leagues table
    $stmt = $pdo->prepare("
        UPDATE leagues 
        SET num_of_players = (SELECT COUNT(*) FROM league_players WHERE league_id = ?)
        WHERE id = ?
    ");
    $stmt->execute([$_POST['league_id'], $_POST['league_id']]);
    
    $success_message = "Player added successfully!";
    break;
                    
                case 'update_player':
    $stmt = $pdo->prepare("
        UPDATE league_players 
        SET player_name = ?, player_role = ?, player_price = ?, team_id = ?
        WHERE player_id = ?
    ");
    $stmt->execute([
        $_POST['player_name'],
        $_POST['player_role'],
        $_POST['player_price'],
        $_POST['team_id'],
        $_POST['player_id']
    ]);
    $success_message = "Player updated successfully!";
    break;
                    
                case 'delete_player':
                    // Get league_id before deleting
                    $stmt = $pdo->prepare("SELECT league_id FROM league_players WHERE player_id = ?");
                    $stmt->execute([$_POST['player_id']]);
                    $league_id = $stmt->fetch(PDO::FETCH_ASSOC)['league_id'];
                    
                    // Delete player
                    $stmt = $pdo->prepare("DELETE FROM league_players WHERE player_id = ?");
                    $stmt->execute([$_POST['player_id']]);
                    
                    // Update num_of_players in leagues table
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

// Fetch all leagues for selection
$leagues_search = isset($_GET['leagues_search']) ? $_GET['leagues_search'] : '';
$leagues_where = '';
$leagues_params = [];

if (!empty($leagues_search)) {
    $leagues_where = "WHERE l.name LIKE ? OR a.username LIKE ? OR a2.username LIKE ?";
    $leagues_params = ["%$leagues_search%", "%$leagues_search%", "%$leagues_search%"];
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            l.id as league_id,
            l.name as league_name,
            l.activated as league_activated,
            l.num_of_players,
            l.num_of_teams,
            l.system,
            a.username as owner_name,
            a2.username as other_owner_name
        FROM leagues l
        LEFT JOIN accounts a ON l.owner = a.id
        LEFT JOIN accounts a2 ON l.other_owner = a2.id
        $leagues_where
        ORDER BY l.name
    ");
    $stmt->execute($leagues_params);
    $leagues_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $leagues_list = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
    
    .main-content {
        margin-left: 280px;
        padding: 30px;
        background: #f5f7fa;
        min-height: calc(100vh - 70px);
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-title {
        font-size: 32px;
        font-weight: 700;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .btn {
        padding: 12px 24px;
        border-radius: 8px;
        border: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(29, 96, 172, 0.3);
    }
    
    .btn-secondary {
        background: #F1A155;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(241, 161, 85, 0.3);
    }
    
    .btn-danger {
        background: #dc3545;
        color: #FFFFFF;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .btn-info {
        background: #17a2b8;
        color: #FFFFFF;
    }
    
    .btn-info:hover {
        background: #138496;
    }
    
    .btn-success {
        background: #28a745;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        background: #218838;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }
    
    .search-bar-standalone {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .search-input-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f8f9fa;
        padding: 12px 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
    }
    
    .search-input-wrapper input {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        font-size: 14px;
        color: #333;
    }
    
    .search-input-wrapper input::placeholder {
        color: #999;
    }
    
    .search-icon {
        color: #1D60AC;
        font-size: 18px;
    }
    
    .data-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .data-card-header {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
        padding: 20px 25px;
        font-size: 20px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .header-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .header-title {
        font-size: 22px;
        font-weight: 700;
    }
    
    .header-meta {
        font-size: 13px;
        opacity: 0.9;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .back-btn {
        background: rgba(255, 255, 255, 0.2);
        color: #FFFFFF;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .back-btn:hover {
        background: rgba(255, 255, 255, 0.3);
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table thead {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
    }
    
    .data-table th {
        padding: 15px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        color: #666;
        font-size: 14px;
    }
    
    .data-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-success {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }
    
    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-gk {
        background: #ffc107;
        color: #000;
    }
    
    .badge-def {
        background: #28a745;
        color: #fff;
    }
    
    .badge-mid {
        background: #007bff;
        color: #fff;
    }
    
    .badge-att {
        background: #dc3545;
        color: #fff;
    }
    
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .tab {
        padding: 12px 24px;
        background: transparent;
        border: none;
        font-size: 14px;
        font-weight: 600;
        color: #666;
        cursor: pointer;
        transition: all 0.3s ease;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    
    .tab:hover {
        color: #1D60AC;
    }
    
    .tab.active {
        color: #1D60AC;
        border-bottom-color: #1D60AC;
    }
    
    .tab-disabled {
        opacity: 0.5;
        cursor: not-allowed !important;
    }
    
    .tab-disabled:hover {
        color: #666 !important;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: #FFFFFF;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    
    .modal-header {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
        padding: 20px 25px;
        font-size: 20px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-close {
        background: none;
        border: none;
        color: #FFFFFF;
        font-size: 24px;
        cursor: pointer;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: background 0.3s ease;
    }
    
    .modal-close:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .info-card {
        background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
        border-left: 4px solid #1D60AC;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .info-card-title {
        font-size: 14px;
        font-weight: 700;
        color: #1D60AC;
        margin-bottom: 5px;
    }
    
    .info-card-text {
        font-size: 13px;
        color: #666;
        line-height: 1.6;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #1D60AC;
        box-shadow: 0 0 0 3px rgba(29, 96, 172, 0.1);
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: linear-gradient(135deg, rgba(29, 96, 172, 0.05), rgba(10, 146, 215, 0.05));
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        border: 1px solid rgba(29, 96, 172, 0.2);
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .price-display {
        font-size: 18px;
        font-weight: 700;
        color: #F1A155;
    }
    
    .player-profile {
        display: flex;
        gap: 20px;
        align-items: center;
        padding: 20px;
        background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
        border-radius: 12px;
        margin-bottom: 20px;
    }
    
    .player-avatar {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 40px;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    .player-info {
        flex: 1;
    }
    
    .player-name-large {
        font-size: 28px;
        font-weight: 700;
        color: #1D60AC;
        margin-bottom: 8px;
    }
    
    .player-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 14px;
        color: #666;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: #FFFFFF;
        border-radius: 8px;
        padding: 20px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: #1D60AC;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(29, 96, 172, 0.1);
    }
    
    .stat-card-positive {
        border-left: 4px solid #28a745;
    }
    
    .stat-card-negative {
        border-left: 4px solid #dc3545;
    }
    
    .stat-card-neutral {
        border-left: 4px solid #17a2b8;
    }
    
    .stat-card-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .stat-card-title {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .stat-card-value {
        font-size: 32px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .stat-card-points {
        font-size: 14px;
        color: #999;
        margin-top: 5px;
    }
    
    .total-points-card {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 30px;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 20px;
    }
    
    .total-points-label {
        font-size: 16px;
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .total-points-value {
        font-size: 56px;
        font-weight: 700;
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .table-container {
            overflow-x: scroll;
        }
        
        .modal-content {
            width: 95%;
        }
        
        .tabs {
            overflow-x: auto;
        }
        
        .header-meta {
            flex-direction: column;
            gap: 5px;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .player-profile {
            flex-direction: column;
            text-align: center;
        }
        
        .player-meta {
            justify-content: center;
        }
    }
</style>
<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">Players Management</h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="openAddPlayerModal()">➕ Add New Player</button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="search-bar-standalone">
        <form method="GET">
            <div class="search-input-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" name="leagues_search" placeholder="Search leagues, owners..." value="<?php echo htmlspecialchars($leagues_search); ?>">
            </div>
        </form>
    </div>

    <div id="leaguesList" class="data-card">
        <div class="data-card-header">
            <div class="header-info">
                <div class="header-title">Leagues</div>
                <div class="header-meta">Select a league to manage players</div>
            </div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Owner</th>
                        <th>Co-Owner</th>
                        <th>Players</th>
                        <th>Teams</th>
                        <th>System</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leagues_list as $league): ?>
                        <tr data-league-id="<?php echo $league['league_id']; ?>">
                            <td><?php echo htmlspecialchars($league['league_name']); ?></td>
                            <td><?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($league['other_owner_name'] ?? 'N/A'); ?></td>
                            <td class="league-players"><?php echo $league['num_of_players']; ?></td>
                            <td class="league-teams"><?php echo $league['num_of_teams']; ?></td>
                            <td><?php echo $league['system'] === 'Budget' ? '💰 Budget' : '🚫 No Limits'; ?></td>
                            <td><?php echo $league['league_activated'] ? '✅ Active' : '⏳ Inactive'; ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="showLeaguePlayers(<?php echo $league['league_id']; ?>, '<?php echo addslashes($league['league_name']); ?>', <?php echo $league['league_activated'] ? 1 : 0; ?>, '<?php echo $league['system']; ?>')">View Players</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="leagueContent" style="display: none;">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="leagueHeaderTitle"></div>
                    <div class="header-meta" id="leagueHeaderMeta"></div>
                </div>
                <button class="back-btn" onclick="backToLeagues()">← Back to Leagues</button>
            </div>
            <div class="tab-container">
                <button id="playersListTab" class="tab active" onclick="switchTab('players-list')">Players List</button>
                <button id="playerStatsTab" class="tab tab-disabled" disabled>Player Statistics</button>
            </div>
            <div id="playersList" class="tab-content active">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Team</th>
                                <th>Points</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="playersTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div id="player-statistics" class="tab-content">
                <div class="data-card-header">
                    <div class="header-info">
                        <div class="header-title" id="statsPlayerName"></div>
                        <div class="header-meta" id="statsPlayerMeta"></div>
                    </div>
                </div>
                <div id="playerStatsContent"></div>
            </div>
        </div>
    </div>

    <!-- Add Player Modal -->
    <div id="addPlayerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddPlayerModal()">&times;</span>
            <h2>Add New Player</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_player">
                <input type="hidden" name="league_id" id="addPlayerLeagueId">
                <div class="form-group">
                    <label for="addPlayerName">Player Name</label>
                    <input type="text" id="addPlayerName" name="player_name" required>
                </div>
                <div class="form-group">
                    <label for="addPlayerRole">Position</label>
                    <select id="addPlayerRole" name="player_role" required>
                        <option value="">Select Position</option>
                        <option value="GK">Goalkeeper</option>
                        <option value="DEF">Defender</option>
                        <option value="MID">Midfielder</option>
                        <option value="ATT">Attacker</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="addPlayerTeam">Team</label>
                    <select id="addPlayerTeam" name="team_id" required></select>
                </div>
                <div class="form-group" id="addPlayerPriceGroup">
                    <label for="addPlayerPrice">Price <span id="addPriceOptional">(Optional)</span></label>
                    <input type="number" step="0.01" id="addPlayerPrice" name="player_price">
                    <small id="addPlayerPriceInfo" style="display: none;">Required for Budget system leagues</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Add Player</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddPlayerModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Player Modal -->
    <div id="editPlayerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditPlayerModal()">&times;</span>
            <h2>Edit Player</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_player">
                <input type="hidden" name="player_id" id="editPlayerId">
                <div class="form-group">
                    <label for="editPlayerName">Player Name</label>
                    <input type="text" id="editPlayerName" name="player_name" required>
                </div>
                <div class="form-group">
                    <label for="editPlayerRole">Position</label>
                    <select id="editPlayerRole" name="player_role" required>
                        <option value="">Select Position</option>
                        <option value="GK">Goalkeeper</option>
                        <option value="DEF">Defender</option>
                        <option value="MID">Midfielder</option>
                        <option value="ATT">Attacker</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editPlayerTeam">Team</label>
                    <select id="editPlayerTeam" name="team_id" required></select>
                </div>
                <div class="form-group">
                    <label for="editPlayerPrice">Price <span id="editPriceOptional">(Optional)</span></label>
                    <input type="number" step="0.01" id="editPlayerPrice" name="player_price">
                    <small id="editPlayerPriceInfo" style="display: none;">Required for Budget system leagues</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Player</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditPlayerModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Player Modal -->
    <div id="deletePlayerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeletePlayerModal()">&times;</span>
            <h2>Delete Player</h2>
            <p>Are you sure you want to delete <strong id="deletePlayerName"></strong> (<span id="deletePlayerPosition"></span>)?</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_player">
                <input type="hidden" name="player_id" id="deletePlayerId">
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeletePlayerModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentLeagueSystem = null;
    let currentLeagueTeams = [];
    let currentPlayerId = null;
    let currentTab = 'players-list';

    function showLeaguePlayers(leagueId, leagueName, activated, system) {
        currentLeagueId = leagueId;
        currentLeagueSystem = system;
        
        // Update header
        document.getElementById('leagueHeaderTitle').textContent = leagueName;
        document.getElementById('leagueHeaderMeta').innerHTML = `
            Players: ${document.querySelector(`[data-league-id="${leagueId}"] .league-players`).textContent} |
            Teams: ${document.querySelector(`[data-league-id="${leagueId}"] .league-teams`).textContent} |
            System: ${system === 'Budget' ? '💰 Budget' : '🚫 No Limits'}
            ${activated ? ' | ✅ Activated' : ''}
        `;
        
        // Show league content
        document.getElementById('leaguesList').style.display = 'none';
        document.getElementById('leagueContent').style.display = 'block';
        
        // Enable tabs based on activation
        const playersTab = document.getElementById('playersListTab');
        const statsTab = document.getElementById('playerStatsTab');
        
        playersTab.disabled = false;
        playersTab.classList.remove('tab-disabled');
        statsTab.disabled = true;
        statsTab.classList.add('tab-disabled');
        
        // Load teams first
        loadLeagueTeams(leagueId).then(() => {
            // Then load players
            loadLeaguePlayers(leagueId);
        });
    }

    function loadLeagueTeams(leagueId) {
        return fetch(`?ajax=get_league_teams&league_id=${leagueId}`)
            .then(response => response.json())
            .then(teams => {
                if (teams.error) {
                    console.error(teams.error);
                    return;
                }
                currentLeagueTeams = teams;
            })
            .catch(error => console.error(error));
    }

    function loadLeaguePlayers(leagueId) {
        const tbody = document.getElementById('playersTableBody');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Loading players...</td></tr>';
        
        fetch(`?ajax=get_league_players&league_id=${leagueId}`)
            .then(response => response.json())
            .then(players => {
                if (players.error) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + players.error + '</td></tr>';
                    return;
                }
                
                if (players.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #666;">No players found in this league</td></tr>';
                    return;
                }
                
                tbody.innerHTML = '';
                players.forEach(player => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${player.player_name}</td>
                        <td>${getRoleBadge(player.player_role)}</td>
                        <td>${player.team_name || 'No Team'}</td>
                        <td>${player.total_points || 0}</td>
                        <td>${currentLeagueSystem === 'Budget' ? parseFloat(player.player_price || 0).toFixed(2) : '-'}</td>
                        <td>
                            <button class="btn btn-info btn-sm" onclick="viewPlayerStats(${player.player_id})">📊 Stats</button>
                            <button class="btn btn-secondary btn-sm" onclick="editPlayer(${player.player_id})">✏️ Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deletePlayer(${player.player_id}, '${escapeHtml(player.player_name)}', '${player.player_role}')">🗑️ Delete</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error loading players</td></tr>';
            });
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function backToLeagues() {
        document.getElementById('leagueContent').style.display = 'none';
        document.getElementById('leaguesList').style.display = 'block';
        currentLeagueId = null;
        currentLeagueSystem = null;
        currentLeagueTeams = [];
        currentPlayerId = null;
        currentTab = 'players-list';
    }

    function switchTab(tabId) {
        if (tabId === 'player-statistics' && !currentPlayerId) return;
        
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.getElementById(tabId + 'Tab').classList.add('active');
        document.getElementById(tabId === 'players-list' ? 'playersList' : 'player-statistics').classList.add('active');
        
        currentTab = tabId;
        
        if (tabId === 'players-list') {
            loadLeaguePlayers(currentLeagueId);
        }
    }

    function getRoleBadge(role) {
        const badges = {
            'GK': '<span class="badge badge-gk">🟡 Goalkeeper</span>',
            'DEF': '<span class="badge badge-def">🟢 Defender</span>',
            'MID': '<span class="badge badge-mid">🔵 Midfielder</span>',
            'ATT': '<span class="badge badge-att">🔴 Attacker</span>'
        };
        return badges[role] || '<span class="badge badge-info">' + role + '</span>';
    }
    
    function viewPlayerStats(playerId) {
        currentPlayerId = playerId;
        
        // Enable stats tab
        const statsTab = document.getElementById('playerStatsTab');
        statsTab.disabled = false;
        statsTab.classList.remove('tab-disabled');
        statsTab.onclick = function() { switchTab('player-statistics'); };
        
        // Switch to stats tab
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        statsTab.classList.add('active');
        document.getElementById('player-statistics').classList.add('active');
        currentTab = 'player-statistics';
        
        // Load player stats
        loadPlayerStats(playerId);
    }
    
    function loadPlayerStats(playerId) {
    const content = document.getElementById('playerStatsContent');
    content.innerHTML = '<div style="text-align: center; padding: 50px; color: #999;">Loading statistics...</div>';
    
    fetch('?ajax=get_player_stats&player_id=' + playerId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<div style="text-align: center; padding: 50px; color: #dc3545;">Error: ' + data.error + '</div>';
                return;
            }
            
            const player = data.player;
            const stats = data.stats;
            const rules = data.rules;
            const lineupCount = data.lineup_count || 0;
            
            // Update header
            document.getElementById('statsPlayerName').textContent = player.player_name;
            const roleBadge = getRoleBadge(player.player_role);
            const priceDisplay = player.player_price && currentLeagueSystem !== 'No Limits' ? 
                ' | Price: <span class="price-display">' + parseFloat(player.player_price).toFixed(2) + '</span>' : '';
            document.getElementById('statsPlayerMeta').innerHTML = `
                <span>${roleBadge}</span>
                <span>Team: ${player.team_name || 'N/A'}</span>
                <span>League: ${player.league_name}</span>
                <span>👥 Owned by: ${lineupCount} contributor${lineupCount !== 1 ? 's' : ''}</span>
                ${priceDisplay}
            `;
            
            // Build stats display
            let statsHtml = `
                <div class="player-profile">
                    <div class="player-avatar">
                        ${player.player_name.charAt(0).toUpperCase()}
                    </div>
                    <div class="player-info">
                        <div class="player-name-large">${player.player_name}</div>
                        <div class="player-meta">
                            <span>${getRoleText(player.player_role)}</span>
                            <span>🏟️ ${player.team_name || 'No Team'}</span>
                            <span>👥 Owned by ${lineupCount} contributor${lineupCount !== 1 ? 's' : ''}</span>
                            ${player.player_price && currentLeagueSystem !== 'No Limits' ? '<span>💰 ' + parseFloat(player.player_price).toFixed(2) + '</span>' : ''}
                        </div>
                    </div>
                </div>
                
                <div class="total-points-card">
                    <div class="total-points-label">🏆 Total Fantasy Points</div>
                    <div class="total-points-value">${stats.total_points}</div>
                </div>
                
                <div class="stats-grid">
            `;
            
            // Goals
            const goalPoints = calculateGoalPoints(player.player_role, stats.goals, rules);
            statsHtml += `
                <div class="stat-card stat-card-positive">
                    <div class="stat-card-icon">⚽</div>
                    <div class="stat-card-title">Goals Scored</div>
                    <div class="stat-card-value">${stats.goals}</div>
                    <div class="stat-card-points">Points: +${goalPoints}</div>
                </div>
            `;
            
            // Assists
            const assistPoints = calculateAssistPoints(player.player_role, stats.assists, rules);
            statsHtml += `
                <div class="stat-card stat-card-positive">
                    <div class="stat-card-icon">🎯</div>
                    <div class="stat-card-title">Assists</div>
                    <div class="stat-card-value">${stats.assists}</div>
                    <div class="stat-card-points">Points: +${assistPoints}</div>
                </div>
            `;
            
            // Clean Sheets (only for GK and DEF)
            if (player.player_role === 'GK' || player.player_role === 'DEF') {
                const csPoints = calculateCleanSheetPoints(player.player_role, stats.clean_sheets, rules);
                statsHtml += `
                    <div class="stat-card stat-card-positive">
                        <div class="stat-card-icon">🛡️</div>
                        <div class="stat-card-title">Clean Sheets</div>
                        <div class="stat-card-value">${stats.clean_sheets}</div>
                        <div class="stat-card-points">Points: +${csPoints}</div>
                    </div>
                `;
            }
            
            // Penalties Saved (only for GK)
            if (player.player_role === 'GK') {
                const penSavePoints = (stats.penalties_saved * (rules?.gk_save_penalty || 0));
                statsHtml += `
                    <div class="stat-card stat-card-positive">
                        <div class="stat-card-icon">🧤</div>
                        <div class="stat-card-title">Penalties Saved</div>
                        <div class="stat-card-value">${stats.penalties_saved}</div>
                        <div class="stat-card-points">Points: +${penSavePoints}</div>
                    </div>
                `;
            }
            
            // Penalties Missed
            const penMissPoints = (stats.penalties_missed * (rules?.miss_penalty || 0));
            statsHtml += `
                <div class="stat-card stat-card-negative">
                    <div class="stat-card-icon">❌</div>
                    <div class="stat-card-title">Penalties Missed</div>
                    <div class="stat-card-value">${stats.penalties_missed}</div>
                    <div class="stat-card-points">Points: ${penMissPoints}</div>
                </div>
            `;
            
            // Yellow Cards
            const yellowPoints = (stats.yellow_cards * (rules?.yellow_card || 0));
            statsHtml += `
                <div class="stat-card stat-card-negative">
                    <div class="stat-card-icon">🟨</div>
                    <div class="stat-card-title">Yellow Cards</div>
                    <div class="stat-card-value">${stats.yellow_cards}</div>
                    <div class="stat-card-points">Points: ${yellowPoints}</div>
                </div>
            `;
            
            // Red Cards
            const redPoints = (stats.red_cards * (rules?.red_card || 0));
            statsHtml += `
                <div class="stat-card stat-card-negative">
                    <div class="stat-card-icon">🟥</div>
                    <div class="stat-card-title">Red Cards</div>
                    <div class="stat-card-value">${stats.red_cards}</div>
                    <div class="stat-card-points">Points: ${redPoints}</div>
                </div>
            `;
            
            // Bonus Events
            statsHtml += `
                <div class="stat-card stat-card-neutral">
                    <div class="stat-card-icon">⭐</div>
                    <div class="stat-card-title">Bonus Events</div>
                    <div class="stat-card-value">${stats.bonus_count}</div>
                    <div class="stat-card-points">Performance bonuses</div>
                </div>
            `;
            
            // Minus Events
            statsHtml += `
                <div class="stat-card stat-card-neutral">
                    <div class="stat-card-icon">⚠️</div>
                    <div class="stat-card-title">Minus Events</div>
                    <div class="stat-card-value">${stats.minus_count}</div>
                    <div class="stat-card-points">Performance penalties</div>
                </div>
            `;
            
            statsHtml += `</div>`;
            
            // Add league scoring rules info
            if (rules) {
                statsHtml += `
                    <div class="info-card">
                        <div class="info-card-title">📋 League Scoring Rules</div>
                        <div class="info-card-text">
                            <strong>For ${getRoleText(player.player_role)}:</strong><br>
                `;
                
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
                        </div>
                    </div>
                `;
            }
            
            content.innerHTML = statsHtml;
        })
        .catch(error => {
            console.error(error);
            content.innerHTML = '<div style="text-align: center; padding: 50px; color: #dc3545;">Error loading player statistics</div>';
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
    
    function getRoleText(role) {
        const roles = {
            'GK': '🟡 Goalkeeper',
            'DEF': '🟢 Defender',
            'MID': '🔵 Midfielder',
            'ATT': '🔴 Attacker'
        };
        return roles[role] || role;
    }
    
    function openAddPlayerModal() {
        document.getElementById('addPlayerLeagueId').value = currentLeagueId;
        document.getElementById('addPlayerName').value = '';
        document.getElementById('addPlayerRole').value = '';
        document.getElementById('addPlayerPrice').value = '';
        
        // Populate team dropdown
        populateTeamDropdown('addPlayerTeam');
        
        // Show/hide price field based on league system
        if (currentLeagueSystem === 'Budget') {
            document.getElementById('addPlayerPriceGroup').style.display = 'block';
            document.getElementById('addPlayerPriceInfo').style.display = 'block';
            document.getElementById('addPriceOptional').style.display = 'none';
            document.getElementById('addPlayerPrice').required = true;
        } else {
            document.getElementById('addPlayerPriceGroup').style.display = 'none';
            document.getElementById('addPlayerPriceInfo').style.display = 'none';
            document.getElementById('addPlayerPrice').required = false;
        }
        
        document.getElementById('addPlayerModal').classList.add('active');
    }
    
    function closeAddPlayerModal() {
        document.getElementById('addPlayerModal').classList.remove('active');
    }
    
    function populateTeamDropdown(selectId) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Select Team</option>';
        
        if (!currentLeagueTeams || currentLeagueTeams.length === 0) {
            select.innerHTML = '<option value="">No teams available</option>';
            select.disabled = true;
            return;
        }
        
        select.disabled = false;
        currentLeagueTeams.forEach(team => {
            const option = document.createElement('option');
            option.value = team.id;
            option.textContent = team.team_name;
            select.appendChild(option);
        });
    }
    
    function editPlayer(playerId) {
        fetch('?ajax=get_player&player_id=' + playerId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('editPlayerId').value = data.player_id;
                document.getElementById('editPlayerName').value = data.player_name;
                document.getElementById('editPlayerRole').value = data.player_role;
                document.getElementById('editPlayerPrice').value = data.player_price || '';
                
                // Populate and set team dropdown
                populateTeamDropdown('editPlayerTeam');
                document.getElementById('editPlayerTeam').value = data.team_id || '';
                
                // Show/hide price info based on league system
                if (data.league_system === 'Budget') {
                    document.getElementById('editPlayerPriceInfo').style.display = 'block';
                    document.getElementById('editPriceOptional').style.display = 'none';
                    document.getElementById('editPlayerPrice').required = true;
                } else {
                    document.getElementById('editPlayerPriceInfo').style.display = 'none';
                    document.getElementById('editPriceOptional').style.display = 'inline';
                    document.getElementById('editPlayerPrice').required = false;
                }
                
                document.getElementById('editPlayerModal').classList.add('active');
            })
            .catch(error => {
                console.error(error);
                alert('Error loading player data');
            });
    }
    
    function closeEditPlayerModal() {
        document.getElementById('editPlayerModal').classList.remove('active');
    }
    
    function deletePlayer(playerId, playerName, playerRole) {
        document.getElementById('deletePlayerId').value = playerId;
        document.getElementById('deletePlayerName').textContent = playerName;
        document.getElementById('deletePlayerPosition').textContent = getRoleText(playerRole);
        document.getElementById('deletePlayerModal').classList.add('active');
    }
    
    function closeDeletePlayerModal() {
        document.getElementById('deletePlayerModal').classList.remove('active');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addPlayerModal');
        const editModal = document.getElementById('editPlayerModal');
        const deleteModal = document.getElementById('deletePlayerModal');
        
        if (event.target === addModal) {
            closeAddPlayerModal();
        }
        if (event.target === editModal) {
            closeEditPlayerModal();
        }
        if (event.target === deleteModal) {
            closeDeletePlayerModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>