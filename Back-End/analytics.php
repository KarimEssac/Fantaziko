<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$selected_league = isset($_GET['league_id']) ? $_GET['league_id'] : null;

// Fetch reports data
$reports = [
    'total_revenue' => 0,
    'avg_league_price' => 0,
    'total_leagues' => 0,
    'active_leagues' => 0,
    'total_contributors' => 0,
    'total_teams' => 0,
    'total_players' => 0,
    'total_matches' => 0,
    'avg_players_per_league' => 0,
    'avg_teams_per_league' => 0,
    'budget_system_count' => 0,
    'no_limits_system_count' => 0
];

try {
    // Total Revenue from all leagues
    $stmt = $pdo->prepare("SELECT SUM(price) as total FROM leagues WHERE activated = 1 AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Average League Price
    $stmt = $pdo->prepare("SELECT AVG(price) as avg FROM leagues WHERE activated = 1 AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['avg_league_price'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    // Total Leagues
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['total_leagues'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Active Leagues
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leagues WHERE activated = 1 AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['active_leagues'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total Contributors
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM league_contributors lc 
                           INNER JOIN leagues l ON lc.league_id = l.id 
                           WHERE l.created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['total_contributors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total Teams
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM league_teams lt 
                           INNER JOIN leagues l ON lt.league_id = l.id 
                           WHERE l.created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['total_teams'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total Players
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM league_players lp 
                           INNER JOIN leagues l ON lp.league_id = l.id 
                           WHERE l.created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['total_players'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Total Matches
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM matches m 
                           INNER JOIN leagues l ON m.league_id = l.id 
                           WHERE l.created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['total_matches'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Average Players Per League
    $stmt = $pdo->prepare("SELECT AVG(num_of_players) as avg FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['avg_players_per_league'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    // Average Teams Per League
    $stmt = $pdo->prepare("SELECT AVG(num_of_teams) as avg FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['avg_teams_per_league'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    // System Distribution
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leagues WHERE system = 'Budget' AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['budget_system_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leagues WHERE system = 'No Limits' AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $reports['no_limits_system_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // League Details Report
    $league_where = $selected_league ? "AND l.id = ?" : "";
    $league_params = $selected_league ? [$date_from, $date_to, $selected_league] : [$date_from, $date_to];
    
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.name,
            l.system,
            l.num_of_players,
            l.num_of_teams,
            l.price,
            l.activated,
            l.round,
            l.triple_captain,
            l.bench_boost,
            l.wild_card,
            l.created_at,
            a.username as owner_name,
            a2.username as other_owner_name,
            COUNT(DISTINCT lc.user_id) as contributors_count,
            COUNT(DISTINCT lt.id) as teams_count,
            COUNT(DISTINCT lp.player_id) as players_count,
            COUNT(DISTINCT m.match_id) as matches_count
        FROM leagues l
        LEFT JOIN accounts a ON l.owner = a.id
        LEFT JOIN accounts a2 ON l.other_owner = a2.id
        LEFT JOIN league_contributors lc ON l.id = lc.league_id
        LEFT JOIN league_teams lt ON l.id = lt.league_id
        LEFT JOIN league_players lp ON l.id = lp.league_id
        LEFT JOIN matches m ON l.id = m.league_id
        WHERE l.created_at BETWEEN ? AND ? $league_where
        GROUP BY l.id
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($league_params);
    $league_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue by System
    $stmt = $pdo->prepare("
        SELECT 
            system,
            COUNT(*) as league_count,
            SUM(price) as total_revenue,
            AVG(price) as avg_price,
            SUM(num_of_players) as total_players,
            SUM(num_of_teams) as total_teams
        FROM leagues
        WHERE created_at BETWEEN ? AND ?
        GROUP BY system
    ");
    $stmt->execute([$date_from, $date_to]);
    $revenue_by_system = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Revenue Leagues
    $stmt = $pdo->prepare("
        SELECT 
            l.name,
            l.price,
            l.system,
            l.activated,
            l.created_at,
            a.username as owner_name,
            COUNT(DISTINCT lc.user_id) as contributors_count
        FROM leagues l
        LEFT JOIN accounts a ON l.owner = a.id
        LEFT JOIN league_contributors lc ON l.id = lc.league_id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY l.id
        ORDER BY l.price DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $top_revenue_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly Revenue Trend
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as leagues_created,
            SUM(price) as monthly_revenue,
            AVG(price) as avg_price
        FROM leagues
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Contributors Report
    $stmt = $pdo->prepare("
        SELECT 
            a.username,
            a.email,
            COUNT(DISTINCT lc.league_id) as leagues_joined,
            SUM(lc.total_score) as total_score,
            AVG(lc.total_score) as avg_score
        FROM league_contributors lc
        INNER JOIN accounts a ON lc.user_id = a.id
        INNER JOIN leagues l ON lc.league_id = l.id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY lc.user_id
        ORDER BY leagues_joined DESC, total_score DESC
        LIMIT 15
    ");
    $stmt->execute([$date_from, $date_to]);
    $contributors_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Teams Performance Report
    $stmt = $pdo->prepare("
        SELECT 
            lt.team_name,
            l.name as league_name,
            lt.team_score,
            COUNT(DISTINCT CASE WHEN m.team1_id = lt.id OR m.team2_id = lt.id THEN m.match_id END) as matches_played,
            COUNT(DISTINCT lp.player_id) as players_count
        FROM league_teams lt
        INNER JOIN leagues l ON lt.league_id = l.id
        LEFT JOIN matches m ON (m.team1_id = lt.id OR m.team2_id = lt.id)
        LEFT JOIN league_players lp ON lp.team_id = lt.id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY lt.id
        ORDER BY lt.team_score DESC
        LIMIT 15
    ");
    $stmt->execute([$date_from, $date_to]);
    $teams_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Player Statistics Report
    $stmt = $pdo->prepare("
        SELECT 
            lp.player_name,
            lp.player_role,
            lp.player_price,
            lp.total_points,
            l.name as league_name,
            lt.team_name,
            COUNT(DISTINCT mp.id) as event_count
        FROM league_players lp
        INNER JOIN leagues l ON lp.league_id = l.id
        LEFT JOIN league_teams lt ON lp.team_id = lt.id
        LEFT JOIN matches_points mp ON (
            mp.scorer = lp.player_id OR 
            mp.assister = lp.player_id OR 
            mp.bonus = lp.player_id OR
            mp.saved_penalty_gk = lp.player_id
        )
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY lp.player_id
        ORDER BY lp.total_points DESC
        LIMIT 20
    ");
    $stmt->execute([$date_from, $date_to]);
    $player_statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Match Activity Report
    $stmt = $pdo->prepare("
        SELECT 
            l.name as league_name,
            m.round,
            lt1.team_name as team1_name,
            lt2.team_name as team2_name,
            m.team1_score,
            m.team2_score,
            m.created_at,
            COUNT(DISTINCT mp.id) as total_events
        FROM matches m
        INNER JOIN leagues l ON m.league_id = l.id
        LEFT JOIN league_teams lt1 ON m.team1_id = lt1.id
        LEFT JOIN league_teams lt2 ON m.team2_id = lt2.id
        LEFT JOIN matches_points mp ON m.match_id = mp.match_id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY m.match_id
        ORDER BY m.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$date_from, $date_to]);
    $match_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all leagues for filter
    $stmt = $pdo->query("SELECT id, name FROM leagues ORDER BY created_at DESC");
    $all_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
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
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .filter-card {
        background: #FFFFFF;
        padding: 20px 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }
    
    .filter-row {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    .filter-input {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }
    
    .filter-input:focus {
        outline: none;
        border-color: #1D60AC;
        box-shadow: 0 0 0 3px rgba(29, 96, 172, 0.1);
    }
    
    .btn {
        padding: 10px 20px;
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
    
    .btn-success {
        background: #28a745;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: #FFFFFF;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-left: 4px solid #1D60AC;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card.revenue {
        border-left-color: #28a745;
    }
    
    .stat-card.leagues {
        border-left-color: #1D60AC;
    }
    
    .stat-card.contributors {
        border-left-color: #F1A155;
    }
    
    .stat-card.matches {
        border-left-color: #17a2b8;
    }
    
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .stat-card-title {
        font-size: 14px;
        color: #666;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-card-icon {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #FFFFFF;
    }
    
    .icon-revenue {
        background: #28a745;
    }
    
    .icon-leagues {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
    }
    
    .icon-contributors {
        background: #F1A155;
    }
    
    .icon-matches {
        background: #17a2b8;
    }
    
    .stat-card-value {
        font-size: 36px;
        font-weight: 700;
        color: #000000;
        margin-bottom: 5px;
    }
    
    .stat-card-subtitle {
        font-size: 13px;
        color: #999;
    }
    
    .data-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    .data-card-header {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
        padding: 20px 25px;
        font-size: 18px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .data-card-body {
        padding: 25px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table thead {
        background: #f8f9fa;
    }
    
    .data-table th {
        padding: 12px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: #333;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        color: #666;
        font-size: 14px;
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
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
    
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
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
        background: rgba(29, 96, 172, 0.15);
        color: #1D60AC;
    }
    
    .badge-secondary {
        background: rgba(241, 161, 85, 0.15);
        color: #F1A155;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .summary-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #1D60AC;
    }
    
    .summary-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .summary-value {
        font-size: 22px;
        font-weight: 700;
        color: #000;
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
    
    .grid-2col {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .grid-2col {
            grid-template-columns: 1fr;
        }
        
        .filter-row {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
    }
    
    @media print {
        .filter-card,
        .btn,
        .page-header .action-buttons {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0;
        }
        
        .data-card {
            break-inside: avoid;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">📊 Comprehensive Reports</h1>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-success">🖨️ Print Report</button>
            <a href="analytics.php" class="btn btn-primary">📈 Analytics</a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">League Filter</label>
                    <select name="league_id" class="filter-input">
                        <option value="">All Leagues</option>
                        <?php foreach ($all_leagues as $league): ?>
                            <option value="<?php echo $league['id']; ?>" <?php echo $selected_league == $league['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($league['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                </div>
                
                <div class="filter-group" style="flex: 0 0 auto;">
                    <a href="reports.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Summary Statistics -->
    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Revenue</span>
                <div class="stat-card-icon icon-revenue">💰</div>
            </div>
            <div class="stat-card-value">EGP <?php echo number_format($reports['total_revenue'], 2); ?></div>
            <div class="stat-card-subtitle">From <?php echo $reports['active_leagues']; ?> active leagues</div>
        </div>
        
        <div class="stat-card leagues">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Leagues</span>
                <div class="stat-card-icon icon-leagues">🏆</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($reports['total_leagues']); ?></div>
            <div class="stat-card-subtitle"><?php echo $reports['active_leagues']; ?> active, <?php echo $reports['total_leagues'] - $reports['active_leagues']; ?> inactive</div>
        </div>
        
        <div class="stat-card contributors">
            <div class="stat-card-header">
                <span class="stat-card-title">Contributors</span>
                <div class="stat-card-icon icon-contributors">👥</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($reports['total_contributors']); ?></div>
            <div class="stat-card-subtitle">Participating users</div>
        </div>
        
        <div class="stat-card matches">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Matches</span>
                <div class="stat-card-icon icon-matches">⚽</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($reports['total_matches']); ?></div>
            <div class="stat-card-subtitle">Played in period</div>
        </div>
    </div>
    
    <!-- Additional Metrics -->
    <div class="data-card">
        <div class="data-card-header">📊 Additional Metrics Overview</div>
        <div class="data-card-body">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Avg League Price</div>
                    <div class="summary-value">EGP <?php echo number_format($reports['avg_league_price'], 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Teams</div>
                    <div class="summary-value"><?php echo number_format($reports['total_teams']); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Players</div>
                    <div class="summary-value"><?php echo number_format($reports['total_players']); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Avg Players/League</div>
                    <div class="summary-value"><?php echo number_format($reports['avg_players_per_league'], 1); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Avg Teams/League</div>
                    <div class="summary-value"><?php echo number_format($reports['avg_teams_per_league'], 1); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Budget System</div>
                    <div class="summary-value"><?php echo number_format($reports['budget_system_count']); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">No Limits System</div>
                    <div class="summary-value"><?php echo number_format($reports['no_limits_system_count']); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Revenue by System -->
    <div class="data-card">
        <div class="data-card-header">💵 Revenue by League System</div>
        <div class="data-card-body">
            <?php if (!empty($revenue_by_system)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>System Type</th>
                            <th>League Count</th>
                            <th>Total Revenue</th>
                            <th>Avg Price</th>
                            <th>Total Players</th>
                            <th>Total Teams</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revenue_by_system as $system): ?>
                            <tr>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($system['system']); ?></span></td>
                                <td><strong><?php echo number_format($system['league_count']); ?></strong></td>
                                <td><strong style="color: #28a745;">EGP <?php echo number_format($system['total_revenue'], 2); ?></strong></td>
                                <td>EGP <?php echo number_format($system['avg_price'], 2); ?></td>
                                <td><?php echo number_format($system['total_players']); ?></td>
                                <td><?php echo number_format($system['total_teams']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No revenue data available for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Revenue Leagues -->
    <div class="data-card">
        <div class="data-card-header">🏆 Top Revenue Generating Leagues</div>
        <div class="data-card-body">
            <?php if (!empty($top_revenue_leagues)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>League Name</th>
                            <th>Owner</th>
                            <th>Price</th>
                            <th>System</th>
                            <th>Contributors</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($top_revenue_leagues as $league): ?>
                            <tr>
                                <td>
                                    <?php if ($rank == 1): ?>
                                        <span style="font-size: 20px;">🥇</span>
                                    <?php elseif ($rank == 2): ?>
                                        <span style="font-size: 20px;">🥈</span>
                                    <?php elseif ($rank == 3): ?>
                                        <span style="font-size: 20px;">🥉</span>
                                    <?php else: ?>
                                        <strong><?php echo $rank; ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($league['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?></td>
                                <td><strong style="color: #28a745; font-size: 16px;">EGP <?php echo number_format($league['price'], 2); ?></strong></td>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($league['system']); ?></span></td>
                                <td><?php echo $league['contributors_count']; ?></td>
                                <td>
                                    <?php if ($league['activated']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($league['created_at'])); ?></td>
                            </tr>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No leagues found for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Monthly Revenue Trend -->
    <div class="data-card">
        <div class="data-card-header">📈 Monthly Revenue Trends</div>
        <div class="data-card-body">
            <?php if (!empty($monthly_trends)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Leagues Created</th>
                            <th>Monthly Revenue</th>
                            <th>Avg Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_trends as $trend): ?>
                            <tr>
                                <td><strong><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></strong></td>
                                <td><?php echo number_format($trend['leagues_created']); ?> leagues</td>
                                <td><strong style="color: #28a745; font-size: 16px;">EGP <?php echo number_format($trend['monthly_revenue'], 2); ?></strong></td>
                                <td>EGP <?php echo number_format($trend['avg_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No monthly trend data available for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- League Details Report -->
    <div class="data-card">
        <div class="data-card-header">📋 Detailed League Report</div>
        <div class="data-card-body">
            <?php if (!empty($league_details)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>League ID</th>
                            <th>League Name</th>
                            <th>Owner</th>
                            <th>Co-Owner</th>
                            <th>System</th>
                            <th>Round</th>
                            <th>Price</th>
                            <th>Contributors</th>
                            <th>Teams</th>
                            <th>Players</th>
                            <th>Matches</th>
                            <th>Power-ups</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($league_details as $league): ?>
                            <tr>
                                <td><strong>#<?php echo $league['id']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($league['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($league['other_owner_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($league['system']); ?></span></td>
                                <td>Round <?php echo $league['round']; ?></td>
                                <td><strong style="color: #28a745;">EGP <?php echo number_format($league['price'], 2); ?></strong></td>
                                <td><?php echo $league['contributors_count']; ?></td>
                                <td><?php echo $league['teams_count']; ?></td>
                                <td><?php echo $league['players_count']; ?></td>
                                <td><?php echo $league['matches_count']; ?></td>
                                <td>
                                    <small>
                                        TC: <?php echo $league['triple_captain']; ?> | 
                                        BB: <?php echo $league['bench_boost']; ?> | 
                                        WC: <?php echo $league['wild_card']; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($league['activated']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($league['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No league details available for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Contributors Report -->
    <div class="data-card">
        <div class="data-card-header">👥 Contributors Performance Report</div>
        <div class="data-card-body">
            <?php if (!empty($contributors_report)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Leagues Joined</th>
                            <th>Total Score</th>
                            <th>Avg Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contributors_report as $contributor): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($contributor['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($contributor['email']); ?></td>
                                <td><strong style="color: #1D60AC;"><?php echo $contributor['leagues_joined']; ?></strong></td>
                                <td><strong style="color: #F1A155; font-size: 16px;"><?php echo number_format($contributor['total_score']); ?></strong></td>
                                <td><?php echo number_format($contributor['avg_score'], 1); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No contributor data available for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Teams Performance Report -->
    <div class="data-card">
        <div class="data-card-header">🎯 Teams Performance Report</div>
        <div class="data-card-body">
            <?php if (!empty($teams_performance)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Team Name</th>
                            <th>League</th>
                            <th>Team Score</th>
                            <th>Matches Played</th>
                            <th>Players Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($teams_performance as $team): ?>
                            <tr>
                                <td><strong><?php echo $rank; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($team['league_name']); ?></td>
                                <td><strong style="color: #1D60AC; font-size: 16px;"><?php echo number_format($team['team_score']); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo $team['matches_played']; ?> matches</span></td>
                                <td><?php echo $team['players_count']; ?> players</td>
                            </tr>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No team performance data available for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Player Statistics Report -->
    <div class="data-card">
        <div class="data-card-header">⚽ Top Players Statistics</div>
        <div class="data-card-body">
            <?php if (!empty($player_statistics)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player Name</th>
                            <th>Role</th>
                            <th>Team</th>
                            <th>League</th>
                            <th>Price</th>
                            <th>Total Points</th>
                            <th>Events</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; ?>
                        <?php foreach ($player_statistics as $player): ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                        <span style="font-size: 18px;">
                                            <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                                        </span>
                                    <?php else: ?>
                                        <strong><?php echo $rank; ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($player['player_name']); ?></strong></td>
                                <td>
                                    <?php
                                    $role_colors = [
                                        'GK' => 'badge-warning',
                                        'DEF' => 'badge-info',
                                        'MID' => 'badge-success',
                                        'ATT' => 'badge-danger'
                                    ];
                                    $badge_class = $role_colors[$player['player_role']] ?? 'badge-primary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($player['player_role']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($player['team_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($player['league_name']); ?></td>
                                <td><strong style="color: #28a745;">EGP <?php echo number_format($player['player_price'], 2); ?></strong></td>
                                <td><strong style="color: #F1A155; font-size: 16px;"><?php echo number_format($player['total_points']); ?></strong></td>
                                <td><?php echo $player['event_count']; ?></td>
                            </tr>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No player statistics available for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Match Activity Report -->
    <div class="data-card">
        <div class="data-card-header">📅 Recent Match Activity</div>
        <div class="data-card-body">
            <?php if (!empty($match_activity)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>League</th>
                            <th>Round</th>
                            <th>Team 1</th>
                            <th>Score</th>
                            <th>Team 2</th>
                            <th>Total Events</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($match_activity as $match): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($match['league_name']); ?></td>
                                <td><span class="badge badge-primary">Round <?php echo $match['round']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($match['team1_name'] ?? 'TBD'); ?></strong></td>
                                <td style="text-align: center;">
                                    <strong style="font-size: 16px; color: #1D60AC;">
                                        <?php echo $match['team1_score']; ?> - <?php echo $match['team2_score']; ?>
                                    </strong>
                                </td>
                                <td><strong><?php echo htmlspecialchars($match['team2_name'] ?? 'TBD'); ?></strong></td>
                                <td><span class="badge badge-info"><?php echo $match['total_events']; ?> events</span></td>
                                <td><?php echo date('M d, Y', strtotime($match['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-text">No match activity found for the selected period.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Report Summary -->
    <div class="data-card">
        <div class="data-card-header">💡 Report Summary</div>
        <div class="data-card-body">
            <div class="info-card">
                <div class="info-card-title">📊 Period Overview</div>
                <div class="info-card-text">
                    <strong>Reporting Period:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>
                    <br><br>
                    <strong>Financial Summary:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Total Revenue: <strong style="color: #28a745;">EGP <?php echo number_format($reports['total_revenue'], 2); ?></strong></li>
                        <li>Average League Price: <strong>EGP <?php echo number_format($reports['avg_league_price'], 2); ?></strong></li>
                        <li>Active Revenue-Generating Leagues: <strong><?php echo $reports['active_leagues']; ?></strong></li>
                    </ul>
                    <br>
                    <strong>Engagement Summary:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Total Leagues Created: <strong><?php echo number_format($reports['total_leagues']); ?></strong></li>
                        <li>Total Contributors: <strong><?php echo number_format($reports['total_contributors']); ?></strong></li>
                        <li>Total Teams Registered: <strong><?php echo number_format($reports['total_teams']); ?></strong></li>
                        <li>Total Players: <strong><?php echo number_format($reports['total_players']); ?></strong></li>
                        <li>Total Matches Played: <strong><?php echo number_format($reports['total_matches']); ?></strong></li>
                    </ul>
                    <br>
                    <strong>System Distribution:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Budget System: <strong><?php echo $reports['budget_system_count']; ?> leagues</strong></li>
                        <li>No Limits System: <strong><?php echo $reports['no_limits_system_count']; ?> leagues</strong></li>
                    </ul>
                </div>
            </div>
            
            <?php if (!empty($revenue_by_system)): ?>
                <div class="info-card" style="border-left-color: #28a745; background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));">
                    <div class="info-card-title" style="color: #28a745;">💵 Most Profitable System</div>
                    <div class="info-card-text">
                        <?php
                        $most_profitable = $revenue_by_system[0];
                        foreach ($revenue_by_system as $sys) {
                            if ($sys['total_revenue'] > $most_profitable['total_revenue']) {
                                $most_profitable = $sys;
                            }
                        }
                        ?>
                        The <strong><?php echo htmlspecialchars($most_profitable['system']); ?></strong> system generated the highest revenue with <strong>EGP <?php echo number_format($most_profitable['total_revenue'], 2); ?></strong> from <?php echo $most_profitable['league_count']; ?> leagues.
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="info-card" style="border-left-color: #F1A155; background: linear-gradient(135deg, rgba(241, 161, 85, 0.1), rgba(241, 161, 85, 0.05));">
                <div class="info-card-title" style="color: #F1A155;">📈 Key Metrics</div>
                <div class="info-card-text">
                    Average engagement shows <strong><?php echo number_format($reports['avg_players_per_league'], 1); ?> players</strong> and <strong><?php echo number_format($reports['avg_teams_per_league'], 1); ?> teams</strong> per league. 
                    <?php if ($reports['avg_players_per_league'] >= 10): ?>
                        This indicates <strong style="color: #28a745;">healthy engagement</strong> across the platform.
                    <?php else: ?>
                        Consider strategies to increase league participation.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>