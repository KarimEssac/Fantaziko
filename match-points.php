<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_match_points' && isset($_GET['match_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    mp.*,
                    m.round,
                    m.team1_id,
                    m.team2_id,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name,
                    scorer.player_name as scorer_name,
                    scorer.player_role as scorer_role,
                    assister.player_name as assister_name,
                    assister.player_role as assister_role,
                    bonus_player.player_name as bonus_name,
                    bonus_player.player_role as bonus_role,
                    minus_player.player_name as minus_name,
                    minus_player.player_role as minus_role
                FROM matches_points mp
                LEFT JOIN matches m ON mp.match_id = m.match_id
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                LEFT JOIN league_players scorer ON mp.scorer = scorer.player_id
                LEFT JOIN league_players assister ON mp.assister = assister.player_id
                LEFT JOIN league_players bonus_player ON mp.bonus = bonus_player.player_id
                LEFT JOIN league_players minus_player ON mp.minus = minus_player.player_id
                WHERE mp.match_id = ?
                ORDER BY mp.id DESC
            ");
            $stmt->execute([$_GET['match_id']]);
            $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($points);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_match_info' && isset($_GET['match_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    l.name as league_name,
                    l.id as league_id,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name
                FROM matches m
                LEFT JOIN leagues l ON m.league_id = l.id
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE m.match_id = ?
            ");
            $stmt->execute([$_GET['match_id']]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($match);
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
                    lt.team_name
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.league_id = ?
                ORDER BY lp.player_name
            ");
            $stmt->execute([$_GET['league_id']]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($players);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_roles' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
            $stmt->execute([$_GET['league_id']]);
            $roles = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$roles) {
                $roles = [
                    'gk_save_penalty' => 0,
                    'gk_score' => 0,
                    'gk_assist' => 0,
                    'gk_clean_sheet' => 0,
                    'def_clean_sheet' => 0,
                    'def_assist' => 0,
                    'def_score' => 0,
                    'mid_assist' => 0,
                    'mid_score' => 0,
                    'miss_penalty' => 0,
                    'for_score' => 0,
                    'for_assist' => 0,
                    'yellow_card' => 0,
                    'red_card' => 0
                ];
            }
            
            echo json_encode($roles);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_point_details' && isset($_GET['point_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    mp.*,
                    m.league_id,
                    scorer.player_name as scorer_name,
                    scorer.player_role as scorer_role,
                    assister.player_name as assister_name,
                    assister.player_role as assister_role,
                    bonus_player.player_name as bonus_name,
                    bonus_player.player_role as bonus_role,
                    minus_player.player_name as minus_name,
                    minus_player.player_role as minus_role
                FROM matches_points mp
                LEFT JOIN matches m ON mp.match_id = m.match_id
                LEFT JOIN league_players scorer ON mp.scorer = scorer.player_id
                LEFT JOIN league_players assister ON mp.assister = assister.player_id
                LEFT JOIN league_players bonus_player ON mp.bonus = bonus_player.player_id
                LEFT JOIN league_players minus_player ON mp.minus = minus_player.player_id
                WHERE mp.id = ?
            ");
            $stmt->execute([$_GET['point_id']]);
            $point = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($point);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_player_history' && isset($_GET['player_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    mp.*,
                    m.round,
                    m.match_id,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name,
                    m.team1_score,
                    m.team2_score,
                    m.created_at as match_date,
                    'scorer' as action_type
                FROM matches_points mp
                INNER JOIN matches m ON mp.match_id = m.match_id
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE mp.scorer = ?
                
                UNION ALL
                
                SELECT 
                    mp.*,
                    m.round,
                    m.match_id,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name,
                    m.team1_score,
                    m.team2_score,
                    m.created_at as match_date,
                    'assister' as action_type
                FROM matches_points mp
                INNER JOIN matches m ON mp.match_id = m.match_id
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE mp.assister = ?
                
                UNION ALL
                
                SELECT 
                    mp.*,
                    m.round,
                    m.match_id,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name,
                    m.team1_score,
                    m.team2_score,
                    m.created_at as match_date,
                    'bonus' as action_type
                FROM matches_points mp
                INNER JOIN matches m ON mp.match_id = m.match_id
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE mp.bonus = ?
                
                UNION ALL
                
                SELECT 
                    mp.*,
                    m.round,
                    m.match_id,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name,
                    m.team1_score,
                    m.team2_score,
                    m.created_at as match_date,
                    'minus' as action_type
                FROM matches_points mp
                INNER JOIN matches m ON mp.match_id = m.match_id
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE mp.minus = ?
                
                ORDER BY match_date DESC, round DESC
            ");
            $stmt->execute([$_GET['player_id'], $_GET['player_id'], $_GET['player_id'], $_GET['player_id']]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($history);
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
                case 'add_point':
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert the match point entry
        $stmt = $pdo->prepare("
            INSERT INTO matches_points (
                match_id, scorer, assister, bonus, bonus_points, minus, minus_points,
                saved_penalty_gk, missed_penalty_player, yellow_card, red_card
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['match_id'],
            !empty($_POST['scorer']) ? $_POST['scorer'] : null,
            !empty($_POST['assister']) ? $_POST['assister'] : null,
            !empty($_POST['bonus']) ? $_POST['bonus'] : null,
            !empty($_POST['bonus_points']) ? $_POST['bonus_points'] : 0,
            !empty($_POST['minus']) ? $_POST['minus'] : null,
            !empty($_POST['minus_points']) ? $_POST['minus_points'] : 0,
            !empty($_POST['saved_penalty_gk']) ? $_POST['saved_penalty_gk'] : null,
            !empty($_POST['missed_penalty_player']) ? $_POST['missed_penalty_player'] : null,
            $_POST['yellow_card'] ?? 0,
            $_POST['red_card'] ?? 0
        ]);
        
        // Get the league_id for this match
        $stmt = $pdo->prepare("SELECT league_id FROM matches WHERE match_id = ?");
        $stmt->execute([$_POST['match_id']]);
        $league_id = $stmt->fetchColumn();
        
        // Get league roles
        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $roles = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Helper function to update player points
        $updatePlayerPoints = function($player_id, $points) use ($pdo) {
            if ($player_id && $points != 0) {
                $stmt = $pdo->prepare("
                    UPDATE league_players 
                    SET total_points = total_points + ? 
                    WHERE player_id = ?
                ");
                $stmt->execute([$points, $player_id]);
            }
        };
        
        // Calculate and update points for scorer
        if (!empty($_POST['scorer'])) {
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$_POST['scorer']]);
            $role = $stmt->fetchColumn();
            
            $points = 0;
            switch($role) {
                case 'GK': $points = $roles['gk_score']; break;
                case 'DEF': $points = $roles['def_score']; break;
                case 'MID': $points = $roles['mid_score']; break;
                case 'ATT': $points = $roles['for_score']; break;
            }
            $updatePlayerPoints($_POST['scorer'], $points);
        }
        
        // Calculate and update points for assister
        if (!empty($_POST['assister'])) {
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$_POST['assister']]);
            $role = $stmt->fetchColumn();
            
            $points = 0;
            switch($role) {
                case 'GK': $points = $roles['gk_assist']; break;
                case 'DEF': $points = $roles['def_assist']; break;
                case 'MID': $points = $roles['mid_assist']; break;
                case 'ATT': $points = $roles['for_assist']; break;
            }
            $updatePlayerPoints($_POST['assister'], $points);
        }
        
        // Update bonus player points (custom points)
        if (!empty($_POST['bonus']) && !empty($_POST['bonus_points'])) {
            $updatePlayerPoints($_POST['bonus'], $_POST['bonus_points']);
        }
        
        // Update minus player points (custom negative points)
        if (!empty($_POST['minus']) && !empty($_POST['minus_points'])) {
            $updatePlayerPoints($_POST['minus'], -abs($_POST['minus_points']));
        }
        
        // Update saved penalty GK points
        if (!empty($_POST['saved_penalty_gk'])) {
            $updatePlayerPoints($_POST['saved_penalty_gk'], $roles['gk_save_penalty']);
        }
        
        // Update missed penalty player points
        if (!empty($_POST['missed_penalty_player'])) {
            $updatePlayerPoints($_POST['missed_penalty_player'], $roles['miss_penalty']);
        }
        
        // Update yellow card points
        if (!empty($_POST['yellow_card']) && $_POST['yellow_card'] > 0) {
            $yellow_points = $roles['yellow_card'] * $_POST['yellow_card'];
            // Yellow and red cards can be for any of the players mentioned
            // We'll apply it to the scorer if exists, otherwise assister
            $card_player = $_POST['scorer'] ?? $_POST['assister'] ?? null;
            if ($card_player) {
                $updatePlayerPoints($card_player, $yellow_points);
            }
        }
        
        // Update red card points
        if (!empty($_POST['red_card']) && $_POST['red_card'] > 0) {
            $red_points = $roles['red_card'] * $_POST['red_card'];
            $card_player = $_POST['scorer'] ?? $_POST['assister'] ?? null;
            if ($card_player) {
                $updatePlayerPoints($card_player, $red_points);
            }
        }
        
        $pdo->commit();
        $success_message = "Match point added successfully and player points updated!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
    break;
                    
                case 'update_point':
    $pdo->beginTransaction();
    
    try {
        // Get old values first
        $stmt = $pdo->prepare("SELECT * FROM matches_points WHERE id = ?");
        $stmt->execute([$_POST['point_id']]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get league info
        $stmt = $pdo->prepare("SELECT league_id FROM matches WHERE match_id = ?");
        $stmt->execute([$old_data['match_id']]);
        $league_id = $stmt->fetchColumn();
        
        // Get league roles
        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $roles = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Helper to reverse old points and apply new points
        $updatePlayerPoints = function($old_player_id, $new_player_id, $old_points, $new_points) use ($pdo) {
            // Reverse old points
            if ($old_player_id && $old_points != 0) {
                $stmt = $pdo->prepare("
                    UPDATE league_players 
                    SET total_points = total_points - ? 
                    WHERE player_id = ?
                ");
                $stmt->execute([$old_points, $old_player_id]);
            }
            
            // Apply new points
            if ($new_player_id && $new_points != 0) {
                $stmt = $pdo->prepare("
                    UPDATE league_players 
                    SET total_points = total_points + ? 
                    WHERE player_id = ?
                ");
                $stmt->execute([$new_points, $new_player_id]);
            }
        };
        
        // Calculate old scorer points
        $old_scorer_points = 0;
        if ($old_data['scorer']) {
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$old_data['scorer']]);
            $role = $stmt->fetchColumn();
            switch($role) {
                case 'GK': $old_scorer_points = $roles['gk_score']; break;
                case 'DEF': $old_scorer_points = $roles['def_score']; break;
                case 'MID': $old_scorer_points = $roles['mid_score']; break;
                case 'ATT': $old_scorer_points = $roles['for_score']; break;
            }
        }
        
        // Calculate new scorer points
        $new_scorer_points = 0;
        if (!empty($_POST['scorer'])) {
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$_POST['scorer']]);
            $role = $stmt->fetchColumn();
            switch($role) {
                case 'GK': $new_scorer_points = $roles['gk_score']; break;
                case 'DEF': $new_scorer_points = $roles['def_score']; break;
                case 'MID': $new_scorer_points = $roles['mid_score']; break;
                case 'ATT': $new_scorer_points = $roles['for_score']; break;
            }
        }
        $updatePlayerPoints($old_data['scorer'], $_POST['scorer'] ?? null, $old_scorer_points, $new_scorer_points);
        
        // Similar logic for assister
        $old_assister_points = 0;
        if ($old_data['assister']) {
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$old_data['assister']]);
            $role = $stmt->fetchColumn();
            switch($role) {
                case 'GK': $old_assister_points = $roles['gk_assist']; break;
                case 'DEF': $old_assister_points = $roles['def_assist']; break;
                case 'MID': $old_assister_points = $roles['mid_assist']; break;
                case 'ATT': $old_assister_points = $roles['for_assist']; break;
            }
        }
        
        $new_assister_points = 0;
        if (!empty($_POST['assister'])) {
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$_POST['assister']]);
            $role = $stmt->fetchColumn();
            switch($role) {
                case 'GK': $new_assister_points = $roles['gk_assist']; break;
                case 'DEF': $new_assister_points = $roles['def_assist']; break;
                case 'MID': $new_assister_points = $roles['mid_assist']; break;
                case 'ATT': $new_assister_points = $roles['for_assist']; break;
            }
        }
        $updatePlayerPoints($old_data['assister'], $_POST['assister'] ?? null, $old_assister_points, $new_assister_points);
        
        // Update bonus/minus with custom points
        $updatePlayerPoints(
            $old_data['bonus'], 
            $_POST['bonus'] ?? null, 
            $old_data['bonus_points'] ?? 0, 
            $_POST['bonus_points'] ?? 0
        );
        
        $updatePlayerPoints(
            $old_data['minus'], 
            $_POST['minus'] ?? null, 
            -abs($old_data['minus_points'] ?? 0), 
            -abs($_POST['minus_points'] ?? 0)
        );
        
        // Update penalty saves/misses
        $updatePlayerPoints(
            $old_data['saved_penalty_gk'], 
            $_POST['saved_penalty_gk'] ?? null, 
            $roles['gk_save_penalty'], 
            $roles['gk_save_penalty']
        );
        
        $updatePlayerPoints(
            $old_data['missed_penalty_player'], 
            $_POST['missed_penalty_player'] ?? null, 
            $roles['miss_penalty'], 
            $roles['miss_penalty']
        );
        
        // Update the record
        $stmt = $pdo->prepare("
            UPDATE matches_points 
            SET scorer = ?, assister = ?, bonus = ?, bonus_points = ?, 
                minus = ?, minus_points = ?, saved_penalty_gk = ?, 
                missed_penalty_player = ?, yellow_card = ?, red_card = ?
            WHERE id = ?
        ");
        $stmt->execute([
            !empty($_POST['scorer']) ? $_POST['scorer'] : null,
            !empty($_POST['assister']) ? $_POST['assister'] : null,
            !empty($_POST['bonus']) ? $_POST['bonus'] : null,
            !empty($_POST['bonus_points']) ? $_POST['bonus_points'] : 0,
            !empty($_POST['minus']) ? $_POST['minus'] : null,
            !empty($_POST['minus_points']) ? $_POST['minus_points'] : 0,
            !empty($_POST['saved_penalty_gk']) ? $_POST['saved_penalty_gk'] : null,
            !empty($_POST['missed_penalty_player']) ? $_POST['missed_penalty_player'] : null,
            $_POST['yellow_card'] ?? 0,
            $_POST['red_card'] ?? 0,
            $_POST['point_id']
        ]);
        
        $pdo->commit();
        $success_message = "Match point updated successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
    break;
                    
                case 'delete_point':
                    $stmt = $pdo->prepare("DELETE FROM matches_points WHERE id = ?");
                    $stmt->execute([$_POST['point_id']]);
                    $success_message = "Match point deleted successfully!";
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
    
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-gk {
        background: #e7f3ff;
        color: #0066cc;
    }
    
    .badge-def {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .badge-mid {
        background: #fff3e0;
        color: #e65100;
    }
    
    .badge-att {
        background: #fce4ec;
        color: #c2185b;
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
        max-width: 800px;
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
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .points-summary {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    .points-summary-title {
        font-size: 16px;
        font-weight: 700;
        color: #1D60AC;
        margin-bottom: 15px;
    }
    
    .points-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .point-item {
        background: #FFFFFF;
        padding: 12px;
        border-radius: 6px;
        border-left: 3px solid #1D60AC;
    }
    
    .point-label {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .point-value {
        font-size: 18px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .player-history-item {
        background: #FFFFFF;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #1D60AC;
        margin-bottom: 15px;
    }
    
    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .history-match {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }
    
    .history-date {
        font-size: 12px;
        color: #999;
    }
    
    .history-details {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 13px;
        color: #666;
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
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .points-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">📈 Match Points Management</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('league-selection')">🏆 Select League</button>
        <button class="tab tab-disabled" id="matchesTab" disabled>📅 Select Match</button>
        <button class="tab tab-disabled" id="pointsTab" disabled>📈 Match Points</button>
        <button class="tab tab-disabled" id="playerHistoryTab" disabled>👤 Player History</button>
    </div>
    
    <!-- League Selection Tab -->
    <div id="league-selection" class="tab-content active">
        <div class="search-bar-standalone">
            <div class="search-input-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="leaguesSearch" placeholder="Search leagues by name or owner..." value="<?php echo htmlspecialchars($leagues_search); ?>" onkeypress="if(event.key==='Enter') searchLeagues()">
            </div>
        </div>
        
        <div class="data-card">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>League ID</th>
                            <th>League Name</th>
                            <th>Owner</th>
                            <th>Other Owner</th>
                            <th>Players</th>
                            <th>Teams</th>
                            <th>System</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leagues_list)): ?>
                            <?php foreach ($leagues_list as $league): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($league['league_id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($league['league_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($league['other_owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $league['num_of_players']; ?></td>
                                    <td><?php echo $league['num_of_teams']; ?></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($league['system']); ?></span></td>
                                    <td>
                                        <?php if ($league['league_activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="selectLeague(<?php echo $league['league_id']; ?>)">
                                            📈 Select
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #999; padding: 30px;">No leagues found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Matches Selection Tab -->
    <div id="matches-selection" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="matchesLeagueName">Select a match</div>
                    <div class="header-meta" id="matchesLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToLeagueSelection()">
                    ← Back to Leagues
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">📅 About Match Selection</div>
                <div class="info-card-text">
                    Select a match to manage its points distribution. Each match can have multiple point entries for goals, assists, cards, and other actions.
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Match ID</th>
                            <th>Round</th>
                            <th>Match</th>
                            <th>Score</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="matchesTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 30px;">Select a league to view matches</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Match Points Tab -->
    <div id="match-points" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="pointsMatchTitle">Match Points</div>
                    <div class="header-meta" id="pointsMatchMeta"></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="openAddPointModal()" id="addPointBtn">
                        ➕ Add Point Entry
                    </button>
                    <button class="btn btn-info btn-sm" onclick="viewLeagueRoles()">
                        ⚙️ View Points System
                    </button>
                    <button class="back-btn" onclick="backToMatchesSelection()">
                        ← Back to Matches
                    </button>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">📈 About Match Points</div>
                <div class="info-card-text">
                    Manage point entries for this match. Each entry can include a scorer, assister, bonus player, minus player (for penalties), and cards. Points are calculated based on the league's role-based scoring system.
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Entry ID</th>
                            <th>Scorer</th>
                            <th>Assister</th>
                            <th>Bonus Player</th>
                            <th>Minus Player</th>
                            <th>Yellow Cards</th>
                            <th>Red Cards</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pointsTableBody">
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 30px;">Select a match to view points</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Player History Tab -->
    <div id="player-history" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title">👤 Player Performance History</div>
                </div>
                <button class="back-btn" onclick="backToLeagueSelection()">
                    ← Back to Leagues
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">👤 About Player History</div>
                <div class="info-card-text">
                    View the complete performance history of any player in the selected league, including all goals, assists, cards, and point distributions across all matches.
                </div>
            </div>
            
            <div class="form-group" style="max-width: 500px; margin: 20px 25px;">
                <label class="form-label">Select Player</label>
                <select id="playerHistorySelect" class="form-control" onchange="loadPlayerHistory(this.value)">
                    <option value="">-- Select a player --</option>
                </select>
            </div>
            
            <div id="playerHistoryContent" style="padding: 0 25px 25px;">
                <p style="text-align: center; color: #999; padding: 30px;">Select a player to view their history</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Point Modal -->
<!-- Add Point Modal -->
<div id="addPointModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Add Match Point Entry</span>
            <button class="modal-close" onclick="closeAddPointModal()">&times;</button>
        </div>
        <form id="addPointForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_point">
                <input type="hidden" name="match_id" id="addPointMatchId">
                
                <div class="info-card">
                    <div class="info-card-text">
                        Select players for different actions. Points are calculated based on player roles and the league's scoring system.
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">⚽ Scorer</label>
                        <select name="scorer" id="addScorer" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🎯 Assister</label>
                        <select name="assister" id="addAssister" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">⭐ Bonus Player (Other Actions)</label>
                        <select name="bonus" id="addBonus" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">➕ Bonus Points</label>
                        <input type="number" name="bonus_points" id="addBonusPoints" class="form-control" value="0" step="1">
                        <small style="color: #666; font-size: 12px;">Enter positive or negative points</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">❌ Minus Player (Penalty/Other)</label>
                        <select name="minus" id="addMinus" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">➖ Minus Points</label>
                        <input type="number" name="minus_points" id="addMinusPoints" class="form-control" value="0" step="1">
                        <small style="color: #666; font-size: 12px;">Enter positive number (will be deducted)</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">🧤 Goalkeeper Saved Penalty</label>
                        <select name="saved_penalty_gk" id="addSavedPenaltyGk" class="form-control" onchange="handlePenaltyChange()">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">⚠️ Player Missed Penalty <span style="color: red;">*</span></label>
                        <select name="missed_penalty_player" id="addMissedPenaltyPlayer" class="form-control" onchange="handlePenaltyChange()">
                            <option value="">-- None --</option>
                        </select>
                        <small style="color: #666; font-size: 12px;">Required if GK saved penalty is selected</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">🟨 Yellow Cards</label>
                        <input type="number" name="yellow_card" id="addYellowCard" class="form-control" min="0" value="0" step="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🟥 Red Cards</label>
                        <input type="number" name="red_card" id="addRedCard" class="form-control" min="0" value="0" step="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeAddPointModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Add Point Entry</button>
            </div>
        </form>
    </div>
</div>


<!-- Edit Point Modal -->
<div id="editPointModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit Match Point Entry</span>
            <button class="modal-close" onclick="closeEditPointModal()">&times;</button>
        </div>
        <form id="editPointForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_point">
                <input type="hidden" name="point_id" id="editPointId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">⚽ Scorer</label>
                        <select name="scorer" id="editScorer" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🎯 Assister</label>
                        <select name="assister" id="editAssister" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">⭐ Bonus Player</label>
                        <select name="bonus" id="editBonus" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">➕ Bonus Points</label>
                        <input type="number" name="bonus_points" id="editBonusPoints" class="form-control" value="0" step="1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">❌ Minus Player</label>
                        <select name="minus" id="editMinus" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">➖ Minus Points</label>
                        <input type="number" name="minus_points" id="editMinusPoints" class="form-control" value="0" step="1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">🧤 Goalkeeper Saved Penalty</label>
                        <select name="saved_penalty_gk" id="editSavedPenaltyGk" class="form-control" onchange="handleEditPenaltyChange()">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">⚠️ Player Missed Penalty <span style="color: red;">*</span></label>
                        <select name="missed_penalty_player" id="editMissedPenaltyPlayer" class="form-control" onchange="handleEditPenaltyChange()">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">🟨 Yellow Cards</label>
                        <input type="number" name="yellow_card" id="editYellowCard" class="form-control" min="0" value="0" step="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🟥 Red Cards</label>
                        <input type="number" name="red_card" id="editRedCard" class="form-control" min="0" value="0" step="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeEditPointModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Point Modal -->
<div id="deletePointModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Delete Match Point Entry</span>
            <button class="modal-close" onclick="closeDeletePointModal()">&times;</button>
        </div>
        <form id="deletePointForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete_point">
                <input type="hidden" name="point_id" id="deletePointId">
                
                <div class="info-card" style="border-left-color: #dc3545; background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                    <div class="info-card-title" style="color: #dc3545;">⚠️ Warning</div>
                    <div class="info-card-text">
                        Are you sure you want to delete this point entry?
                        <br><br>
                        <strong>Entry Details:</strong>
                        <div id="deletePointDetails" style="margin-top: 10px; line-height: 1.8;"></div>
                        <br>
                        <strong>This action cannot be undone.</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeletePointModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Delete Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- View League Roles Modal -->
<div id="leagueRolesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>⚙️ League Points System</span>
            <button class="modal-close" onclick="closeLeagueRolesModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="info-card">
                <div class="info-card-title">📊 About Points System</div>
                <div class="info-card-text">
                    This shows the points awarded for different actions based on player roles in this league.
                </div>
            </div>
            
            <div class="points-summary">
                <div class="points-summary-title">Goalkeeper (GK) Points</div>
                <div class="points-grid" id="gkPoints"></div>
            </div>
            
            <div class="points-summary">
                <div class="points-summary-title">Defender (DEF) Points</div>
                <div class="points-grid" id="defPoints"></div>
            </div>
            
            <div class="points-summary">
                <div class="points-summary-title">Midfielder (MID) Points</div>
                <div class="points-grid" id="midPoints"></div>
            </div>
            
            <div class="points-summary">
                <div class="points-summary-title">Forward/Attacker (ATT) Points</div>
                <div class="points-grid" id="forPoints"></div>
            </div>
            
            <div class="points-summary">
                <div class="points-summary-title">General Penalties</div>
                <div class="points-grid" id="generalPoints"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeLeagueRolesModal()">Close</button>
        </div>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentMatchId = null;
    let currentTab = 'league-selection';
    let leaguePlayers = [];
    let leagueRoles = null;
    
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
        // Load data based on tab
        if (tabName === 'matches-selection' && currentLeagueId) {
            loadLeagueMatches(currentLeagueId);
        } else if (tabName === 'match-points' && currentMatchId) {
            loadMatchPoints(currentMatchId);
        } else if (tabName === 'player-history' && currentLeagueId) {
            loadLeaguePlayers(currentLeagueId);
        }
    }
    
    function searchLeagues() {
        const search = document.getElementById('leaguesSearch').value;
        let url = window.location.pathname + '?';
        
        if (search) {
            url += 'leagues_search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }
    
    function selectLeague(leagueId) {
        currentLeagueId = leagueId;
        
        // Enable tabs
        const matchesTab = document.getElementById('matchesTab');
        const playerHistoryTab = document.getElementById('playerHistoryTab');
        
        matchesTab.disabled = false;
        matchesTab.classList.remove('tab-disabled');
        matchesTab.onclick = function() { switchTab('matches-selection'); };
        
        playerHistoryTab.disabled = false;
        playerHistoryTab.classList.remove('tab-disabled');
        playerHistoryTab.onclick = function() { switchTab('player-history'); };
        
        // Load league data
        loadLeagueData(leagueId);
        
        // Switch to matches tab
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        matchesTab.classList.add('active');
        document.getElementById('matches-selection').classList.add('active');
        currentTab = 'matches-selection';
        
        loadLeagueMatches(leagueId);
    }
    
    function loadLeagueData(leagueId) {
        // Load league players
        fetch('?ajax=get_league_players&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    leaguePlayers = data;
                }
            });
        
        // Load league roles
        fetch('?ajax=get_league_roles&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    leagueRoles = data;
                }
            });
    }
    
    function loadLeagueMatches(leagueId) {
        const tbody = document.getElementById('matchesTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_players&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    leaguePlayers = data;
                }
            });
        
        // Fetch matches for this league
        fetch('matches.php?ajax=get_league_matches&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No matches found in this league.</td></tr>';
                    
                    document.getElementById('matchesLeagueName').textContent = 'No matches available';
                    document.getElementById('matchesLeagueMeta').innerHTML = '';
                    return;
                }
                
                // Update header with first match's league info
                if (data[0]) {
                    document.getElementById('matchesLeagueName').textContent = data[0].league_name || 'Matches';
                    document.getElementById('matchesLeagueMeta').innerHTML = `
                        <span>League ID: ${leagueId}</span>
                        <span>Total Matches: ${data.length}</span>
                    `;
                }
                
                let html = '';
                data.forEach(match => {
                    const team1 = match.team1_name || 'TBD';
                    const team2 = match.team2_name || 'TBD';
                    const score = `${match.team1_score || 0} - ${match.team2_score || 0}`;
                    const date = new Date(match.created_at).toLocaleDateString();
                    
                    html += `
                        <tr>
                            <td>${match.match_id}</td>
                            <td><strong>Round ${match.round}</strong></td>
                            <td>${team1} vs ${team2}</td>
                            <td><strong style="color: #1D60AC; font-size: 16px;">${score}</strong></td>
                            <td>${date}</td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="selectMatch(${match.match_id})">
                                    📈 Manage Points
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error loading matches</td></tr>';
            });
    }
    
    function selectMatch(matchId) {
        currentMatchId = matchId;
        
        // Enable points tab
        const pointsTab = document.getElementById('pointsTab');
        pointsTab.disabled = false;
        pointsTab.classList.remove('tab-disabled');
        pointsTab.onclick = function() { switchTab('match-points'); };
        
        // Load match info
        fetch('?ajax=get_match_info&match_id=' + matchId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                updateMatchHeader(data);
                
                // Switch to points tab
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                pointsTab.classList.add('active');
                document.getElementById('match-points').classList.add('active');
                currentTab = 'match-points';
                
                loadMatchPoints(matchId);
            })
            .catch(error => {
                console.error(error);
                alert('Error loading match information');
            });
    }
    
    function updateMatchHeader(match) {
        const team1 = match.team1_name || 'TBD';
        const team2 = match.team2_name || 'TBD';
        const score = `${match.team1_score || 0} - ${match.team2_score || 0}`;
        
        const metaHtml = `
            <span>League: ${match.league_name}</span>
            <span>Round: ${match.round}</span>
            <span>Score: ${score}</span>
        `;
        
        document.getElementById('pointsMatchTitle').textContent = `${team1} vs ${team2}`;
        document.getElementById('pointsMatchMeta').innerHTML = metaHtml;
    }
    
    function loadMatchPoints(matchId) {
        const tbody = document.getElementById('pointsTableBody');
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_match_points&match_id=' + matchId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #999;">No point entries for this match. Click "Add Point Entry" to create one.</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(point => {
                    const scorer = point.scorer_name ? `${point.scorer_name} <span class="badge badge-${getRoleBadgeClass(point.scorer_role)}">${point.scorer_role}</span>` : '-';
                    const assister = point.assister_name ? `${point.assister_name} <span class="badge badge-${getRoleBadgeClass(point.assister_role)}">${point.assister_role}</span>` : '-';
                    const bonus = point.bonus_name ? `${point.bonus_name} <span class="badge badge-${getRoleBadgeClass(point.bonus_role)}">${point.bonus_role}</span>` : '-';
                    const minus = point.minus_name ? `${point.minus_name} <span class="badge badge-${getRoleBadgeClass(point.minus_role)}">${point.minus_role}</span>` : '-';
                    
                    html += `
                        <tr>
                            <td><strong>#${point.id}</strong></td>
                            <td>${scorer}</td>
                            <td>${assister}</td>
                            <td>${bonus}</td>
                            <td>${minus}</td>
                            <td>${point.yellow_card > 0 ? '🟨 ' + point.yellow_card : '-'}</td>
                            <td>${point.red_card > 0 ? '🟥 ' + point.red_card : '-'}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-secondary btn-sm" onclick="editPoint(${point.id})">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deletePoint(${point.id})">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #dc3545;">Error loading points</td></tr>';
            });
    }
    
    function getRoleBadgeClass(role) {
        const classes = {
            'GK': 'gk',
            'DEF': 'def',
            'MID': 'mid',
            'ATT': 'att'
        };
        return classes[role] || 'info';
    }
    function populateGKDropdown(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    select.innerHTML = '<option value="">-- None --</option>';
    
    leaguePlayers.filter(player => player.player_role === 'GK').forEach(player => {
        const option = document.createElement('option');
        option.value = player.player_id;
        option.textContent = `${player.player_name} - ${player.team_name || 'No Team'}`;
        select.appendChild(option);
    });
}
    function populatePlayerSelects() {
    const regularSelects = ['addScorer', 'addAssister', 'addBonus', 'addMinus', 
                           'editScorer', 'editAssister', 'editBonus', 'editMinus', 
                           'addMissedPenaltyPlayer', 'editMissedPenaltyPlayer'];
    
    regularSelects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '<option value="">-- None --</option>';
        
        leaguePlayers.forEach(player => {
            const option = document.createElement('option');
            option.value = player.player_id;
            option.textContent = `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
            select.appendChild(option);
        });
    });
    
    // Populate GK-only dropdowns
    populateGKDropdown('addSavedPenaltyGk');
    populateGKDropdown('editSavedPenaltyGk');
}
    document.getElementById('editPointForm').addEventListener('submit', function(e) {
    const savedGk = document.getElementById('editSavedPenaltyGk').value;
    const missedPlayer = document.getElementById('editMissedPenaltyPlayer').value;
    
    if ((savedGk && !missedPlayer) || (!savedGk && missedPlayer)) {
        e.preventDefault();
        alert('If a goalkeeper saved a penalty, you must select which player missed it, and vice versa.');
        return false;
    }
});
    function openAddPointModal() {
        document.getElementById('addPointMatchId').value = currentMatchId;
        populatePlayerSelects();
        document.getElementById('addPointForm').reset();
        document.getElementById('addPointMatchId').value = currentMatchId;
        document.getElementById('addPointModal').classList.add('active');
    }
    document.getElementById('addPointForm').addEventListener('submit', function(e) {
    const savedGk = document.getElementById('addSavedPenaltyGk').value;
    const missedPlayer = document.getElementById('addMissedPenaltyPlayer').value;
    
    if ((savedGk && !missedPlayer) || (!savedGk && missedPlayer)) {
        e.preventDefault();
        alert('If a goalkeeper saved a penalty, you must select which player missed it, and vice versa.');
        return false;
    }
});
    function closeAddPointModal() {
        document.getElementById('addPointModal').classList.remove('active');
    }
    
    function editPoint(pointId) {
    fetch('?ajax=get_point_details&point_id=' + pointId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            populatePlayerSelects();
            
            document.getElementById('editPointId').value = data.id;
            document.getElementById('editScorer').value = data.scorer || '';
            document.getElementById('editAssister').value = data.assister || '';
            document.getElementById('editBonus').value = data.bonus || '';
            document.getElementById('editBonusPoints').value = data.bonus_points || 0;
            document.getElementById('editMinus').value = data.minus || '';
            document.getElementById('editMinusPoints').value = data.minus_points || 0;
            document.getElementById('editSavedPenaltyGk').value = data.saved_penalty_gk || '';
            document.getElementById('editMissedPenaltyPlayer').value = data.missed_penalty_player || '';
            document.getElementById('editYellowCard').value = data.yellow_card || 0;
            document.getElementById('editRedCard').value = data.red_card || 0;
            
            document.getElementById('editPointModal').classList.add('active');
        })
        .catch(error => {
            console.error(error);
            alert('Error loading point data');
        });
}
    
    function closeEditPointModal() {
        document.getElementById('editPointModal').classList.remove('active');
    }
    
    function deletePoint(pointId) {
        fetch('?ajax=get_point_details&point_id=' + pointId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                let details = '<ul style="margin: 10px 0 0 20px; line-height: 1.8;">';
                if (data.scorer_name) details += `<li>⚽ Scorer: ${data.scorer_name} (${data.scorer_role})</li>`;
                if (data.assister_name) details += `<li>🎯 Assister: ${data.assister_name} (${data.assister_role})</li>`;
                if (data.bonus_name) details += `<li>⭐ Bonus: ${data.bonus_name} (${data.bonus_role})</li>`;
                if (data.minus_name) details += `<li>❌ Minus: ${data.minus_name} (${data.minus_role})</li>`;
                if (data.yellow_card > 0) details += `<li>🟨 Yellow Cards: ${data.yellow_card}</li>`;
                if (data.red_card > 0) details += `<li>🟥 Red Cards: ${data.red_card}</li>`;
                details += '</ul>';
                
                document.getElementById('deletePointId').value = pointId;
                document.getElementById('deletePointDetails').innerHTML = details;
                document.getElementById('deletePointModal').classList.add('active');
            })
            .catch(error => {
                console.error(error);
                alert('Error loading point data');
            });
    }
    
    function closeDeletePointModal() {
        document.getElementById('deletePointModal').classList.remove('active');
    }
    
    function viewLeagueRoles() {
        if (!leagueRoles) {
            alert('Loading league roles...');
            return;
        }
        
        // Populate GK points
        document.getElementById('gkPoints').innerHTML = `
            <div class="point-item">
                <div class="point-label">Save Penalty</div>
                <div class="point-value">${leagueRoles.gk_save_penalty}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Score Goal</div>
                <div class="point-value">${leagueRoles.gk_score}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Assist</div>
                <div class="point-value">${leagueRoles.gk_assist}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Clean Sheet</div>
                <div class="point-value">${leagueRoles.gk_clean_sheet}</div>
            </div>
        `;
        
        // Populate DEF points
        document.getElementById('defPoints').innerHTML = `
            <div class="point-item">
                <div class="point-label">Clean Sheet</div>
                <div class="point-value">${leagueRoles.def_clean_sheet}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Assist</div>
                <div class="point-value">${leagueRoles.def_assist}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Score Goal</div>
                <div class="point-value">${leagueRoles.def_score}</div>
            </div>
        `;
        
        // Populate MID points
        document.getElementById('midPoints').innerHTML = `
            <div class="point-item">
                <div class="point-label">Assist</div>
                <div class="point-value">${leagueRoles.mid_assist}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Score Goal</div>
                <div class="point-value">${leagueRoles.mid_score}</div>
            </div>
        `;
        
        // Populate FOR points
        document.getElementById('forPoints').innerHTML = `
            <div class="point-item">
                <div class="point-label">Score Goal</div>
                <div class="point-value">${leagueRoles.for_score}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Assist</div>
                <div class="point-value">${leagueRoles.for_assist}</div>
            </div>
        `;
        
        // Populate general penalties
        document.getElementById('generalPoints').innerHTML = `
            <div class="point-item">
                <div class="point-label">Miss Penalty</div>
                <div class="point-value">${leagueRoles.miss_penalty}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Yellow Card</div>
                <div class="point-value">${leagueRoles.yellow_card}</div>
            </div>
            <div class="point-item">
                <div class="point-label">Red Card</div>
                <div class="point-value">${leagueRoles.red_card}</div>
            </div>
        `;
        
        document.getElementById('leagueRolesModal').classList.add('active');
    }
    
    function closeLeagueRolesModal() {
        document.getElementById('leagueRolesModal').classList.remove('active');
    }
    
    function loadLeaguePlayers(leagueId) {
        const select = document.getElementById('playerHistorySelect');
        select.innerHTML = '<option value="">-- Loading players... --</option>';
        
        fetch('?ajax=get_league_players&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    select.innerHTML = '<option value="">Error loading players</option>';
                    return;
                }
                
                if (data.length === 0) {
                    select.innerHTML = '<option value="">No players found in this league</option>';
                    return;
                }
                
                select.innerHTML = '<option value="">-- Select a player --</option>';
                data.forEach(player => {
                    const option = document.createElement('option');
                    option.value = player.player_id;
                    option.textContent = `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
                    select.appendChild(option);
                });
            })
            .catch(error => {
                console.error(error);
                select.innerHTML = '<option value="">Error loading players</option>';
            });
    }
    
    function loadPlayerHistory(playerId) {
        if (!playerId) {
            document.getElementById('playerHistoryContent').innerHTML = '<p style="text-align: center; color: #999; padding: 30px;">Select a player to view their history</p>';
            return;
        }
        
        const content = document.getElementById('playerHistoryContent');
        content.innerHTML = '<p style="text-align: center; color: #999; padding: 30px;">Loading player history...</p>';
        
        fetch('?ajax=get_player_history&player_id=' + playerId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    content.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 30px;">Error: ' + data.error + '</p>';
                    return;
                }
                
                if (data.length === 0) {
                    content.innerHTML = '<p style="text-align: center; color: #999; padding: 30px;">No history found for this player.</p>';
                    return;
                }
                
                // Get player info
                const playerInfo = leaguePlayers.find(p => p.player_id == playerId);
                
                let html = '';
                
                // Summary stats
                const totalGoals = data.filter(d => d.action_type === 'scorer').length;
                const totalAssists = data.filter(d => d.action_type === 'assister').length;
                const totalBonus = data.filter(d => d.action_type === 'bonus').length;
                const totalMinus = data.filter(d => d.action_type === 'minus').length;
                const totalYellow = data.reduce((sum, d) => sum + (d.yellow_card || 0), 0);
                const totalRed = data.reduce((sum, d) => sum + (d.red_card || 0), 0);
                
                html += `
                    <div class="info-card" style="margin-bottom: 20px;">
                        <div class="info-card-title">📊 ${playerInfo ? playerInfo.player_name : 'Player'} Statistics</div>
                        <div class="points-grid" style="margin-top: 15px;">
                            <div class="point-item">
                                <div class="point-label">⚽ Goals</div>
                                <div class="point-value">${totalGoals}</div>
                            </div>
                            <div class="point-item">
                                <div class="point-label">🎯 Assists</div>
                                <div class="point-value">${totalAssists}</div>
                            </div>
                            <div class="point-item">
                                <div class="point-label">⭐ Bonus Points</div>
                                <div class="point-value">${totalBonus}</div>
                            </div>
                            <div class="point-item">
                                <div class="point-label">❌ Penalties Missed</div>
                                <div class="point-value">${totalMinus}</div>
                            </div>
                            <div class="point-item">
                                <div class="point-label">🟨 Yellow Cards</div>
                                <div class="point-value">${totalYellow}</div>
                            </div>
                            <div class="point-item">
                                <div class="point-label">🟥 Red Cards</div>
                                <div class="point-value">${totalRed}</div>
                            </div>
                        </div>
                    </div>
                    
                    <h3 style="font-size: 18px; font-weight: 700; color: #1D60AC; margin-bottom: 15px;">📜 Match History</h3>
                `;
                
                data.forEach(entry => {
                    const actionIcon = {
                        'scorer': '⚽',
                        'assister': '🎯',
                        'bonus': '⭐',
                        'minus': '❌'
                    };
                    
                    const actionLabel = {
                        'scorer': 'Scored a goal',
                        'assister': 'Provided an assist',
                        'bonus': 'Earned bonus points',
                        'minus': 'Missed a penalty'
                    };
                    
                    const date = new Date(entry.match_date).toLocaleDateString();
                    
                    html += `
                        <div class="player-history-item">
                            <div class="history-header">
                                <div class="history-match">
                                    <strong>Round ${entry.round}:</strong> ${entry.team1_name} ${entry.team1_score} - ${entry.team2_score} ${entry.team2_name}
                                </div>
                                <div class="history-date">${date}</div>
                            </div>
                            <div class="history-details">
                                <span><strong>${actionIcon[entry.action_type]} ${actionLabel[entry.action_type]}</strong></span>
                                ${entry.yellow_card > 0 ? `<span>🟨 Yellow Card</span>` : ''}
                                ${entry.red_card > 0 ? `<span>🟥 Red Card</span>` : ''}
                            </div>
                        </div>
                    `;
                });
                
                content.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                content.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 30px;">Error loading player history</p>';
            });
    }
    
    function backToLeagueSelection() {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelectorAll('.tab')[0].classList.add('active');
        document.getElementById('league-selection').classList.add('active');
        
        // Disable other tabs
        const matchesTab = document.getElementById('matchesTab');
        const pointsTab = document.getElementById('pointsTab');
        const playerHistoryTab = document.getElementById('playerHistoryTab');
        
        matchesTab.disabled = true;
        matchesTab.classList.add('tab-disabled');
        matchesTab.onclick = null;
        
        pointsTab.disabled = true;
        pointsTab.classList.add('tab-disabled');
        pointsTab.onclick = null;
        
        playerHistoryTab.disabled = true;
        playerHistoryTab.classList.add('tab-disabled');
        playerHistoryTab.onclick = null;
        
        currentTab = 'league-selection';
        currentLeagueId = null;
        currentMatchId = null;
    }
    
    function backToMatchesSelection() {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        const matchesTab = document.getElementById('matchesTab');
        matchesTab.classList.add('active');
        document.getElementById('matches-selection').classList.add('active');
        
        // Disable points tab
        const pointsTab = document.getElementById('pointsTab');
        pointsTab.disabled = true;
        pointsTab.classList.add('tab-disabled');
        pointsTab.onclick = null;
        
        currentTab = 'matches-selection';
        currentMatchId = null;
        
        loadLeagueMatches(currentLeagueId);
    }
    function handlePenaltyChange() {
    const savedGk = document.getElementById('addSavedPenaltyGk').value;
    const missedPlayer = document.getElementById('addMissedPenaltyPlayer').value;
    
    // If one is selected, the other becomes required
    if (savedGk && !missedPlayer) {
        document.getElementById('addMissedPenaltyPlayer').style.borderColor = '#dc3545';
    } else if (!savedGk && missedPlayer) {
        document.getElementById('addSavedPenaltyGk').style.borderColor = '#dc3545';
    } else {
        document.getElementById('addMissedPenaltyPlayer').style.borderColor = '';
        document.getElementById('addSavedPenaltyGk').style.borderColor = '';
    }
}
function handleEditPenaltyChange() {
    const savedGk = document.getElementById('editSavedPenaltyGk').value;
    const missedPlayer = document.getElementById('editMissedPenaltyPlayer').value;
    
    if (savedGk && !missedPlayer) {
        document.getElementById('editMissedPenaltyPlayer').style.borderColor = '#dc3545';
    } else if (!savedGk && missedPlayer) {
        document.getElementById('editSavedPenaltyGk').style.borderColor = '#dc3545';
    } else {
        document.getElementById('editMissedPenaltyPlayer').style.borderColor = '';
        document.getElementById('editSavedPenaltyGk').style.borderColor = '';
    }
}
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addPointModal');
        const editModal = document.getElementById('editPointModal');
        const deleteModal = document.getElementById('deletePointModal');
        const rolesModal = document.getElementById('leagueRolesModal');
        
        if (event.target === addModal) {
            closeAddPointModal();
        }
        if (event.target === editModal) {
            closeEditPointModal();
        }
        if (event.target === deleteModal) {
            closeDeletePointModal();
        }
        if (event.target === rolesModal) {
            closeLeagueRolesModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>