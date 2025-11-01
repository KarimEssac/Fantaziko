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
        $is_budget_system = ($league['system'] === 'Budget');
        $has_positions = ($league['positions'] === 'positions');
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM matches WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $total_matches = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $pdo->prepare("
            SELECT SUM(team1_score + team2_score) as total_goals
            FROM matches
            WHERE league_id = ?
        ");
        $stmt->execute([$league_id]);
        $total_goals = $stmt->fetch(PDO::FETCH_ASSOC)['total_goals'] ?? 0;
        $avg_goals_per_match = $total_matches > 0 ? round($total_goals / $total_matches, 2) : 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM league_contributors WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $total_contributors = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM league_players WHERE league_id = ?");
        $stmt->execute([$league_id]);
        $total_players = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $pdo->prepare("
            SELECT round, COUNT(*) as matches_count,
                   SUM(team1_score + team2_score) as goals_in_round
            FROM matches
            WHERE league_id = ?
            GROUP BY round
            ORDER BY round ASC
        ");
        $stmt->execute([$league_id]);
        $matches_by_round = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   lt1.team_name as team1_name, 
                   lt2.team_name as team2_name,
                   (m.team1_score + m.team2_score) as total_goals
            FROM matches m
            LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
            LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
            WHERE m.league_id = ?
            ORDER BY total_goals DESC
            LIMIT 1
        ");
        $stmt->execute([$league_id]);
        $highest_scoring_match = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT a.username, COUNT(cp.id) as total_selections
            FROM contributor_players cp
            INNER JOIN accounts a ON cp.user_id = a.id
            WHERE cp.league_id = ?
            GROUP BY cp.user_id
            ORDER BY total_selections DESC
            LIMIT 1
        ");
        $stmt->execute([$league_id]);
        $most_active_contributor = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT 
                SUM(yellow_card) as total_yellow_cards,
                SUM(red_card) as total_red_cards
            FROM matches_points mp
            INNER JOIN matches m ON mp.match_id = m.match_id
            WHERE m.league_id = ?
        ");
        $stmt->execute([$league_id]);
        $cards_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT saved_penalty_gk) as penalties_saved,
                COUNT(DISTINCT missed_penalty_player) as penalties_missed
            FROM matches_points mp
            INNER JOIN matches m ON mp.match_id = m.match_id
            WHERE m.league_id = ?
            AND (saved_penalty_gk IS NOT NULL OR missed_penalty_player IS NOT NULL)
        ");
        $stmt->execute([$league_id]);
        $penalty_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($has_positions) {
            $stmt = $pdo->prepare("
                SELECT lp.player_role, COUNT(mp.scorer) as goals
                FROM league_players lp
                LEFT JOIN matches_points mp ON lp.player_id = mp.scorer
                WHERE lp.league_id = ? AND lp.player_role IS NOT NULL
                GROUP BY lp.player_role
                ORDER BY goals DESC
            ");
            $stmt->execute([$league_id]);
            $goals_by_position = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("
                SELECT lp.player_role, COUNT(mp.assister) as assists
                FROM league_players lp
                LEFT JOIN matches_points mp ON lp.player_id = mp.assister
                WHERE lp.league_id = ? AND lp.player_role IS NOT NULL
                GROUP BY lp.player_role
                ORDER BY assists DESC
            ");
            $stmt->execute([$league_id]);
            $assists_by_position = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("
                SELECT player_role, COUNT(*) as count
                FROM league_players
                WHERE league_id = ? AND player_role IS NOT NULL
                GROUP BY player_role
                ORDER BY 
                    CASE player_role
                        WHEN 'GK' THEN 1
                        WHEN 'DEF' THEN 2
                        WHEN 'MID' THEN 3
                        WHEN 'ATT' THEN 4
                    END
            ");
            $stmt->execute([$league_id]);
            $players_by_position = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($is_budget_system) {
            $stmt = $pdo->prepare("
                SELECT AVG(player_price) as avg_price,
                       MAX(player_price) as max_price,
                       MIN(player_price) as min_price
                FROM league_players
                WHERE league_id = ?
            ");
            $stmt->execute([$league_id]);
            $price_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("
                SELECT lp.player_name, lp.player_price, lp.player_role, lt.team_name
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.league_id = ?
                ORDER BY lp.player_price DESC
                LIMIT 5
            ");
            $stmt->execute([$league_id]);
            $most_expensive_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare("
                SELECT lp.player_name, lp.player_price, lp.total_points, lp.player_role,
                       lt.team_name,
                       (lp.total_points / NULLIF(lp.player_price, 0)) as value_ratio
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.league_id = ? AND lp.player_price > 0 AND lp.total_points > 0
                ORDER BY value_ratio DESC
                LIMIT 5
            ");
            $stmt->execute([$league_id]);
            $best_value_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $pdo->prepare("
            SELECT lt.team_name, lt.team_score,
                   COUNT(DISTINCT m.match_id) as matches_played,
                   SUM(CASE 
                       WHEN (m.team1_id = lt.id AND m.team1_score > m.team2_score) 
                       OR (m.team2_id = lt.id AND m.team2_score > m.team1_score) 
                       THEN 1 ELSE 0 
                   END) as wins,
                   SUM(CASE 
                       WHEN (m.team1_id = lt.id AND m.team1_score = m.team2_score) 
                       OR (m.team2_id = lt.id AND m.team2_score = m.team1_score) 
                       THEN 1 ELSE 0 
                   END) as draws,
                   SUM(CASE 
                       WHEN (m.team1_id = lt.id AND m.team1_score < m.team2_score) 
                       OR (m.team2_id = lt.id AND m.team2_score < m.team1_score) 
                       THEN 1 ELSE 0 
                   END) as losses
            FROM league_teams lt
            LEFT JOIN matches m ON (m.team1_id = lt.id OR m.team2_id = lt.id)
            WHERE lt.league_id = ?
            GROUP BY lt.id
            HAVING matches_played > 0
            ORDER BY wins DESC, draws DESC
            LIMIT 10
        ");
        $stmt->execute([$league_id]);
        $team_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN total_score > 0 THEN 1 END) as active_contributors,
                COUNT(CASE WHEN total_score = 0 THEN 1 END) as inactive_contributors,
                AVG(total_score) as avg_contributor_score,
                MAX(total_score) as highest_contributor_score
            FROM league_contributors
            WHERE league_id = ?
        ");
        $stmt->execute([$league_id]);
        $contributor_engagement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT 
                SUM(bonus_points) as total_bonus_points,
                SUM(minus_points) as total_minus_points,
                COUNT(DISTINCT bonus) as players_with_bonus,
                COUNT(DISTINCT minus) as players_with_minus
            FROM matches_points mp
            INNER JOIN matches m ON mp.match_id = m.match_id
            WHERE m.league_id = ?
        ");
        $stmt->execute([$league_id]);
        $points_distribution = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - <?php echo htmlspecialchars($league['name'] ?? 'League'); ?></title>
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
            --info: #3b82f6;
            --purple: #8b5cf6;
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
            margin-bottom: 2.5rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .league-badges {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .league-badge {
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .league-badge.system {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .league-badge.positions {
            background: linear-gradient(135deg, var(--purple), #a855f7);
            color: white;
        }

        .section-header {
            margin: 3rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title i {
            color: var(--gradient-end);
            font-size: 1.5rem;
        }

        .section-line {
            flex: 1;
            height: 2px;
            background: linear-gradient(90deg, var(--gradient-end), transparent);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
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

        .stat-card.success::before {
            background: linear-gradient(90deg, var(--success), #059669);
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, var(--warning), #d97706);
        }

        .stat-card.error::before {
            background: linear-gradient(90deg, var(--error), #dc2626);
        }

        .stat-card.info::before {
            background: linear-gradient(90deg, var(--info), #2563eb);
        }

        .stat-card.purple::before {
            background: linear-gradient(90deg, var(--purple), #7c3aed);
        }

        .stat-icon {
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

        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, var(--success), #059669);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, var(--warning), #d97706);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
        }

        .stat-card.error .stat-icon {
            background: linear-gradient(135deg, var(--error), #dc2626);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .stat-card.info .stat-icon {
            background: linear-gradient(135deg, var(--info), #2563eb);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        .stat-card.purple .stat-icon {
            background: linear-gradient(135deg, var(--purple), #7c3aed);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.3rem;
        }

        .stat-card.success .stat-value {
            background: linear-gradient(135deg, var(--success), #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.warning .stat-value {
            background: linear-gradient(135deg, var(--warning), #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.error .stat-value {
            background: linear-gradient(135deg, var(--error), #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.info .stat-value {
            background: linear-gradient(135deg, var(--info), #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.purple .stat-value {
            background: linear-gradient(135deg, var(--purple), #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        body.dark-mode .content-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .content-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--gradient-end);
        }

        .card-badge {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }

        body.dark-mode .list-item {
            background: rgba(10, 20, 35, 0.5);
        }

        .list-item:hover {
            background: rgba(10, 146, 215, 0.1);
            transform: translateX(5px);
        }

        .list-item-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .list-rank {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            min-width: 35px;
        }

        .list-info {
            display: flex;
            flex-direction: column;
        }

        .list-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .list-detail {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .list-value {
            font-weight: 700;
            color: var(--gradient-end);
            font-size: 1.1rem;
            white-space: nowrap;
        }

        .progress-item {
            margin-bottom: 1.5rem;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .progress-value {
            font-weight: 700;
            color: var(--gradient-end);
            font-size: 0.95rem;
        }

        .progress-bar-container {
            width: 100%;
            height: 12px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        body.dark-mode .progress-bar-container {
            background: rgba(10, 20, 35, 0.5);
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.05));
            border: 2px solid rgba(10, 146, 215, 0.3);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        body.dark-mode .highlight-box {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.2), rgba(10, 146, 215, 0.1));
        }

        .highlight-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gradient-end);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .highlight-content {
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .highlight-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 0.3rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        body.dark-mode .info-item {
            background: rgba(10, 20, 35, 0.5);
        }

        .info-item:hover {
            background: rgba(10, 146, 215, 0.1);
            transform: translateY(-3px);
        }

        .info-icon {
            font-size: 2rem;
            margin-bottom: 0.8rem;
        }

        .info-icon.success {
            color: var(--success);
        }

        .info-icon.warning {
            color: var(--warning);
        }

        .info-icon.error {
            color: var(--error);
        }

        .info-icon.primary {
            color: var(--gradient-end);
        }

        .info-value {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
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


        .stats-table {
            width: 100%;
            border-collapse: collapse;
        }

        .stats-table thead {
            background: var(--bg-secondary);
        }

        body.dark-mode .stats-table thead {
            background: rgba(10, 20, 35, 0.5);
        }

        .stats-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .stats-table tbody tr {
            transition: all 0.3s ease;
        }

        .stats-table tbody tr:hover {
            background: rgba(10, 146, 215, 0.05);
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.4rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
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
        <div class="loading-text">Loading Statistics...</div>
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
                    <p class="not-owner-text">You don't have permission to access these statistics. Only the league owner can view this page.</p>
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
            <!-- Statistics Content -->
            <div class="page-header">
                <h2 class="page-title">
                    <i class="fas fa-chart-bar"></i>
                    League Statistics
                </h2>
                <p class="page-subtitle">Comprehensive analytics and insights for <?php echo htmlspecialchars($league['name']); ?></p>
                <div class="league-badges">
                    <span class="league-badge system">
                        <i class="fas fa-cog"></i>
                        <?php echo htmlspecialchars($league['system']); ?> System
                    </span>
                    <span class="league-badge positions">
                        <i class="fas fa-sitemap"></i>
                        <?php echo htmlspecialchars(ucfirst($league['positions'])); ?>
                    </span>
                </div>
            </div>

            <!-- Overall Stats -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Overall Performance
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_matches; ?></div>
                    <div class="stat-label">Total Matches Played</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_goals; ?></div>
                    <div class="stat-label">Total Goals Scored</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo $avg_goals_per_match; ?></div>
                    <div class="stat-label">Avg Goals Per Match</div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_players; ?></div>
                    <div class="stat-label">Total Players</div>
                </div>
            </div>

            <!-- Match Statistics -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-gamepad"></i>
                    Match Analysis
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-grid">
                <!-- Highest Scoring Match -->
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-fire"></i>
                            Highest Scoring Match
                        </h4>
                    </div>
                    <?php if ($highest_scoring_match && $highest_scoring_match['total_goals'] > 0): ?>
                        <div class="highlight-box">
                            <div class="highlight-title">
                                <i class="fas fa-trophy"></i>
                                Match of the Season
                            </div>
                            <div class="highlight-content">
                                <?php echo htmlspecialchars($highest_scoring_match['team1_name'] ?? 'Team 1'); ?> 
                                <?php echo $highest_scoring_match['team1_score']; ?> - 
                                <?php echo $highest_scoring_match['team2_score']; ?> 
                                <?php echo htmlspecialchars($highest_scoring_match['team2_name'] ?? 'Team 2'); ?>
                            </div>
                            <div class="highlight-subtitle">
                                Round <?php echo $highest_scoring_match['round']; ?> • <?php echo $highest_scoring_match['total_goals']; ?> total goals
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <p>No matches played yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Goals by Round -->
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-chart-area"></i>
                            Goals Distribution
                        </h4>
                        <span class="card-badge">By Round</span>
                    </div>
                    <?php if (!empty($matches_by_round)): ?>
                        <?php foreach ($matches_by_round as $round_data): ?>
                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label">Round <?php echo $round_data['round']; ?></span>
                                    <span class="progress-value"><?php echo $round_data['goals_in_round']; ?> goals</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $total_goals > 0 ? ($round_data['goals_in_round'] / $total_goals * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <p>No round data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Discipline Statistics -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-balance-scale"></i>
                    Discipline & Fair Play
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-icon warning">
                        <i class="fas fa-square"></i>
                    </div>
                    <div class="info-value"><?php echo $cards_stats['total_yellow_cards'] ?? 0; ?></div>
                    <div class="info-label">Yellow Cards</div>
                </div>

                <div class="info-item">
                    <div class="info-icon error">
                        <i class="fas fa-square"></i>
                    </div>
                    <div class="info-value"><?php echo $cards_stats['total_red_cards'] ?? 0; ?></div>
                    <div class="info-label">Red Cards</div>
                </div>

                <div class="info-item">
                    <div class="info-icon success">
                        <i class="fas fa-hand-paper"></i>
                    </div>
                    <div class="info-value"><?php echo $penalty_stats['penalties_saved'] ?? 0; ?></div>
                    <div class="info-label">Penalties Saved</div>
                </div>

                <div class="info-item">
                    <div class="info-icon error">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="info-value"><?php echo $penalty_stats['penalties_missed'] ?? 0; ?></div>
                    <div class="info-label">Penalties Missed</div>
                </div>
            </div>

            <?php if ($has_positions): ?>
            <!-- Position Statistics (Only for Positions System) -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-sitemap"></i>
                    Position Analysis
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-grid">
                <!-- Players by Position -->
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-users"></i>
                            Squad Distribution
                        </h4>
                        <span class="card-badge"><?php echo $total_players; ?> Players</span>
                    </div>
                    <?php if (!empty($players_by_position)): ?>
                        <?php 
                        $total_position_players = array_sum(array_column($players_by_position, 'count'));
                        foreach ($players_by_position as $position): 
                        ?>
                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label">
                                        <?php 
                                        $position_names = ['GK' => 'Goalkeepers', 'DEF' => 'Defenders', 'MID' => 'Midfielders', 'ATT' => 'Attackers'];
                                        echo $position_names[$position['player_role']] ?? $position['player_role']; 
                                        ?>
                                    </span>
                                    <span class="progress-value"><?php echo $position['count']; ?> players</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $total_position_players > 0 ? ($position['count'] / $total_position_players * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No player data available</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Goals by Position -->
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-crosshairs"></i>
                            Goals by Position
                        </h4>
                    </div>
                    <?php if (!empty($goals_by_position) && array_sum(array_column($goals_by_position, 'goals')) > 0): ?>
                        <?php 
                        $total_position_goals = array_sum(array_column($goals_by_position, 'goals'));
                        foreach ($goals_by_position as $position): 
                            if ($position['goals'] > 0):
                        ?>
                            <div class="list-item">
                                <div class="list-item-left">
                                    <div class="list-rank">
                                        <?php 
                                        $icons = ['GK' => 'fa-hand-paper', 'DEF' => 'fa-shield-alt', 'MID' => 'fa-sync', 'ATT' => 'fa-bullseye'];
                                        echo '<i class="fas ' . ($icons[$position['player_role']] ?? 'fa-user') . '"></i>'; 
                                        ?>
                                    </div>
                                    <div class="list-info">
                                        <div class="list-name">
                                            <?php 
                                            $position_names = ['GK' => 'Goalkeepers', 'DEF' => 'Defenders', 'MID' => 'Midfielders', 'ATT' => 'Attackers'];
                                            echo $position_names[$position['player_role']] ?? $position['player_role']; 
                                            ?>
                                        </div>
                                        <div class="list-detail">
                                            <?php echo $total_position_goals > 0 ? round(($position['goals'] / $total_position_goals) * 100, 1) : 0; ?>% of total goals
                                        </div>
                                    </div>
                                </div>
                                <div class="list-value"><?php echo $position['goals']; ?> <i class="fas fa-futbol"></i></div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-futbol"></i>
                            <p>No goals scored yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($is_budget_system): ?>
            <!-- Budget System Statistics -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Budget Analysis
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($price_stats['avg_price'] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Player Price</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($price_stats['max_price'] ?? 0, 2); ?></div>
                    <div class="stat-label">Most Expensive Player</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($price_stats['min_price'] ?? 0, 2); ?></div>
                    <div class="stat-label">Cheapest Player</div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($league['price'] ?? 0, 2); ?></div>
                    <div class="stat-label">Entry Fee</div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Most Expensive Players -->
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-gem"></i>
                            Premium Players
                        </h4>
                        <span class="card-badge">Top 5</span>
                    </div>
                    <?php if (!empty($most_expensive_players)): ?>
                        <?php foreach ($most_expensive_players as $index => $player): ?>
                            <div class="list-item">
                                <div class="list-item-left">
                                    <div class="list-rank"><?php echo $index + 1; ?></div>
                                    <div class="list-info">
                                        <div class="list-name"><?php echo htmlspecialchars($player['player_name']); ?></div>
                                        <div class="list-detail">
                                            <?php echo htmlspecialchars($player['team_name'] ?? 'No Team'); ?>
                                            <?php if ($has_positions && $player['player_role']): ?>
                                                • <?php echo htmlspecialchars($player['player_role']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-value"><?php echo number_format($player['player_price'], 2); ?> <i class="fas fa-coins"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-gem"></i>
                            <p>No player pricing data</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Best Value Players -->
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-award"></i>
                            Best Value Players
                        </h4>
                        <span class="card-badge">Points per Price</span>
                    </div>
                    <?php if (!empty($best_value_players)): ?>
                        <?php foreach ($best_value_players as $index => $player): ?>
                            <div class="list-item">
                                <div class="list-item-left">
                                    <div class="list-rank"><?php echo $index + 1; ?></div>
                                    <div class="list-info">
                                        <div class="list-name"><?php echo htmlspecialchars($player['player_name']); ?></div>
                                        <div class="list-detail">
                                            <?php echo $player['total_points']; ?> pts • <?php echo number_format($player['player_price'], 2); ?> price
                                        </div>
                                    </div>
                                </div>
                                <div class="list-value"><?php echo number_format($player['value_ratio'], 2); ?> <i class="fas fa-star"></i></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-award"></i>
                            <p>No value data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Team Performance -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-flag"></i>
                    Team Performance Breakdown
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Win/Draw/Loss Records
                    </h4>
                    <span class="card-badge"><?php echo count($team_performance); ?> Teams</span>
                </div>
                <?php if (!empty($team_performance)): ?>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Matches</th>
                                <th>W</th>
                                <th>D</th>
                                <th>L</th>
                                <th>Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_performance as $team): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                    <td><?php echo $team['matches_played']; ?></td>
                                    <td style="color: var(--success);"><?php echo $team['wins']; ?></td>
                                    <td style="color: var(--warning);"><?php echo $team['draws']; ?></td>
                                    <td style="color: var(--error);"><?php echo $team['losses']; ?></td>
                                    <td><strong><?php echo number_format($team['team_score']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-flag"></i>
                        <p>No team performance data available</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contributor Engagement -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-user-friends"></i>
                    Contributor Engagement
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Activity Overview
                        </h4>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="info-value"><?php echo $contributor_engagement['active_contributors'] ?? 0; ?></div>
                            <div class="info-label">Active Contributors</div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon warning">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="info-value"><?php echo $contributor_engagement['inactive_contributors'] ?? 0; ?></div>
                            <div class="info-label">Inactive Contributors</div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon primary">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="info-value"><?php echo number_format($contributor_engagement['avg_contributor_score'] ?? 0, 1); ?></div>
                            <div class="info-label">Average Score</div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon success">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="info-value"><?php echo number_format($contributor_engagement['highest_contributor_score'] ?? 0); ?></div>
                            <div class="info-label">Highest Score</div>
                        </div>
                    </div>
                </div>

                <?php if ($most_active_contributor): ?>
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-fire"></i>
                            Most Active Contributor
                        </h4>
                    </div>
                    <div class="highlight-box">
                        <div class="highlight-title">
                            <i class="fas fa-star"></i>
                            Team Manager of the Season
                        </div>
                        <div class="highlight-content">
                            <?php echo htmlspecialchars($most_active_contributor['username']); ?>
                        </div>
                        <div class="highlight-subtitle">
                            <?php echo $most_active_contributor['total_selections']; ?> player selections made
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Points Distribution -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-star-half-alt"></i>
                    Points Distribution
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-plus-circle"></i>
                            Bonus Points
                        </h4>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon success">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div class="info-value"><?php echo $points_distribution['total_bonus_points'] ?? 0; ?></div>
                            <div class="info-label">Total Bonus Points</div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon success">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="info-value"><?php echo $points_distribution['players_with_bonus'] ?? 0; ?></div>
                            <div class="info-label">Players Awarded</div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-minus-circle"></i>
                            Minus Points
                        </h4>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon error">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="info-value"><?php echo $points_distribution['total_minus_points'] ?? 0; ?></div>
                            <div class="info-label">Total Minus Points</div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon error">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="info-value"><?php echo $points_distribution['players_with_minus'] ?? 0; ?></div>
                            <div class="info-label">Players Penalized</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- League Insights -->
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-lightbulb"></i>
                    League Insights
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-info-circle"></i>
                            Key Statistics
                        </h4>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">Current Round</div>
                            <div class="list-detail">League progress tracker</div>
                        </div>
                        <div class="list-value">Round <?php echo $league['round']; ?></div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">Total Teams</div>
                            <div class="list-detail">Competing teams</div>
                        </div>
                        <div class="list-value"><?php echo $league['num_of_teams']; ?></div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">Total Contributors</div>
                            <div class="list-detail">Fantasy managers</div>
                        </div>
                        <div class="list-value"><?php echo $total_contributors; ?></div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">Player Pool</div>
                            <div class="list-detail">Available players</div>
                        </div>
                        <div class="list-value"><?php echo $total_players; ?></div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h4 class="card-title">
                            <i class="fas fa-cogs"></i>
                            Power-ups Status
                        </h4>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">
                                <i class="fas fa-user-astronaut"></i> Triple Captain
                            </div>
                            <div class="list-detail">3x captain points boost</div>
                        </div>
                        <div class="list-value"><?php echo $league['triple_captain'] > 0 ? 'Enabled' : 'Disabled'; ?></div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">
                                <i class="fas fa-rocket"></i> Bench Boost
                            </div>
                            <div class="list-detail">Count bench player points</div>
                        </div>
                        <div class="list-value"><?php echo $league['bench_boost'] > 0 ? 'Enabled' : 'Disabled'; ?></div>
                    </div>
                    <div class="list-item">
                        <div class="list-info">
                            <div class="list-name">
                                <i class="fas fa-id-card"></i> Wild Card
                            </div>
                            <div class="list-detail">Unlimited transfers</div>
                        </div>
                        <div class="list-value"><?php echo $league['wild_card'] > 0 ? 'Enabled' : 'Disabled'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Competition Health -->
            <?php 
            $engagement_rate = $total_contributors > 0 ? 
                round(($contributor_engagement['active_contributors'] / $total_contributors) * 100, 1) : 0;
            $avg_match_goals = $total_matches > 0 ? round($total_goals / $total_matches, 1) : 0;
            ?>
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-heartbeat"></i>
                    League Health Score
                </h3>
                <div class="section-line"></div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Competition Metrics
                    </h4>
                </div>
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">
                            <i class="fas fa-users"></i> Contributor Engagement Rate
                        </span>
                        <span class="progress-value"><?php echo $engagement_rate; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo $engagement_rate; ?>%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">
                            <i class="fas fa-futbol"></i> Match Activity Level
                        </span>
                        <span class="progress-value"><?php echo $avg_match_goals; ?> goals/match</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo min($avg_match_goals * 20, 100); ?>%"></div>
                    </div>
                </div>

                <?php if ($is_budget_system): ?>
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">
                            <i class="fas fa-coins"></i> Average Player Value
                        </span>
                        <span class="progress-value"><?php echo number_format($price_stats['avg_price'] ?? 0, 2); ?></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php 
                            $max_price = $price_stats['max_price'] ?? 1;
                            $avg_price = $price_stats['avg_price'] ?? 0;
                            echo $max_price > 0 ? min(($avg_price / $max_price) * 100, 100) : 0; 
                        ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-label">
                            <i class="fas fa-calendar-check"></i> Season Progress
                        </span>
                        <span class="progress-value">Round <?php echo $league['round']; ?></span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo min($league['round'] * 5, 100); ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Summary Box -->
            <div class="content-card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h4 class="card-title">
                        <i class="fas fa-clipboard-check"></i>
                        Season Summary
                    </h4>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div>
                        <div style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem;">Total Matches</div>
                        <div style="font-size: 1.8rem; font-weight: 900; color: var(--gradient-end);"><?php echo $total_matches; ?></div>
                    </div>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem;">Total Goals</div>
                        <div style="font-size: 1.8rem; font-weight: 900; color: var(--success);"><?php echo $total_goals; ?></div>
                    </div>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem;">Active Contributors</div>
                        <div style="font-size: 1.8rem; font-weight: 900; color: var(--info);"><?php echo $contributor_engagement['active_contributors'] ?? 0; ?></div>
                    </div>
                    <div>
                        <div style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.3rem;">Total Cards</div>
                        <div style="font-size: 1.8rem; font-weight: 900; color: var(--warning);"><?php echo ($cards_stats['total_yellow_cards'] ?? 0) + ($cards_stats['total_red_cards'] ?? 0); ?></div>
                    </div>
                </div>
            </div>

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
    </script>
</body>
</html>