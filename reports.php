<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_report' && isset($_GET['league_id'])) {
        try {
            $league_id = $_GET['league_id'];
            
            // Get league details
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
            $stmt->execute([$league_id]);
            $league = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$league) {
                echo json_encode(['error' => 'League not found']);
                exit();
            }
            
            // Get contributors with scores
            $stmt = $pdo->prepare("
                SELECT 
                    lc.*,
                    a.username,
                    a.email
                FROM league_contributors lc
                LEFT JOIN accounts a ON lc.user_id = a.id
                WHERE lc.league_id = ?
                ORDER BY lc.total_score DESC
            ");
            $stmt->execute([$league_id]);
            $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get teams with scores
            $stmt = $pdo->prepare("
                SELECT * FROM league_teams
                WHERE league_id = ?
                ORDER BY team_score DESC
            ");
            $stmt->execute([$league_id]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total matches
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_matches
                FROM matches
                WHERE league_id = ?
            ");
            $stmt->execute([$league_id]);
            $match_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get players count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total_players
                FROM league_players
                WHERE league_id = ?
            ");
            $stmt->execute([$league_id]);
            $player_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get top scorers
            $stmt = $pdo->prepare("
                SELECT 
                    lp.player_name,
                    lp.player_role,
                    lt.team_name,
                    COUNT(mp.scorer) as goals
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN matches_points mp ON lp.player_id = mp.scorer
                LEFT JOIN matches m ON mp.match_id = m.match_id
                WHERE lp.league_id = ? AND m.league_id = ?
                GROUP BY lp.player_id, lp.player_name, lp.player_role, lt.team_name
                HAVING goals > 0
                ORDER BY goals DESC
                LIMIT 10
            ");
            $stmt->execute([$league_id, $league_id]);
            $top_scorers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top assisters
            $stmt = $pdo->prepare("
                SELECT 
                    lp.player_name,
                    lp.player_role,
                    lt.team_name,
                    COUNT(mp.assister) as assists
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN matches_points mp ON lp.player_id = mp.assister
                LEFT JOIN matches m ON mp.match_id = m.match_id
                WHERE lp.league_id = ? AND m.league_id = ?
                GROUP BY lp.player_id, lp.player_name, lp.player_role, lt.team_name
                HAVING assists > 0
                ORDER BY assists DESC
                LIMIT 10
            ");
            $stmt->execute([$league_id, $league_id]);
            $top_assisters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get top players by total points
            $stmt = $pdo->prepare("
                SELECT 
                    lp.player_name,
                    lp.player_role,
                    lp.total_points,
                    lt.team_name
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.league_id = ?
                ORDER BY lp.total_points DESC
                LIMIT 10
            ");
            $stmt->execute([$league_id]);
            $top_players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get league roles configuration
            $stmt = $pdo->prepare("
                SELECT * FROM league_roles
                WHERE league_id = ?
            ");
            $stmt->execute([$league_id]);
            $league_roles = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'league' => $league,
                'contributors' => $contributors,
                'teams' => $teams,
                'match_stats' => $match_stats,
                'player_stats' => $player_stats,
                'top_scorers' => $top_scorers,
                'top_assisters' => $top_assisters,
                'top_players' => $top_players,
                'league_roles' => $league_roles
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_overall_stats') {
        try {
            // Total statistics
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM accounts");
            $total_accounts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM leagues");
            $total_leagues = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM leagues WHERE activated = 1");
            $active_leagues = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM league_players");
            $total_players = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM league_teams");
            $total_teams = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM matches");
            $total_matches = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Top leagues by players
            $stmt = $pdo->query("
                SELECT 
                    l.name,
                    l.num_of_players,
                    l.num_of_teams,
                    a.username as owner_name
                FROM leagues l
                LEFT JOIN accounts a ON l.owner = a.id
                ORDER BY l.num_of_players DESC
                LIMIT 5
            ");
            $top_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent activity
            $stmt = $pdo->query("
                SELECT 
                    l.name as league_name,
                    l.created_at,
                    a.username as owner_name
                FROM leagues l
                LEFT JOIN accounts a ON l.owner = a.id
                ORDER BY l.created_at DESC
                LIMIT 10
            ");
            $recent_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'total_accounts' => $total_accounts,
                'total_leagues' => $total_leagues,
                'active_leagues' => $active_leagues,
                'total_players' => $total_players,
                'total_teams' => $total_teams,
                'total_matches' => $total_matches,
                'top_leagues' => $top_leagues,
                'recent_leagues' => $recent_leagues
            ]);
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
    
    .btn-secondary {
        background: #F1A155;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(241, 161, 85, 0.3);
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
    
    .badge-primary {
        background: rgba(29, 96, 172, 0.2);
        color: #1D60AC;
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-left: 4px solid #1D60AC;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .stat-card-title {
        font-size: 12px;
        color: #666;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
    }
    
    .stat-card-value {
        font-size: 32px;
        font-weight: 700;
        color: #000000;
    }
    
    .report-section {
        margin-bottom: 30px;
    }
    
    .report-section-title {
        font-size: 20px;
        font-weight: 700;
        color: #333;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .report-section-title::before {
        content: '';
        width: 4px;
        height: 24px;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        border-radius: 2px;
    }
    
    .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
    }
    
    .grid-3 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .ranking-item {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
    }
    
    .ranking-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }
    
    .ranking-position {
        width: 35px;
        height: 35px;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .ranking-position.gold {
        background: linear-gradient(135deg, #FFD700, #FFA500);
    }
    
    .ranking-position.silver {
        background: linear-gradient(135deg, #C0C0C0, #A8A8A8);
    }
    
    .ranking-position.bronze {
        background: linear-gradient(135deg, #CD7F32, #B8733C);
    }
    
    .ranking-info {
        flex: 1;
    }
    
    .ranking-name {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }
    
    .ranking-meta {
        font-size: 12px;
        color: #999;
        margin-top: 2px;
    }
    
    .ranking-value {
        font-size: 18px;
        font-weight: 700;
        color: #1D60AC;
        flex-shrink: 0;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .no-data-message {
        text-align: center;
        padding: 40px;
        color: #999;
        font-size: 14px;
    }
    
    .roles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .role-config-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #1D60AC;
    }
    
    .role-config-title {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .role-config-value {
        font-size: 18px;
        font-weight: 700;
        color: #1D60AC;
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
        
        .grid-2, .grid-3 {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">📊 Reports & Statistics</h1>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('overall-stats')">📈 Overall Statistics</button>
        <button class="tab" onclick="switchTab('league-selection')">🏆 League Reports</button>
        <button class="tab tab-disabled" id="leagueReportTab" disabled>📋 League Analysis</button>
    </div>
    
    <!-- Overall Statistics Tab -->
    <div id="overall-stats" class="tab-content active">
        <div class="info-card">
            <div class="info-card-title">📊 Platform Overview</div>
            <div class="info-card-text">
                View comprehensive statistics across all leagues, accounts, and activities on the Fantaziko platform. This dashboard provides insights into overall platform performance and usage.
            </div>
        </div>
        
        <div class="stats-grid" id="overallStatsGrid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Accounts</span>
                    <div class="stat-card-icon">👥</div>
                </div>
                <div class="stat-card-value">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Leagues</span>
                    <div class="stat-card-icon">🏆</div>
                </div>
                <div class="stat-card-value">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Active Leagues</span>
                    <div class="stat-card-icon">✔</div>
                </div>
                <div class="stat-card-value">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Players</span>
                    <div class="stat-card-icon">⚽</div>
                </div>
                <div class="stat-card-value">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Teams</span>
                    <div class="stat-card-icon">🎯</div>
                </div>
                <div class="stat-card-value">-</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Matches</span>
                    <div class="stat-card-icon">📊</div>
                </div>
                <div class="stat-card-value">-</div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="data-card">
                <div class="data-card-header">
                    <span>🏆 Top Leagues by Players</span>
                </div>
                <div style="padding: 20px;" id="topLeaguesContainer">
                    <div class="no-data-message">Loading...</div>
                </div>
            </div>
            
            <div class="data-card">
                <div class="data-card-header">
                    <span>🕒 Recent Activity</span>
                </div>
                <div style="padding: 20px;" id="recentActivityContainer">
                    <div class="no-data-message">Loading...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- League Selection Tab -->
    <div id="league-selection" class="tab-content">
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
                            <th>Round</th>
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
                                    <td><span class="badge badge-primary">Round <?php echo $league['round']; ?></span></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($league['system']); ?></span></td>
                                    <td>
                                        <?php if ($league['league_activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="generateLeagueReport(<?php echo $league['league_id']; ?>)">
                                            📋 View Report
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #999; padding: 30px;">No leagues found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- League Report Tab -->
    <div id="league-report" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="leagueReportName">Select a league to view report</div>
                    <div class="header-meta" id="leagueReportMeta"></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="exportLeagueReport()" id="exportBtn" style="display: none;">
                        📥 Export Report
                    </button>
                    <button class="back-btn" onclick="backToSelection()">
                        ← Back to Selection
                    </button>
                </div>
            </div>
            
            <div style="padding: 25px;" id="leagueReportContent">
                <div class="no-data-message">Select a league to view detailed report</div>
            </div>
        </div>
    </div>
</div>

<script>
let currentLeagueId = null;
let currentTab = 'overall-stats';
let currentReportData = null;

// Load overall stats on page load
document.addEventListener('DOMContentLoaded', function() {
    loadOverallStats();
});

function switchTab(tabName) {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => tab.classList.remove('active'));
    contents.forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tabName).classList.add('active');
    
    currentTab = tabName;
}

function loadOverallStats() {
    fetch('?ajax=get_overall_stats')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error:', data.error);
                return;
            }
            
            // Update stat cards
            const statCards = document.querySelectorAll('#overallStatsGrid .stat-card-value');
            statCards[0].textContent = data.total_accounts.toLocaleString();
            statCards[1].textContent = data.total_leagues.toLocaleString();
            statCards[2].textContent = data.active_leagues.toLocaleString();
            statCards[3].textContent = data.total_players.toLocaleString();
            statCards[4].textContent = data.total_teams.toLocaleString();
            statCards[5].textContent = data.total_matches.toLocaleString();
            
            // Update top leagues
            const topLeaguesContainer = document.getElementById('topLeaguesContainer');
            if (data.top_leagues.length === 0) {
                topLeaguesContainer.innerHTML = '<div class="no-data-message">No leagues data available</div>';
            } else {
                let html = '';
                data.top_leagues.forEach((league, index) => {
                    let positionClass = '';
                    if (index === 0) positionClass = 'gold';
                    else if (index === 1) positionClass = 'silver';
                    else if (index === 2) positionClass = 'bronze';
                    
                    html += `
                        <div class="ranking-item">
                            <div class="ranking-position ${positionClass}">${index + 1}</div>
                            <div class="ranking-info">
                                <div class="ranking-name">${league.name}</div>
                                <div class="ranking-meta">Owner: ${league.owner_name || 'N/A'}</div>
                            </div>
                            <div class="ranking-value">${league.num_of_players} <small style="font-size: 12px; color: #999;">players</small></div>
                        </div>
                    `;
                });
                topLeaguesContainer.innerHTML = html;
            }
            
            // Update recent activity
            const recentActivityContainer = document.getElementById('recentActivityContainer');
            if (data.recent_leagues.length === 0) {
                recentActivityContainer.innerHTML = '<div class="no-data-message">No recent activity</div>';
            } else {
                let html = '';
                data.recent_leagues.forEach(league => {
                    const date = new Date(league.created_at);
                    const formattedDate = date.toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    html += `
                        <div class="ranking-item">
                            <div class="ranking-info">
                                <div class="ranking-name">${league.league_name}</div>
                                <div class="ranking-meta">Created by ${league.owner_name || 'N/A'} • ${formattedDate}</div>
                            </div>
                        </div>
                    `;
                });
                recentActivityContainer.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading overall stats:', error);
        });
}

function searchLeagues() {
    const search = document.getElementById('leaguesSearch').value;
    let url = window.location.pathname + '?';
    
    if (search) {
        url += 'leagues_search=' + encodeURIComponent(search);
    }
    
    window.location.href = url;
}

function generateLeagueReport(leagueId) {
    currentLeagueId = leagueId;
    
    // Enable the league report tab
    const reportTab = document.getElementById('leagueReportTab');
    reportTab.disabled = false;
    reportTab.classList.remove('tab-disabled');
    reportTab.onclick = function() { switchTab('league-report'); };
    
    // Switch to league report tab
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    reportTab.classList.add('active');
    document.getElementById('league-report').classList.add('active');
    currentTab = 'league-report';
    
    // Show loading
    document.getElementById('leagueReportContent').innerHTML = '<div class="no-data-message">Loading report...</div>';
    
    // Load league report
    fetch('?ajax=get_league_report&league_id=' + leagueId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('leagueReportContent').innerHTML = '<div class="no-data-message" style="color: #dc3545;">Error: ' + data.error + '</div>';
                return;
            }
            
            currentReportData = data;
            displayLeagueReport(data);
            
            // Show export button
            document.getElementById('exportBtn').style.display = 'inline-flex';
        })
        .catch(error => {
            console.error('Error loading league report:', error);
            document.getElementById('leagueReportContent').innerHTML = '<div class="no-data-message" style="color: #dc3545;">Error loading report</div>';
        });
}

function displayLeagueReport(data) {
    const league = data.league;
    
    // Update header
    const ownerText = 'Owner: ' + (league.owner_name || 'N/A') + 
        (league.other_owner_name ? ' & ' + league.other_owner_name : '');
    const statusBadge = league.activated == 1 ? 
        '<span class="badge badge-success">Active</span>' : 
        '<span class="badge badge-warning">Inactive</span>';
    
    const metaHtml = `
        <span>${ownerText}</span>
        <span>Round: ${league.round}</span>
        <span>System: ${league.system}</span>
        <span>Price: ${parseFloat(league.price).toFixed(2)}</span>
        <span>Status: ${statusBadge}</span>
    `;
    
    document.getElementById('leagueReportName').textContent = league.name;
    document.getElementById('leagueReportMeta').innerHTML = metaHtml;
    
    // Build report content
    let html = '';
    
    // League Statistics
    html += `
        <div class="report-section">
            <div class="report-section-title">📊 League Statistics</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Players</span>
                        <div class="stat-card-icon">⚽</div>
                    </div>
                    <div class="stat-card-value">${data.player_stats.total_players}</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Teams</span>
                        <div class="stat-card-icon">🎯</div>
                    </div>
                    <div class="stat-card-value">${league.num_of_teams}</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Matches</span>
                        <div class="stat-card-icon">📊</div>
                    </div>
                    <div class="stat-card-value">${data.match_stats.total_matches}</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Contributors</span>
                        <div class="stat-card-icon">👥</div>
                    </div>
                    <div class="stat-card-value">${data.contributors.length}</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Current Round</span>
                        <div class="stat-card-icon">🔄</div>
                    </div>
                    <div class="stat-card-value">${league.round}</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">League Price</span>
                        <div class="stat-card-icon">💰</div>
                    </div>
                    <div class="stat-card-value">${parseFloat(league.price).toFixed(2)}</div>
                </div>
            </div>
        </div>
    `;
    
    // Contributors Leaderboard
    html += `
        <div class="report-section">
            <div class="report-section-title">🏆 Contributors Leaderboard</div>
            <div class="data-card">
    `;
    
    if (data.contributors.length === 0) {
        html += '<div class="no-data-message">No contributors found</div>';
    } else {
        html += `
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
                    <tbody>
        `;
        
        data.contributors.forEach((contributor, index) => {
            let rankBadge = '';
            if (index === 0) rankBadge = '🥇';
            else if (index === 1) rankBadge = '🥈';
            else if (index === 2) rankBadge = '🥉';
            else rankBadge = (index + 1);
            
            const roleBadge = contributor.role === 'Admin' ? 
                '<span class="badge badge-info">Admin</span>' : 
                '<span class="badge badge-primary">Contributor</span>';
            
            html += `
                <tr>
                    <td><strong style="font-size: 16px;">${rankBadge}</strong></td>
                    <td><strong>${contributor.username || 'N/A'}</strong></td>
                    <td>${contributor.email || 'N/A'}</td>
                    <td>${roleBadge}</td>
                    <td><strong style="color: #1D60AC; font-size: 16px;">${contributor.total_score}</strong></td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    html += '</div></div>';
    
    // Teams Standings
    html += `
        <div class="report-section">
            <div class="report-section-title">🎯 Teams Standings</div>
            <div class="data-card">
    `;
    
    if (data.teams.length === 0) {
        html += '<div class="no-data-message">No teams found</div>';
    } else {
        html += `
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Team Name</th>
                            <th>Team Score</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.teams.forEach((team, index) => {
            let positionBadge = '';
            if (index === 0) positionBadge = '🥇';
            else if (index === 1) positionBadge = '🥈';
            else if (index === 2) positionBadge = '🥉';
            else positionBadge = (index + 1);
            
            html += `
                <tr>
                    <td><strong style="font-size: 16px;">${positionBadge}</strong></td>
                    <td><strong>${team.team_name}</strong></td>
                    <td><strong style="color: #1D60AC; font-size: 16px;">${team.team_score}</strong></td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    html += '</div></div>';
    
    // Top Performers
    html += `
        <div class="report-section">
            <div class="report-section-title">⭐ Top Performers</div>
            <div class="grid-3">
    `;
    
    // Top Players by Points
    html += `
        <div class="data-card">
            <div class="data-card-header">
                <span>🌟 Top Players</span>
            </div>
            <div style="padding: 20px;">
    `;
    
    if (data.top_players.length === 0) {
        html += '<div class="no-data-message">No player data available</div>';
    } else {
        data.top_players.forEach((player, index) => {
            let positionClass = '';
            if (index === 0) positionClass = 'gold';
            else if (index === 1) positionClass = 'silver';
            else if (index === 2) positionClass = 'bronze';
            
            html += `
                <div class="ranking-item">
                    <div class="ranking-position ${positionClass}">${index + 1}</div>
                    <div class="ranking-info">
                        <div class="ranking-name">${player.player_name}</div>
                        <div class="ranking-meta">${player.team_name || 'No Team'} • ${player.player_role}</div>
                    </div>
                    <div class="ranking-value">${player.total_points} <small style="font-size: 12px; color: #999;">pts</small></div>
                </div>
            `;
        });
    }
    
    html += '</div></div>';
    
    // Top Scorers
    html += `
        <div class="data-card">
            <div class="data-card-header">
                <span>⚽ Top Scorers</span>
            </div>
            <div style="padding: 20px;">
    `;
    
    if (data.top_scorers.length === 0) {
        html += '<div class="no-data-message">No scoring data available</div>';
    } else {
        data.top_scorers.forEach((player, index) => {
            let positionClass = '';
            if (index === 0) positionClass = 'gold';
            else if (index === 1) positionClass = 'silver';
            else if (index === 2) positionClass = 'bronze';
            
            html += `
                <div class="ranking-item">
                    <div class="ranking-position ${positionClass}">${index + 1}</div>
                    <div class="ranking-info">
                        <div class="ranking-name">${player.player_name}</div>
                        <div class="ranking-meta">${player.team_name || 'No Team'} • ${player.player_role}</div>
                    </div>
                    <div class="ranking-value">${player.goals} <small style="font-size: 12px; color: #999;">goals</small></div>
                </div>
            `;
        });
    }
    
    html += '</div></div>';
    
    // Top Assisters
    html += `
        <div class="data-card">
            <div class="data-card-header">
                <span>🎯 Top Assisters</span>
            </div>
            <div style="padding: 20px;">
    `;
    
    if (data.top_assisters.length === 0) {
        html += '<div class="no-data-message">No assist data available</div>';
    } else {
        data.top_assisters.forEach((player, index) => {
            let positionClass = '';
            if (index === 0) positionClass = 'gold';
            else if (index === 1) positionClass = 'silver';
            else if (index === 2) positionClass = 'bronze';
            
            html += `
                <div class="ranking-item">
                    <div class="ranking-position ${positionClass}">${index + 1}</div>
                    <div class="ranking-info">
                        <div class="ranking-name">${player.player_name}</div>
                        <div class="ranking-meta">${player.team_name || 'No Team'} • ${player.player_role}</div>
                    </div>
                    <div class="ranking-value">${player.assists} <small style="font-size: 12px; color: #999;">assists</small></div>
                </div>
            `;
        });
    }
    
    html += '</div></div>';
    html += '</div></div>';
    
    // League Configuration
    html += `
        <div class="report-section">
            <div class="report-section-title">⚙️ League Configuration</div>
            <div class="data-card">
                <div style="padding: 25px;">
                    <div class="stats-grid">
                        <div class="info-card" style="margin: 0;">
                            <div class="info-card-title">System Type</div>
                            <div class="info-card-text"><strong>${league.system}</strong></div>
                        </div>
                        
                        <div class="info-card" style="margin: 0;">
                            <div class="info-card-title">Triple Captain</div>
                            <div class="info-card-text"><strong>${league.triple_captain}</strong> available</div>
                        </div>
                        
                        <div class="info-card" style="margin: 0;">
                            <div class="info-card-title">Bench Boost</div>
                            <div class="info-card-text"><strong>${league.bench_boost}</strong> available</div>
                        </div>
                        
                        <div class="info-card" style="margin: 0;">
                            <div class="info-card-title">Wild Card</div>
                            <div class="info-card-text"><strong>${league.wild_card}</strong> available</div>
                        </div>
                        
                        <div class="info-card" style="margin: 0;">
                            <div class="info-card-title">League Price</div>
                            <div class="info-card-text"><strong>${parseFloat(league.price).toFixed(2)}</strong></div>
                        </div>
                        
                        <div class="info-card" style="margin: 0;">
                            <div class="info-card-title">Created</div>
                            <div class="info-card-text"><strong>${new Date(league.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // League Roles Points Configuration
    if (data.league_roles) {
        html += `
            <div class="report-section">
                <div class="report-section-title">🎮 Scoring System Configuration</div>
                <div class="data-card">
                    <div style="padding: 25px;">
                        <div class="roles-grid">
                            <div class="role-config-item">
                                <div class="role-config-title">GK Save Penalty</div>
                                <div class="role-config-value">${data.league_roles.gk_save_penalty} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">GK Score</div>
                                <div class="role-config-value">${data.league_roles.gk_score} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">GK Assist</div>
                                <div class="role-config-value">${data.league_roles.gk_assist} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">GK Clean Sheet</div>
                                <div class="role-config-value">${data.league_roles.gk_clean_sheet} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">DEF Clean Sheet</div>
                                <div class="role-config-value">${data.league_roles.def_clean_sheet} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">DEF Assist</div>
                                <div class="role-config-value">${data.league_roles.def_assist} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">DEF Score</div>
                                <div class="role-config-value">${data.league_roles.def_score} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">MID Assist</div>
                                <div class="role-config-value">${data.league_roles.mid_assist} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">MID Score</div>
                                <div class="role-config-value">${data.league_roles.mid_score} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">FOR Score</div>
                                <div class="role-config-value">${data.league_roles.for_score} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">FOR Assist</div>
                                <div class="role-config-value">${data.league_roles.for_assist} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">Miss Penalty</div>
                                <div class="role-config-value">${data.league_roles.miss_penalty} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">Yellow Card</div>
                                <div class="role-config-value">${data.league_roles.yellow_card} pts</div>
                            </div>
                            <div class="role-config-item">
                                <div class="role-config-title">Red Card</div>
                                <div class="role-config-value">${data.league_roles.red_card} pts</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    document.getElementById('leagueReportContent').innerHTML = html;
}

function backToSelection() {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelectorAll('.tab')[1].classList.add('active');
    document.getElementById('league-selection').classList.add('active');
    
    // Disable the report tab
    const reportTab = document.getElementById('leagueReportTab');
    reportTab.disabled = true;
    reportTab.classList.add('tab-disabled');
    reportTab.onclick = null;
    
    // Hide export button
    document.getElementById('exportBtn').style.display = 'none';
    
    currentTab = 'league-selection';
    currentLeagueId = null;
    currentReportData = null;
}

function exportLeagueReport() {
    if (!currentReportData) {
        alert('No report data available to export');
        return;
    }
    
    const league = currentReportData.league;
    let reportText = `FANTAZIKO LEAGUE REPORT\n`;
    reportText += `${'='.repeat(50)}\n\n`;
    reportText += `League: ${league.name}\n`;
    reportText += `Owner: ${league.owner_name || 'N/A'}`;
    if (league.other_owner_name) {
        reportText += ` & ${league.other_owner_name}`;
    }
    reportText += `\n`;
    reportText += `System: ${league.system}\n`;
    reportText += `Round: ${league.round}\n`;
    reportText += `Price: ${parseFloat(league.price).toFixed(2)}\n`;
    reportText += `Status: ${league.activated == 1 ? 'Active' : 'Inactive'}\n`;
    reportText += `Created: ${new Date(league.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}\n\n`;
    
    reportText += `STATISTICS\n`;
    reportText += `${'-'.repeat(50)}\n`;
    reportText += `Total Players: ${currentReportData.player_stats.total_players}\n`;
    reportText += `Total Teams: ${league.num_of_teams}\n`;
    reportText += `Total Matches: ${currentReportData.match_stats.total_matches}\n`;
    reportText += `Contributors: ${currentReportData.contributors.length}\n\n`;
    
    reportText += `CONTRIBUTORS LEADERBOARD\n`;
    reportText += `${'-'.repeat(50)}\n`;
    if (currentReportData.contributors.length > 0) {
        currentReportData.contributors.forEach((contributor, index) => {
            reportText += `${index + 1}. ${contributor.username || 'N/A'} - ${contributor.total_score} points (${contributor.role})\n`;
        });
    } else {
        reportText += `No contributors\n`;
    }
    reportText += `\n`;
    
    reportText += `TEAMS STANDINGS\n`;
    reportText += `${'-'.repeat(50)}\n`;
    if (currentReportData.teams.length > 0) {
        currentReportData.teams.forEach((team, index) => {
            reportText += `${index + 1}. ${team.team_name} - ${team.team_score} points\n`;
        });
    } else {
        reportText += `No teams\n`;
    }
    reportText += `\n`;
    
    reportText += `TOP PLAYERS BY POINTS\n`;
    reportText += `${'-'.repeat(50)}\n`;
    if (currentReportData.top_players.length > 0) {
        currentReportData.top_players.forEach((player, index) => {
            reportText += `${index + 1}. ${player.player_name} (${player.team_name || 'No Team'}) - ${player.total_points} points\n`;
        });
    } else {
        reportText += `No player data\n`;
    }
    reportText += `\n`;
    
    reportText += `TOP SCORERS\n`;
    reportText += `${'-'.repeat(50)}\n`;
    if (currentReportData.top_scorers.length > 0) {
        currentReportData.top_scorers.forEach((player, index) => {
            reportText += `${index + 1}. ${player.player_name} (${player.team_name || 'No Team'}) - ${player.goals} goals\n`;
        });
    } else {
        reportText += `No scoring data\n`;
    }
    reportText += `\n`;
    
    reportText += `TOP ASSISTERS\n`;
    reportText += `${'-'.repeat(50)}\n`;
    if (currentReportData.top_assisters.length > 0) {
        currentReportData.top_assisters.forEach((player, index) => {
            reportText += `${index + 1}. ${player.player_name} (${player.team_name || 'No Team'}) - ${player.assists} assists\n`;
        });
    } else {
        reportText += `No assist data\n`;
    }
    reportText += `\n`;
    
    reportText += `LEAGUE CONFIGURATION\n`;
    reportText += `${'-'.repeat(50)}\n`;
    reportText += `System: ${league.system}\n`;
    reportText += `Triple Captain: ${league.triple_captain}\n`;
    reportText += `Bench Boost: ${league.bench_boost}\n`;
    reportText += `Wild Card: ${league.wild_card}\n\n`;
    
    if (currentReportData.league_roles) {
        reportText += `SCORING SYSTEM CONFIGURATION\n`;
        reportText += `${'-'.repeat(50)}\n`;
        reportText += `GK Save Penalty: ${currentReportData.league_roles.gk_save_penalty} pts\n`;
        reportText += `GK Score: ${currentReportData.league_roles.gk_score} pts\n`;
        reportText += `GK Assist: ${currentReportData.league_roles.gk_assist} pts\n`;
        reportText += `GK Clean Sheet: ${currentReportData.league_roles.gk_clean_sheet} pts\n`;
        reportText += `DEF Clean Sheet: ${currentReportData.league_roles.def_clean_sheet} pts\n`;
        reportText += `DEF Assist: ${currentReportData.league_roles.def_assist} pts\n`;
        reportText += `DEF Score: ${currentReportData.league_roles.def_score} pts\n`;
        reportText += `MID Assist: ${currentReportData.league_roles.mid_assist} pts\n`;
        reportText += `MID Score: ${currentReportData.league_roles.mid_score} pts\n`;
        reportText += `FOR Score: ${currentReportData.league_roles.for_score} pts\n`;
        reportText += `FOR Assist: ${currentReportData.league_roles.for_assist} pts\n`;
        reportText += `Miss Penalty: ${currentReportData.league_roles.miss_penalty} pts\n`;
        reportText += `Yellow Card: ${currentReportData.league_roles.yellow_card} pts\n`;
        reportText += `Red Card: ${currentReportData.league_roles.red_card} pts\n\n`;
    }
    
    reportText += `${'-'.repeat(50)}\n`;
    reportText += `Report generated on: ${new Date().toLocaleString()}\n`;
    reportText += `Fantaziko Admin Panel\n`;
    
    // Create and download file
    const blob = new Blob([reportText], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${league.name.replace(/[^a-z0-9]/gi, '_')}_Report_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include 'includes/footer.php'; ?>