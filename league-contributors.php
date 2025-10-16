<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests for getting contributor data
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_contributor' && isset($_GET['user_id']) && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lc.*,
                    a.username,
                    a.email,
                    l.name as league_name
                FROM league_contributors lc
                JOIN accounts a ON lc.user_id = a.id
                JOIN leagues l ON lc.league_id = l.id
                WHERE lc.user_id = ? AND lc.league_id = ?
            ");
            $stmt->execute([$_GET['user_id'], $_GET['league_id']]);
            $contributor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contributor) {
                echo json_encode($contributor);
            } else {
                echo json_encode(['error' => 'Contributor not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_standings' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lc.user_id,
                    lc.total_score,
                    lc.role,
                    a.username,
                    a.email
                FROM league_contributors lc
                JOIN accounts a ON lc.user_id = a.id
                WHERE lc.league_id = ?
                ORDER BY lc.total_score DESC
            ");
            $stmt->execute([$_GET['league_id']]);
            $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($standings);
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
    
    if ($_GET['ajax'] === 'get_available_users' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, username, email 
                FROM accounts 
                WHERE id NOT IN (
                    SELECT user_id FROM league_contributors WHERE league_id = ?
                )
                AND activated = 1
                ORDER BY username
            ");
            $stmt->execute([$_GET['league_id']]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($users);
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
                    $stmt = $pdo->prepare("INSERT INTO league_contributors (user_id, league_id, role, total_score) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['user_id'],
                        $_POST['league_id'],
                        $_POST['role'],
                        $_POST['total_score'] ?? 0
                    ]);
                    $success_message = "Contributor added successfully!";
                    break;
                    
                case 'update':
                    $stmt = $pdo->prepare("UPDATE league_contributors SET role = ?, total_score = ? WHERE user_id = ? AND league_id = ?");
                    $stmt->execute([
                        $_POST['role'],
                        $_POST['total_score'],
                        $_POST['user_id'],
                        $_POST['league_id']
                    ]);
                    $success_message = "Contributor updated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM league_contributors WHERE user_id = ? AND league_id = ?");
                    $stmt->execute([$_POST['user_id'], $_POST['league_id']]);
                    $success_message = "Contributor removed successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch contributors with search and filter functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$league_filter = isset($_GET['league_filter']) ? $_GET['league_filter'] : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(a.username LIKE ? OR a.email LIKE ? OR l.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($league_filter)) {
    $where_clauses[] = "lc.league_id = ?";
    $params[] = $league_filter;
}

if (!empty($role_filter)) {
    $where_clauses[] = "lc.role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    // Fetch all contributors
    $stmt = $pdo->prepare("
        SELECT 
            lc.*,
            a.username,
            a.email,
            l.name as league_name,
            l.activated as league_activated
        FROM league_contributors lc
        JOIN accounts a ON lc.user_id = a.id
        JOIN leagues l ON lc.league_id = l.id
        $where_clause
        ORDER BY l.name, lc.total_score DESC
    ");
    $stmt->execute($params);
    $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all leagues for filter dropdown
    $stmt = $pdo->query("SELECT id, name FROM leagues ORDER BY name");
    $leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all leagues with owners for standings selection
    $standings_search = isset($_GET['standings_search']) ? $_GET['standings_search'] : '';
    $standings_where = '';
    $standings_params = [];
    
    if (!empty($standings_search)) {
        $standings_where = "WHERE l.name LIKE ? OR a.username LIKE ? OR a2.username LIKE ?";
        $standings_params = ["%$standings_search%", "%$standings_search%", "%$standings_search%"];
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            l.id as league_id,
            l.name as league_name,
            l.activated as league_activated,
            l.num_of_players,
            l.num_of_teams,
            l.system,
            a.username as owner_name,
            a2.username as other_owner_name
        FROM leagues l
        LEFT JOIN accounts a ON l.owner = a.id
        LEFT JOIN accounts a2 ON l.other_owner = a2.id
        $standings_where
        ORDER BY l.name
    ");
    $stmt->execute($standings_params);
    $leagues_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $contributors = [];
    $leagues = [];
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
    
    .filters-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        background: #FFFFFF;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
    
    .filter-control {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }
    
    .filter-control:focus {
        outline: none;
        border-color: #1D60AC;
        box-shadow: 0 0 0 3px rgba(29, 96, 172, 0.1);
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
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .data-card {
        background: #FFFFFF;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 20px;
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
    }
    
    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 14px;
    }
    
    .rank-1 {
        background: linear-gradient(135deg, #FFD700, #FFA500);
        color: #FFFFFF;
    }
    
    .rank-2 {
        background: linear-gradient(135deg, #C0C0C0, #A8A8A8);
        color: #FFFFFF;
    }
    
    .rank-3 {
        background: linear-gradient(135deg, #CD7F32, #B8860B);
        color: #FFFFFF;
    }
    
    .rank-other {
        background: #f8f9fa;
        color: #666;
    }
    
    .standings-header {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
        padding: 20px 25px;
        font-size: 20px;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .standings-header-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .standings-header-title {
        font-size: 22px;
        font-weight: 700;
    }
    
    .standings-header-meta {
        font-size: 13px;
        opacity: 0.9;
        display: flex;
        gap: 20px;
    }
    
    .standings-back-btn {
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
    
    .standings-back-btn:hover {
        background: rgba(255, 255, 255, 0.3);
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
        max-width: 600px;
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
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
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
        
        .filters-bar {
            flex-direction: column;
        }
        
        .filter-group {
            min-width: 100%;
        }
        
        .table-container {
            overflow-x: scroll;
        }
        
        .modal-content {
            width: 95%;
        }
        
        .tabs {
            overflow-x: auto;
        }
        
        .standings-header-meta {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">League Contributors Management</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">
            ➕ Add Contributor
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('all-contributors')">📋 All Contributors</button>
        <button class="tab" onclick="switchTab('standings')">🏆 League Standings</button>
    </div>
    
    <!-- All Contributors Tab -->
    <div id="all-contributors" class="tab-content active">
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Search</label>
                <input type="text" id="searchInput" class="filter-control" placeholder="Search by username, email or league..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">League</label>
                <select id="leagueFilter" class="filter-control">
                    <option value="">All Leagues</option>
                    <?php foreach ($leagues as $league): ?>
                        <option value="<?php echo $league['id']; ?>" <?php echo $league_filter == $league['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($league['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Role</label>
                <select id="roleFilter" class="filter-control">
                    <option value="">All Roles</option>
                    <option value="Admin" <?php echo $role_filter == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="Contributor" <?php echo $role_filter == 'Contributor' ? 'selected' : ''; ?>>Contributor</option>
                </select>
            </div>
            
            <div class="filter-group" style="display: flex; align-items: flex-end;">
                <button class="btn btn-primary" onclick="applyFilters()">🔍 Apply Filters</button>
            </div>
        </div>
        
        <div class="data-card">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>League</th>
                            <th>Role</th>
                            <th>Total Score</th>
                            <th>League Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($contributors)): ?>
                            <?php foreach ($contributors as $contributor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contributor['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($contributor['username']); ?></td>
                                    <td><?php echo htmlspecialchars($contributor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($contributor['league_name']); ?></td>
                                    <td>
                                        <?php if ($contributor['role'] == 'Admin'): ?>
                                            <span class="badge badge-primary">Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Contributor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo number_format($contributor['total_score']); ?></strong></td>
                                    <td>
                                        <?php if ($contributor['league_activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-secondary btn-sm" onclick="editContributor(<?php echo $contributor['user_id']; ?>, <?php echo $contributor['league_id']; ?>)">✏️ Edit</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteContributor(<?php echo $contributor['user_id']; ?>, <?php echo $contributor['league_id']; ?>, '<?php echo htmlspecialchars($contributor['username']); ?>', '<?php echo htmlspecialchars($contributor['league_name']); ?>')">🗑️ Remove</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #999; padding: 30px;">No contributors found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- League Standings Tab -->
    <div id="standings" class="tab-content">
        <!-- League Selection View -->
        <div id="leagueSelectionView">
            <div class="search-bar-standalone">
                <div class="search-input-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="standingsSearch" placeholder="Search leagues by name or owner..." value="<?php echo htmlspecialchars($standings_search); ?>" onkeypress="if(event.key==='Enter') searchLeagues()">
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
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($league['system']); ?></span></td>
                                        <td>
                                            <?php if ($league['league_activated']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Inactive</span>
                                                <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="navigateToStandings(<?php echo $league['league_id']; ?>)">
                                                🎯 Navigate
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: #999; padding: 30px;">No leagues found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- League Standings View (Initially Hidden) -->
        <div id="leagueStandingsView" style="display: none;">
            <div class="data-card">
                <div class="standings-header">
                    <div class="standings-header-info">
                        <div class="standings-header-title" id="standingsLeagueName">League Name</div>
                        <div class="standings-header-meta">
                            <span id="standingsLeagueOwner">Owner: N/A</span>
                            <span id="standingsLeaguePlayers">Players: 0</span>
                            <span id="standingsLeagueSystem">System: N/A</span>
                            <span id="standingsLeagueStatus">Status: N/A</span>
                        </div>
                    </div>
                    <button class="standings-back-btn" onclick="backToLeagueSelection()">
                        ← Back to Leagues
                    </button>
                </div>
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
                        <tbody id="standingsTableBody">
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px; color: #999;">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="contributorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Add New Contributor</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="contributorForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create">
                
                <div class="form-group" id="userSelectGroup">
                    <label class="form-label">User *</label>
                    <select name="user_id" id="user_id" class="form-control" required>
                        <option value="">Select User</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">League *</label>
                    <select name="league_id" id="league_id" class="form-control" required onchange="loadAvailableUsers()">
                        <option value="">Select League</option>
                        <?php foreach ($leagues as $league): ?>
                            <option value="<?php echo $league['id']; ?>"><?php echo htmlspecialchars($league['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="Contributor">Contributor</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Total Score</label>
                    <input type="number" name="total_score" id="total_score" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Contributor</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <span>Confirm Remove</span>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form id="deleteForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <input type="hidden" name="league_id" id="deleteLeagueId">
                <p style="font-size: 14px; color: #333; margin: 0;">
                    Are you sure you want to remove <strong id="deleteUsername"></strong> from league <strong id="deleteLeagueName"></strong>? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Remove</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        // Reset standings view when switching to standings tab
        if (tabName === 'standings') {
            backToLeagueSelection();
        }
    }
    
    function applyFilters() {
        const search = document.getElementById('searchInput').value;
        const league = document.getElementById('leagueFilter').value;
        const role = document.getElementById('roleFilter').value;
        
        let url = window.location.pathname + '?';
        const params = [];
        
        if (search) params.push('search=' + encodeURIComponent(search));
        if (league) params.push('league_filter=' + encodeURIComponent(league));
        if (role) params.push('role_filter=' + encodeURIComponent(role));
        
        url += params.join('&');
        window.location.href = url;
    }
    
    function searchLeagues() {
        const search = document.getElementById('standingsSearch').value;
        let url = window.location.pathname + '?';
        
        if (search) {
            url += 'standings_search=' + encodeURIComponent(search);
        }
        
        window.location.href = url;
    }
    
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Add New Contributor';
        document.getElementById('formAction').value = 'create';
        document.getElementById('contributorForm').reset();
        document.getElementById('userSelectGroup').style.display = 'block';
        document.getElementById('user_id').innerHTML = '<option value="">Select League First</option>';
        document.getElementById('contributorModal').classList.add('active');
    }
    
    function loadAvailableUsers() {
        const leagueId = document.getElementById('league_id').value;
        const userSelect = document.getElementById('user_id');
        
        if (!leagueId) {
            userSelect.innerHTML = '<option value="">Select League First</option>';
            return;
        }
        
        userSelect.innerHTML = '<option value="">Loading...</option>';
        
        fetch('?ajax=get_available_users&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    userSelect.innerHTML = '<option value="">Error loading users</option>';
                    return;
                }
                
                if (data.length === 0) {
                    userSelect.innerHTML = '<option value="">No available users</option>';
                    return;
                }
                
                let html = '<option value="">Select User</option>';
                data.forEach(user => {
                    html += `<option value="${user.id}">${user.username} (${user.email})</option>`;
                });
                userSelect.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                userSelect.innerHTML = '<option value="">Error loading users</option>';
            });
    }
    
    function editContributor(userId, leagueId) {
        fetch('?ajax=get_contributor&user_id=' + userId + '&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('modalTitle').textContent = 'Edit Contributor';
                document.getElementById('formAction').value = 'update';
                document.getElementById('userSelectGroup').style.display = 'none';
                
                // Create hidden input for user_id since we're hiding the select
                let userIdInput = document.getElementById('hidden_user_id');
                if (!userIdInput) {
                    userIdInput = document.createElement('input');
                    userIdInput.type = 'hidden';
                    userIdInput.name = 'user_id';
                    userIdInput.id = 'hidden_user_id';
                    document.getElementById('contributorForm').appendChild(userIdInput);
                }
                userIdInput.value = data.user_id;
                
                document.getElementById('league_id').value = data.league_id;
                document.getElementById('role').value = data.role;
                document.getElementById('total_score').value = data.total_score;
                
                document.getElementById('contributorModal').classList.add('active');
            })
            .catch(error => {
                alert('Error loading contributor data');
                console.error(error);
            });
    }
    
    function deleteContributor(userId, leagueId, username, leagueName) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteLeagueId').value = leagueId;
        document.getElementById('deleteUsername').textContent = username;
        document.getElementById('deleteLeagueName').textContent = leagueName;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    function navigateToStandings(leagueId) {
        // Hide league selection view
        document.getElementById('leagueSelectionView').style.display = 'none';
        
        // Show standings view
        document.getElementById('leagueStandingsView').style.display = 'block';
        
        // Load league info
        fetch('?ajax=get_league_info&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    backToLeagueSelection();
                    return;
                }
                
                document.getElementById('standingsLeagueName').textContent = data.name;
                document.getElementById('standingsLeagueOwner').textContent = 'Owner: ' + (data.owner_name || 'N/A') + 
                    (data.other_owner_name ? ' & ' + data.other_owner_name : '');
                document.getElementById('standingsLeaguePlayers').textContent = 'Players: ' + data.num_of_players;
                document.getElementById('standingsLeagueSystem').textContent = 'System: ' + data.system;
                
                const statusBadge = data.activated == 1 ? 
                    '<span class="badge badge-success">Active</span>' : 
                    '<span class="badge badge-warning">Inactive</span>';
                document.getElementById('standingsLeagueStatus').innerHTML = 'Status: ' + statusBadge;
            })
            .catch(error => {
                console.error(error);
                alert('Error loading league information');
                backToLeagueSelection();
            });
        
        // Load standings
        loadStandings(leagueId);
    }
    
    function backToLeagueSelection() {
        document.getElementById('leagueSelectionView').style.display = 'block';
        document.getElementById('leagueStandingsView').style.display = 'none';
    }
    
    function loadStandings(leagueId) {
        const tbody = document.getElementById('standingsTableBody');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_standings&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No contributors in this league</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach((contributor, index) => {
                    const rank = index + 1;
                    let rankClass = 'rank-other';
                    let rankIcon = rank;
                    
                    if (rank === 1) {
                        rankClass = 'rank-1';
                        rankIcon = '🥇';
                    } else if (rank === 2) {
                        rankClass = 'rank-2';
                        rankIcon = '🥈';
                    } else if (rank === 3) {
                        rankClass = 'rank-3';
                        rankIcon = '🥉';
                    }
                    
                    const roleClass = contributor.role === 'Admin' ? 'badge-primary' : 'badge-secondary';
                    
                    html += `
                        <tr>
                            <td>
                                <span class="rank-badge ${rankClass}">${rankIcon}</span>
                            </td>
                            <td><strong>${contributor.username}</strong></td>
                            <td>${contributor.email}</td>
                            <td><span class="badge ${roleClass}">${contributor.role}</span></td>
                            <td><strong style="font-size: 16px; color: #1D60AC;">${Number(contributor.total_score).toLocaleString()}</strong></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #dc3545;">Error loading standings</td></tr>';
            });
    }
    
    function closeModal() {
        document.getElementById('contributorModal').classList.remove('active');
        // Remove hidden user_id input if it exists
        const hiddenInput = document.getElementById('hidden_user_id');
        if (hiddenInput) {
            hiddenInput.remove();
        }
        // Show user select group again
        document.getElementById('userSelectGroup').style.display = 'block';
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const contributorModal = document.getElementById('contributorModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target === contributorModal) {
            closeModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>