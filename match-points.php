<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
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
        minus_player.player_role as minus_role,
        saved_gk.player_name as saved_gk_name,
        saved_gk.player_role as saved_gk_role,
        missed_p.player_name as missed_player_name,
        missed_p.player_role as missed_player_role,
        yellow_p.player_name as yellow_card_player_name,
        yellow_p.player_role as yellow_card_player_role,
        red_p.player_name as red_card_player_name,
        red_p.player_role as red_card_player_role
    FROM matches_points mp
    LEFT JOIN matches m ON mp.match_id = m.match_id
    LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
    LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
    LEFT JOIN league_players scorer ON mp.scorer = scorer.player_id
    LEFT JOIN league_players assister ON mp.assister = assister.player_id
    LEFT JOIN league_players bonus_player ON mp.bonus = bonus_player.player_id
    LEFT JOIN league_players minus_player ON mp.minus = minus_player.player_id
    LEFT JOIN league_players saved_gk ON mp.saved_penalty_gk = saved_gk.player_id
    LEFT JOIN league_players missed_p ON mp.missed_penalty_player = missed_p.player_id
    LEFT JOIN league_players yellow_p ON mp.yellow_card_player = yellow_p.player_id
    LEFT JOIN league_players red_p ON mp.red_card_player = red_p.player_id
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
        // First get the player's role and team
        $stmt = $pdo->prepare("SELECT player_role, team_id FROM league_players WHERE player_id = ?");
        $stmt->execute([$_GET['player_id']]);
        $player_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$player_info) {
            echo json_encode(['error' => 'Player not found']);
            exit();
        }
        
        $player_role = $player_info['player_role'];
        $player_team_id = $player_info['team_id'];
        
        $stmt = $pdo->prepare("
            SELECT 
                mp.*,
                m.round,
                m.match_id,
                m.team1_id,
                m.team2_id,
                m.team1_score,
                m.team2_score,
                lt1.team_name as team1_name,
                lt2.team_name as team2_name,
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
                m.team1_id,
                m.team2_id,
                m.team1_score,
                m.team2_score,
                lt1.team_name as team1_name,
                lt2.team_name as team2_name,
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
                m.team1_id,
                m.team2_id,
                m.team1_score,
                m.team2_score,
                lt1.team_name as team1_name,
                lt2.team_name as team2_name,
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
                m.team1_id,
                m.team2_id,
                m.team1_score,
                m.team2_score,
                lt1.team_name as team1_name,
                lt2.team_name as team2_name,
                m.created_at as match_date,
                'minus' as action_type
            FROM matches_points mp
            INNER JOIN matches m ON mp.match_id = m.match_id
            LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
            LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
            WHERE mp.minus = ?
            
            UNION ALL
            
            SELECT 
                mp.*,
                m.round,
                m.match_id,
                m.team1_id,
                m.team2_id,
                m.team1_score,
                m.team2_score,
                lt1.team_name as team1_name,
                lt2.team_name as team2_name,
                m.created_at as match_date,
                'penalty_saved' as action_type
            FROM matches_points mp
            INNER JOIN matches m ON mp.match_id = m.match_id
            LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
            LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
            WHERE mp.saved_penalty_gk = ?
            
            ORDER BY match_date DESC, round DESC
        ");
        $stmt->execute([$_GET['player_id'], $_GET['player_id'], $_GET['player_id'], $_GET['player_id'], $_GET['player_id']]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add clean sheet records for GK and DEF
        if ($player_role === 'GK' || $player_role === 'DEF') {
            // Get all matches where player's team kept a clean sheet
            $clean_sheet_stmt = $pdo->prepare("
                SELECT 
                    m.round,
                    m.match_id,
                    m.team1_id,
                    m.team2_id,
                    m.team1_score,
                    m.team2_score,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name,
                    m.created_at as match_date,
                    'clean_sheet' as action_type
                FROM matches m
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE (
                    (m.team1_id = ? AND m.team2_score = 0)
                    OR 
                    (m.team2_id = ? AND m.team1_score = 0)
                )
                ORDER BY m.created_at DESC, m.round DESC
            ");
            $clean_sheet_stmt->execute([$player_team_id, $player_team_id]);
            $clean_sheets = $clean_sheet_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge clean sheets with history
            $history = array_merge($history, $clean_sheets);
            
            // Re-sort by date
            usort($history, function($a, $b) {
                return strtotime($b['match_date']) - strtotime($a['match_date']);
            });
        }
        
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
    $pdo->beginTransaction();
    
    try {
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
        
        // Helper function to get player role and calculate points
        $getPlayerRole = function($player_id) use ($pdo) {
            if (!$player_id) return null;
            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
            $stmt->execute([$player_id]);
            return $stmt->fetchColumn();
        };
        
        $calculateScorerPoints = function($player_id) use ($roles, $getPlayerRole) {
            if (!$player_id) return 0;
            $role = $getPlayerRole($player_id);
            switch($role) {
                case 'GK': return $roles['gk_score'];
                case 'DEF': return $roles['def_score'];
                case 'MID': return $roles['mid_score'];
                case 'ATT': return $roles['for_score'];
                default: return 0;
            }
        };
        
        $calculateAssisterPoints = function($player_id) use ($roles, $getPlayerRole) {
            if (!$player_id) return 0;
            $role = $getPlayerRole($player_id);
            switch($role) {
                case 'GK': return $roles['gk_assist'];
                case 'DEF': return $roles['def_assist'];
                case 'MID': return $roles['mid_assist'];
                case 'ATT': return $roles['for_assist'];
                default: return 0;
            }
        };
        
        // Collect all data from POST
        $scorers = isset($_POST['scorers']) && is_array($_POST['scorers']) ? array_filter($_POST['scorers']) : [];
        $assisters = isset($_POST['assisters']) && is_array($_POST['assisters']) ? $_POST['assisters'] : [];
        $bonus_players = isset($_POST['bonus_players']) && is_array($_POST['bonus_players']) ? array_filter($_POST['bonus_players']) : [];
        $bonus_points_arr = isset($_POST['bonus_points']) && is_array($_POST['bonus_points']) ? $_POST['bonus_points'] : [];
        $minus_players = isset($_POST['minus_players']) && is_array($_POST['minus_players']) ? array_filter($_POST['minus_players']) : [];
        $minus_points_arr = isset($_POST['minus_points']) && is_array($_POST['minus_points']) ? $_POST['minus_points'] : [];
        $saved_gks = isset($_POST['saved_penalty_gks']) && is_array($_POST['saved_penalty_gks']) ? array_filter($_POST['saved_penalty_gks']) : [];
        $missed_players = isset($_POST['missed_penalty_players']) && is_array($_POST['missed_penalty_players']) ? $_POST['missed_penalty_players'] : [];
        $yellow_players = isset($_POST['yellow_card_players']) && is_array($_POST['yellow_card_players']) ? array_filter($_POST['yellow_card_players']) : [];
        $red_players = isset($_POST['red_card_players']) && is_array($_POST['red_card_players']) ? array_filter($_POST['red_card_players']) : [];
        
        // Prepare arrays of bonus/minus data
        $bonus_data = [];
        foreach ($bonus_players as $idx => $player_id) {
            $bonus_data[] = [
                'player_id' => $player_id,
                'points' => isset($bonus_points_arr[$idx]) ? $bonus_points_arr[$idx] : 0
            ];
        }
        
        $minus_data = [];
        foreach ($minus_players as $idx => $player_id) {
            $minus_data[] = [
                'player_id' => $player_id,
                'points' => isset($minus_points_arr[$idx]) ? $minus_points_arr[$idx] : 0
            ];
        }
        
        $penalty_data = [];
        foreach ($saved_gks as $idx => $gk_id) {
            if (isset($missed_players[$idx]) && !empty($missed_players[$idx])) {
                $penalty_data[] = [
                    'gk_id' => $gk_id,
                    'missed_id' => $missed_players[$idx]
                ];
            }
        }
        
        // Calculate maximum number of rows needed
        $max_rows = max(
            count($scorers),
            count($bonus_data),
            count($minus_data),
            count($penalty_data),
            count($yellow_players),
            count($red_players)
        );
        
        // If nothing to add, skip
        if ($max_rows == 0) {
            throw new Exception("No data provided to add.");
        }
        
        $entries_added = 0;
        
        // Create rows, maximizing each row's usage
        for ($row = 0; $row < $max_rows; $row++) {
            // Get data for this row (if available)
            $scorer = isset($scorers[$row]) ? $scorers[$row] : null;
            $assister = isset($assisters[$row]) && !empty($assisters[$row]) ? $assisters[$row] : null;
            $bonus = isset($bonus_data[$row]) ? $bonus_data[$row]['player_id'] : null;
            $bonus_pts = isset($bonus_data[$row]) ? $bonus_data[$row]['points'] : 0;
            $minus = isset($minus_data[$row]) ? $minus_data[$row]['player_id'] : null;
            $minus_pts = isset($minus_data[$row]) ? $minus_data[$row]['points'] : 0;
            $saved_gk = isset($penalty_data[$row]) ? $penalty_data[$row]['gk_id'] : null;
            $missed_player = isset($penalty_data[$row]) ? $penalty_data[$row]['missed_id'] : null;
            
            // Handle yellow and red cards - store player_id in a dedicated field
            // We'll use a new approach: create separate fields for card player IDs
            $yellow_player_id = isset($yellow_players[$row]) ? $yellow_players[$row] : null;
            $red_player_id = isset($red_players[$row]) ? $red_players[$row] : null;
            
            // Check if row has any data
            if (!$scorer && !$assister && !$bonus && !$minus && !$saved_gk && !$missed_player && !$yellow_player_id && !$red_player_id) {
                continue;
            }
            
            // Insert the row
$stmt = $pdo->prepare("
    INSERT INTO matches_points (
        match_id, scorer, assister, bonus, bonus_points, minus, minus_points,
        saved_penalty_gk, missed_penalty_player, yellow_card, yellow_card_player, red_card, red_card_player
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $_POST['match_id'],
    $scorer,
    $assister,
    $bonus,
    $bonus_pts,
    $minus,
    $minus_pts,
    $saved_gk,
    $missed_player,
    $yellow_player_id ? 1 : 0,
    $yellow_player_id,
    $red_player_id ? 1 : 0,
    $red_player_id
]);
            
            // Update player points
            if ($scorer) {
                $points = $calculateScorerPoints($scorer);
                $updatePlayerPoints($scorer, $points);
            }
            
            if ($assister) {
                $points = $calculateAssisterPoints($assister);
                $updatePlayerPoints($assister, $points);
            }
            
            if ($bonus) {
                $updatePlayerPoints($bonus, $bonus_pts);
            }
            
            if ($minus) {
                $updatePlayerPoints($minus, -abs($minus_pts));
            }
            
            if ($saved_gk) {
                $updatePlayerPoints($saved_gk, $roles['gk_save_penalty']);
            }
            
            if ($missed_player) {
                $updatePlayerPoints($missed_player, $roles['miss_penalty']);
            }
            
            if ($yellow_player_id) {
                $updatePlayerPoints($yellow_player_id, $roles['yellow_card']);
            }
            
            if ($red_player_id) {
                $updatePlayerPoints($red_player_id, $roles['red_card']);
            }
            
            $entries_added++;
        }
        
        $pdo->commit();
        $success_message = "Successfully added {$entries_added} match point entries and updated player points!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
    break;
                    
                case 'delete_point':
                    $pdo->beginTransaction();
                    
                    try {
                        // Get the point data before deletion
                        $stmt = $pdo->prepare("SELECT * FROM matches_points WHERE id = ?");
                        $stmt->execute([$_POST['point_id']]);
                        $point_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$point_data) {
                            throw new Exception("Point entry not found.");
                        }
                        
                        // Get league info
                        $stmt = $pdo->prepare("SELECT league_id FROM matches WHERE match_id = ?");
                        $stmt->execute([$point_data['match_id']]);
                        $league_id = $stmt->fetchColumn();
                        
                        // Get league roles
                        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
                        $stmt->execute([$league_id]);
                        $roles = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Helper function to reverse player points
                        $reversePlayerPoints = function($player_id, $points) use ($pdo) {
                            if ($player_id && $points != 0) {
                                $stmt = $pdo->prepare("
                                    UPDATE league_players 
                                    SET total_points = total_points - ? 
                                    WHERE player_id = ?
                                ");
                                $stmt->execute([$points, $player_id]);
                            }
                        };
                        
                        // Reverse scorer points
                        if ($point_data['scorer']) {
                            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
                            $stmt->execute([$point_data['scorer']]);
                            $role = $stmt->fetchColumn();
                            $scorer_points = 0;
                            switch($role) {
                                case 'GK': $scorer_points = $roles['gk_score']; break;
                                case 'DEF': $scorer_points = $roles['def_score']; break;
                                case 'MID': $scorer_points = $roles['mid_score']; break;
                                case 'ATT': $scorer_points = $roles['for_score']; break;
                            }
                            $reversePlayerPoints($point_data['scorer'], $scorer_points);
                        }
                        
                        // Reverse assister points
                        if ($point_data['assister']) {
                            $stmt = $pdo->prepare("SELECT player_role FROM league_players WHERE player_id = ?");
                            $stmt->execute([$point_data['assister']]);
                            $role = $stmt->fetchColumn();
                            $assister_points = 0;
                            switch($role) {
                                case 'GK': $assister_points = $roles['gk_assist']; break;
                                case 'DEF': $assister_points = $roles['def_assist']; break;
                                case 'MID': $assister_points = $roles['mid_assist']; break;
                                case 'ATT': $assister_points = $roles['for_assist']; break;
                            }
                            $reversePlayerPoints($point_data['assister'], $assister_points);
                        }
                        
                        // Reverse bonus points
                        if ($point_data['bonus']) {
                            $reversePlayerPoints($point_data['bonus'], $point_data['bonus_points']);
                        }
                        
                        // Reverse minus points (add them back since they were deducted)
                        if ($point_data['minus']) {
                            $reversePlayerPoints($point_data['minus'], -abs($point_data['minus_points']));
                        }
                        
                        // Reverse penalty save points
                        if ($point_data['saved_penalty_gk']) {
                            $reversePlayerPoints($point_data['saved_penalty_gk'], $roles['gk_save_penalty']);
                        }
                        
                        // Reverse missed penalty points
                        if ($point_data['missed_penalty_player']) {
                            $reversePlayerPoints($point_data['missed_penalty_player'], $roles['miss_penalty']);
                        }
                        
                        // Reverse yellow card points
                        if ($point_data['yellow_card_player']) {
                            $reversePlayerPoints($point_data['yellow_card_player'], $roles['yellow_card']);
                        }
                        
                        // Reverse red card points
                        if ($point_data['red_card_player']) {
                            $reversePlayerPoints($point_data['red_card_player'], $roles['red_card']);
                        }
                        
                        // Delete the point entry
                        $stmt = $pdo->prepare("DELETE FROM matches_points WHERE id = ?");
                        $stmt->execute([$_POST['point_id']]);
                        
                        $pdo->commit();
                        $success_message = "Match point deleted successfully and player points updated!";
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "Error: " . $e->getMessage();
                    }
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
    .form-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #1D60AC;
}

.section-title {
    font-size: 16px;
    font-weight: 700;
    color: #1D60AC;
    margin: 0;
}

.scorer-pair, .bonus-item, .minus-item, .penalty-item, .card-item {
    background: #FFFFFF;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    position: relative;
    border: 1px solid #ddd;
}

.remove-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #dc3545;
    color: #FFFFFF;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s ease;
}

.remove-btn:hover {
    background: #c82333;
}

.empty-state {
    text-align: center;
    color: #999;
    padding: 20px;
    font-size: 14px;
    font-style: italic;
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
    .scorer-pair.locked {
    background: #f8f9fa;
    border: 2px solid #1D60AC;
}

.section-header.locked .btn {
    display: none;
}

.form-control:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
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
                        Add all goals, assists, and other actions for this match. You can add multiple scorers/assisters by clicking the "Add Another" buttons.
                    </div>
                </div>
                
                <!-- Scorers Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">⚽ Scorers</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addScorerField()">➕ Add Another Scorer</button>
                    </div>
                    <div id="scorersContainer">
                        <div class="scorer-pair" data-index="0">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Scorer</label>
                                    <select name="scorers[]" class="form-control">
                                        <option value="">-- Select Scorer --</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Assister (Optional)</label>
                                    <select name="assisters[]" class="form-control">
                                        <option value="">-- No Assist --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bonus Players Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">⭐ Bonus Players (Other Actions)</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addBonusField()">➕ Add Bonus Player</button>
                    </div>
                    <div id="bonusContainer"></div>
                </div>
                
                <!-- Minus Players Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">❌ Minus Players (Penalties/Other)</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addMinusField()">➕ Add Minus Player</button>
                    </div>
                    <div id="minusContainer"></div>
                </div>
                
                <!-- Penalty Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">🧤 Penalty Saves/Misses</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addPenaltyField()">➕ Add Penalty Event</button>
                    </div>
                    <div id="penaltyContainer"></div>
                </div>
                
                <!-- Yellow Cards Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">🟨 Yellow Cards</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addYellowCardField()">➕ Add Yellow Card</button>
                    </div>
                    <div id="yellowCardContainer"></div>
                </div>
                
                <!-- Red Cards Section -->
                <div class="form-section">
                    <div class="section-header">
                        <h3 class="section-title">🟥 Red Cards</h3>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addRedCardField()">➕ Add Red Card</button>
                    </div>
                    <div id="redCardContainer"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeAddPointModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Submit All Entries</button>
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
                        <strong>This action cannot be undone and will reverse all points awarded in this entry.</strong>
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
    let scorerCount = 0;
    let bonusCount = 0;
    let minusCount = 0;
    let penaltyCount = 0;
    let yellowCardCount = 0;
    let redCardCount = 0;
    let currentMatchScore = { team1: 0, team2: 0 };
    let currentMatchTeams = { team1_id: null, team2_id: null };
    
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
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
        
        const matchesTab = document.getElementById('matchesTab');
        const playerHistoryTab = document.getElementById('playerHistoryTab');
        
        matchesTab.disabled = false;
        matchesTab.classList.remove('tab-disabled');
        matchesTab.onclick = function() { switchTab('matches-selection'); };
        
        playerHistoryTab.disabled = false;
        playerHistoryTab.classList.remove('tab-disabled');
        playerHistoryTab.onclick = function() { switchTab('player-history'); };
        
        loadLeagueData(leagueId);
        
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        matchesTab.classList.add('active');
        document.getElementById('matches-selection').classList.add('active');
        currentTab = 'matches-selection';
        
        loadLeagueMatches(leagueId);
    }
    
    function loadLeagueData(leagueId) {
        fetch('?ajax=get_league_players&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (!data.error) {
                    leaguePlayers = data;
                }
            });
        
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
        
        const pointsTab = document.getElementById('pointsTab');
        pointsTab.disabled = false;
        pointsTab.classList.remove('tab-disabled');
        pointsTab.onclick = function() { switchTab('match-points'); };
        
        fetch('?ajax=get_match_info&match_id=' + matchId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Store match score and team IDs
                currentMatchScore = {
                    team1: parseInt(data.team1_score) || 0,
                    team2: parseInt(data.team2_score) || 0
                };
                
                currentMatchTeams = {
                    team1_id: data.team1_id,
                    team2_id: data.team2_id
                };
                
                updateMatchHeader(data);
                
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
                    
                    let bonus = '-';
                    if (point.bonus_name) {
                        if (point.yellow_card > 0 && point.bonus_points == 0) {
                            // Handled in yellow card column
                        } else {
                            bonus = `${point.bonus_name} <span class="badge badge-${getRoleBadgeClass(point.bonus_role)}">${point.bonus_role}</span>`;
                            if (point.bonus_points != 0) {
                                const pointColor = point.bonus_points > 0 ? '#28a745' : '#dc3545';
                                const pointSign = point.bonus_points > 0 ? '+' : '';
                                bonus += ` <span style="color: ${pointColor}; font-weight: 600;">(${pointSign}${point.bonus_points})</span>`;
                            }
                        }
                    }
                    
                    let minus = '-';
                    if (point.minus_name) {
                        if (point.red_card > 0 && point.minus_points == 0) {
                            // Handled in red card column
                        } else {
                            minus = `${point.minus_name} <span class="badge badge-${getRoleBadgeClass(point.minus_role)}">${point.minus_role}</span>`;
                            if (point.minus_points != 0) {
                                minus += ` <span style="color: #dc3545; font-weight: 600;">(-${point.minus_points})</span>`;
                            }
                        }
                    }
                    
                    let yellowCard = '-';
                    if (point.yellow_card > 0 && point.yellow_card_player_name) {
                        yellowCard = `🟨 ${point.yellow_card_player_name}`;
                    }
                    
                    let redCard = '-';
                    if (point.red_card > 0 && point.red_card_player_name) {
                        redCard = `🟥 ${point.red_card_player_name}`;
                    }
                    
                    html += `
                        <tr>
                            <td><strong>#${point.id}</strong></td>
                            <td>${scorer}</td>
                            <td>${assister}</td>
                            <td>${bonus}</td>
                            <td>${minus}</td>
                            <td>${yellowCard}</td>
                            <td>${redCard}</td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="deletePoint(${point.id})">
                                    🗑️ Delete
                                </button>
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
    
    function openAddPointModal() {
    document.getElementById('addPointMatchId').value = currentMatchId;
    resetModalCounters();
    
    const totalGoals = currentMatchScore.team1 + currentMatchScore.team2;
    
    // Fetch existing match points to count already recorded goals
    fetch('?ajax=get_match_points&match_id=' + currentMatchId)
        .then(response => response.json())
        .then(existingPoints => {
            if (existingPoints.error) {
                alert('Error checking existing points: ' + existingPoints.error);
                return;
            }
            
            // Count how many goals have already been recorded
            let recordedGoals = 0;
            existingPoints.forEach(point => {
                if (point.scorer) {
                    recordedGoals++;
                }
            });
            
            // Calculate remaining goals that can be recorded
            const remainingGoals = totalGoals - recordedGoals;
            
            // Generate scorer fields based on remaining goals
            let scorersHtml = '';
            
            if (totalGoals === 0) {
                scorersHtml = '<p class="empty-state">No goals scored in this match (0-0). You can still add bonus/minus players and cards below.</p>';
            } else if (remainingGoals === 0) {
                scorersHtml = '<p class="empty-state" style="background: #fff3cd; color: #856404; border-left-color: #ffc107;">All goals for this match have already been recorded! ✅<br><br>Total goals in match: ' + totalGoals + '<br>Already recorded: ' + recordedGoals + '<br><br>You can still add bonus/minus players, penalties, and cards below.</p>';
                
                // Also disable the submit button for goals
                setTimeout(() => {
                    const submitBtn = document.querySelector('#addPointForm button[type="submit"]');
                    if (submitBtn && remainingGoals === 0) {
                        // Check if there are any other fields filled
                        submitBtn.textContent = '💾 Submit Other Actions (No Goals Available)';
                    }
                }, 100);
            } else {
                for (let i = 0; i < remainingGoals; i++) {
                    scorersHtml += `
                        <div class="scorer-pair locked" data-index="${i}">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Scorer #${i + 1}</label>
                                    <select name="scorers[]" class="form-control scorer-select" data-pair="${i}" onchange="handleScorerChange(this)">
                                        <option value="">-- Select Scorer --</option>
                                        ${getMatchPlayerOptions()}
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Assister (Optional)</label>
                                    <select name="assisters[]" class="form-control assister-select" data-pair="${i}">
                                        <option value="">-- No Assist --</option>
                                        ${getMatchPlayerOptions()}
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }
            
            document.getElementById('scorersContainer').innerHTML = scorersHtml;
            
            // Update section header
            const scorerSection = document.querySelector('.form-section');
            const scorerHeader = scorerSection.querySelector('.section-header');
            scorerHeader.classList.add('locked');
            
            if (totalGoals === 0) {
                scorerHeader.querySelector('.section-title').innerHTML = `⚽ Scorers (0 goals in match)`;
            } else if (remainingGoals === 0) {
                scorerHeader.querySelector('.section-title').innerHTML = `⚽ Scorers (All ${totalGoals} goals already recorded ✅)`;
            } else {
                scorerHeader.querySelector('.section-title').innerHTML = `⚽ Scorers (${remainingGoals} of ${totalGoals} goals remaining - ${recordedGoals} already recorded)`;
            }
            
            document.getElementById('bonusContainer').innerHTML = '<p class="empty-state">No bonus players added yet. Click "Add Bonus Player" to add one.</p>';
            document.getElementById('minusContainer').innerHTML = '<p class="empty-state">No minus players added yet. Click "Add Minus Player" to add one.</p>';
            document.getElementById('penaltyContainer').innerHTML = '<p class="empty-state">No penalty events added yet. Click "Add Penalty Event" to add one.</p>';
            document.getElementById('yellowCardContainer').innerHTML = '<p class="empty-state">No yellow cards added yet. Click "Add Yellow Card" to add one.</p>';
            document.getElementById('redCardContainer').innerHTML = '<p class="empty-state">No red cards added yet. Click "Add Red Card" to add one.</p>';
            
            document.getElementById('addPointModal').classList.add('active');
        })
        .catch(error => {
            console.error(error);
            alert('Error loading match data. Please try again.');
        });
}
    function getMatchPlayerOptions() {
    let options = '';
    leaguePlayers.forEach(player => {
        // Only include players from the two teams in this match
        if (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id) {
            options += `<option value="${player.player_id}">${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}</option>`;
        }
    });
    return options;
}
function getMatchGKOptions() {
    let options = '';
    leaguePlayers.filter(player => player.player_role === 'GK').forEach(player => {
        // Only include GKs from the two teams in this match
        if (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id) {
            options += `<option value="${player.player_id}">${player.player_name} - ${player.team_name || 'No Team'}</option>`;
        }
    });
    return options;
}
    function handleScorerChange(scorerSelect) {
        const pairIndex = scorerSelect.getAttribute('data-pair');
        const scorerId = scorerSelect.value;
        const assisterSelect = document.querySelector(`.assister-select[data-pair="${pairIndex}"]`);
        
        if (!assisterSelect) return;
        
        // Get current assister value
        const currentAssister = assisterSelect.value;
        
        // Find the scorer's team
        let scorerTeamId = null;
        if (scorerId) {
            const scorerPlayer = leaguePlayers.find(p => p.player_id == scorerId);
            if (scorerPlayer) {
                scorerTeamId = scorerPlayer.team_id;
            }
        }
        
        // Rebuild assister options - only show players from same team (excluding the scorer)
        assisterSelect.innerHTML = '<option value="">-- No Assist --</option>';
        
        leaguePlayers.forEach(player => {
            // Skip if this player is the scorer (can't assist own goal)
            if (scorerId && player.player_id == scorerId) {
                return;
            }
            
            // Skip if this player is from a different team
            if (scorerId && scorerTeamId && player.team_id != scorerTeamId) {
                return;
            }
            
            // Only add valid players (same team, not the scorer)
            const option = document.createElement('option');
            option.value = player.player_id;
            option.textContent = `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
            assisterSelect.appendChild(option);
        });
        
        // Restore previous selection if it's still valid (same team, not the scorer)
        if (currentAssister && currentAssister != scorerId) {
            const currentAssisterPlayer = leaguePlayers.find(p => p.player_id == currentAssister);
            if (currentAssisterPlayer && currentAssisterPlayer.team_id == scorerTeamId) {
                assisterSelect.value = currentAssister;
            }
        }
    }
    
    function handlePenaltyGKChange(selectElement, pairIndex) {
    const savedGkId = selectElement.value;
    const missedPlayerSelect = document.querySelector(`select[name="missed_penalty_players[]"][data-pair="${pairIndex}"]`);
    
    if (!missedPlayerSelect) return;
    
    const currentMissedPlayer = missedPlayerSelect.value;
    
    // Find the GK's team
    let gkTeamId = null;
    if (savedGkId) {
        const gkPlayer = leaguePlayers.find(p => p.player_id == savedGkId);
        if (gkPlayer) {
            gkTeamId = gkPlayer.team_id;
        }
    }
    
    // Rebuild missed player options - ONLY show players from the opposing team in this match
    missedPlayerSelect.innerHTML = '<option value="">-- Select Player --</option>';
    
    leaguePlayers.forEach(player => {
        // CRITICAL FIX: Only include players from the two match teams
        const isInMatch = (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id);
        if (!isInMatch) return;
        
        // Skip if this player is the GK (can't miss own save)
        if (savedGkId && player.player_id == savedGkId) {
            return;
        }
        
        // Skip if this player is from the same team as GK (can't miss penalty from own team)
        if (savedGkId && gkTeamId && player.team_id == gkTeamId) {
            return;
        }
        
        // Only add valid players (opposing team in the match, not the GK)
        const option = document.createElement('option');
        option.value = player.player_id;
        option.textContent = `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
        missedPlayerSelect.appendChild(option);
    });
    
    // Restore previous selection if it's still valid
    if (currentMissedPlayer && currentMissedPlayer != savedGkId) {
        const currentMissedPlayerObj = leaguePlayers.find(p => p.player_id == currentMissedPlayer);
        if (currentMissedPlayerObj && currentMissedPlayerObj.team_id != gkTeamId) {
            missedPlayerSelect.value = currentMissedPlayer;
        }
    }
}
    
    function handlePenaltyMissedPlayerChange(selectElement, pairIndex) {
    const missedPlayerId = selectElement.value;
    const savedGkSelect = document.querySelector(`select[name="saved_penalty_gks[]"][data-pair="${pairIndex}"]`);
    
    if (!savedGkSelect) return;
    
    const currentGk = savedGkSelect.value;
    
    // Find the missed player's team
    let missedPlayerTeamId = null;
    if (missedPlayerId) {
        const missedPlayerObj = leaguePlayers.find(p => p.player_id == missedPlayerId);
        if (missedPlayerObj) {
            missedPlayerTeamId = missedPlayerObj.team_id;
        }
    }
    
    // Rebuild GK options - ONLY show GKs from the opposing team in this match
    savedGkSelect.innerHTML = '<option value="">-- Select Goalkeeper --</option>';
    
    leaguePlayers.filter(player => player.player_role === 'GK').forEach(player => {
        // CRITICAL FIX: Only include GKs from the two match teams
        const isInMatch = (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id);
        if (!isInMatch) return;
        
        // Skip if this GK is the missed player (can't save own penalty)
        if (missedPlayerId && player.player_id == missedPlayerId) {
            return;
        }
        
        // Skip if this GK is from the same team as missed player (can't save penalty from own team)
        if (missedPlayerId && missedPlayerTeamId && player.team_id == missedPlayerTeamId) {
            return;
        }
        
        // Only add valid GKs (opposing team in the match, not the missed player)
        const option = document.createElement('option');
        option.value = player.player_id;
        option.textContent = `${player.player_name} - ${player.team_name || 'No Team'}`;
        savedGkSelect.appendChild(option);
    });
    
    // Restore previous selection if it's still valid
    if (currentGk && currentGk != missedPlayerId) {
        const currentGkObj = leaguePlayers.find(p => p.player_id == currentGk);
        if (currentGkObj && currentGkObj.team_id != missedPlayerTeamId) {
            savedGkSelect.value = currentGk;
        }
    }
}
    
    function closeAddPointModal() {
        document.getElementById('addPointModal').classList.remove('active');
        resetModalCounters();
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
            
            const playerInfo = leaguePlayers.find(p => p.player_id == playerId);
            
            let html = '';
            
            const totalGoals = data.filter(d => d.action_type === 'scorer').length;
            const totalAssists = data.filter(d => d.action_type === 'assister').length;
            const totalBonus = data.filter(d => d.action_type === 'bonus').length;
            const totalMinus = data.filter(d => d.action_type === 'minus').length;
            const totalPenaltySaved = data.filter(d => d.action_type === 'penalty_saved').length;
            const totalCleanSheets = data.filter(d => d.action_type === 'clean_sheet').length;
            const totalYellow = data.reduce((sum, d) => sum + (d.yellow_card || 0), 0);
            const totalRed = data.reduce((sum, d) => sum + (d.red_card || 0), 0);
            
            html += `
                <div class="info-card" style="margin-bottom: 20px;">
                    <div class="info-card-title">📊 ${playerInfo ? playerInfo.player_name : 'Player'} Statistics</div>
                    <div class="points-grid" style="margin-top: 15px;">
            `;
            
            // Always show goals and assists
            html += `
                        <div class="point-item">
                            <div class="point-label">⚽ Goals</div>
                            <div class="point-value">${totalGoals}</div>
                        </div>
                        <div class="point-item">
                            <div class="point-label">🎯 Assists</div>
                            <div class="point-value">${totalAssists}</div>
                        </div>
            `;
            
            // Show clean sheets for GK and DEF
            if (playerInfo && (playerInfo.player_role === 'GK' || playerInfo.player_role === 'DEF')) {
                html += `
                        <div class="point-item">
                            <div class="point-label">🛡️ Clean Sheets</div>
                            <div class="point-value">${totalCleanSheets}</div>
                        </div>
                `;
            }
            
            // Show penalty saves for GK only
            if (playerInfo && playerInfo.player_role === 'GK') {
                html += `
                        <div class="point-item">
                            <div class="point-label">🧤 Penalties Saved</div>
                            <div class="point-value">${totalPenaltySaved}</div>
                        </div>
                `;
            }
            
            html += `
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
                    'minus': '❌',
                    'penalty_saved': '🧤',
                    'clean_sheet': '🛡️'
                };
                
                const actionLabel = {
                    'scorer': 'Scored a goal',
                    'assister': 'Provided an assist',
                    'bonus': 'Earned bonus points',
                    'minus': 'Missed a penalty',
                    'penalty_saved': 'Saved a penalty',
                    'clean_sheet': 'Kept a clean sheet'
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
        
        const pointsTab = document.getElementById('pointsTab');
        pointsTab.disabled = true;
        pointsTab.classList.add('tab-disabled');
        pointsTab.onclick = null;
        
        currentTab = 'matches-selection';
        currentMatchId = null;
        
        loadLeagueMatches(currentLeagueId);
    }

    function addBonusField() {
    bonusCount++;
    const container = document.getElementById('bonusContainer');
    
    if (bonusCount === 1 && container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    
    const div = document.createElement('div');
    div.className = 'bonus-item';
    div.setAttribute('data-index', bonusCount);
    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Player</label>
                <select name="bonus_players[]" class="form-control">
                    <option value="">-- Select Player --</option>
                    ${getMatchPlayerOptions()}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">➕ Bonus Points</label>
                <input type="number" name="bonus_points[]" class="form-control" value="0" step="1">
                <small style="color: #666; font-size: 12px;">Enter positive or negative points</small>
            </div>
        </div>
    `;
    container.appendChild(div);
}

    function addMinusField() {
    minusCount++;
    const container = document.getElementById('minusContainer');
    
    if (minusCount === 1 && container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    
    const div = document.createElement('div');
    div.className = 'minus-item';
    div.setAttribute('data-index', minusCount);
    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Player</label>
                <select name="minus_players[]" class="form-control">
                    <option value="">-- Select Player --</option>
                    ${getMatchPlayerOptions()}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">➖ Minus Points</label>
                <input type="number" name="minus_points[]" class="form-control" value="0" step="1">
                <small style="color: #666; font-size: 12px;">Enter positive number (will be deducted)</small>
            </div>
        </div>
    `;
    container.appendChild(div);
}

    function addPenaltyField() {
    penaltyCount++;
    const container = document.getElementById('penaltyContainer');
    
    if (penaltyCount === 1 && container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    
    const div = document.createElement('div');
    div.className = 'penalty-item';
    div.setAttribute('data-index', penaltyCount);
    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">🧤 Goalkeeper Who Saved</label>
                <select name="saved_penalty_gks[]" class="form-control penalty-gk-select" data-pair="${penaltyCount}" onchange="handlePenaltyGKChange(this, ${penaltyCount})">
                    <option value="">-- Select Goalkeeper --</option>
                    ${getMatchGKOptions()}
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">⚠️ Player Who Missed <span style="color: red;">*</span></label>
                <select name="missed_penalty_players[]" class="form-control penalty-missed-select" data-pair="${penaltyCount}" onchange="handlePenaltyMissedPlayerChange(this, ${penaltyCount})">
                    <option value="">-- Select Player --</option>
                    ${getMatchPlayerOptions()}
                </select>
            </div>
        </div>
    `;
    container.appendChild(div);
}

    function addYellowCardField() {
    yellowCardCount++;
    const container = document.getElementById('yellowCardContainer');
    
    if (yellowCardCount === 1 && container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    
    const div = document.createElement('div');
    div.className = 'card-item';
    div.setAttribute('data-index', yellowCardCount);
    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
        <div class="form-group">
            <label class="form-label">Player Who Received Yellow Card</label>
            <select name="yellow_card_players[]" class="form-control">
                <option value="">-- Select Player --</option>
                ${getMatchPlayerOptions()}
            </select>
        </div>
    `;
    container.appendChild(div);
}

    function addRedCardField() {
    redCardCount++;
    const container = document.getElementById('redCardContainer');
    
    if (redCardCount === 1 && container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    
    const div = document.createElement('div');
    div.className = 'card-item';
    div.setAttribute('data-index', redCardCount);
    div.innerHTML = `
        <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
        <div class="form-group">
            <label class="form-label">Player Who Received Red Card</label>
            <select name="red_card_players[]" class="form-control">
                <option value="">-- Select Player --</option>
                ${getMatchPlayerOptions()}
            </select>
        </div>
    `;
    container.appendChild(div);
}

    function removeField(button) {
        button.parentElement.remove();
    }

    function getPlayerOptions() {
        let options = '';
        leaguePlayers.forEach(player => {
            options += `<option value="${player.player_id}">${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}</option>`;
        });
        return options;
    }

    function getGKOptions() {
        let options = '';
        leaguePlayers.filter(player => player.player_role === 'GK').forEach(player => {
            options += `<option value="${player.player_id}">${player.player_name} - ${player.team_name || 'No Team'}</option>`;
        });
        return options;
    }

    function resetModalCounters() {
        scorerCount = 0;
        bonusCount = 0;
        minusCount = 0;
        penaltyCount = 0;
        yellowCardCount = 0;
        redCardCount = 0;
    }

    window.onclick = function(event) {
        const addModal = document.getElementById('addPointModal');
        const deleteModal = document.getElementById('deletePointModal');
        const rolesModal = document.getElementById('leagueRolesModal');
        
        if (event.target === addModal) {
            closeAddPointModal();
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