<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM leagues WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $league = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($league) {
                echo json_encode($league);
            } else {
                echo json_encode(['error' => 'League not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_details' && isset($_GET['id'])) {
        try {
            $id = $_GET['id'];
            
            // Get league basic info
            $stmt = $pdo->prepare("
                SELECT l.*, 
                       a1.username as owner_name,
                       a2.username as other_owner_name
                FROM leagues l
                LEFT JOIN accounts a1 ON l.owner = a1.id
                LEFT JOIN accounts a2 ON l.other_owner = a2.id
                WHERE l.id = ?
            ");
            $stmt->execute([$id]);
            $league = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$league) {
                echo json_encode(['error' => 'League not found']);
                exit();
            }
            
            // Get league contributors
            $stmt = $pdo->prepare("
                SELECT lc.*, a.username, a.email
                FROM league_contributors lc
                JOIN accounts a ON lc.user_id = a.id
                WHERE lc.league_id = ?
            ");
            $stmt->execute([$id]);
            $league['contributors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get league teams
            $stmt = $pdo->prepare("
                SELECT * FROM league_teams
                WHERE league_id = ?
                ORDER BY team_score DESC
            ");
            $stmt->execute([$id]);
            $league['teams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get league players
            $stmt = $pdo->prepare("
                SELECT * FROM league_players
                WHERE league_id = ?
                ORDER BY player_role, player_name
            ");
            $stmt->execute([$id]);
            $league['players'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get league roles/points
            $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
            $stmt->execute([$id]);
            $league['roles'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get matches count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM matches WHERE league_id = ?");
            $stmt->execute([$id]);
            $league['matches_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode($league);
            
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_accounts') {
        try {
            $stmt = $pdo->query("SELECT id, username, email FROM accounts WHERE activated = 1 ORDER BY username");
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($accounts);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create':
                    $pdo->beginTransaction();
                    
                    // Insert league
                    $stmt = $pdo->prepare("
                        INSERT INTO leagues (
                            name, owner, other_owner, system, 
                            triple_captain, bench_boost, wild_card, 
                            price, activated
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['owner'],
                        !empty($_POST['other_owner']) ? $_POST['other_owner'] : null,
                        $_POST['system'],
                        $_POST['triple_captain'],
                        $_POST['bench_boost'],
                        $_POST['wild_card'],
                        $_POST['price'],
                        isset($_POST['activated']) ? 1 : 0
                    ]);
                    
                    $league_id = $pdo->lastInsertId();
                    
                    // Insert default league roles
                    $stmt = $pdo->prepare("
                        INSERT INTO league_roles (league_id) VALUES (?)
                    ");
                    $stmt->execute([$league_id]);
                    
                    $pdo->commit();
                    $success_message = "League created successfully!";
                    break;
                    
                case 'update':
                    $stmt = $pdo->prepare("
                        UPDATE leagues SET 
                            name = ?, 
                            owner = ?, 
                            other_owner = ?, 
                            system = ?,
                            triple_captain = ?,
                            bench_boost = ?,
                            wild_card = ?,
                            price = ?,
                            activated = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['owner'],
                        !empty($_POST['other_owner']) ? $_POST['other_owner'] : null,
                        $_POST['system'],
                        $_POST['triple_captain'],
                        $_POST['bench_boost'],
                        $_POST['wild_card'],
                        $_POST['price'],
                        isset($_POST['activated']) ? 1 : 0,
                        $_POST['id']
                    ]);
                    $success_message = "League updated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM leagues WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = "League deleted successfully!";
                    break;
                    
                case 'toggle_activation':
                    $stmt = $pdo->prepare("UPDATE leagues SET activated = NOT activated WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = "League activation status updated!";
                    break;
                    
                case 'activate_pending':
                    $stmt = $pdo->prepare("UPDATE leagues SET activated = 1 WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = "League activated successfully!";
                    break;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch active leagues with search and filter functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$system = isset($_GET['system']) ? $_GET['system'] : '';

$where_conditions = ['l.activated = 1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(l.id = ? OR l.name LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
}

if (!empty($system)) {
    $where_conditions[] = "l.system = ?";
    $params[] = $system;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               a1.username as owner_name,
               a2.username as other_owner_name,
               (SELECT COUNT(*) FROM league_contributors WHERE league_id = l.id) as contributors_count
        FROM leagues l
        LEFT JOIN accounts a1 ON l.owner = a1.id
        LEFT JOIN accounts a2 ON l.other_owner = a2.id
        $where_clause
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($params);
    $leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $leagues = [];
}

// Fetch pending leagues (activated = 0)
$pending_search = isset($_GET['pending_search']) ? $_GET['pending_search'] : '';
$pending_where_conditions = ['l.activated = 0'];
$pending_params = [];

if (!empty($pending_search)) {
    $pending_where_conditions[] = "(l.id = ? OR l.name LIKE ?)";
    $pending_params[] = $pending_search;
    $pending_params[] = "%$pending_search%";
}

$pending_where_clause = 'WHERE ' . implode(' AND ', $pending_where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT l.*, 
               a1.username as owner_name,
               a1.email as owner_email,
               a2.username as other_owner_name
        FROM leagues l
        LEFT JOIN accounts a1 ON l.owner = a1.id
        LEFT JOIN accounts a2 ON l.other_owner = a2.id
        $pending_where_clause
        ORDER BY l.created_at DESC
    ");
    $stmt->execute($pending_params);
    $pending_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_error_message = "Database error: " . $e->getMessage();
    $pending_leagues = [];
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
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 40px 0 20px 0;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .section-title-main {
        font-size: 24px;
        font-weight: 700;
        color: #333;
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
    
    .btn-success {
        background: #28a745;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        background: #218838;
    }
    
    .btn-warning {
        background: #ffc107;
        color: #000;
    }
    
    .btn-warning:hover {
        background: #e0a800;
    }
    
    .filter-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #FFFFFF;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        flex: 1;
        min-width: 250px;
    }
    
    .search-bar input {
        flex: 1;
        border: none;
        outline: none;
        font-size: 14px;
        color: #333;
    }
    
    .search-bar input::placeholder {
        color: #999;
    }
    
    .search-icon {
        color: #1D60AC;
        font-size: 18px;
    }
    
    .filter-select {
        padding: 12px 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        background: #FFFFFF;
        color: #333;
        cursor: pointer;
        transition: border-color 0.3s ease;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: #1D60AC;
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
    
    .data-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
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
    
    .data-table.pending thead {
        background: linear-gradient(135deg, #F1A155, #e89944);
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
        background: rgba(29, 96, 172, 0.2);
        color: #1D60AC;
    }
    
    .badge-secondary {
        background: rgba(241, 161, 85, 0.2);
        color: #F1A155;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
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
        max-width: 700px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    
    .modal-content.large {
        max-width: 900px;
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
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
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
    
    .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }
    
    .form-check input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .form-check label {
        cursor: pointer;
        font-size: 14px;
        color: #333;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .detail-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #1D60AC;
    }
    
    .detail-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .detail-value {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin: 25px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .info-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid #F1A155;
    }
    
    .info-card-title {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .info-card-content {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 13px;
        color: #666;
    }
    
    .info-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
        font-size: 14px;
    }
    
    .stats-mini {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .stat-mini {
        background: rgba(29, 96, 172, 0.1);
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
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
        
        .filter-bar {
            flex-direction: column;
            width: 100%;
        }
        
        .search-bar {
            width: 100%;
        }
        
        .filter-select {
            width: 100%;
        }
        
        .table-container {
            overflow-x: scroll;
        }
        
        .modal-content {
            width: 95%;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">Leagues Management</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">
            ➕ Create New League
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <!-- Active Leagues Section -->
    <div class="section-header">
        <h2 class="section-title-main">Active Leagues</h2>
    </div>
    
    <div class="filter-bar">
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Search by ID or League Name..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <select id="systemFilter" class="filter-select">
            <option value="">All Systems</option>
            <option value="Budget" <?php echo $system === 'Budget' ? 'selected' : ''; ?>>Budget</option>
            <option value="No Limits" <?php echo $system === 'No Limits' ? 'selected' : ''; ?>>No Limits</option>
        </select>
    </div>
    
    <div class="data-card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>League Name</th>
                        <th>Owner</th>
                        <th>System</th>
                        <th>Players/Teams</th>
                        <th>Round</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($leagues)): ?>
                        <?php foreach ($leagues as $league): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($league['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($league['name']); ?></strong>
                                    <?php if ($league['contributors_count'] > 0): ?>
                                        <br><small style="color: #999;">👥 <?php echo $league['contributors_count']; ?> contributors</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($league['owner_name'] ?? 'N/A'); ?>
                                    <?php if ($league['other_owner_name']): ?>
                                        <br><small style="color: #999;">& <?php echo htmlspecialchars($league['other_owner_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $league['system'] === 'Budget' ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo htmlspecialchars($league['system']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stats-mini">
                                        <span class="stat-mini">⚽ <?php echo $league['num_of_players']; ?></span>
                                        <span class="stat-mini">🎯 <?php echo $league['num_of_teams']; ?></span>
                                    </div>
                                </td>
                                <td><strong>Round <?php echo $league['round']; ?></strong></td>
                                <td>EGP <?php echo number_format($league['price'], 2); ?></td>
                                <td>
                                    <span class="badge badge-success">Active</span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info btn-sm" onclick="viewLeague(<?php echo $league['id']; ?>)">👁️ View</button>
                                        <button class="btn btn-secondary btn-sm" onclick="editLeague(<?php echo $league['id']; ?>)">✏️ Edit</button>
                                        <button class="btn btn-warning btn-sm" onclick="toggleActivation(<?php echo $league['id']; ?>, '<?php echo htmlspecialchars(addslashes($league['name'])); ?>', 1)">
                                            ⏸️ Deactivate
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteLeague(<?php echo $league['id']; ?>, '<?php echo htmlspecialchars(addslashes($league['name'])); ?>')">🗑️ Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #999; padding: 30px;">No active leagues found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pending Leagues Section -->
    <div class="section-header">
        <h2 class="section-title-main">إفتح التتش يا كيمو</h2>
    </div>
    
    <div class="filter-bar">
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="pendingSearchInput" placeholder="Search pending leagues..." value="<?php echo htmlspecialchars($pending_search); ?>">
        </div>
    </div>
    
    <div class="data-card">
        <div class="table-container">
            <table class="data-table pending">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>League Name</th>
                        <th>Owner</th>
                        <th>Email</th>
                        <th>System</th>
                        <th>Price</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pending_leagues)): ?>
                        <?php foreach ($pending_leagues as $pending): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pending['id']); ?></td>
                                <td><?php echo htmlspecialchars($pending['name']); ?></td>
                                <td><?php echo htmlspecialchars($pending['owner_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pending['owner_email'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge <?php echo $pending['system'] === 'Budget' ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo htmlspecialchars($pending['system']); ?>
                                    </span>
                                </td>
                                <td>EGP <?php echo number_format($pending['price'], 2); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($pending['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info btn-sm" onclick="viewLeague(<?php echo $pending['id']; ?>)">👁️ View</button>
                                        <button class="btn btn-success btn-sm" onclick="activatePending(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars(addslashes($pending['name'])); ?>')">
                                            ✅ Activate
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteLeague(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars(addslashes($pending['name'])); ?>')">🗑️ Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 30px;">No pending league requests</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Create/Edit Modal -->
<div id="leagueModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Create New League</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="leagueForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="leagueId">
            <div class="form-group">
                <label class="form-label">League Name *</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Owner *</label>
                    <select name="owner" id="owner" class="form-control" required>
                        <option value="">Select Owner</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Co-Owner (Optional)</label>
                    <select name="other_owner" id="other_owner" class="form-control">
                        <option value="">Select Co-Owner</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">System *</label>
                    <select name="system" id="system" class="form-control" required>
                        <option value="No Limits">No Limits</option>
                        <option value="Budget">Budget</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" value="0.00">
                </div>
            </div>
            
            <div class="section-title">Power-ups</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Triple Captain</label>
                    <input type="number" name="triple_captain" id="triple_captain" class="form-control" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Bench Boost</label>
                    <input type="number" name="bench_boost" id="bench_boost" class="form-control" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Wild Card</label>
                    <input type="number" name="wild_card" id="wild_card" class="form-control" min="0" value="0">
                </div>
            </div>
            
            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" name="activated" id="activated" value="1">
                    <label for="activated">Activated</label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save League</button>
        </div>
    </form>
</div>
</div>
<!-- View Details Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <span>League Details</span>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="modal-body" id="viewModalBody">
            <!-- Content will be loaded dynamically -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>
<!-- Activate Pending Confirmation Modal -->
<div id="activateModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span>Confirm Activation</span>
            <button class="modal-close" onclick="closeActivateModal()">&times;</button>
        </div>
        <form id="activateForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="activate_pending">
                <input type="hidden" name="id" id="activateLeagueId">
                <p style="font-size: 14px; color: #333; margin: 0;" id="activateMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeActivateModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Activate League</button>
            </div>
        </form>
    </div>
</div>
<!-- Toggle Activation Confirmation Modal -->
<div id="toggleModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span>Confirm Action</span>
            <button class="modal-close" onclick="closeToggleModal()">&times;</button>
        </div>
        <form id="toggleForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="toggle_activation">
                <input type="hidden" name="id" id="toggleLeagueId">
                <p style="font-size: 14px; color: #333; margin: 0;" id="toggleMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeToggleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span>Confirm Delete</span>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form id="deleteForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteLeagueId">
                <p style="font-size: 14px; color: #333; margin: 0;">Are you sure you want to delete league <strong id="deleteLeagueName"></strong>? This action cannot be undone and will delete all associated data.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>
<script>
    // Load accounts for owner selection
    function loadAccounts() {
        fetch('?ajax=get_accounts')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading accounts:', data.error);
                    return;
                }
                const ownerSelect = document.getElementById('owner');
                const otherOwnerSelect = document.getElementById('other_owner');
                
                ownerSelect.innerHTML = '<option value="">Select Owner</option>';
                otherOwnerSelect.innerHTML = '<option value="">Select Co-Owner</option>';
                
                data.forEach(account => {
                    const option1 = new Option(account.username + ' (' + account.email + ')', account.id);
                    const option2 = new Option(account.username + ' (' + account.email + ')', account.id);
                    ownerSelect.add(option1);
                    otherOwnerSelect.add(option2);
                });
            })
            .catch(error => {
                console.error('Error loading accounts:', error);
            });
    }
    
    // Search and filter functionality for active leagues
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    
    document.getElementById('systemFilter').addEventListener('change', applyFilters);
    
    function applyFilters() {
        const search = document.getElementById('searchInput').value;
        const system = document.getElementById('systemFilter').value;
        
        const url = new URL(window.location.href);
        url.search = '';
        
        if (search) url.searchParams.set('search', search);
        if (system) url.searchParams.set('system', system);
        
        window.location.href = url;
    }
    
    // Search functionality for pending leagues
    document.getElementById('pendingSearchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            applyPendingFilters();
        }
    });
    
    function applyPendingFilters() {
        const pendingSearch = document.getElementById('pendingSearchInput').value;
        
        const url = new URL(window.location.href);
        
        if (pendingSearch) {
            url.searchParams.set('pending_search', pendingSearch);
        } else {
            url.searchParams.delete('pending_search');
        }
        
        window.location.href = url;
    }
    
    function openCreateModal() {
        loadAccounts();
        document.getElementById('modalTitle').textContent = 'Create New League';
        document.getElementById('formAction').value = 'create';
        document.getElementById('leagueForm').reset();
        document.getElementById('leagueId').value = '';
        document.getElementById('leagueModal').classList.add('active');
    }
    
    function editLeague(id) {
        loadAccounts();
        fetch('?ajax=get_league&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                document.getElementById('modalTitle').textContent = 'Edit League';
                document.getElementById('formAction').value = 'update';
                document.getElementById('leagueId').value = data.id;
                document.getElementById('name').value = data.name;
                
                // Wait a bit for accounts to load
                setTimeout(() => {
                    document.getElementById('owner').value = data.owner;
                    document.getElementById('other_owner').value = data.other_owner || '';
                }, 300);
                
                document.getElementById('system').value = data.system;
                document.getElementById('triple_captain').value = data.triple_captain;
                document.getElementById('bench_boost').value = data.bench_boost;
                document.getElementById('wild_card').value = data.wild_card;
                document.getElementById('price').value = data.price;
                document.getElementById('activated').checked = data.activated == 1;
                document.getElementById('leagueModal').classList.add('active');
            })
            .catch(error => {
                alert('Error loading league data');
                console.error(error);
            });
    }
    
    function viewLeague(id) {
        fetch('?ajax=get_league_details&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                let html = '<div class="details-grid">';
                html += `<div class="detail-item">
                    <div class="detail-label">League ID</div>
                    <div class="detail-value">${data.id}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">League Name</div>
                    <div class="detail-value">${data.name}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">System</div>
                    <div class="detail-value">
                        <span class="badge ${data.system === 'Budget' ? 'badge-primary' : 'badge-secondary'}">
                            ${data.system}
                        </span>
                    </div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Current Round</div>
                    <div class="detail-value">Round ${data.round}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Price</div>
                    <div class="detail-value">${parseFloat(data.price).toFixed(2)}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="badge ${data.activated == 1 ? 'badge-success' : 'badge-warning'}">
                            ${data.activated == 1 ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Total Players</div>
                    <div class="detail-value">${data.num_of_players}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Total Teams</div>
                    <div class="detail-value">${data.num_of_teams}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Matches Played</div>
                    <div class="detail-value">${data.matches_count}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Created At</div>
                    <div class="detail-value">${new Date(data.created_at).toLocaleDateString()}</div>
                </div>`;
                html += '</div>';
                
                // Owners Section
                html += '<div class="section-title">Ownership</div>';
                html += '<div class="info-card">';
                html += '<div class="info-card-content">';
                html += `<div class="info-item"><strong>Owner:</strong> ${data.owner_name || 'N/A'}</div>`;
                if (data.other_owner_name) {
                    html += `<div class="info-item"><strong>Co-Owner:</strong> ${data.other_owner_name}</div>`;
                }
                html += '</div></div>';
                
                // Power-ups Section
                html += '<div class="section-title">Power-ups</div>';
                html += '<div class="details-grid">';
                html += `<div class="detail-item">
                    <div class="detail-label">Triple Captain</div>
                    <div class="detail-value">${data.triple_captain}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Bench Boost</div>
                    <div class="detail-value">${data.bench_boost}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Wild Card</div>
                    <div class="detail-value">${data.wild_card}</div>
                </div>`;
                html += '</div>';
                
                // Contributors Section
                if (data.contributors && data.contributors.length > 0) {
                    html += '<div class="section-title">Contributors (' + data.contributors.length + ')</div>';
                    data.contributors.forEach(contrib => {
                        html += `<div class="info-card">
                            <div class="info-card-title">${contrib.username}</div>
                            <div class="info-card-content">
                                <div class="info-item"><strong>Email:</strong> ${contrib.email}</div>
                                <div class="info-item"><strong>Role:</strong> 
                                    <span class="badge badge-info">${contrib.role}</span>
                                </div>
                                <div class="info-item"><strong>Total Score:</strong> ${contrib.total_score}</div>
                            </div>
                        </div>`;
                    });
                }
                
                // Teams Section
                if (data.teams && data.teams.length > 0) {
                    html += '<div class="section-title">Teams (' + data.teams.length + ')</div>';
                    data.teams.forEach(team => {
                        html += `<div class="info-card">
                            <div class="info-card-title">${team.team_name}</div>
                            <div class="info-card-content">
                                <div class="info-item"><strong>Team Score:</strong> ${team.team_score}</div>
                            </div>
                        </div>`;
                    });
                }
                
                // Players Section
                if (data.players && data.players.length > 0) {
                    html += '<div class="section-title">Players (' + data.players.length + ')</div>';
                    const playersByRole = {
                        'GK': [],
                        'DEF': [],
                        'MID': [],
                        'ATT': []
                    };
                    data.players.forEach(player => {
                        playersByRole[player.player_role].push(player);
                    });
                    
                    // Create a map of team_id to team_name from data.teams
                    const teamMap = {};
                    if (data.teams && data.teams.length > 0) {
                        data.teams.forEach(team => {
                            teamMap[team.id] = team.team_name;
                        });
                    }
                    
                    Object.keys(playersByRole).forEach(role => {
                        if (playersByRole[role].length > 0) {
                            html += `<div style="margin-bottom: 15px;">
                                <strong style="color: #1D60AC; font-size: 14px;">${role} (${playersByRole[role].length})</strong>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">`;
                            playersByRole[role].forEach(player => {
                                const teamName = teamMap[player.team_id] || 'N/A';
                                let playerDisplay = `${player.player_name} (${teamName})`;
                                if (data.system === 'Budget') {
                                    playerDisplay += ` - ${parseFloat(player.player_price).toFixed(2)}M`;
                                }
                                html += `<span class="badge badge-primary">${playerDisplay}</span>`;
                            });
                            html += '</div></div>';
                        }
                    });
                }
                
                // League Roles/Points Section
                if (data.roles) {
                    html += '<div class="section-title">Points System</div>';
                    html += '<div class="details-grid">';
                    html += `<div class="detail-item">
                        <div class="detail-label">GK Save Penalty</div>
                        <div class="detail-value">${data.roles.gk_save_penalty}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">GK Score</div>
                        <div class="detail-value">${data.roles.gk_score}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">GK Assist</div>
                        <div class="detail-value">${data.roles.gk_assist}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">GK Clean Sheet</div>
                        <div class="detail-value">${data.roles.gk_clean_sheet}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">DEF Clean Sheet</div>
                        <div class="detail-value">${data.roles.def_clean_sheet}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">DEF Assist</div>
                        <div class="detail-value">${data.roles.def_assist}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">DEF Score</div>
                        <div class="detail-value">${data.roles.def_score}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">MID Assist</div>
                        <div class="detail-value">${data.roles.mid_assist}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">MID Score</div>
                        <div class="detail-value">${data.roles.mid_score}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">FOR Score</div>
                        <div class="detail-value">${data.roles.for_score}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">FOR Assist</div>
                        <div class="detail-value">${data.roles.for_assist}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">Miss Penalty</div>
                        <div class="detail-value">${data.roles.miss_penalty}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">Yellow Card</div>
                        <div class="detail-value">${data.roles.yellow_card}</div>
                    </div>`;
                    html += `<div class="detail-item">
                        <div class="detail-label">Red Card</div>
                        <div class="detail-value">${data.roles.red_card}</div>
                    </div>`;
                    html += '</div>';
                }
                
                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModal').classList.add('active');
            })
            .catch(error => {
                alert('Error loading league details');
                console.error(error);
            });
    }
    
    function activatePending(id, name) {
        document.getElementById('activateLeagueId').value = id;
        document.getElementById('activateMessage').textContent = 
            `Are you sure you want to activate league "${name}"? This will move it to the active leagues list.`;
        document.getElementById('activateModal').classList.add('active');
    }
    
    function toggleActivation(id, name, currentStatus) {
        document.getElementById('toggleLeagueId').value = id;
        const action = currentStatus ? 'deactivate' : 'activate';
        document.getElementById('toggleMessage').textContent = 
            `Are you sure you want to ${action} league "${name}"?`;
        document.getElementById('toggleModal').classList.add('active');
    }
    
    function deleteLeague(id, name) {
        document.getElementById('deleteLeagueId').value = id;
        document.getElementById('deleteLeagueName').textContent = name;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('leagueModal').classList.remove('active');
    }
    
    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
    }
    
    function closeActivateModal() {
        document.getElementById('activateModal').classList.remove('active');
    }
    
    function closeToggleModal() {
        document.getElementById('toggleModal').classList.remove('active');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const leagueModal = document.getElementById('leagueModal');
        const viewModal = document.getElementById('viewModal');
        const activateModal = document.getElementById('activateModal');
        const toggleModal = document.getElementById('toggleModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target === leagueModal) {
            closeModal();
        }
        if (event.target === viewModal) {
            closeViewModal();
        }
        if (event.target === activateModal) {
            closeActivateModal();
        }
        if (event.target === toggleModal) {
            closeToggleModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
</script>
<?php include 'includes/footer.php'; ?>