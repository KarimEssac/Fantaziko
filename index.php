<?php
session_start();
require_once 'config/db.php';

// Check if admin is logged in
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: login.php');
//     exit();
// }

// Fetch dashboard statistics
$stats = [
    'total_accounts' => 0,
    'total_leagues' => 0,
    'active_leagues' => 0,
    'total_players' => 0,
    'total_teams' => 0,
    'total_matches' => 0
];

try {
    // Total Accounts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM accounts");
    $stats['total_accounts'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Leagues
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leagues");
    $stats['total_leagues'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Active Leagues
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leagues WHERE activated = 1");
    $stats['active_leagues'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Players
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM league_players");
    $stats['total_players'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Teams
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM league_teams");
    $stats['total_teams'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total Matches
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM matches");
    $stats['total_matches'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Recent Leagues
    $stmt = $pdo->query("SELECT l.*, a.username as owner_name 
                         FROM leagues l 
                         LEFT JOIN accounts a ON l.owner = a.id 
                         ORDER BY l.created_at DESC 
                         LIMIT 5");
    $recent_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Accounts
    $stmt = $pdo->query("SELECT * FROM accounts ORDER BY created_at DESC LIMIT 5");
    $recent_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Roboto', sans-serif;
    }
    
    .main-content {
        margin-left: 280px;
        padding: 30px;
        background: #f5f7fa;
        min-height: calc(100vh - 70px);
    }
    
    .dashboard-header {
        margin-bottom: 30px;
    }
    
    .dashboard-header h1 {
        font-size: 32px;
        font-weight: 700;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 5px;
    }
    
    .dashboard-header p {
        color: #666;
        font-size: 14px;
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
        border-left: 4px solid #1D60AC; /* Standardized to primary color */
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card.primary {
        border-left-color: #1D60AC;
    }
    
    .stat-card.secondary {
        border-left-color: #F1A155;
    }
    
    .stat-card.info {
        border-left-color: #0A92D7;
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
    
    .icon-primary {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
    }
    
    .icon-secondary {
        background: #F1A155;
    }
    
    .stat-card-value {
        font-size: 36px;
        font-weight: 700;
        color: #000000;
    }
    
    .data-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .data-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
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
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .data-section {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="dashboard-header">
        <h1>Dashboard Overview</h1>
        <p>Welcome to Fantaziko Admin Panel</p>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
        <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Accounts</span>
                <div class="stat-card-icon icon-primary">👥</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['total_accounts']); ?></div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Leagues</span>
                <div class="stat-card-icon icon-primary">🏆</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['total_leagues']); ?></div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Active Leagues</span>
                <div class="stat-card-icon icon-primary">✓</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['active_leagues']); ?></div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Players</span>
                <div class="stat-card-icon icon-primary">⚽</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['total_players']); ?></div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Teams</span>
                <div class="stat-card-icon icon-primary">🎯</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['total_teams']); ?></div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Matches</span>
                <div class="stat-card-icon icon-primary">📊</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($stats['total_matches']); ?></div>
        </div>
    </div>
    
    <div class="data-section">
        <div class="data-card">
            <div class="data-card-header">Recent Leagues</div>
            <div class="data-card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>League Name</th>
                            <th>Owner</th>
                            <th>Players</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_leagues)): ?>
                            <?php foreach ($recent_leagues as $league): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($league['name']); ?></td>
                                    <td><?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $league['num_of_players']; ?></td>
                                    <td>
                                        <?php if ($league['activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
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
        
        <div class="data-card">
            <div class="data-card-header">Recent Accounts</div>
            <div class="data-card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_accounts)): ?>
                            <?php foreach ($recent_accounts as $account): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['username']); ?></td>
                                    <td><?php echo htmlspecialchars($account['email']); ?></td>
                                    <td>
                                        <?php if ($account['activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                        <?php if ($account['admin']): ?>
                                            <span class="badge badge-info">Admin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #999;">No accounts found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>