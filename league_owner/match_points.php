<?php
session_start();
require_once '../config/db.php';
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
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

if (isset($_GET['ajax']) && !$league_not_found && !$not_owner && !$not_activated) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_rounds') {
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT round 
                FROM matches 
                WHERE league_id = ? 
                ORDER BY round DESC
            ");
            $stmt->execute([$league_id]);
            $rounds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'rounds' => $rounds]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_matches_by_round' && isset($_GET['round'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name
                FROM matches m
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE m.league_id = ? AND m.round = ?
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$league_id, $_GET['round']]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'matches' => $matches]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
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
            echo json_encode(['success' => true, 'points' => $points]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_match_info' && isset($_GET['match_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    lt1.team_name as team1_name,
                    lt2.team_name as team2_name
                FROM matches m
                LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
                LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
                WHERE m.match_id = ?
            ");
            $stmt->execute([$_GET['match_id']]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'match' => $match]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_players') {
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
            $stmt->execute([$league_id]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'players' => $players]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_roles') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
            $stmt->execute([$league_id]);
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
            
            echo json_encode(['success' => true, 'roles' => $roles]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_point_details' && isset($_GET['point_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    mp.*,
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
                LEFT JOIN league_players scorer ON mp.scorer = scorer.player_id
                LEFT JOIN league_players assister ON mp.assister = assister.player_id
                LEFT JOIN league_players bonus_player ON mp.bonus = bonus_player.player_id
                LEFT JOIN league_players minus_player ON mp.minus = minus_player.player_id
                LEFT JOIN league_players saved_gk ON mp.saved_penalty_gk = saved_gk.player_id
                LEFT JOIN league_players missed_p ON mp.missed_penalty_player = missed_p.player_id
                LEFT JOIN league_players yellow_p ON mp.yellow_card_player = yellow_p.player_id
                LEFT JOIN league_players red_p ON mp.red_card_player = red_p.player_id
                WHERE mp.id = ?
            ");
            $stmt->execute([$_GET['point_id']]);
            $point = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'point' => $point]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$league_not_found && !$not_owner && !$not_activated) {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_point') {
                $pdo->beginTransaction();
                
                try {
                    $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
                    $stmt->execute([$league_id]);
                    $roles = $stmt->fetch(PDO::FETCH_ASSOC);
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
                    $max_rows = max(
                        count($scorers),
                        count($bonus_data),
                        count($minus_data),
                        count($penalty_data),
                        count($yellow_players),
                        count($red_players)
                    );
                    
                    if ($max_rows == 0) {
                        throw new Exception("No data provided to add.");
                    }
                    
                    $entries_added = 0;
                    for ($row = 0; $row < $max_rows; $row++) {
                        $scorer = isset($scorers[$row]) ? $scorers[$row] : null;
                        $assister = isset($assisters[$row]) && !empty($assisters[$row]) ? $assisters[$row] : null;
                        $bonus = isset($bonus_data[$row]) ? $bonus_data[$row]['player_id'] : null;
                        $bonus_pts = isset($bonus_data[$row]) ? $bonus_data[$row]['points'] : 0;
                        $minus = isset($minus_data[$row]) ? $minus_data[$row]['player_id'] : null;
                        $minus_pts = isset($minus_data[$row]) ? $minus_data[$row]['points'] : 0;
                        $saved_gk = isset($penalty_data[$row]) ? $penalty_data[$row]['gk_id'] : null;
                        $missed_player = isset($penalty_data[$row]) ? $penalty_data[$row]['missed_id'] : null;
                        $yellow_player_id = isset($yellow_players[$row]) ? $yellow_players[$row] : null;
                        $red_player_id = isset($red_players[$row]) ? $red_players[$row] : null;
                        if (!$scorer && !$assister && !$bonus && !$minus && !$saved_gk && !$missed_player && !$yellow_player_id && !$red_player_id) {
                            continue;
                        }
                        $stmt = $pdo->prepare("
                            INSERT INTO matches_points (
                                match_id, scorer, assister, bonus, bonus_points, minus, minus_points,
                                saved_penalty_gk, missed_penalty_player, yellow_card, yellow_card_player, 
                                red_card, red_card_player
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
                    $stmt = $pdo->prepare("SELECT round FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    $match_round = $stmt->fetchColumn();
                    $_SESSION['success_message'] = "Successfully added {$entries_added} match point entries!";
                    header("Location: ?id={$league_id}&round={$match_round}&match={$_POST['match_id']}");
                    exit();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Error: " . $e->getMessage();
                }
            }
            
            if ($_POST['action'] === 'delete_point') {
                $pdo->beginTransaction();
                
                try {
                    $stmt = $pdo->prepare("SELECT * FROM matches_points WHERE id = ?");
                    $stmt->execute([$_POST['point_id']]);
                    $point_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$point_data) {
                        throw new Exception("Point entry not found.");
                    }
                    $redirect_match_id = $point_data['match_id'];
                    $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
                    $stmt->execute([$league_id]);
                    $roles = $stmt->fetch(PDO::FETCH_ASSOC);
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
                    
                    if ($point_data['bonus']) {
                        $reversePlayerPoints($point_data['bonus'], $point_data['bonus_points']);
                    }
                    
                    if ($point_data['minus']) {
                        $reversePlayerPoints($point_data['minus'], -abs($point_data['minus_points']));
                    }
                    
                    if ($point_data['saved_penalty_gk']) {
                        $reversePlayerPoints($point_data['saved_penalty_gk'], $roles['gk_save_penalty']);
                    }
                    
                    if ($point_data['missed_penalty_player']) {
                        $reversePlayerPoints($point_data['missed_penalty_player'], $roles['miss_penalty']);
                    }
                    
                    if ($point_data['yellow_card_player']) {
                        $reversePlayerPoints($point_data['yellow_card_player'], $roles['yellow_card']);
                    }
                    
                    if ($point_data['red_card_player']) {
                        $reversePlayerPoints($point_data['red_card_player'], $roles['red_card']);
                    }
                    $stmt = $pdo->prepare("DELETE FROM matches_points WHERE id = ?");
                    $stmt->execute([$_POST['point_id']]);
                    
                    $pdo->commit();
                    $stmt = $pdo->prepare("SELECT round FROM matches WHERE match_id = ?");
                    $stmt->execute([$redirect_match_id]);
                    $match_round = $stmt->fetchColumn();
                    $_SESSION['success_message'] = "Match point deleted successfully!";
                    header("Location: ?id={$league_id}&round={$match_round}&match={$redirect_match_id}");
                    exit();
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Error: " . $e->getMessage();
                }
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
    <title>Match Points - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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

        .not-owner-card, .not-activated-card, .not-found-card {
            background: var(--card-bg);
            border: 2px solid var(--error);
            border-radius: 25px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(239, 68, 68, 0.2);
        }

        .not-activated-card {
            border-color: var(--warning);
            box-shadow: 0 20px 60px rgba(245, 158, 11, 0.2);
        }

        .not-found-card {
            border-color: var(--text-secondary);
            box-shadow: 0 20px 60px rgba(102, 102, 102, 0.2);
        }

        body.dark-mode .not-owner-card,
        body.dark-mode .not-activated-card,
        body.dark-mode .not-found-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
        }

        .not-owner-icon, .not-activated-icon, .not-found-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }

        .not-owner-icon {
            color: var(--error);
        }

        .not-activated-icon {
            color: var(--warning);
        }

        .not-found-icon {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .not-owner-title, .not-activated-title, .not-found-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 1rem;
        }

        .not-owner-title {
            color: var(--error);
        }

        .not-activated-title {
            color: var(--warning);
        }

        .not-found-title {
            color: var(--text-primary);
        }

        .not-owner-text {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
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
            border-color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-color: var(--error);
        }
        .selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .selection-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        body.dark-mode .selection-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .selection-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-5px);
        }

        .selection-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .selection-label i {
            color: var(--gradient-end);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-family: 'Roboto', sans-serif;
            font-size: 1rem;
            color: var(--text-primary);
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }

        body.dark-mode .form-control {
            background: rgba(10, 20, 35, 0.5);
        }
        .data-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        body.dark-mode .data-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .data-card-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-info {
            flex: 1;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .header-meta {
            font-size: 0.9rem;
            opacity: 0.9;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
        }

        body.dark-mode .data-table thead {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.2), rgba(10, 146, 215, 0.2));
        }

        .data-table th {
            padding: 1.2rem 1rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-primary);
        }

        .data-table td {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .data-table tbody tr {
            transition: all 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.05);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-gk {
            background: rgba(29, 96, 172, 0.1);
            color: var(--gradient-start);
        }

        .badge-def {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-mid {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-att {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
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
            color: white;
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
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
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
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 25px 25px 0 0;
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
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: 15px;
            border: 1px solid var(--border-color);
        }

        body.dark-mode .form-section {
            background: rgba(10, 20, 35, 0.5);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gradient-end);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--gradient-end);
        }

        .scorer-pair, .bonus-item, .minus-item, .penalty-item, .card-item {
            background: var(--card-bg);
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            position: relative;
            border: 1px solid var(--border-color);
        }

        body.dark-mode .scorer-pair,
        body.dark-mode .bonus-item,
        body.dark-mode .minus-item,
        body.dark-mode .penalty-item,
        body.dark-mode .card-item {
            background: rgba(20, 30, 48, 0.5);
        }

        .scorer-pair.locked {
            border: 2px solid var(--gradient-end);
            background: rgba(10, 146, 215, 0.05);
        }

        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--error);
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-control:disabled {
            background: var(--bg-secondary);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 0.9rem;
        }

        .info-card {
            background: rgba(10, 146, 215, 0.1);
            border-left: 4px solid var(--gradient-end);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .info-card-text {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
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

            .selection-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
            }

            .header-meta {
                flex-direction: column;
                gap: 0.5rem;
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
        <!-- Loading Spinner -->
    <div class="loading-spinner-overlay" id="loadingSpinner">
        <div class="loading-logo">
            <img src="../assets/images/logo white outline.png" alt="Fantazina Logo">
        </div>
        <div class="spinner-large"></div>
        <div class="loading-text">Loading Match Points Management...</div>
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
                    <h1 class="not-found-title">League Not Found</h1>
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
                    <p class="not-owner-text">You don't have permission to access this page. Only the league owner can manage match points.</p>
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
                    <h1 class="not-activated-title">League Not Activated</h1>
                    <p class="not-owner-text">This league is currently under review. Please wait until we review your application.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Match Points Management -->
            <div class="page-header">
                <h2 class="page-title">Match Points Management</h2>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Selection Cards -->
            <div class="selection-grid">
                <div class="selection-card">
                    <label class="selection-label">
                        <i class="fas fa-calendar-alt"></i>
                        Select Round
                    </label>
                    <select id="roundSelect" class="form-control" onchange="loadMatches()">
                        <option value="">-- Select a round --</option>
                    </select>
                </div>

                <div class="selection-card">
                    <label class="selection-label">
                        <i class="fas fa-futbol"></i>
                        Select Match
                    </label>
                    <select id="matchSelect" class="form-control" onchange="loadMatchPoints()" disabled>
                        <option value="">-- Select a round first --</option>
                    </select>
                </div>
            </div>

            <!-- Match Points Table -->
            <div id="matchPointsSection" style="display: none;">
                <div class="data-card">
                    <div class="data-card-header">
                        <div class="header-info">
                            <div class="header-title" id="matchTitle">Match Points</div>
                            <div class="header-meta" id="matchMeta"></div>
                        </div>
                        <button class="btn btn-gradient" onclick="openAddPointModal()">
                            <i class="fas fa-plus"></i>
                            Add Point Entry
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Scorer</th>
                                    <th>Assister</th>
                                    <th>Bonus</th>
                                    <th>Minus</th>
                                    <th>Penalties</th>
                                    <th>Cards</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pointsTableBody">
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                        Select a match to view points
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Point Modal -->
    <div id="addPointModal" class="modal-overlay">
        <div class="modal-content">
            <form id="addPointForm" method="POST">
                <div class="modal-header">
                    <span class="modal-title">Add Match Point Entry</span>
                    <button type="button" class="modal-close" onclick="closeAddPointModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_point">
                    <input type="hidden" name="match_id" id="addPointMatchId">
                    
                    <div class="info-card">
                        <div class="info-card-text">
                            Add goals, assists, cards, and other actions for this match. The system will automatically calculate points based on your league's rules.
                        </div>
                    </div>
                    
                    <!-- Scorers Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-futbol"></i>
                                Goals
                            </h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addScorerField()" style="display: none;" id="addScorerBtn">
                                <i class="fas fa-plus"></i> Add Goal
                            </button>
                        </div>
                        <div id="scorersContainer"></div>
                    </div>
                    
                    <!-- Bonus Players Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-star"></i>
                                Bonus Points
                            </h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addBonusField()">
                                <i class="fas fa-plus"></i> Add Bonus
                            </button>
                        </div>
                        <div id="bonusContainer">
                            <p class="empty-state">No bonus players added</p>
                        </div>
                    </div>
                    
                    <!-- Minus Players Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-minus-circle"></i>
                                Minus Points
                            </h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addMinusField()">
                                <i class="fas fa-plus"></i> Add Minus
                            </button>
                        </div>
                        <div id="minusContainer">
                            <p class="empty-state">No minus players added</p>
                        </div>
                    </div>
                    
                    <!-- Penalties Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-hand-paper"></i>
                                Penalties
                            </h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addPenaltyField()">
                                <i class="fas fa-plus"></i> Add Penalty
                            </button>
                        </div>
                        <div id="penaltyContainer">
                            <p class="empty-state">No penalties added</p>
                        </div>
                    </div>
                    
                    <!-- Yellow Cards Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-square" style="color: #f59e0b;"></i>
                                Yellow Cards
                            </h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addYellowCardField()">
                                <i class="fas fa-plus"></i> Add Card
                            </button>
                        </div>
                        <div id="yellowCardContainer">
                            <p class="empty-state">No yellow cards added</p>
                        </div>
                    </div>
                    
                    <!-- Red Cards Section -->
                    <div class="form-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-square" style="color: #ef4444;"></i>
                                Red Cards
                            </h3>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addRedCardField()">
                                <i class="fas fa-plus"></i> Add Card
                            </button>
                        </div>
                        <div id="redCardContainer">
                            <p class="empty-state">No red cards added</p>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddPointModal()">Cancel</button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i>
                        Save All Entries
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Point Modal -->
    <div id="deletePointModal" class="modal-overlay">
        <div class="modal-content">
            <form id="deletePointForm" method="POST">
                <div class="modal-header">
                    <span class="modal-title">Delete Point Entry</span>
                    <button type="button" class="modal-close" onclick="closeDeletePointModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_point">
                    <input type="hidden" name="point_id" id="deletePointId">
                    
                    <div class="info-card" style="border-left-color: var(--error); background: rgba(239, 68, 68, 0.1);">
                        <div class="info-card-text">
                            <strong style="color: var(--error);">⚠️ Warning:</strong> Are you sure you want to delete this point entry?
                            <br><br>
                            <strong>Entry Details:</strong>
                            <div id="deletePointDetails" style="margin-top: 1rem; line-height: 1.8;"></div>
                            <br>
                            <strong style="color: var(--error);">This action cannot be undone and will reverse all points awarded in this entry.</strong>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeletePointModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Delete Entry
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
        let currentMatchId = null;
        let currentMatchScore = { team1: 0, team2: 0 };
        let currentMatchTeams = { team1_id: null, team2_id: null };
        let leaguePlayers = [];
        let leagueRoles = null;
        let leaguePositions = '<?php echo $league['positions'] ?? 'positions'; ?>';
        let bonusCount = 0;
        let minusCount = 0;
        let penaltyCount = 0;
        let yellowCardCount = 0;
        let redCardCount = 0;
document.addEventListener('DOMContentLoaded', function() {
    loadRounds();
    loadLeagueData();
    
    const urlParams = new URLSearchParams(window.location.search);
    const preselectedRound = urlParams.get('round');
    const preselectedMatch = urlParams.get('match');
    
    if (preselectedRound) {
        setTimeout(() => {
            document.getElementById('roundSelect').value = preselectedRound;
            loadMatches(() => {
                if (preselectedMatch) {
                    setTimeout(() => {
                        document.getElementById('matchSelect').value = preselectedMatch;
                        loadMatchPoints();
                    }, 300);
                }
            });
        }, 300);
    }
});

        function loadRounds() {
            fetch('?ajax=get_rounds&id=<?php echo $league_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.rounds.length > 0) {
                        const select = document.getElementById('roundSelect');
                        select.innerHTML = '<option value="">-- Select a round --</option>';
                        data.rounds.forEach(round => {
                            const option = document.createElement('option');
                            option.value = round;
                            option.textContent = `Round ${round}`;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading rounds:', error));
        }

        function loadMatches(callback) {
    const roundSelect = document.getElementById('roundSelect');
    const matchSelect = document.getElementById('matchSelect');
    const round = roundSelect.value;

    if (!round) {
        matchSelect.disabled = true;
        matchSelect.innerHTML = '<option value="">-- Select a round first --</option>';
        document.getElementById('matchPointsSection').style.display = 'none';
        return;
    }

    matchSelect.disabled = false;
    matchSelect.innerHTML = '<option value="">Loading...</option>';

    fetch(`?ajax=get_matches_by_round&id=<?php echo $league_id; ?>&round=${round}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                matchSelect.innerHTML = '<option value="">-- Select a match --</option>';
                data.matches.forEach(match => {
                    const option = document.createElement('option');
                    option.value = match.match_id;
                    option.textContent = `${match.team1_name || 'TBD'} ${match.team1_score}-${match.team2_score} ${match.team2_name || 'TBD'}`;
                    matchSelect.appendChild(option);
                });
                
                if (callback) callback();
            } else {
                matchSelect.innerHTML = '<option value="">No matches found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading matches:', error);
            matchSelect.innerHTML = '<option value="">Error loading matches</option>';
        });
}

        function loadMatchPoints() {
            const matchSelect = document.getElementById('matchSelect');
            const matchId = matchSelect.value;

            if (!matchId) {
                document.getElementById('matchPointsSection').style.display = 'none';
                return;
            }

            currentMatchId = matchId;
            document.getElementById('matchPointsSection').style.display = 'block';

            fetch(`?ajax=get_match_info&id=<?php echo $league_id; ?>&match_id=${matchId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.match) {
                        const match = data.match;
                        currentMatchScore = {
                            team1: parseInt(match.team1_score) || 0,
                            team2: parseInt(match.team2_score) || 0
                        };
                        currentMatchTeams = {
                            team1_id: match.team1_id,
                            team2_id: match.team2_id
                        };

                        document.getElementById('matchTitle').textContent = 
                            `${match.team1_name || 'TBD'} vs ${match.team2_name || 'TBD'}`;
                        document.getElementById('matchMeta').innerHTML = `
                            <span>Round ${match.round}</span>
                            <span>Score: ${match.team1_score}-${match.team2_score}</span>
                        `;
                    }
                });

            const tbody = document.getElementById('pointsTableBody');
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">Loading...</td></tr>';

            fetch(`?ajax=get_match_points&id=<?php echo $league_id; ?>&match_id=${matchId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.points.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No point entries yet. Click "Add Point Entry" to create one.</td></tr>';
                            return;
                        }

                        let html = '';
                        data.points.forEach(point => {
                            const scorer = point.scorer_name ? 
                                `${point.scorer_name} <span class="badge badge-${getRoleBadgeClass(point.scorer_role)}">${point.scorer_role}</span>` : 
                                '-';
                            const assister = point.assister_name ? 
                                `${point.assister_name} <span class="badge badge-${getRoleBadgeClass(point.assister_role)}">${point.assister_role}</span>` : 
                                '-';
                            
                            let bonus = '-';
                            if (point.bonus_name) {
                                bonus = `${point.bonus_name} <span class="badge badge-${getRoleBadgeClass(point.bonus_role)}">${point.bonus_role}</span>`;
                                if (point.bonus_points != 0) {
                                    const sign = point.bonus_points > 0 ? '+' : '';
                                    bonus += ` <strong style="color: ${point.bonus_points > 0 ? 'var(--success)' : 'var(--error)'};">(${sign}${point.bonus_points})</strong>`;
                                }
                            }
                            
                            let minus = '-';
                            if (point.minus_name) {
                                minus = `${point.minus_name} <span class="badge badge-${getRoleBadgeClass(point.minus_role)}">${point.minus_role}</span>`;
                                if (point.minus_points != 0) {
                                    minus += ` <strong style="color: var(--error);">(-${point.minus_points})</strong>`;
                                }
                            }
                            
                            let penalties = '-';
                            if (point.saved_gk_name || point.missed_player_name) {
                                penalties = '';
                                if (point.saved_gk_name) {
                                    penalties += `<div>✋ Saved: ${point.saved_gk_name}</div>`;
                                }
                                if (point.missed_player_name) {
                                    penalties += `<div>❌ Missed: ${point.missed_player_name}</div>`;
                                }
                            }
                            
                            let cards = '-';
                            if (point.yellow_card > 0 || point.red_card > 0) {
                                cards = '';
                                if (point.yellow_card > 0 && point.yellow_card_player_name) {
                                    cards += `<div>🟨 ${point.yellow_card_player_name}</div>`;
                                }
                                if (point.red_card > 0 && point.red_card_player_name) {
                                    cards += `<div>🟥 ${point.red_card_player_name}</div>`;
                                }
                            }

                            html += `
                                <tr>
                                    <td><strong>#${point.id}</strong></td>
                                    <td>${scorer}</td>
                                    <td>${assister}</td>
                                    <td>${bonus}</td>
                                    <td>${minus}</td>
                                    <td>${penalties}</td>
                                    <td>${cards}</td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deletePoint(${point.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });

                        tbody.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Error loading match points:', error);
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--error);">Error loading points</td></tr>';
                });
        }

        function loadLeagueData() {
            fetch('?ajax=get_league_players&id=<?php echo $league_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        leaguePlayers = data.players;
                    }
                });
            fetch('?ajax=get_league_roles&id=<?php echo $league_id; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        leagueRoles = data.roles;
                    }
                });
        }

        function getRoleBadgeClass(role) {
            const classes = {
                'GK': 'gk',
                'DEF': 'def',
                'MID': 'mid',
                'ATT': 'att'
            };
            return classes[role] || 'gk';
        }

        function openAddPointModal() {
            if (!currentMatchId) return;

            document.getElementById('addPointMatchId').value = currentMatchId;
            resetModalCounters();
            fetch(`?ajax=get_match_points&id=<?php echo $league_id; ?>&match_id=${currentMatchId}`)
                .then(response => response.json())
                .then(data => {
                    const totalGoals = currentMatchScore.team1 + currentMatchScore.team2;
                    let recordedGoals = 0;

                    if (data.success) {
                        data.points.forEach(point => {
                            if (point.scorer) recordedGoals++;
                        });
                    }

                    const remainingGoals = totalGoals - recordedGoals;
                    const scorersContainer = document.getElementById('scorersContainer');
                    const addScorerBtn = document.getElementById('addScorerBtn');

                    if (totalGoals === 0) {
                        scorersContainer.innerHTML = '<p class="empty-state">No goals scored in this match (0-0)</p>';
                        addScorerBtn.style.display = 'none';
                    } else if (remainingGoals === 0) {
                        scorersContainer.innerHTML = `<p class="empty-state" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">All ${totalGoals} goals have been recorded ✅</p>`;
                        addScorerBtn.style.display = 'none';
                    } else {
                        scorersContainer.innerHTML = '';
                        for (let i = 0; i < remainingGoals; i++) {
                            addScorerFieldDirect(i);
                        }
                        addScorerBtn.style.display = 'none';
                    }
                    document.getElementById('bonusContainer').innerHTML = '<p class="empty-state">No bonus players added</p>';
                    document.getElementById('minusContainer').innerHTML = '<p class="empty-state">No minus players added</p>';
                    document.getElementById('penaltyContainer').innerHTML = '<p class="empty-state">No penalties added</p>';
                    document.getElementById('yellowCardContainer').innerHTML = '<p class="empty-state">No yellow cards added</p>';
                    document.getElementById('redCardContainer').innerHTML = '<p class="empty-state">No red cards added</p>';

                    document.getElementById('addPointModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
        }

        function closeAddPointModal() {
            document.getElementById('addPointModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function addScorerFieldDirect(index) {
            const container = document.getElementById('scorersContainer');
            const div = document.createElement('div');
            div.className = 'scorer-pair locked';
            div.setAttribute('data-index', index);
            div.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Scorer #${index + 1}</label>
                        <select name="scorers[]" class="form-control scorer-select" data-pair="${index}" onchange="handleScorerChange(this)">
                            <option value="">-- Select Scorer --</option>
                            ${getSmartScorerOptions(index)}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assister (Optional)</label>
                        <select name="assisters[]" class="form-control assister-select" data-pair="${index}">
                            <option value="">-- No Assist --</option>
                        </select>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        function getSmartScorerOptions(goalIndex) {
            const team1Goals = currentMatchScore.team1;
            const team2Goals = currentMatchScore.team2;
            let team1Recorded = 0;
            let team2Recorded = 0;
            
            for (let i = 0; i < goalIndex; i++) {
                const scorerSelect = document.querySelector(`.scorer-select[data-pair="${i}"]`);
                if (scorerSelect && scorerSelect.value) {
                    const scorerPlayer = leaguePlayers.find(p => p.player_id == scorerSelect.value);
                    if (scorerPlayer) {
                        if (scorerPlayer.team_id == currentMatchTeams.team1_id) {
                            team1Recorded++;
                        } else if (scorerPlayer.team_id == currentMatchTeams.team2_id) {
                            team2Recorded++;
                        }
                    }
                }
            }
            let targetTeamId = null;
            if (team1Recorded < team1Goals) {
                targetTeamId = currentMatchTeams.team1_id;
            } 
            else if (team2Recorded < team2Goals) {
                targetTeamId = currentMatchTeams.team2_id;
            }
            let options = '';
            leaguePlayers.forEach(player => {
                if (targetTeamId && player.team_id == targetTeamId) {
                    const displayName = leaguePositions === 'positionless' 
                        ? `${player.player_name} - ${player.team_name || 'No Team'}`
                        : `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
                    options += `<option value="${player.player_id}">${displayName}</option>`;
                }
            });
            
            return options;
        }

        function handleScorerChange(scorerSelect) {
            const pairIndex = scorerSelect.getAttribute('data-pair');
            const scorerId = scorerSelect.value;
            const assisterSelect = document.querySelector(`.assister-select[data-pair="${pairIndex}"]`);
            
            if (!assisterSelect) return;
            
            const currentAssister = assisterSelect.value;
            let scorerTeamId = null;
            
            if (scorerId) {
                const scorerPlayer = leaguePlayers.find(p => p.player_id == scorerId);
                if (scorerPlayer) {
                    scorerTeamId = scorerPlayer.team_id;
                }
            }
            
            assisterSelect.innerHTML = '<option value="">-- No Assist --</option>';
            
            leaguePlayers.forEach(player => {
                if (scorerId && player.player_id == scorerId) return;
                if (scorerId && scorerTeamId && player.team_id != scorerTeamId) return;
                
                const displayName = leaguePositions === 'positionless' 
                    ? `${player.player_name} - ${player.team_name || 'No Team'}`
                    : `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
                
                const option = document.createElement('option');
                option.value = player.player_id;
                option.textContent = displayName;
                assisterSelect.appendChild(option);
            });
            
            if (currentAssister && currentAssister != scorerId) {
                const currentAssisterPlayer = leaguePlayers.find(p => p.player_id == currentAssister);
                if (currentAssisterPlayer && currentAssisterPlayer.team_id == scorerTeamId) {
                    assisterSelect.value = currentAssister;
                }
            }
            updateSubsequentScorerOptions();
        }

        function updateSubsequentScorerOptions() {
            const scorerSelects = document.querySelectorAll('.scorer-select');
            scorerSelects.forEach((select, index) => {
                if (!select.value) {
                    const currentValue = select.value;
                    const newOptions = getSmartScorerOptions(index);
                    select.innerHTML = '<option value="">-- Select Scorer --</option>' + newOptions;
                    if (currentValue) select.value = currentValue;
                }
            });
        }

        function addBonusField() {
            bonusCount++;
            const container = document.getElementById('bonusContainer');
            
            if (bonusCount === 1 && container.querySelector('.empty-state')) {
                container.innerHTML = '';
            }
            
            const div = document.createElement('div');
            div.className = 'bonus-item';
            div.innerHTML = `
                <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Player</label>
                        <select name="bonus_players[]" class="form-control">
                            <option value="">-- Select Player --</option>
                            ${getMatchPlayerOptionsFormatted()}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Points</label>
                        <input type="number" name="bonus_points[]" class="form-control" value="0" step="1">
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
            div.innerHTML = `
                <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Player</label>
                        <select name="minus_players[]" class="form-control">
                            <option value="">-- Select Player --</option>
                            ${getMatchPlayerOptionsFormatted()}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Points to Deduct</label>
                        <input type="number" name="minus_points[]" class="form-control" value="0" step="1" min="0">
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
            div.innerHTML = `
                <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Goalkeeper Who Saved</label>
                        <select name="saved_penalty_gks[]" class="form-control penalty-gk-select" data-pair="${penaltyCount}" onchange="handlePenaltyGKChange(this, ${penaltyCount})">
                            <option value="">-- Select Goalkeeper --</option>
                            ${getMatchGKOptionsFormatted()}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Player Who Missed <span style="color: var(--error);">*</span></label>
                        <select name="missed_penalty_players[]" class="form-control penalty-missed-select" data-pair="${penaltyCount}" onchange="handlePenaltyMissedPlayerChange(this, ${penaltyCount})">
                            <option value="">-- Select Player --</option>
                            ${getMatchPlayerOptionsFormatted()}
                        </select>
                    </div>
                </div>
            `;
            container.appendChild(div);
        }

        function handlePenaltyGKChange(selectElement, pairIndex) {
            const savedGkId = selectElement.value;
            const missedPlayerSelect = document.querySelector(`select[name="missed_penalty_players[]"][data-pair="${pairIndex}"]`);
            
            if (!missedPlayerSelect) return;
            
            const currentMissedPlayer = missedPlayerSelect.value;
            let gkTeamId = null;
            
            if (savedGkId) {
                const gkPlayer = leaguePlayers.find(p => p.player_id == savedGkId);
                if (gkPlayer) gkTeamId = gkPlayer.team_id;
            }
            
            missedPlayerSelect.innerHTML = '<option value="">-- Select Player --</option>';
            
            leaguePlayers.forEach(player => {
                const isInMatch = (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id);
                if (!isInMatch) return;
                if (savedGkId && player.player_id == savedGkId) return;
                if (savedGkId && gkTeamId && player.team_id == gkTeamId) return;
                
                const displayName = leaguePositions === 'positionless' 
                    ? `${player.player_name} - ${player.team_name || 'No Team'}`
                    : `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
                
                const option = document.createElement('option');
                option.value = player.player_id;
                option.textContent = displayName;
                missedPlayerSelect.appendChild(option);
            });
            
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
            let missedPlayerTeamId = null;
            
            if (missedPlayerId) {
                const missedPlayerObj = leaguePlayers.find(p => p.player_id == missedPlayerId);
                if (missedPlayerObj) missedPlayerTeamId = missedPlayerObj.team_id;
            }
            
            savedGkSelect.innerHTML = '<option value="">-- Select Goalkeeper --</option>';
            
            leaguePlayers.filter(player => player.player_role === 'GK' || leaguePositions === 'positionless').forEach(player => {
                const isInMatch = (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id);
                if (!isInMatch) return;
                if (missedPlayerId && player.player_id == missedPlayerId) return;
                if (missedPlayerId && missedPlayerTeamId && player.team_id == missedPlayerTeamId) return;
                
                const option = document.createElement('option');
                option.value = player.player_id;
                option.textContent = `${player.player_name} - ${player.team_name || 'No Team'}`;
                savedGkSelect.appendChild(option);
            });
            
            if (currentGk && currentGk != missedPlayerId) {
                const currentGkObj = leaguePlayers.find(p => p.player_id == currentGk);
                if (currentGkObj && currentGkObj.team_id != missedPlayerTeamId) {
                    savedGkSelect.value = currentGk;
                }
            }
        }

        function addYellowCardField() {
            yellowCardCount++;
            const container = document.getElementById('yellowCardContainer');
            
            if (yellowCardCount === 1 && container.querySelector('.empty-state')) {
                container.innerHTML = '';
            }
            
            const div = document.createElement('div');
            div.className = 'card-item';
            div.innerHTML = `
                <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
                <div class="form-group">
                    <label class="form-label">Player</label>
                    <select name="yellow_card_players[]" class="form-control">
                        <option value="">-- Select Player --</option>
                        ${getMatchPlayerOptionsFormatted()}
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
            div.innerHTML = `
                <button type="button" class="remove-btn" onclick="removeField(this)">×</button>
                <div class="form-group">
                    <label class="form-label">Player</label>
                    <select name="red_card_players[]" class="form-control">
                        <option value="">-- Select Player --</option>
                        ${getMatchPlayerOptionsFormatted()}
                    </select>
                </div>
            `;
            container.appendChild(div);
        }

        function removeField(button) {
            button.parentElement.remove();
        }

        function getMatchPlayerOptions() {
            let options = '';
            leaguePlayers.forEach(player => {
                if (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id) {
                    options += `<option value="${player.player_id}">${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}</option>`;
                }
            });
            return options;
        }

        function getMatchPlayerOptionsFormatted() {
            let options = '';
            leaguePlayers.forEach(player => {
                if (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id) {
                    const displayName = leaguePositions === 'positionless' 
                        ? `${player.player_name} - ${player.team_name || 'No Team'}`
                        : `${player.player_name} (${player.player_role}) - ${player.team_name || 'No Team'}`;
                    options += `<option value="${player.player_id}">${displayName}</option>`;
                }
            });
            return options;
        }

        function getMatchGKOptions() {
            let options = '';
            const gkFilter = leaguePositions === 'positions' ? 
                player => player.player_role === 'GK' : 
                player => true;
            
            leaguePlayers.filter(gkFilter).forEach(player => {
                if (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id) {
                    options += `<option value="${player.player_id}">${player.player_name} - ${player.team_name || 'No Team'}</option>`;
                }
            });
            return options;
        }

        function getMatchGKOptionsFormatted() {
            let options = '';
            const gkFilter = leaguePositions === 'positions' ? 
                player => player.player_role === 'GK' : 
                player => true;
            
            leaguePlayers.filter(gkFilter).forEach(player => {
                if (player.team_id == currentMatchTeams.team1_id || player.team_id == currentMatchTeams.team2_id) {
                    options += `<option value="${player.player_id}">${player.player_name} - ${player.team_name || 'No Team'}</option>`;
                }
            });
            return options;
        }

        function resetModalCounters() {
            bonusCount = 0;
            minusCount = 0;
            penaltyCount = 0;
            yellowCardCount = 0;
            redCardCount = 0;
        }

        function deletePoint(pointId) {
            fetch(`?ajax=get_point_details&id=<?php echo $league_id; ?>&point_id=${pointId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.point) {
                        const point = data.point;
                        let details = '<ul style="margin: 0.5rem 0 0 1.5rem; line-height: 2;">';
                        if (point.scorer_name) details += `<li>⚽ Scorer: ${point.scorer_name} (${point.scorer_role})</li>`;
                        if (point.assister_name) details += `<li>🎯 Assister: ${point.assister_name} (${point.assister_role})</li>`;
                        if (point.bonus_name) details += `<li>⭐ Bonus: ${point.bonus_name} (+${point.bonus_points})</li>`;
                        if (point.minus_name) details += `<li>❌ Minus: ${point.minus_name} (-${point.minus_points})</li>`;
                        if (point.saved_gk_name) details += `<li>✋ Penalty Saved: ${point.saved_gk_name}</li>`;
                        if (point.missed_player_name) details += `<li>❌ Penalty Missed: ${point.missed_player_name}</li>`;
                        if (point.yellow_card > 0) details += `<li>🟨 Yellow Card: ${point.yellow_card_player_name}</li>`;
                        if (point.red_card > 0) details += `<li>🟥 Red Card: ${point.red_card_player_name}</li>`;
                        details += '</ul>';

                        document.getElementById('deletePointId').value = pointId;
                        document.getElementById('deletePointDetails').innerHTML = details;
                        document.getElementById('deletePointModal').classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(error => console.error('Error loading point details:', error));
        }

        function closeDeletePointModal() {
            document.getElementById('deletePointModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        document.getElementById('addPointModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddPointModal();
        });

        document.getElementById('deletePointModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeletePointModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('addPointModal').classList.contains('active')) {
                    closeAddPointModal();
                }
                if (document.getElementById('deletePointModal').classList.contains('active')) {
                    closeDeletePointModal();
                }
            }
        });
    </script>
</body>
</html>