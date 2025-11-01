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
    
    // Only fetch data if user has access and league is activated
    if (!$not_owner && !$not_activated) {
        // Get league roles for clean sheet calculation
        $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $league_roles = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get all teams in the league
        $stmt = $pdo->prepare("
            SELECT id, team_name, team_score 
            FROM league_teams 
            WHERE league_id = ? 
            ORDER BY team_name
        ");
        $stmt->execute([$league_id]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get selected round (default to current round)
        $selected_round = isset($_GET['round']) ? intval($_GET['round']) : $league['round'];
        
        // Get matches for selected round
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                t1.team_name as team1_name,
                t2.team_name as team2_name
            FROM matches m
            LEFT JOIN league_teams t1 ON m.team1_id = t1.id
            LEFT JOIN league_teams t2 ON m.team2_id = t2.id
            WHERE m.league_id = ? AND m.round = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$league_id, $selected_round]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Function to apply clean sheet bonuses
function applyCleanSheetBonuses($pdo, $league_id, $team_id, $league_positions) {
    // Get clean sheet bonuses from league_roles
    $stmt = $pdo->prepare("SELECT gk_clean_sheet, def_clean_sheet FROM league_roles WHERE league_id = ?");
    $stmt->execute([$league_id]);
    $bonuses = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bonuses) return;
    
    $gk_bonus = $bonuses['gk_clean_sheet'];
    $def_bonus = $bonuses['def_clean_sheet'];
    
    if ($league_positions === 'positions') {
        // Only GK and DEF get clean sheet bonuses
        $stmt = $pdo->prepare("SELECT player_id, player_role FROM league_players WHERE team_id = ? AND player_role IN ('GK', 'DEF')");
        $stmt->execute([$team_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($players as $player) {
            $bonus = ($player['player_role'] == 'GK') ? $gk_bonus : $def_bonus;
            if ($bonus > 0) {
                $pdo->prepare("UPDATE league_players SET total_points = total_points + ? WHERE player_id = ?")->execute([$bonus, $player['player_id']]);
            }
        }
    } else {
        // Positionless - all team members get clean sheet bonus (use def_clean_sheet as the universal bonus)
        $stmt = $pdo->prepare("SELECT player_id FROM league_players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($players as $player) {
            if ($def_bonus > 0) {
                $pdo->prepare("UPDATE league_players SET total_points = total_points + ? WHERE player_id = ?")->execute([$def_bonus, $player['player_id']]);
            }
        }
    }
}

// Function to revert clean sheet bonuses
function revertCleanSheetBonuses($pdo, $league_id, $team_id, $league_positions) {
    $stmt = $pdo->prepare("SELECT gk_clean_sheet, def_clean_sheet FROM league_roles WHERE league_id = ?");
    $stmt->execute([$league_id]);
    $bonuses = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bonuses) return;
    
    $gk_bonus = $bonuses['gk_clean_sheet'];
    $def_bonus = $bonuses['def_clean_sheet'];
    
    if ($league_positions === 'positions') {
        $stmt = $pdo->prepare("SELECT player_id, player_role FROM league_players WHERE team_id = ? AND player_role IN ('GK', 'DEF')");
        $stmt->execute([$team_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($players as $player) {
            $bonus = ($player['player_role'] == 'GK') ? $gk_bonus : $def_bonus;
            if ($bonus > 0) {
                $pdo->prepare("UPDATE league_players SET total_points = total_points - ? WHERE player_id = ?")->execute([$bonus, $player['player_id']]);
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT player_id FROM league_players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($players as $player) {
            if ($def_bonus > 0) {
                $pdo->prepare("UPDATE league_players SET total_points = total_points - ? WHERE player_id = ?")->execute([$def_bonus, $player['player_id']]);
            }
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$not_owner && !$not_activated && !$league_not_found) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_match':
                    if ($_POST['team1_id'] == $_POST['team2_id']) {
                        $error_message = "Team 1 and Team 2 cannot be the same!";
                        break;
                    }
                    
                    $pdo->beginTransaction();
                    
                    $team1_score = intval($_POST['team1_score']);
                    $team2_score = intval($_POST['team2_score']);
                    $match_round = intval($_POST['round']);
                    
                    // Update league round if the match round is higher
                    if ($match_round > $league['round']) {
                        $stmt = $pdo->prepare("UPDATE leagues SET round = ? WHERE id = ?");
                        $stmt->execute([$match_round, $league_id]);
                    }
                    
                    // Insert match
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (league_id, round, team1_id, team2_id, team1_score, team2_score)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $league_id,
                        $match_round,
                        $_POST['team1_id'],
                        $_POST['team2_id'],
                        $team1_score,
                        $team2_score
                    ]);
                    
                    // Update team scores based on result
                    if ($team1_score > $team2_score) {
                        // Team 1 wins
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 3 WHERE id = ?")->execute([$_POST['team1_id']]);
                    } elseif ($team2_score > $team1_score) {
                        // Team 2 wins
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 3 WHERE id = ?")->execute([$_POST['team2_id']]);
                    } else {
                        // Draw
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 1 WHERE id = ?")->execute([$_POST['team1_id']]);
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 1 WHERE id = ?")->execute([$_POST['team2_id']]);
                    }
                    
                    // Apply clean sheet bonuses
                    if ($team2_score == 0) {
                        applyCleanSheetBonuses($pdo, $league_id, $_POST['team1_id'], $league['positions']);
                    }
                    if ($team1_score == 0) {
                        applyCleanSheetBonuses($pdo, $league_id, $_POST['team2_id'], $league['positions']);
                    }
                    
                    $pdo->commit();
                    $success_message = "Match added successfully! Team scores and clean sheets updated.";
                    
                    // Reload league data
                    $stmt = $pdo->prepare("SELECT l.*, lt.token FROM leagues l LEFT JOIN league_tokens lt ON l.id = lt.league_id WHERE l.id = ?");
                    $stmt->execute([$league_id]);
                    $league = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Reload matches
                    $selected_round = $match_round;
                    $stmt = $pdo->prepare("
                        SELECT 
                            m.*,
                            t1.team_name as team1_name,
                            t2.team_name as team2_name
                        FROM matches m
                        LEFT JOIN league_teams t1 ON m.team1_id = t1.id
                        LEFT JOIN league_teams t2 ON m.team2_id = t2.id
                        WHERE m.league_id = ? AND m.round = ?
                        ORDER BY m.created_at DESC
                    ");
                    $stmt->execute([$league_id, $selected_round]);
                    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'update_match':
                    if ($_POST['team1_id'] == $_POST['team2_id']) {
                        $error_message = "Team 1 and Team 2 cannot be the same!";
                        break;
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Get old match data
                    $stmt = $pdo->prepare("SELECT team1_id, team2_id, team1_score, team2_score FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    $old_match = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $old_team1_score = intval($old_match['team1_score']);
                    $old_team2_score = intval($old_match['team2_score']);
                    $new_team1_score = intval($_POST['team1_score']);
                    $new_team2_score = intval($_POST['team2_score']);
                    $match_round = intval($_POST['round']);
                    
                    // Update league round if the match round is higher
                    if ($match_round > $league['round']) {
                        $stmt = $pdo->prepare("UPDATE leagues SET round = ? WHERE id = ?");
                        $stmt->execute([$match_round, $league_id]);
                    }
                    
                    // Revert old scores
                    if ($old_team1_score > $old_team2_score) {
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$old_match['team1_id']]);
                    } elseif ($old_team2_score > $old_team1_score) {
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$old_match['team2_id']]);
                    } else {
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$old_match['team1_id']]);
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$old_match['team2_id']]);
                    }
                    
                    // Revert old clean sheets
                    if ($old_team2_score == 0) {
                        revertCleanSheetBonuses($pdo, $league_id, $old_match['team1_id'], $league['positions']);
                    }
                    if ($old_team1_score == 0) {
                        revertCleanSheetBonuses($pdo, $league_id, $old_match['team2_id'], $league['positions']);
                    }
                    
                    // Update match
                    $stmt = $pdo->prepare("
                        UPDATE matches 
                        SET round = ?, team1_id = ?, team2_id = ?, team1_score = ?, team2_score = ?
                        WHERE match_id = ?
                    ");
                    $stmt->execute([
                        $match_round,
                        $_POST['team1_id'],
                        $_POST['team2_id'],
                        $new_team1_score,
                        $new_team2_score,
                        $_POST['match_id']
                    ]);
                    
                    // Apply new scores
                    if ($new_team1_score > $new_team2_score) {
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 3 WHERE id = ?")->execute([$_POST['team1_id']]);
                    } elseif ($new_team2_score > $new_team1_score) {
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 3 WHERE id = ?")->execute([$_POST['team2_id']]);
                    } else {
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 1 WHERE id = ?")->execute([$_POST['team1_id']]);
                        $pdo->prepare("UPDATE league_teams SET team_score = team_score + 1 WHERE id = ?")->execute([$_POST['team2_id']]);
                    }
                    
                    // Apply new clean sheets
                    if ($new_team2_score == 0) {
                        applyCleanSheetBonuses($pdo, $league_id, $_POST['team1_id'], $league['positions']);
                    }
                    if ($new_team1_score == 0) {
                        applyCleanSheetBonuses($pdo, $league_id, $_POST['team2_id'], $league['positions']);
                    }
                    
                    $pdo->commit();
                    $success_message = "Match updated successfully!";
                    
                    // Reload league data
                    $stmt = $pdo->prepare("SELECT l.*, lt.token FROM leagues l LEFT JOIN league_tokens lt ON l.id = lt.league_id WHERE l.id = ?");
                    $stmt->execute([$league_id]);
                    $league = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Reload matches
                    $selected_round = $match_round;
                    $stmt = $pdo->prepare("
                        SELECT 
                            m.*,
                            t1.team_name as team1_name,
                            t2.team_name as team2_name
                        FROM matches m
                        LEFT JOIN league_teams t1 ON m.team1_id = t1.id
                        LEFT JOIN league_teams t2 ON m.team2_id = t2.id
                        WHERE m.league_id = ? AND m.round = ?
                        ORDER BY m.created_at DESC
                    ");
                    $stmt->execute([$league_id, $selected_round]);
                    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                    
                case 'delete_match':
                    $pdo->beginTransaction();
                    
                    // Get match data
                    $stmt = $pdo->prepare("SELECT team1_id, team2_id, team1_score, team2_score FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    $match = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($match) {
                        $team1_score = intval($match['team1_score']);
                        $team2_score = intval($match['team2_score']);
                        
                        // Revert team scores
                        if ($team1_score > $team2_score) {
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$match['team1_id']]);
                        } elseif ($team2_score > $team1_score) {
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$match['team2_id']]);
                        } else {
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$match['team1_id']]);
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$match['team2_id']]);
                        }
                        
                        // Revert clean sheets
                        if ($team2_score == 0) {
                            revertCleanSheetBonuses($pdo, $league_id, $match['team1_id'], $league['positions']);
                        }
                        if ($team1_score == 0) {
                            revertCleanSheetBonuses($pdo, $league_id, $match['team2_id'], $league['positions']);
                        }
                    }
                    
                    // Delete match points
                    $stmt = $pdo->prepare("DELETE FROM matches_points WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    
                    // Delete match
                    $stmt = $pdo->prepare("DELETE FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    
                    $pdo->commit();
                    $success_message = "Match deleted successfully!";
                    
                    // Reload matches
                    $stmt = $pdo->prepare("
                        SELECT 
                            m.*,
                            t1.team_name as team1_name,
                            t2.team_name as team2_name
                        FROM matches m
                        LEFT JOIN league_teams t1 ON m.team1_id = t1.id
                        LEFT JOIN league_teams t2 ON m.team2_id = t2.id
                        WHERE m.league_id = ? AND m.round = ?
                        ORDER BY m.created_at DESC
                    ");
                    $stmt->execute([$league_id, $selected_round]);
                    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
    <title>Manage Matches - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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

        /* Access Denied Pages */
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

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
        }

        .page-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
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
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            color: var(--error);
        }

        /* Round Selector */
        .round-selector {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        body.dark-mode .round-selector {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .round-selector-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.8rem;
            display: block;
        }

        .round-selector-wrapper {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .round-select {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Roboto', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        body.dark-mode .round-select {
            background: rgba(10, 20, 35, 0.5);
        }

        .round-select:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }

        /* Data Card */
        .data-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        body.dark-mode .data-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
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
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 1.2rem 1.5rem;
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state-text {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        /* Match Info */
        .match-teams {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .team-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .match-vs {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .match-score {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gradient-end);
        }

        .score-separator {
            font-size: 1rem;
            color: var(--text-secondary);
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-primary {
            background: rgba(29, 96, 172, 0.15);
            color: var(--gradient-end);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.15);
            color: var(--error);
        }

        .result-badge {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
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
            padding: 0.6rem 1.5rem;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: var(--gradient-end);
            color: white;
        }

        .btn-danger {
            background: var(--error);
            color: white;
            padding: 0.6rem 1.5rem;
            font-size: 0.9rem;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
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
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.8rem;
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
            background: rgba(255, 255, 255, 0.2);
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

        /* Form Elements */
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
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.95rem;
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

        select.form-control {
            cursor: pointer;
        }

        .info-box {
            background: rgba(10, 146, 215, 0.1);
            border-left: 4px solid var(--gradient-end);
            padding: 1rem 1.2rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }

        .info-box-title {
            font-weight: 700;
            color: var(--gradient-end);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-box-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .info-box ul {
            margin: 0.5rem 0 0 1.2rem;
            padding: 0;
        }

        .info-box li {
            margin-bottom: 0.3rem;
        }

        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
        }

        .warning-box .info-box-title {
            color: var(--error);
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

            .modal-body {
                padding: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
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
        <div class="loading-text">Loading Matches Management...</div>
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
                    <p class="not-owner-text">You don't have permission to access this league's settings. Only the league owner can manage matches.</p>
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
                <h1 class="page-title">⚽ Manage Matches</h1>
                <div class="page-actions">
                    <button class="btn btn-gradient" onclick="openAddMatchModal()">
                        <i class="fas fa-plus"></i>
                        Add Match
                    </button>
                </div>
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

            <div class="round-selector">
                <label class="round-selector-label">
                    <i class="fas fa-calendar-alt"></i> Select Round
                </label>
                <div class="round-selector-wrapper">
                    <select class="round-select" id="roundSelect" onchange="changeRound()">
                        <?php for ($i = 1; $i <= $league['round']; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $selected_round ? 'selected' : ''; ?>>
                                Round <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span class="badge badge-primary">Current: Round <?php echo $league['round']; ?></span>
                </div>
            </div>

            <div class="data-card">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Match ID</th>
                                <th>Teams</th>
                                <th>Score</th>
                                <th>Result</th>
                                <th>Goals</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($matches)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar-times"></i>
                                            <div class="empty-state-text">No matches found for Round <?php echo $selected_round; ?></div>
                                            <button class="btn btn-gradient btn-sm" onclick="openAddMatchModal()">
                                                <i class="fas fa-plus"></i>
                                                Add First Match
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($matches as $match): ?>
                                    <?php
                                    $team1_score = intval($match['team1_score']);
                                    $team2_score = intval($match['team2_score']);
                                    $total_goals = $team1_score + $team2_score;
                                    $formatted_date = date('M j, Y', strtotime($match['created_at']));
                                    
                                    $team1_result = '';
                                    $team2_result = '';
                                    
                                    if ($team1_score > $team2_score) {
                                        $team1_result = '<span class="result-badge badge-success">WIN +3</span>';
                                        $team2_result = '<span class="result-badge badge-error">LOSS</span>';
                                    } elseif ($team2_score > $team1_score) {
                                        $team1_result = '<span class="result-badge badge-error">LOSS</span>';
                                        $team2_result = '<span class="result-badge badge-success">WIN +3</span>';
                                    } else {
                                        $team1_result = '<span class="result-badge badge-warning">DRAW +1</span>';
                                        $team2_result = '<span class="result-badge badge-warning">DRAW +1</span>';
                                    }
                                    ?>
                                    <tr>
                                        <td><strong>#<?php echo $match['match_id']; ?></strong></td>
                                        <td>
                                            <div class="match-teams">
                                                <span class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                                <span class="match-vs">vs</span>
                                                <span class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="match-score">
                                                <span><?php echo $team1_score; ?></span>
                                                <span class="score-separator">-</span>
                                                <span><?php echo $team2_score; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                                                <?php echo $team1_result; ?>
                                                <?php echo $team2_result; ?>
                                            </div>
                                        </td>
                                        <td><strong style="color: var(--gradient-end);"><?php echo $total_goals; ?></strong></td>
                                        <td style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo $formatted_date; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-secondary btn-sm" onclick='editMatch(<?php echo json_encode($match); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                    Edit
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick='deleteMatch(<?php echo json_encode($match); ?>)'>
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <!-- Add Match Modal -->
    <div class="modal-overlay" id="addMatchModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">Add New Match</span>
                <button class="modal-close" onclick="closeAddMatchModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_match">
                    
                    <div class="form-group">
                        <label class="form-label">Round *</label>
                        <input type="number" name="round" id="addRoundInput" class="form-control" required min="1" value="<?php echo $league['round']; ?>" placeholder="Enter round number" onchange="filterAvailableTeams()" oninput="if(this.value < 1) this.value = 1;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 1 *</label>
                        <select name="team1_id" id="addTeam1" class="form-control" required>
                            <option value="">Select Team 1</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" data-team-id="<?php echo $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['team_name']); ?> (Score: <?php echo $team['team_score']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 1 Score *</label>
                        <input type="number" name="team1_score" class="form-control" required min="0" value="0" placeholder="Enter score">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 2 *</label>
                        <select name="team2_id" id="addTeam2" class="form-control" required>
                            <option value="">Select Team 2</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" data-team-id="<?php echo $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['team_name']); ?> (Score: <?php echo $team['team_score']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 2 Score *</label>
                        <input type="number" name="team2_score" class="form-control" required min="0" value="0" placeholder="Enter score">
                    </div>
                    
                    <div class="info-box">
                        <div class="info-box-title">💡 Scoring Rules</div>
                        <div class="info-box-text">
                            <ul>
                                <li><strong>Winner:</strong> +3 points</li>
                                <li><strong>Draw:</strong> +1 point each</li>
                                <li><strong>Loser:</strong> 0 points</li>
                                <li><strong>Clean Sheet:</strong> Bonus points for <?php echo $league['positions'] === 'positions' ? 'goalkeepers & defenders' : 'all players'; ?></li>
                                <li><strong>Note:</strong> Teams can only play once per round</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeAddMatchModal()">Cancel</button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-plus"></i>
                        Add Match
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Match Modal -->
    <div class="modal-overlay" id="editMatchModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">Edit Match</span>
                <button class="modal-close" onclick="closeEditMatchModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_match">
                    <input type="hidden" name="match_id" id="editMatchId">
                    
                    <div class="form-group">
                        <label class="form-label">Round *</label>
                        <input type="number" name="round" id="editRound" class="form-control" required min="1" oninput="if(this.value < 1) this.value = 1;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 1 *</label>
                        <select name="team1_id" id="editTeam1" class="form-control" required>
                            <option value="">Select Team 1</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['team_name']); ?> (Score: <?php echo $team['team_score']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 1 Score *</label>
                        <input type="number" name="team1_score" id="editTeam1Score" class="form-control" required min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 2 *</label>
                        <select name="team2_id" id="editTeam2" class="form-control" required>
                            <option value="">Select Team 2</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['team_name']); ?> (Score: <?php echo $team['team_score']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Team 2 Score *</label>
                        <input type="number" name="team2_score" id="editTeam2Score" class="form-control" required min="0">
                    </div>
                    
                    <div class="info-box warning-box">
                        <div class="info-box-title">⚠️ Warning</div>
                        <div class="info-box-text">
                            Editing this match will automatically update team standings and clean sheet bonuses based on the new result.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeEditMatchModal()">Cancel</button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Match Modal -->
    <div class="modal-overlay" id="deleteMatchModal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">Delete Match</span>
                <button class="modal-close" onclick="closeDeleteMatchModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_match">
                    <input type="hidden" name="match_id" id="deleteMatchId">
                    
                    <div class="info-box warning-box">
                        <div class="info-box-title">⚠️ Warning</div>
                        <div class="info-box-text">
                            <p style="margin-bottom: 1rem;">Are you sure you want to delete this match?</p>
                            <p><strong>Match Details:</strong></p>
                            <ul>
                                <li>Match ID: <strong id="deleteMatchIdDisplay"></strong></li>
                                <li>Round: <strong id="deleteRoundDisplay"></strong></li>
                                <li>Teams: <strong id="deleteTeamsDisplay"></strong></li>
                                <li>Score: <strong id="deleteScoreDisplay"></strong></li>
                            </ul>
                            <p style="margin-top: 1rem;"><strong>This action will:</strong></p>
                            <ul>
                                <li>Revert team standings points</li>
                                <li>Remove clean sheet bonuses</li>
                                <li>Delete all match points data</li>
                                <li><strong>This cannot be undone</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteMatchModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Delete Match
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php endif; ?>

    <script>
        window.addEventListener('load', function() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            setTimeout(() => {
                loadingSpinner.classList.add('hidden');
            }, 500);
        });
        const allMatchesData = <?php echo json_encode($pdo->query("SELECT team1_id, team2_id, round FROM matches WHERE league_id = " . intval($league_id))->fetchAll(PDO::FETCH_ASSOC)); ?>;
        const allTeamsData = <?php echo json_encode($teams); ?>;
        
        function filterAvailableTeams() {
            const selectedRound = parseInt(document.getElementById('addRoundInput').value);
            if (!selectedRound) return;
            
            const playedTeams = new Set();
            allMatchesData.forEach(match => {
                if (parseInt(match.round) === selectedRound) {
                    if (match.team1_id) playedTeams.add(parseInt(match.team1_id));
                    if (match.team2_id) playedTeams.add(parseInt(match.team2_id));
                }
            });

            const team1Select = document.getElementById('addTeam1');
            const team2Select = document.getElementById('addTeam2');
            
            const currentTeam1 = team1Select.value;
            const currentTeam2 = team2Select.value;
            
            [team1Select, team2Select].forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select Team</option>';
                allTeamsData.forEach(team => {
                    if (!playedTeams.has(parseInt(team.id))) {
                        const option = document.createElement('option');
                        option.value = team.id;
                        option.textContent = team.team_name + ' (Score: ' + team.team_score + ')';
                        select.appendChild(option);
                    }
                });
                if (currentValue && !playedTeams.has(parseInt(currentValue))) {
                    select.value = currentValue;
                }
            });
        }
        
        function changeRound() {
            const round = document.getElementById('roundSelect').value;
            window.location.href = 'manage_matches.php?id=<?php echo $league_id; ?>&round=' + round;
        }

        function openAddMatchModal() {
            document.getElementById('addRoundInput').value = <?php echo $league['round']; ?>;
            document.getElementById('addTeam1').value = '';
            document.getElementById('addTeam2').value = '';
            filterAvailableTeams();
            const team1 = document.getElementById('addTeam1');
            const team2 = document.getElementById('addTeam2');
            
            team1.addEventListener('change', function() {
                if (team2.value && team1.value === team2.value) {
                    alert('Team 1 and Team 2 cannot be the same!');
                    team1.value = '';
                }
            });
            
            team2.addEventListener('change', function() {
                if (team1.value && team2.value === team1.value) {
                    alert('Team 1 and Team 2 cannot be the same!');
                    team2.value = '';
                }
            });
            
            document.getElementById('addMatchModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddMatchModal() {
            document.getElementById('addMatchModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function editMatch(match) {
            document.getElementById('editMatchId').value = match.match_id;
            document.getElementById('editRound').value = match.round;
            document.getElementById('editTeam1').value = match.team1_id;
            document.getElementById('editTeam2').value = match.team2_id;
            document.getElementById('editTeam1Score').value = match.team1_score;
            document.getElementById('editTeam2Score').value = match.team2_score;
            const team1 = document.getElementById('editTeam1');
            const team2 = document.getElementById('editTeam2');
            
            team1.addEventListener('change', function() {
                if (team2.value && team1.value === team2.value) {
                    alert('Team 1 and Team 2 cannot be the same!');
                    team1.value = match.team1_id;
                }
            });
            
            team2.addEventListener('change', function() {
                if (team1.value && team2.value === team1.value) {
                    alert('Team 1 and Team 2 cannot be the same!');
                    team2.value = match.team2_id;
                }
            });
            
            document.getElementById('editMatchModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditMatchModal() {
            document.getElementById('editMatchModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function deleteMatch(match) {
            document.getElementById('deleteMatchId').value = match.match_id;
            document.getElementById('deleteMatchIdDisplay').textContent = match.match_id;
            document.getElementById('deleteRoundDisplay').textContent = 'Round ' + match.round;
            document.getElementById('deleteTeamsDisplay').textContent = match.team1_name + ' vs ' + match.team2_name;
            document.getElementById('deleteScoreDisplay').textContent = match.team1_score + ' - ' + match.team2_score;
            
            document.getElementById('deleteMatchModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteMatchModal() {
            document.getElementById('deleteMatchModal').classList.remove('active');
            document.body.style.overflow = '';
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
                    document.body.style.overflow = '';
                });
            }
        });
    </script>
</body>
</html>