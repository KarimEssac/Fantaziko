<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_contributors' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lc.*,
                    a.username,
                    a.email,
                    l.name as league_name
                FROM league_contributors lc
                LEFT JOIN accounts a ON lc.user_id = a.id
                LEFT JOIN leagues l ON lc.league_id = l.id
                WHERE lc.league_id = ?
                ORDER BY lc.total_score DESC
            ");
            $stmt->execute([$_GET['league_id']]);
            $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($contributors);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_teams' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lt.*,
                    l.name as league_name,
                    (SELECT COUNT(*) FROM matches WHERE (team1_id = lt.id OR team2_id = lt.id) AND league_id = ?) as matches_played,
                    (SELECT COUNT(*) FROM matches WHERE ((team1_id = lt.id AND team1_score > team2_score) OR (team2_id = lt.id AND team2_score > team1_score)) AND league_id = ?) as wins,
                    (SELECT COUNT(*) FROM matches WHERE (team1_id = lt.id OR team2_id = lt.id) AND team1_score = team2_score AND league_id = ?) as draws,
                    (SELECT COUNT(*) FROM matches WHERE ((team1_id = lt.id AND team1_score < team2_score) OR (team2_id = lt.id AND team2_score < team1_score)) AND league_id = ?) as losses
                FROM league_teams lt
                LEFT JOIN leagues l ON lt.league_id = l.id
                WHERE lt.league_id = ?
                ORDER BY lt.team_score DESC
            ");
            $stmt->execute([$_GET['league_id'], $_GET['league_id'], $_GET['league_id'], $_GET['league_id'], $_GET['league_id']]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($teams);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_top_scorers' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.player_id,
                    lp.player_name,
                    lp.player_role,
                    lt.team_name,
                    COUNT(mp.scorer) as goals_scored,
                    COUNT(DISTINCT mp.match_id) as matches_with_goals
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN matches_points mp ON mp.scorer = lp.player_id
                LEFT JOIN matches m ON mp.match_id = m.match_id
                WHERE lp.league_id = ? AND m.league_id = ?
                GROUP BY lp.player_id, lp.player_name, lp.player_role, lt.team_name
                HAVING goals_scored > 0
                ORDER BY goals_scored DESC, matches_with_goals ASC
                LIMIT 20
            ");
            $stmt->execute([$_GET['league_id'], $_GET['league_id']]);
            $scorers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($scorers);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_top_assisters' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.player_id,
                    lp.player_name,
                    lp.player_role,
                    lt.team_name,
                    COUNT(mp.assister) as assists_made,
                    COUNT(DISTINCT mp.match_id) as matches_with_assists
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN matches_points mp ON mp.assister = lp.player_id
                LEFT JOIN matches m ON mp.match_id = m.match_id
                WHERE lp.league_id = ? AND m.league_id = ?
                GROUP BY lp.player_id, lp.player_name, lp.player_role, lt.team_name
                HAVING assists_made > 0
                ORDER BY assists_made DESC, matches_with_assists ASC
                LIMIT 20
            ");
            $stmt->execute([$_GET['league_id'], $_GET['league_id']]);
            $assisters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($assisters);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_disciplinary' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.player_id,
                    lp.player_name,
                    lp.player_role,
                    lt.team_name,
                    SUM(mp.yellow_card) as yellow_cards,
                    SUM(mp.red_card) as red_cards,
                    (SUM(mp.yellow_card) + (SUM(mp.red_card) * 3)) as discipline_score
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN matches_points mp ON (mp.minus = lp.player_id)
                LEFT JOIN matches m ON mp.match_id = m.match_id
                WHERE lp.league_id = ? AND m.league_id = ?
                GROUP BY lp.player_id, lp.player_name, lp.player_role, lt.team_name
                HAVING (yellow_cards > 0 OR red_cards > 0)
                ORDER BY discipline_score DESC, red_cards DESC, yellow_cards DESC
                LIMIT 20
            ");
            $stmt->execute([$_GET['league_id'], $_GET['league_id']]);
            $disciplinary = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($disciplinary);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
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
            l.round,
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
    
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e9ecef;
        overflow-x: auto;
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
        white-space: nowrap;
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
    
    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 14px;
    }
    
    .rank-1 {
        background: linear-gradient(135deg, #FFD700, #FFA500);
        color: #FFFFFF;
        box-shadow: 0 2px 8px rgba(255, 215, 0, 0.4);
    }
    
    .rank-2 {
        background: linear-gradient(135deg, #C0C0C0, #808080);
        color: #FFFFFF;
        box-shadow: 0 2px 8px rgba(192, 192, 192, 0.4);
    }
    
    .rank-3 {
        background: linear-gradient(135deg, #CD7F32, #8B4513);
        color: #FFFFFF;
        box-shadow: 0 2px 8px rgba(205, 127, 50, 0.4);
    }
    
    .rank-default {
        background: #f8f9fa;
        color: #666;
        border: 2px solid #e9ecef;
    }
    
    .score-highlight {
        font-size: 20px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-mini-card {
        background: #FFFFFF;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid #1D60AC;
    }
    
    .stat-mini-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .stat-mini-value {
        font-size: 24px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .role-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .role-gk {
        background: #fff3cd;
        color: #856404;
    }
    
    .role-def {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .role-mid {
        background: #d4edda;
        color: #155724;
    }
    
    .role-att {
        background: #f8d7da;
        color: #721c24;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }
    
    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    
    .empty-state-title {
        font-size: 20px;
        font-weight: 600;
        color: #666;
        margin-bottom: 10px;
    }
    
    .empty-state-text {
        font-size: 14px;
        color: #999;
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
        
        .tabs {
            overflow-x: auto;
        }
        
        .header-meta {
            flex-direction: column;
            gap: 5px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">🥇 Leaderboards</h1>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('league-selection')">🏆 Select League</button>
        <button class="tab tab-disabled" id="contributorsTab" disabled>👥 Contributors</button>
        <button class="tab tab-disabled" id="teamsTab" disabled>🎯 Teams</button>
        <button class="tab tab-disabled" id="scorersTab" disabled>⚽ Top Scorers</button>
        <button class="tab tab-disabled" id="assistersTab" disabled>🎯 Top Assisters</button>
        <button class="tab tab-disabled" id="disciplinaryTab" disabled>🟨 Disciplinary</button>
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
                            <th>Players</th>
                            <th>Teams</th>
                            <th>Round</th>
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
                                    <td><?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?><?php echo $league['other_owner_name'] ? ' & ' . htmlspecialchars($league['other_owner_name']) : ''; ?></td>
                                    <td><?php echo $league['num_of_players']; ?></td>
                                    <td><?php echo $league['num_of_teams']; ?></td>
                                    <td><span class="badge badge-info">Round <?php echo $league['round']; ?></span></td>
                                    <td>
                                        <?php if ($league['league_activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="selectLeague(<?php echo $league['league_id']; ?>)">
                                            🥇 View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #999; padding: 30px;">No leagues found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Contributors Leaderboard Tab -->
    <div id="contributors-leaderboard" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="contributorsLeagueName">Contributors Leaderboard</div>
                    <div class="header-meta" id="contributorsLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">👥 About Contributors Leaderboard</div>
                <div class="info-card-text">
                    This leaderboard shows the ranking of all contributors (players) in the selected league based on their total scores. Contributors with Admin role have additional privileges in league management.
                </div>
            </div>
            
            <div id="contributorsStats" class="stats-grid"></div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Total Score</th>
                        </tr>
                    </thead>
                    <tbody id="contributorsTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999; padding: 30px;">Select a league to view contributors</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Teams Leaderboard Tab -->
    <div id="teams-leaderboard" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="teamsLeagueName">Teams Leaderboard</div>
                    <div class="header-meta" id="teamsLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">🎯 About Teams Leaderboard</div>
                <div class="info-card-text">
                    This leaderboard displays team standings based on their total scores. Team statistics include matches played, wins, draws, and losses calculated from match results.
                </div>
            </div>
            
            <div id="teamsStats" class="stats-grid"></div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Team Name</th>
                            <th>Matches Played</th>
                            <th>W</th>
                            <th>D</th>
                            <th>L</th>
                            <th>Total Score</th>
                        </tr>
                    </thead>
                    <tbody id="teamsTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999; padding: 30px;">Select a league to view teams</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Top Scorers Tab -->
    <div id="scorers-leaderboard" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="scorersLeagueName">Top Scorers</div>
                    <div class="header-meta" id="scorersLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">⚽ About Top Scorers</div>
                <div class="info-card-text">
                    This leaderboard ranks players based on the number of goals they've scored in matches. Only players who have scored at least one goal are displayed. The ranking prioritizes total goals, with matches played as a tiebreaker.
                </div>
            </div>
            
            <div id="scorersStats" class="stats-grid"></div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player Name</th>
                            <th>Team</th>
                            <th>Position</th>
                            <th>Goals Scored</th>
                            <th>Matches with Goals</th>
                        </tr>
                    </thead>
                    <tbody id="scorersTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 30px;">Select a league to view top scorers</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Top Assisters Tab -->
    <div id="assisters-leaderboard" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="assistersLeagueName">Top Assisters</div>
                    <div class="header-meta" id="assistersLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">🎯 About Top Assisters</div>
                <div class="info-card-text">
                    This leaderboard highlights players who have provided the most assists in matches. Only players with at least one assist are shown. Rankings are based on total assists with matches played as a secondary factor.
                </div>
            </div>
            
            <div id="assistersStats" class="stats-grid"></div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player Name</th>
                            <th>Team</th>
                            <th>Position</th>
                            <th>Assists Made</th>
                            <th>Matches with Assists</th>
                        </tr>
                    </thead>
                    <tbody id="assistersTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 30px;">Select a league to view top assisters</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Disciplinary Tab -->
    <div id="disciplinary-leaderboard" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="disciplinaryLeagueName">Disciplinary Records</div>
                    <div class="header-meta" id="disciplinaryLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">🟨 About Disciplinary Records</div>
                <div class="info-card-text">
                    This leaderboard tracks players with disciplinary issues (yellow and red cards). Players are ranked by a discipline score (yellow card = 1 point, red card = 3 points). Only players with at least one card are displayed.
                </div>
            </div>
            
            <div id="disciplinaryStats" class="stats-grid"></div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player Name</th>
                            <th>Team</th>
                            <th>Position</th>
                            <th>Yellow Cards</th>
                            <th>Red Cards</th>
                            <th>Discipline Score</th>
                        </tr>
                    </thead>
                    <tbody id="disciplinaryTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999; padding: 30px;">Select a league to view disciplinary records</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentTab = 'league-selection';
    let leagueData = null;
    
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
        // Load data when switching tabs
        if (currentLeagueId) {
            switch(tabName) {
                case 'contributors-leaderboard':
                    loadContributors(currentLeagueId);
                    break;
                case 'teams-leaderboard':
                    loadTeams(currentLeagueId);
                    break;
                case 'scorers-leaderboard':
                    loadTopScorers(currentLeagueId);
                    break;
                case 'assisters-leaderboard':
                    loadTopAssisters(currentLeagueId);
                    break;
                case 'disciplinary-leaderboard':
                    loadDisciplinary(currentLeagueId);
                    break;
            }
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
        
        // Enable all tabs
        const tabs = ['contributorsTab', 'teamsTab', 'scorersTab', 'assistersTab', 'disciplinaryTab'];
        tabs.forEach(tabId => {
            const tab = document.getElementById(tabId);
            tab.disabled = false;
            tab.classList.remove('tab-disabled');
        });
        
        // Load league info
        fetch('?ajax=get_league_info&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                leagueData = data;
                
                // Switch to contributors tab
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                document.getElementById('contributorsTab').classList.add('active');
                document.getElementById('contributors-leaderboard').classList.add('active');
                currentTab = 'contributors-leaderboard';
                
                loadContributors(leagueId);
            })
            .catch(error => {
                console.error(error);
                alert('Error loading league information');
            });
    }
    
    function updateLeagueHeader(elementPrefix) {
        if (!leagueData) return;
        
        const ownerText = 'Owner: ' + (leagueData.owner_name || 'N/A') + 
            (leagueData.other_owner_name ? ' & ' + leagueData.other_owner_name : '');
        const statusBadge = leagueData.activated == 1 ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-warning">Inactive</span>';
        
        const metaHtml = `
            <span>${ownerText}</span>
            <span>Players: ${leagueData.num_of_players}</span>
            <span>Teams: ${leagueData.num_of_teams}</span>
            <span>Round: ${leagueData.round}</span>
            <span>Status: ${statusBadge}</span>
        `;
        
        document.getElementById(elementPrefix + 'LeagueName').textContent = leagueData.name;
        document.getElementById(elementPrefix + 'LeagueMeta').innerHTML = metaHtml;
    }
    
    function backToSelection() {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelectorAll('.tab')[0].classList.add('active');
        document.getElementById('league-selection').classList.add('active');
        
        // Disable all leaderboard tabs
        const tabs = ['contributorsTab', 'teamsTab', 'scorersTab', 'assistersTab', 'disciplinaryTab'];
        tabs.forEach(tabId => {
            const tab = document.getElementById(tabId);
            tab.disabled = true;
            tab.classList.add('tab-disabled');
        });
        
        currentTab = 'league-selection';
        currentLeagueId = null;
        leagueData = null;
    }
    
    function getRankBadge(rank) {
        if (rank === 1) {
            return '<span class="rank-badge rank-1">🥇</span>';
        } else if (rank === 2) {
            return '<span class="rank-badge rank-2">🥈</span>';
        } else if (rank === 3) {
            return '<span class="rank-badge rank-3">🥉</span>';
        } else {
            return '<span class="rank-badge rank-default">' + rank + '</span>';
        }
    }
    
    function getRoleBadge(role) {
        const roleMap = {
            'GK': 'role-gk',
            'DEF': 'role-def',
            'MID': 'role-mid',
            'ATT': 'role-att'
        };
        const className = roleMap[role] || 'role-gk';
        return '<span class="role-badge ' + className + '">' + role + '</span>';
    }
    
    function loadContributors(leagueId) {
        updateLeagueHeader('contributors');
        
        const tbody = document.getElementById('contributorsTableBody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_contributors&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">👥</div><div class="empty-state-title">No Contributors Found</div><div class="empty-state-text">This league doesn\'t have any contributors yet.</div></div></td></tr>';
                    document.getElementById('contributorsStats').innerHTML = '';
                    return;
                }
                
                // Calculate stats
                const totalContributors = data.length;
                const admins = data.filter(c => c.role === 'Admin').length;
                const totalScore = data.reduce((sum, c) => sum + parseInt(c.total_score || 0), 0);
                const avgScore = totalContributors > 0 ? Math.round(totalScore / totalContributors) : 0;
                
                // Display stats
                document.getElementById('contributorsStats').innerHTML = `
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Contributors</div>
                        <div class="stat-mini-value">${totalContributors}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Admins</div>
                        <div class="stat-mini-value">${admins}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Score</div>
                        <div class="stat-mini-value">${totalScore.toLocaleString()}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Average Score</div>
                        <div class="stat-mini-value">${avgScore.toLocaleString()}</div>
                    </div>
                `;
                
                let html = '';
                data.forEach((contributor, index) => {
                    const rank = index + 1;
                    const roleBadge = contributor.role === 'Admin' ? 
                        '<span class="badge badge-info">Admin</span>' : 
                        '<span class="badge badge-warning">Contributor</span>';
                    
                    html += `
                        <tr>
                            <td>${getRankBadge(rank)}</td>
                            <td><strong>${contributor.username || 'N/A'}</strong></td>
                            <td>${contributor.email || 'N/A'}</td>
                            <td>${roleBadge}</td>
                            <td><span class="score-highlight">${parseInt(contributor.total_score || 0).toLocaleString()}</span></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc3545;">Error loading contributors</td></tr>';
            });
    }
    
    function loadTeams(leagueId) {
        updateLeagueHeader('teams');
        
        const tbody = document.getElementById('teamsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_teams&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">🎯</div><div class="empty-state-title">No Teams Found</div><div class="empty-state-text">This league doesn\'t have any teams yet.</div></div></td></tr>';
                    document.getElementById('teamsStats').innerHTML = '';
                    return;
                }
                
                // Calculate stats
                const totalTeams = data.length;
                const totalMatches = data.reduce((sum, t) => sum + parseInt(t.matches_played || 0), 0);
                const totalScore = data.reduce((sum, t) => sum + parseInt(t.team_score || 0), 0);
                const avgScore = totalTeams > 0 ? Math.round(totalScore / totalTeams) : 0;
                
                // Display stats
                document.getElementById('teamsStats').innerHTML = `
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Teams</div>
                        <div class="stat-mini-value">${totalTeams}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Matches</div>
                        <div class="stat-mini-value">${totalMatches}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Score</div>
                        <div class="stat-mini-value">${totalScore.toLocaleString()}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Average Score</div>
                        <div class="stat-mini-value">${avgScore.toLocaleString()}</div>
                    </div>
                `;
                
                let html = '';
                data.forEach((team, index) => {
                    const rank = index + 1;
                    const wins = parseInt(team.wins || 0);
                    const draws = parseInt(team.draws || 0);
                    const losses = parseInt(team.losses || 0);
                    const matchesPlayed = parseInt(team.matches_played || 0);
                    
                    html += `
                        <tr>
                            <td>${getRankBadge(rank)}</td>
                            <td><strong>${team.team_name}</strong></td>
                            <td>${matchesPlayed}</td>
                            <td><span class="badge badge-success">${wins}</span></td>
                            <td><span class="badge badge-info">${draws}</span></td>
                            <td><span class="badge badge-danger">${losses}</span></td>
                            <td><span class="score-highlight">${parseInt(team.team_score || 0).toLocaleString()}</span></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error loading teams</td></tr>';
            });
    }
    
    function loadTopScorers(leagueId) {
        updateLeagueHeader('scorers');
        
        const tbody = document.getElementById('scorersTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_top_scorers&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">⚽</div><div class="empty-state-title">No Goals Scored Yet</div><div class="empty-state-text">No players have scored goals in this league yet.</div></div></td></tr>';
                    document.getElementById('scorersStats').innerHTML = '';
                    return;
                }
                
                // Calculate stats
                const totalPlayers = data.length;
                const totalGoals = data.reduce((sum, p) => sum + parseInt(p.goals_scored || 0), 0);
                const topScorer = data[0];
                const avgGoals = totalPlayers > 0 ? (totalGoals / totalPlayers).toFixed(1) : 0;
                
                // Display stats
                document.getElementById('scorersStats').innerHTML = `
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Goals</div>
                        <div class="stat-mini-value">${totalGoals}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Top Scorer</div>
                        <div class="stat-mini-value" style="font-size: 16px;">${topScorer.player_name}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Top Scorer Goals</div>
                        <div class="stat-mini-value">${topScorer.goals_scored}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Avg Goals/Player</div>
                        <div class="stat-mini-value">${avgGoals}</div>
                    </div>
                `;
                
                let html = '';
                data.forEach((player, index) => {
                    const rank = index + 1;
                    const goals = parseInt(player.goals_scored || 0);
                    const matches = parseInt(player.matches_with_goals || 0);
                    
                    html += `
                        <tr>
                            <td>${getRankBadge(rank)}</td>
                            <td><strong>${player.player_name}</strong></td>
                            <td>${player.team_name || 'N/A'}</td>
                            <td>${getRoleBadge(player.player_role)}</td>
                            <td><span class="score-highlight">${goals}</span></td>
                            <td>${matches}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error loading scorers</td></tr>';
            });
    }
    
    function loadTopAssisters(leagueId) {
        updateLeagueHeader('assisters');
        
        const tbody = document.getElementById('assistersTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_top_assisters&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🎯</div><div class="empty-state-title">No Assists Recorded Yet</div><div class="empty-state-text">No players have made assists in this league yet.</div></div></td></tr>';
                    document.getElementById('assistersStats').innerHTML = '';
                    return;
                }
                
                // Calculate stats
                const totalPlayers = data.length;
                const totalAssists = data.reduce((sum, p) => sum + parseInt(p.assists_made || 0), 0);
                const topAssister = data[0];
                const avgAssists = totalPlayers > 0 ? (totalAssists / totalPlayers).toFixed(1) : 0;
                
                // Display stats
                document.getElementById('assistersStats').innerHTML = `
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Total Assists</div>
                        <div class="stat-mini-value">${totalAssists}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Top Assister</div>
                        <div class="stat-mini-value" style="font-size: 16px;">${topAssister.player_name}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Top Assister Count</div>
                        <div class="stat-mini-value">${topAssister.assists_made}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Avg Assists/Player</div>
                        <div class="stat-mini-value">${avgAssists}</div>
                    </div>
                `;
                
                let html = '';
                data.forEach((player, index) => {
                    const rank = index + 1;
                    const assists = parseInt(player.assists_made || 0);
                    const matches = parseInt(player.matches_with_assists || 0);
                    
                    html += `
                        <tr>
                            <td>${getRankBadge(rank)}</td>
                            <td><strong>${player.player_name}</strong></td>
                            <td>${player.team_name || 'N/A'}</td>
                            <td>${getRoleBadge(player.player_role)}</td>
                            <td><span class="score-highlight">${assists}</span></td>
                            <td>${matches}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error loading assisters</td></tr>';
            });
    }
    
    function loadDisciplinary(leagueId) {
        updateLeagueHeader('disciplinary');
        
        const tbody = document.getElementById('disciplinaryTableBody');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_disciplinary&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">✅</div><div class="empty-state-title">Clean Record!</div><div class="empty-state-text">No disciplinary actions have been recorded in this league yet.</div></div></td></tr>';
                    document.getElementById('disciplinaryStats').innerHTML = '';
                    return;
                }
                
                // Calculate stats
                const totalPlayers = data.length;
                const totalYellow = data.reduce((sum, p) => sum + parseInt(p.yellow_cards || 0), 0);
                const totalRed = data.reduce((sum, p) => sum + parseInt(p.red_cards || 0), 0);
                const worstOffender = data[0];
                
                // Display stats
                document.getElementById('disciplinaryStats').innerHTML = `
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Yellow Cards</div>
                        <div class="stat-mini-value">${totalYellow}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Red Cards</div>
                        <div class="stat-mini-value">${totalRed}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Players with Cards</div>
                        <div class="stat-mini-value">${totalPlayers}</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label">Most Disciplined Against</div>
                        <div class="stat-mini-value" style="font-size: 16px;">${worstOffender.player_name}</div>
                    </div>
                `;
                
                let html = '';
                data.forEach((player, index) => {
                    const rank = index + 1;
                    const yellowCards = parseInt(player.yellow_cards || 0);
                    const redCards = parseInt(player.red_cards || 0);
                    const disciplineScore = parseInt(player.discipline_score || 0);
                    
                    html += `
                        <tr>
                            <td>${getRankBadge(rank)}</td>
                            <td><strong>${player.player_name}</strong></td>
                            <td>${player.team_name || 'N/A'}</td>
                            <td>${getRoleBadge(player.player_role)}</td>
                            <td><span class="badge badge-warning">🟨 ${yellowCards}</span></td>
                            <td><span class="badge badge-danger">🟥 ${redCards}</span></td>
                            <td><span class="score-highlight">${disciplineScore}</span></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #dc3545;">Error loading disciplinary records</td></tr>';
            });
    }
</script>

<?php include 'includes/footer.php'; ?>