<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_payments' && isset($_GET['league_id'])) {
        try {
            // Get league information
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
            
            // Get contributors and their payment status
            $stmt = $pdo->prepare("
                SELECT 
                    lc.*,
                    a.username,
                    a.email,
                    a.phone_number
                FROM league_contributors lc
                LEFT JOIN accounts a ON lc.user_id = a.id
                WHERE lc.league_id = ?
                ORDER BY a.username
            ");
            $stmt->execute([$_GET['league_id']]);
            $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'league' => $league,
                'contributors' => $contributors
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_payment_statistics') {
        try {
            // Total revenue from all leagues
            $stmt = $pdo->query("
                SELECT 
                    SUM(l.price * l.num_of_players) as total_revenue,
                    COUNT(DISTINCT l.id) as paid_leagues,
                    SUM(l.num_of_players) as total_paid_players
                FROM leagues l
                WHERE l.price > 0
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Count leagues by price range
            $stmt = $pdo->query("
                SELECT 
                    CASE 
                        WHEN price = 0 THEN 'Free'
                        WHEN price <= 50 THEN 'Low (1-50)'
                        WHEN price <= 100 THEN 'Medium (51-100)'
                        ELSE 'High (100+)'
                    END as price_range,
                    COUNT(*) as count
                FROM leagues
                GROUP BY price_range
                ORDER BY 
                    CASE 
                        WHEN price = 0 THEN 1
                        WHEN price <= 50 THEN 2
                        WHEN price <= 100 THEN 3
                        ELSE 4
                    END
            ");
            $price_ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'statistics' => $stats,
                'price_ranges' => $price_ranges
            ]);
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
                case 'update_league_price':
                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET price = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['price'],
                        $_POST['league_id']
                    ]);
                    $success_message = "League price updated successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch payment overview data
$search = isset($_GET['search']) ? $_GET['search'] : '';
$price_filter = isset($_GET['price_filter']) ? $_GET['price_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(l.name LIKE ? OR a.username LIKE ? OR a2.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($price_filter)) {
    switch ($price_filter) {
        case 'free':
            $where_clauses[] = "l.price = 0";
            break;
        case 'low':
            $where_clauses[] = "l.price > 0 AND l.price <= 50";
            break;
        case 'medium':
            $where_clauses[] = "l.price > 50 AND l.price <= 100";
            break;
        case 'high':
            $where_clauses[] = "l.price > 100";
            break;
    }
}

if (!empty($status_filter)) {
    $where_clauses[] = "l.activated = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    $stmt = $pdo->prepare("
        SELECT 
            l.id as league_id,
            l.name as league_name,
            l.price,
            l.num_of_players,
            l.activated,
            l.created_at,
            a.username as owner_name,
            a.email as owner_email,
            a2.username as other_owner_name,
            (l.price * l.num_of_players) as total_revenue
        FROM leagues l
        LEFT JOIN accounts a ON l.owner = a.id
        LEFT JOIN accounts a2 ON l.other_owner = a2.id
        $where_sql
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($params);
    $leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $total_revenue = 0;
    $total_players = 0;
    $paid_leagues = 0;
    
    foreach ($leagues as $league) {
        $total_revenue += $league['total_revenue'];
        if ($league['price'] > 0) {
            $paid_leagues++;
            $total_players += $league['num_of_players'];
        }
    }
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $leagues = [];
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
    
    .btn-success {
        background: #28a745;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        background: #218838;
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
    
    .stat-card.primary {
        border-left-color: #1D60AC;
    }
    
    .stat-card.success {
        border-left-color: #28a745;
    }
    
    .stat-card.warning {
        border-left-color: #ffc107;
    }
    
    .stat-card.secondary {
        border-left-color: #F1A155;
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
    
    .icon-success {
        background: #28a745;
    }
    
    .icon-warning {
        background: #ffc107;
    }
    
    .icon-secondary {
        background: #F1A155;
    }
    
    .stat-card-value {
        font-size: 36px;
        font-weight: 700;
        color: #000000;
    }
    
    .stat-card-label {
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }
    
    .filters-bar {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        align-items: end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .filter-label {
        font-size: 13px;
        font-weight: 600;
        color: #333;
    }
    
    .filter-input,
    .filter-select {
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }
    
    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #1D60AC;
        box-shadow: 0 0 0 3px rgba(29, 96, 172, 0.1);
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
    
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
    
    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-primary {
        background: rgba(29, 96, 172, 0.15);
        color: #1D60AC;
    }
    
    .price-display {
        font-size: 18px;
        font-weight: 700;
        color: #28a745;
    }
    
    .price-free {
        color: #6c757d;
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
        max-width: 900px;
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
    
    .league-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .league-info-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .league-info-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .league-info-value {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .contributors-grid {
        display: grid;
        gap: 15px;
    }
    
    .contributor-card {
        background: #FFFFFF;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 15px;
        align-items: center;
        transition: all 0.3s ease;
    }
    
    .contributor-card:hover {
        border-color: #1D60AC;
        box-shadow: 0 2px 8px rgba(29, 96, 172, 0.1);
    }
    
    .contributor-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .contributor-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .contributor-meta {
        font-size: 13px;
        color: #666;
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
    
    .empty-state-text {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .empty-state-subtext {
        font-size: 14px;
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
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filters-grid {
            grid-template-columns: 1fr;
        }
        
        .table-container {
            overflow-x: scroll;
        }
        
        .modal-content {
            width: 95%;
        }
        
        .league-info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">💰 Payments & Revenue Management</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Revenue</span>
                <div class="stat-card-icon icon-success">💵</div>
            </div>
            <div class="stat-card-value">$<?php echo number_format($total_revenue, 2); ?></div>
            <div class="stat-card-label">From all paid leagues</div>
        </div>
        
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">Paid Leagues</span>
                <div class="stat-card-icon icon-primary">🏆</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($paid_leagues); ?></div>
            <div class="stat-card-label">Leagues with entry fee</div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-card-header">
                <span class="stat-card-title">Paid Players</span>
                <div class="stat-card-icon icon-warning">👥</div>
            </div>
            <div class="stat-card-value"><?php echo number_format($total_players); ?></div>
            <div class="stat-card-label">Total paying participants</div>
        </div>
        
        <div class="stat-card secondary">
            <div class="stat-card-header">
                <span class="stat-card-title">Total Leagues</span>
                <div class="stat-card-icon icon-secondary">📊</div>
            </div>
            <div class="stat-card-value"><?php echo number_format(count($leagues)); ?></div>
            <div class="stat-card-label">All leagues in system</div>
        </div>
    </div>
    
    <!-- Filters Bar -->
    <div class="filters-bar">
        <form method="GET" id="filtersForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">🔍 Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="filter-input" 
                        placeholder="Search by league name or owner..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">💰 Price Range</label>
                    <select name="price_filter" class="filter-select">
                        <option value="">All Prices</option>
                        <option value="free" <?php echo $price_filter === 'free' ? 'selected' : ''; ?>>Free Leagues</option>
                        <option value="low" <?php echo $price_filter === 'low' ? 'selected' : ''; ?>>Low ($1-$50)</option>
                        <option value="medium" <?php echo $price_filter === 'medium' ? 'selected' : ''; ?>>Medium ($51-$100)</option>
                        <option value="high" <?php echo $price_filter === 'high' ? 'selected' : ''; ?>>High ($100+)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">📊 Status</label>
                    <select name="status_filter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" style="visibility: hidden;">Action</label>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        🔍 Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Leagues Payment Table -->
    <div class="data-card">
        <div class="data-card-header">
            <span>💳 League Payments Overview</span>
            <span style="font-size: 14px; font-weight: 500; opacity: 0.9;">
                <?php echo count($leagues); ?> League<?php echo count($leagues) !== 1 ? 's' : ''; ?>
            </span>
        </div>
        
        <div class="info-card">
            <div class="info-card-title">💡 About Payment Management</div>
            <div class="info-card-text">
                This page shows all leagues and their associated payment information. Each league has an entry price that contributors must pay. The total revenue is calculated as (League Price × Number of Players). Click "View Details" to see individual contributor information and manage league pricing.
            </div>
        </div>
        
        <div class="table-container">
            <?php if (!empty($leagues)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>League ID</th>
                            <th>League Name</th>
                            <th>Owner</th>
                            <th>Entry Price</th>
                            <th>Players</th>
                            <th>Total Revenue</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leagues as $league): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($league['league_id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($league['league_name']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?>
                                    <?php if ($league['other_owner_name']): ?>
                                        <br><small style="color: #999;">& <?php echo htmlspecialchars($league['other_owner_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="price-display <?php echo $league['price'] == 0 ? 'price-free' : ''; ?>">
                                        <?php echo $league['price'] == 0 ? 'FREE' : '$' . number_format($league['price'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($league['num_of_players']); ?></td>
                                <td>
                                    <strong style="color: #28a745; font-size: 16px;">
                                        $<?php echo number_format($league['total_revenue'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($league['activated']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($league['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewLeagueDetails(<?php echo $league['league_id']; ?>)">
                                        👁️ View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💳</div>
                    <div class="empty-state-text">No Leagues Found</div>
                    <div class="empty-state-subtext">No leagues match your current filters. Try adjusting your search criteria.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- League Payment Details Modal -->
<div id="leagueDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>💰 League Payment Details</span>
            <button class="modal-close" onclick="closeLeagueDetailsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="league-info-grid" id="leagueInfoGrid">
                <div class="league-info-item">
                    <span class="league-info-label">League Name</span>
                    <span class="league-info-value" id="detailLeagueName">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">Entry Price</span>
                    <span class="league-info-value" id="detailLeaguePrice">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">Total Players</span>
                    <span class="league-info-value" id="detailLeaguePlayers">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">Total Revenue</span>
                    <span class="league-info-value" id="detailLeagueRevenue" style="color: #28a745;">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">Owner</span>
                    <span class="league-info-value" id="detailLeagueOwner">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">System</span>
                    <span class="league-info-value" id="detailLeagueSystem">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">Status</span>
                    <span class="league-info-value" id="detailLeagueStatus">-</span>
                </div>
                <div class="league-info-item">
                    <span class="league-info-label">Created</span>
                    <span class="league-info-value" id="detailLeagueCreated">-</span>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 18px; font-weight: 600; color: #333; margin: 0;">
                    👥 Contributors (<span id="contributorsCount">0</span>)
                </h3>
                <button class="btn btn-secondary btn-sm" onclick="openEditPriceModal()">
                    ✏️ Edit Price
                </button>
            </div>
            
            <div id="contributorsContainer" class="contributors-grid">
                <div class="empty-state" style="padding: 40px 20px;">
                    <div class="empty-state-icon" style="font-size: 48px;">👥</div>
                    <div class="empty-state-text" style="font-size: 16px;">No Contributors</div>
                    <div class="empty-state-subtext">This league has no contributors yet.</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-info" onclick="closeLeagueDetailsModal()">Close</button>
        </div>
    </div>
</div>

<!-- Edit League Price Modal -->
<div id="editPriceModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <span>✏️ Edit League Price</span>
            <button class="modal-close" onclick="closeEditPriceModal()">&times;</button>
        </div>
        <form id="editPriceForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_league_price">
                <input type="hidden" name="league_id" id="editPriceLeagueId">
                
                <div class="info-card" style="border-left-color: #ffc107; background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05));">
                    <div class="info-card-title" style="color: #ffc107;">⚠️ Important Notice</div>
                    <div class="info-card-text">
                        Changing the league price will affect the total revenue calculation. Make sure all contributors are aware of any price changes. Set to $0.00 for a free league.
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">League Name</label>
                    <input type="text" id="editPriceLeagueName" class="form-control" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Entry Price ($) *</label>
                    <input 
                        type="number" 
                        name="price" 
                        id="editPriceValue" 
                        class="form-control" 
                        required 
                        min="0" 
                        step="0.01"
                        placeholder="Enter price (e.g., 25.00)"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Players</label>
                    <input type="text" id="editPricePlayers" class="form-control" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Total Revenue</label>
                    <input type="text" id="editPriceNewRevenue" class="form-control" readonly style="background: #d4edda; color: #28a745; font-weight: 700; font-size: 16px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeEditPriceModal()">Cancel</button>
                <button type="submit" class="btn btn-success">💾 Update Price</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentLeague = null;
    
    function viewLeagueDetails(leagueId) {
        fetch('?ajax=get_league_payments&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                currentLeague = data.league;
                
                // Update league info
                document.getElementById('detailLeagueName').textContent = data.league.name;
                
                const priceDisplay = data.league.price == 0 ? 'FREE' : '
                 + parseFloat(data.league.price).toFixed(2);
                document.getElementById('detailLeaguePrice').textContent = priceDisplay;
                
                document.getElementById('detailLeaguePlayers').textContent = data.league.num_of_players;
                
                const totalRevenue = parseFloat(data.league.price) * parseInt(data.league.num_of_players);
                document.getElementById('detailLeagueRevenue').textContent = '
                 + totalRevenue.toFixed(2);
                
                let ownerText = data.league.owner_name || 'N/A';
                if (data.league.other_owner_name) {
                    ownerText += ' & ' + data.league.other_owner_name;
                }
                document.getElementById('detailLeagueOwner').textContent = ownerText;
                
                document.getElementById('detailLeagueSystem').textContent = data.league.system;
                
                const statusBadge = data.league.activated == 1 ? 
                    '<span class="badge badge-success">Active</span>' : 
                    '<span class="badge badge-warning">Inactive</span>';
                document.getElementById('detailLeagueStatus').innerHTML = statusBadge;
                
                const createdDate = new Date(data.league.created_at);
                document.getElementById('detailLeagueCreated').textContent = createdDate.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                
                // Update contributors
                document.getElementById('contributorsCount').textContent = data.contributors.length;
                
                const container = document.getElementById('contributorsContainer');
                
                if (data.contributors.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state" style="padding: 40px 20px;">
                            <div class="empty-state-icon" style="font-size: 48px;">👥</div>
                            <div class="empty-state-text" style="font-size: 16px;">No Contributors</div>
                            <div class="empty-state-subtext">This league has no contributors yet.</div>
                        </div>
                    `;
                } else {
                    let html = '';
                    data.contributors.forEach(contributor => {
                        const roleBadge = contributor.role === 'Admin' ? 
                            '<span class="badge badge-info">Admin</span>' : 
                            '<span class="badge badge-primary">Contributor</span>';
                        
                        const paymentAmount = data.league.price == 0 ? 
                            '<span class="badge badge-success">FREE</span>' : 
                            '<span class="price-display">
                 + parseFloat(data.league.price).toFixed(2) + '</span>';
                        
                        html += `
                            <div class="contributor-card">
                                <div class="contributor-info">
                                    <div class="contributor-name">${contributor.username || 'N/A'}</div>
                                    <div class="contributor-meta">
                                        📧 ${contributor.email || 'N/A'} 
                                        ${contributor.phone_number ? ' | 📱 ' + contributor.phone_number : ''}
                                    </div>
                                    <div class="contributor-meta">
                                        🏆 Total Score: <strong>${contributor.total_score}</strong> | ${roleBadge}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Entry Fee</div>
                                    ${paymentAmount}
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                }
                
                document.getElementById('leagueDetailsModal').classList.add('active');
            })
            .catch(error => {
                console.error(error);
                alert('Error loading league details');
            });
    }
    
    function closeLeagueDetailsModal() {
        document.getElementById('leagueDetailsModal').classList.remove('active');
        currentLeague = null;
    }
    
    function openEditPriceModal() {
        if (!currentLeague) return;
        
        document.getElementById('editPriceLeagueId').value = currentLeague.id;
        document.getElementById('editPriceLeagueName').value = currentLeague.name;
        document.getElementById('editPriceValue').value = parseFloat(currentLeague.price).toFixed(2);
        document.getElementById('editPricePlayers').value = currentLeague.num_of_players;
        
        calculateNewRevenue();
        
        document.getElementById('editPriceModal').classList.add('active');
    }
    
    function closeEditPriceModal() {
        document.getElementById('editPriceModal').classList.remove('active');
    }
    
    function calculateNewRevenue() {
        const priceInput = document.getElementById('editPriceValue');
        const playersInput = document.getElementById('editPricePlayers');
        const revenueDisplay = document.getElementById('editPriceNewRevenue');
        
        const price = parseFloat(priceInput.value) || 0;
        const players = parseInt(playersInput.value) || 0;
        const newRevenue = price * players;
        
        revenueDisplay.value = '
                 + newRevenue.toFixed(2)';
    }
    
    // Add event listener for price input
    document.addEventListener('DOMContentLoaded', function() {
        const priceInput = document.getElementById('editPriceValue');
        if (priceInput) {
            priceInput.addEventListener('input', calculateNewRevenue);
        }
    });
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const detailsModal = document.getElementById('leagueDetailsModal');
        const priceModal = document.getElementById('editPriceModal');
        
        if (event.target === detailsModal) {
            closeLeagueDetailsModal();
        }
        if (event.target === priceModal) {
            closeEditPriceModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>