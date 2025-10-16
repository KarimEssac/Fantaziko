<?php
session_start();
require_once 'config/db.php';

// Get date range filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$selected_league = isset($_GET['league_id']) ? $_GET['league_id'] : null;

// Fetch analytics data
$analytics = [
    'total_revenue' => 0,
    'avg_league_price' => 0,
    'total_contributors' => 0,
    'avg_players_per_league' => 0,
    'avg_teams_per_league' => 0,
    'total_matches' => 0,
    'active_users' => 0,
    'growth_rate' => 0
];

try {
    // Total Revenue
    $stmt = $pdo->prepare("SELECT SUM(price) as total FROM leagues WHERE activated = 1 AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Average League Price
    $stmt = $pdo->prepare("SELECT AVG(price) as avg FROM leagues WHERE activated = 1 AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['avg_league_price'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    // Total Contributors
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM league_contributors lc 
                           INNER JOIN leagues l ON lc.league_id = l.id 
                           WHERE l.created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['total_contributors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Average Players Per League
    $stmt = $pdo->prepare("SELECT AVG(num_of_players) as avg FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['avg_players_per_league'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    // Average Teams Per League
    $stmt = $pdo->prepare("SELECT AVG(num_of_teams) as avg FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['avg_teams_per_league'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg'] ?? 0;
    
    // Total Matches
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM matches m 
                           INNER JOIN leagues l ON m.league_id = l.id 
                           WHERE l.created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['total_matches'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Active Users (users with accounts created in period)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM accounts WHERE activated = 1 AND created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $analytics['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Growth Rate (compare current period to previous period)
    $date_diff = (strtotime($date_to) - strtotime($date_from));
    $prev_from = date('Y-m-d', strtotime($date_from) - $date_diff);
    $prev_to = $date_from;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as current FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $current_leagues = $stmt->fetch(PDO::FETCH_ASSOC)['current'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as previous FROM leagues WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$prev_from, $prev_to]);
    $previous_leagues = $stmt->fetch(PDO::FETCH_ASSOC)['previous'] ?? 0;
    
    if ($previous_leagues > 0) {
        $analytics['growth_rate'] = (($current_leagues - $previous_leagues) / $previous_leagues) * 100;
    }
    
    // League Performance Data
    $league_where = $selected_league ? "AND l.id = ?" : "";
    $league_params = $selected_league ? [$date_from, $date_to, $selected_league] : [$date_from, $date_to];
    
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.name,
            l.num_of_players,
            l.num_of_teams,
            l.price,
            l.activated,
            l.system,
            COUNT(DISTINCT lc.user_id) as contributors_count,
            COUNT(DISTINCT m.match_id) as matches_count,
            a.username as owner_name
        FROM leagues l
        LEFT JOIN accounts a ON l.owner = a.id
        LEFT JOIN league_contributors lc ON l.id = lc.league_id
        LEFT JOIN matches m ON l.id = m.league_id
        WHERE l.created_at BETWEEN ? AND ? $league_where
        GROUP BY l.id
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    $stmt->execute($league_params);
    $league_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Contributors by Score
    $stmt = $pdo->prepare("
        SELECT 
            a.username,
            a.email,
            SUM(lc.total_score) as total_score,
            COUNT(DISTINCT lc.league_id) as leagues_participated
        FROM league_contributors lc
        INNER JOIN accounts a ON lc.user_id = a.id
        INNER JOIN leagues l ON lc.league_id = l.id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY lc.user_id
        ORDER BY total_score DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $top_contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Most Active Teams
    $stmt = $pdo->prepare("
        SELECT 
            lt.team_name,
            l.name as league_name,
            lt.team_score,
            COUNT(DISTINCT m1.match_id) + COUNT(DISTINCT m2.match_id) as matches_played
        FROM league_teams lt
        INNER JOIN leagues l ON lt.league_id = l.id
        LEFT JOIN matches m1 ON lt.id = m1.team1_id
        LEFT JOIN matches m2 ON lt.id = m2.team2_id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY lt.id
        ORDER BY matches_played DESC, lt.team_score DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $active_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Player Statistics
    $stmt = $pdo->prepare("
        SELECT 
            lp.player_name,
            lp.player_role,
            l.name as league_name,
            lt.team_name,
            lp.player_price,
            COUNT(DISTINCT mp.id) as total_contributions
        FROM league_players lp
        INNER JOIN leagues l ON lp.league_id = l.id
        LEFT JOIN league_teams lt ON lp.team_id = lt.id
        LEFT JOIN matches_points mp ON (mp.scorer = lp.player_id OR mp.assister = lp.player_id OR mp.bonus = lp.player_id)
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY lp.player_id
        ORDER BY total_contributions DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $player_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System Distribution
    $stmt = $pdo->prepare("
        SELECT 
            system,
            COUNT(*) as count,
            SUM(price) as total_revenue,
            AVG(num_of_players) as avg_players
        FROM leagues
        WHERE created_at BETWEEN ? AND ?
        GROUP BY system
    ");
    $stmt->execute([$date_from, $date_to]);
    $system_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily League Creation Trend
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as leagues_created,
            SUM(price) as daily_revenue
        FROM leagues
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Match Statistics
    $stmt = $pdo->prepare("
        SELECT 
            l.name as league_name,
            COUNT(DISTINCT m.match_id) as total_matches,
            AVG(m.team1_score + m.team2_score) as avg_total_score,
            COUNT(DISTINCT mp.id) as total_events
        FROM matches m
        INNER JOIN leagues l ON m.league_id = l.id
        LEFT JOIN matches_points mp ON m.match_id = mp.match_id
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY l.id
        ORDER BY total_matches DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $match_statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all leagues for filter
    $stmt = $pdo->query("SELECT id, name FROM leagues ORDER BY name");
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
    
    .stat-card.users {
        border-left-color: #1D60AC;
    }
    
    .stat-card.matches {
        border-left-color: #F1A155;
    }
    
    .stat-card.growth {
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
    
    .icon-users {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
    }
    
    .icon-matches {
        background: #F1A155;
    }
    
    .icon-growth {
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
    
    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
    }
    
    .chart-placeholder {
        background: linear-gradient(135deg, rgba(29, 96, 172, 0.05), rgba(10, 146, 215, 0.05));
        border: 2px dashed #1D60AC;
        border-radius: 8px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1D60AC;
        font-size: 14px;
        font-weight: 600;
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
    
    .grid-2col {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
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
    
    .growth-indicator {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .growth-positive {
        background: #d4edda;
        color: #155724;
    }
    
    .growth-negative {
        background: #f8d7da;
        color: #721c24;
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
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">📊 Analytics & Insights</h1>
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
                    <a href="analytics.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Key Metrics -->
    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Revenue</span>
                <div class="stat-card-icon icon-revenue">💰</div>
            </div>
            <div class="stat-card-value">$<?php echo number_format($analytics['total_revenue'], 2); ?></div>
            <div class="stat-card-subtitle">From active leagues</div>
        </div>
        
        <div class="stat-card users">
            <div class="stat-card-header">
                <span class="stat-card-title">Active Contributors</span>
                <div class="stat-card-icon icon-users">👥</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($analytics['total_contributors']); ?></div>
            <div class="stat-card-subtitle">Participating in leagues</div>
        </div>
        
        <div class="stat-card matches">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Matches</span>
                <div class="stat-card-icon icon-matches">📅</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($analytics['total_matches']); ?></div>
            <div class="stat-card-subtitle">Played in period</div>
        </div>
        
        <div class="stat-card growth">
            <div class="stat-card-header">
                <span class="stat-card-title">Growth Rate</span>
                <div class="stat-card-icon icon-growth">📈</div>
            </div>
            <div class="stat-card-value">
                <?php 
                $growth = $analytics['growth_rate'];
                echo ($growth >= 0 ? '+' : '') . number_format($growth, 1); 
                ?>%
            </div>
            <div class="stat-card-subtitle">
                <span class="growth-indicator <?php echo $growth >= 0 ? 'growth-positive' : 'growth-negative'; ?>">
                    <?php echo $growth >= 0 ? '▲' : '▼'; ?> vs previous period
                </span>
            </div>
        </div>
    </div>
    
    <!-- Average Metrics -->
    <div class="data-card">
        <div class="data-card-header">📊 Average Metrics</div>
        <div class="data-card-body">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Avg League Price</div>
                    <div class="summary-value">$<?php echo number_format($analytics['avg_league_price'], 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Avg Players/League</div>
                    <div class="summary-value"><?php echo number_format($analytics['avg_players_per_league'], 1); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Avg Teams/League</div>
                    <div class="summary-value"><?php echo number_format($analytics['avg_teams_per_league'], 1); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Active Users</div>
                    <div class="summary-value"><?php echo number_format($analytics['active_users']); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Distribution -->
    <div class="data-card">
        <div class="data-card-header">⚙️ League System Distribution</div>
        <div class="data-card-body">
            <div class="data-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>System Type</th>
                            <th>Number of Leagues</th>
                            <th>Total Revenue</th>
                            <th>Avg Players</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($system_distribution)): ?>
                            <?php foreach ($system_distribution as $system): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($system['system']); ?></span></td>
                                    <td><strong><?php echo number_format($system['count']); ?></strong></td>
                                    <td><strong style="color: #28a745;">$<?php echo number_format($system['total_revenue'], 2); ?></strong></td>
                                    <td><?php echo number_format($system['avg_players'], 1); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #999;">No data available for selected period</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Grid Layout for Multiple Tables -->
    <div class="grid-2col">
        <!-- League Performance -->
        <div class="data-card">
            <div class="data-card-header">🏆 League Performance</div>
            <div class="data-card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>League</th>
                            <th>Contributors</th>
                            <th>Matches</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($league_performance)): ?>
                            <?php foreach ($league_performance as $league): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($league['name']); ?></strong>
                                        <br>
                                        <small style="color: #999;">Owner: <?php echo htmlspecialchars($league['owner_name']); ?></small>
                                    </td>
                                    <td><strong style="color: #1D60AC;"><?php echo $league['contributors_count']; ?></strong></td>
                                    <td><?php echo $league['matches_count']; ?></td>
                                    <td>
                                        <?php if ($league['activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #999;">No leagues found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Contributors -->
        <div class="data-card">
            <div class="data-card-header">🏅 Top Contributors by Score</div>
            <div class="data-card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Username</th>
                            <th>Total Score</th>
                            <th>Leagues</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_contributors)): ?>
                            <?php $rank = 1; ?>
                            <?php foreach ($top_contributors as $contributor): ?>
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
                                    <td>
                                        <strong><?php echo htmlspecialchars($contributor['username']); ?></strong>
                                        <br>
                                        <small style="color: #999;"><?php echo htmlspecialchars($contributor['email']); ?></small>
                                    </td>
                                    <td><strong style="color: #F1A155; font-size: 16px;"><?php echo number_format($contributor['total_score']); ?></strong></td>
                                    <td><?php echo $contributor['leagues_participated']; ?></td>
                                </tr>
                                <?php $rank++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #999;">No contributors found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Most Active Teams -->
    <div class="data-card">
        <div class="data-card-header">🎯 Most Active Teams</div>
        <div class="data-card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Team Name</th>
                        <th>League</th>
                        <th>Team Score</th>
                        <th>Matches Played</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($active_teams)): ?>
                        <?php foreach ($active_teams as $team): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($team['league_name']); ?></td>
                                <td><strong style="color: #1D60AC; font-size: 16px;"><?php echo number_format($team['team_score']); ?></strong></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $team['matches_played']; ?> matches</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999;">No teams found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Player Statistics -->
    <div class="data-card">
        <div class="data-card-header">⚽ Top Player Statistics</div>
        <div class="data-card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Player Name</th>
                        <th>Role</th>
                        <th>League</th>
                        <th>Team</th>
                        <th>Price</th>
                        <th>Contributions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($player_stats)): ?>
                        <?php foreach ($player_stats as $player): ?>
                            <tr>
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
                                <td><?php echo htmlspecialchars($player['league_name']); ?></td>
                                <td><?php echo htmlspecialchars($player['team_name'] ?? 'N/A'); ?></td>
                                <td><strong style="color: #28a745;">$<?php echo number_format($player['player_price'], 2); ?></strong></td>
                                <td><strong style="color: #1D60AC;"><?php echo $player['total_contributions']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999;">No player statistics available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Match Statistics -->
    <div class="data-card">
        <div class="data-card-header">📅 Match Statistics by League</div>
        <div class="data-card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>League Name</th>
                        <th>Total Matches</th>
                        <th>Avg Total Score</th>
                        <th>Total Events</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($match_statistics)): ?>
                        <?php foreach ($match_statistics as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['league_name']); ?></strong></td>
                                <td><strong style="color: #1D60AC;"><?php echo number_format($stat['total_matches']); ?></strong></td>
                                <td><?php echo number_format($stat['avg_total_score'], 2); ?></td>
                                <td><?php echo number_format($stat['total_events']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999;">No match statistics available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Daily Trends -->
    <div class="data-card">
        <div class="data-card-header">📈 Daily League Creation Trends</div>
        <div class="data-card-body">
            <?php if (!empty($daily_trends)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Leagues Created</th>
                            <th>Daily Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_trends as $trend): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($trend['date'])); ?></td>
                                <td><strong style="color: #1D60AC;"><?php echo $trend['leagues_created']; ?></strong></td>
                                <td><strong style="color: #28a745;">$<?php echo number_format($trend['daily_revenue'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-title">📊 No Trend Data Available</div>
                    <div class="info-card-text">
                        No daily trends are available for the selected period. Try selecting a different date range with more activity.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary Insights -->
    <div class="data-card">
        <div class="data-card-header">💡 Key Insights</div>
        <div class="data-card-body">
            <div class="info-card">
                <div class="info-card-title">📊 Period Overview</div>
                <div class="info-card-text">
                    <strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> to <?php echo date('M d, Y', strtotime($date_to)); ?>
                    <br><br>
                    <?php if ($analytics['growth_rate'] > 0): ?>
                        <strong>🎉 Growth:</strong> Your platform is experiencing positive growth with a <?php echo number_format($analytics['growth_rate'], 1); ?>% increase in league creation compared to the previous period.
                    <?php elseif ($analytics['growth_rate'] < 0): ?>
                        <strong>⚠️ Decline:</strong> League creation has decreased by <?php echo number_format(abs($analytics['growth_rate']), 1); ?>% compared to the previous period. Consider promotional strategies.
                    <?php else: ?>
                        <strong>📊 Stable:</strong> League creation remains stable compared to the previous period.
                    <?php endif; ?>
                    <br><br>
                    <strong>Revenue Performance:</strong> Total revenue of $<?php echo number_format($analytics['total_revenue'], 2); ?> generated from <?php echo number_format($analytics['total_contributors']); ?> active contributors across <?php echo number_format($analytics['total_matches']); ?> matches.
                    <br><br>
                    <strong>League Engagement:</strong> Average of <?php echo number_format($analytics['avg_players_per_league'], 1); ?> players and <?php echo number_format($analytics['avg_teams_per_league'], 1); ?> teams per league, indicating <?php echo $analytics['avg_players_per_league'] >= 10 ? 'healthy' : 'developing'; ?> engagement levels.
                </div>
            </div>
            
            <?php if (!empty($system_distribution)): ?>
                <div class="info-card" style="border-left-color: #F1A155; background: linear-gradient(135deg, rgba(241, 161, 85, 0.1), rgba(241, 161, 85, 0.05));">
                    <div class="info-card-title" style="color: #F1A155;">⚙️ System Preferences</div>
                    <div class="info-card-text">
                        <?php
                        $most_popular = $system_distribution[0];
                        foreach ($system_distribution as $sys) {
                            if ($sys['count'] > $most_popular['count']) {
                                $most_popular = $sys;
                            }
                        }
                        ?>
                        The most popular league system is <strong><?php echo htmlspecialchars($most_popular['system']); ?></strong> with <?php echo $most_popular['count']; ?> leagues, generating $<?php echo number_format($most_popular['total_revenue'], 2); ?> in revenue.
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($top_contributors)): ?>
                <div class="info-card" style="border-left-color: #28a745; background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));">
                    <div class="info-card-title" style="color: #28a745;">🏅 Top Performer</div>
                    <div class="info-card-text">
                        <strong><?php echo htmlspecialchars($top_contributors[0]['username']); ?></strong> leads with a total score of <?php echo number_format($top_contributors[0]['total_score']); ?> across <?php echo $top_contributors[0]['leagues_participated']; ?> leagues.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>