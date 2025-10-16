<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_roles' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM league_roles WHERE league_id = ?");
            $stmt->execute([$_GET['league_id']]);
            $roles = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($roles) {
                echo json_encode($roles);
            } else {
                // Return default values if no roles exist
                echo json_encode([
                    'league_id' => $_GET['league_id'],
                    'gk_save_penalty' => 0,
                    'gk_score' => 0,
                    'gk_assist' => 0,
                    'gk_clean_sheet' => 0,
                    'def_clean_sheet' => 0,
                    'def_assist' => 0,
                    'def_score' => 0,
                    'mid_assist' => 0,
                    'mid_score' => 0,
                    'miss_penalty' => 0,
                    'for_score' => 0,
                    'for_assist' => 0,
                    'yellow_card' => 0,
                    'red_card' => 0
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_players' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    lt.team_name
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.league_id = ?
                ORDER BY lp.player_role, lp.player_name
            ");
            $stmt->execute([$_GET['league_id']]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($players);
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
    
    if ($_GET['ajax'] === 'get_player' && isset($_GET['player_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    lt.team_name
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                WHERE lp.player_id = ?
            ");
            $stmt->execute([$_GET['player_id']]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($player) {
                echo json_encode($player);
            } else {
                echo json_encode(['error' => 'Player not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_league_teams' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, team_name FROM league_teams WHERE league_id = ? ORDER BY team_name");
            $stmt->execute([$_GET['league_id']]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($teams);
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
                case 'update_roles':
                    // Check if roles exist
                    $stmt = $pdo->prepare("SELECT league_id FROM league_roles WHERE league_id = ?");
                    $stmt->execute([$_POST['league_id']]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update existing roles
                        $stmt = $pdo->prepare("
                            UPDATE league_roles SET 
                                gk_save_penalty = ?, gk_score = ?, gk_assist = ?, gk_clean_sheet = ?,
                                def_clean_sheet = ?, def_assist = ?, def_score = ?,
                                mid_assist = ?, mid_score = ?, miss_penalty = ?,
                                for_score = ?, for_assist = ?,
                                yellow_card = ?, red_card = ?
                            WHERE league_id = ?
                        ");
                        $stmt->execute([
                            $_POST['gk_save_penalty'], $_POST['gk_score'], $_POST['gk_assist'], $_POST['gk_clean_sheet'],
                            $_POST['def_clean_sheet'], $_POST['def_assist'], $_POST['def_score'],
                            $_POST['mid_assist'], $_POST['mid_score'], $_POST['miss_penalty'],
                            $_POST['for_score'], $_POST['for_assist'],
                            $_POST['yellow_card'], $_POST['red_card'],
                            $_POST['league_id']
                        ]);
                    } else {
                        // Insert new roles
                        $stmt = $pdo->prepare("
                            INSERT INTO league_roles (
                                league_id, gk_save_penalty, gk_score, gk_assist, gk_clean_sheet,
                                def_clean_sheet, def_assist, def_score,
                                mid_assist, mid_score, miss_penalty,
                                for_score, for_assist,
                                yellow_card, red_card
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_POST['league_id'],
                            $_POST['gk_save_penalty'], $_POST['gk_score'], $_POST['gk_assist'], $_POST['gk_clean_sheet'],
                            $_POST['def_clean_sheet'], $_POST['def_assist'], $_POST['def_score'],
                            $_POST['mid_assist'], $_POST['mid_score'], $_POST['miss_penalty'],
                            $_POST['for_score'], $_POST['for_assist'],
                            $_POST['yellow_card'], $_POST['red_card']
                        ]);
                    }
                    $success_message = "League roles updated successfully!";
                    break;
                    
                case 'update_player':
                    $stmt = $pdo->prepare("
                        UPDATE league_players 
                        SET player_name = ?, player_role = ?, player_price = ?, team_id = ?
                        WHERE player_id = ?
                    ");
                    $stmt->execute([
                        $_POST['player_name'],
                        $_POST['player_role'],
                        $_POST['player_price'],
                        $_POST['team_id'] ?: null,
                        $_POST['player_id']
                    ]);
                    $success_message = "Player updated successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
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
    
    .badge-gk {
        background: rgba(255, 193, 7, 0.2);
        color: #856404;
    }
    
    .badge-def {
        background: rgba(40, 167, 69, 0.2);
        color: #155724;
    }
    
    .badge-mid {
        background: rgba(0, 123, 255, 0.2);
        color: #004085;
    }
    
    .badge-att {
        background: rgba(220, 53, 69, 0.2);
        color: #721c24;
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
    
    .roles-form {
        padding: 25px;
    }
    
    .form-section {
        margin-bottom: 30px;
    }
    
    .form-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #1D60AC;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
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
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
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
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
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
        
        .modal-content {
            width: 95%;
        }
        
        .tabs {
            overflow-x: auto;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .header-meta {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">League Roles & Players Management</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('league-selection')">🏆 Select League</button>
        <button class="tab tab-disabled" id="rolesTab" disabled>⚙️ Roles & Points</button>
        <button class="tab tab-disabled" id="playersTab" disabled>⚽ Players Management</button>
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
                                        <button class="btn btn-primary btn-sm" onclick="selectLeague(<?php echo $league['league_id']; ?>)">
                                            🎯 Select
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
    
    <!-- Roles & Points Tab -->
    <div id="roles-points" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="rolesLeagueName">Select a league to manage roles</div>
                    <div class="header-meta" id="rolesLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">📋 About League Roles & Points</div>
                <div class="info-card-text">
                    Configure point values for different player actions in this league. These values determine how many points players earn for goals, assists, clean sheets, and other actions during matches.
                </div>
            </div>
            
            <form id="rolesForm" method="POST" class="roles-form">
                <input type="hidden" name="action" value="update_roles">
                <input type="hidden" name="league_id" id="rolesLeagueId">
                
                <div class="form-section">
                    <div class="form-section-title">🧤 Goalkeeper Points</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Save Penalty</label>
                            <input type="number" name="gk_save_penalty" id="gk_save_penalty" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Score Goal</label>
                            <input type="number" name="gk_score" id="gk_score" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assist</label>
                            <input type="number" name="gk_assist" id="gk_assist" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clean Sheet</label>
                            <input type="number" name="gk_clean_sheet" id="gk_clean_sheet" class="form-control" value="0" step="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">🛡️ Defender Points</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Clean Sheet</label>
                            <input type="number" name="def_clean_sheet" id="def_clean_sheet" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assist</label>
                            <input type="number" name="def_assist" id="def_assist" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Score Goal</label>
                            <input type="number" name="def_score" id="def_score" class="form-control" value="0" step="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">⚡ Midfielder Points</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Assist</label>
                            <input type="number" name="mid_assist" id="mid_assist" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Score Goal</label>
                            <input type="number" name="mid_score" id="mid_score" class="form-control" value="0" step="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">🎯 Forward Points</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Score Goal</label>
                            <input type="number" name="for_score" id="for_score" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assist</label>
                            <input type="number" name="for_assist" id="for_assist" class="form-control" value="0" step="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="form-section-title">⚠️ Penalty & Cards</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Miss Penalty</label>
                            <input type="number" name="miss_penalty" id="miss_penalty" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Yellow Card</label>
                            <input type="number" name="yellow_card" id="yellow_card" class="form-control" value="0" step="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Red Card</label>
                            <input type="number" name="red_card" id="red_card" class="form-control" value="0" step="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Save League Roles</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Players Management Tab -->
    <div id="players-management" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="playersLeagueName">Select a league to manage players</div>
                    <div class="header-meta" id="playersLeagueMeta"></div>
                </div>
                <button class="back-btn" onclick="backToSelection()">
                    ← Back to Selection
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">⚽ About Players Management</div>
                <div class="info-card-text">
                    Manage player information including names, roles, prices, and team assignments. Click the edit button next to any player to update their details.
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Player ID</th>
                            <th>Player Name</th>
                            <th>Role</th>
                            <th>Team</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="playersTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 30px;">Select a league to view players</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Player Modal -->
<div id="playerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit Player</span>
            <button class="modal-close" onclick="closePlayerModal()">&times;</button>
        </div>
        <form id="playerForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_player">
                <input type="hidden" name="player_id" id="editPlayerId">
                
                <div class="form-group">
                    <label class="form-label">Player Name *</label>
                    <input type="text" name="player_name" id="editPlayerName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Player Role *</label>
                    <select name="player_role" id="editPlayerRole" class="form-control" required>
                        <option value="GK">Goalkeeper (GK)</option>
                        <option value="DEF">Defender (DEF)</option>
                        <option value="MID">Midfielder (MID)</option>
                        <option value="ATT">Attacker (ATT)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team</label>
                    <select name="team_id" id="editPlayerTeam" class="form-control">
                        <option value="">No Team</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Player Price</label>
                    <input type="number" name="player_price" id="editPlayerPrice" class="form-control" step="0.01" min="0" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closePlayerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Player</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentTab = 'league-selection';
    
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
        // Load data if league is selected and switching to other tabs
        if (currentLeagueId && tabName === 'roles-points') {
            loadLeagueRoles(currentLeagueId);
        } else if (currentLeagueId && tabName === 'players-management') {
            loadLeaguePlayers(currentLeagueId);
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
        
        // Enable the tabs
        const rolesTab = document.getElementById('rolesTab');
        const playersTab = document.getElementById('playersTab');
        rolesTab.disabled = false;
        playersTab.disabled = false;
        rolesTab.classList.remove('tab-disabled');
        playersTab.classList.remove('tab-disabled');
        rolesTab.onclick = function() { switchTab('roles-points'); };
        playersTab.onclick = function() { switchTab('players-management'); };
        
        // Load league info
        fetch('?ajax=get_league_info&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Update all league headers
                updateLeagueHeaders(data);
                
                // Switch to roles tab and load data
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                rolesTab.classList.add('active');
                document.getElementById('roles-points').classList.add('active');
                currentTab = 'roles-points';
                
                loadLeagueRoles(leagueId);
            })
            .catch(error => {
                console.error(error);
                alert('Error loading league information');
            });
    }
    
    function updateLeagueHeaders(league) {
        const ownerText = 'Owner: ' + (league.owner_name || 'N/A') + 
            (league.other_owner_name ? ' & ' + league.other_owner_name : '');
        const statusBadge = league.activated == 1 ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-warning">Inactive</span>';
        
        const metaHtml = `
            <span>${ownerText}</span>
            <span>Players: ${league.num_of_players}</span>
            <span>Teams: ${league.num_of_teams}</span>
            <span>System: ${league.system}</span>
            <span>Status: ${statusBadge}</span>
        `;
        
        // Update roles section
        document.getElementById('rolesLeagueName').textContent = league.name;
        document.getElementById('rolesLeagueMeta').innerHTML = metaHtml;
        
        // Update players section
        document.getElementById('playersLeagueName').textContent = league.name;
        document.getElementById('playersLeagueMeta').innerHTML = metaHtml;
    }
    
    function backToSelection() {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelectorAll('.tab')[0].classList.add('active');
        document.getElementById('league-selection').classList.add('active');
        
        // Disable the tabs again
        const rolesTab = document.getElementById('rolesTab');
        const playersTab = document.getElementById('playersTab');
        rolesTab.disabled = true;
        playersTab.disabled = true;
        rolesTab.classList.add('tab-disabled');
        playersTab.classList.add('tab-disabled');
        rolesTab.onclick = null;
        playersTab.onclick = null;
        
        currentTab = 'league-selection';
        currentLeagueId = null;
    }
    
    function loadLeagueRoles(leagueId) {
        fetch('?ajax=get_league_roles&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Populate form
                document.getElementById('rolesLeagueId').value = leagueId;
                document.getElementById('gk_save_penalty').value = data.gk_save_penalty || 0;
                document.getElementById('gk_score').value = data.gk_score || 0;
                document.getElementById('gk_assist').value = data.gk_assist || 0;
                document.getElementById('gk_clean_sheet').value = data.gk_clean_sheet || 0;
                document.getElementById('def_clean_sheet').value = data.def_clean_sheet || 0;
                document.getElementById('def_assist').value = data.def_assist || 0;
                document.getElementById('def_score').value = data.def_score || 0;
                document.getElementById('mid_assist').value = data.mid_assist || 0;
                document.getElementById('mid_score').value = data.mid_score || 0;
                document.getElementById('miss_penalty').value = data.miss_penalty || 0;
                document.getElementById('for_score').value = data.for_score || 0;
                document.getElementById('for_assist').value = data.for_assist || 0;
                document.getElementById('yellow_card').value = data.yellow_card || 0;
                document.getElementById('red_card').value = data.red_card || 0;
            })
            .catch(error => {
                console.error(error);
                alert('Error loading league roles');
            });
    }
    
    function loadLeaguePlayers(leagueId) {
        const tbody = document.getElementById('playersTableBody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_players&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No players found in this league</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(player => {
                    let roleClass = 'badge-info';
                    if (player.player_role === 'GK') roleClass = 'badge-gk';
                    else if (player.player_role === 'DEF') roleClass = 'badge-def';
                    else if (player.player_role === 'MID') roleClass = 'badge-mid';
                    else if (player.player_role === 'ATT') roleClass = 'badge-att';
                    
                    html += `
                        <tr>
                            <td>${player.player_id}</td>
                            <td><strong>${player.player_name}</strong></td>
                            <td><span class="badge ${roleClass}">${player.player_role}</span></td>
                            <td>${player.team_name || 'No Team'}</td>
                            <td><strong>${Number(player.player_price || 0).toFixed(2)}</strong></td>
                            <td>
                                <button class="btn btn-secondary btn-sm" onclick="editPlayer(${player.player_id}, ${leagueId})">
                                    ✏️ Edit
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">Error loading players</td></tr>';
            });
    }
    
    function editPlayer(playerId, leagueId) {
        // Load player data
        fetch('?ajax=get_player&player_id=' + playerId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Populate form
                document.getElementById('editPlayerId').value = data.player_id;
                document.getElementById('editPlayerName').value = data.player_name;
                document.getElementById('editPlayerRole').value = data.player_role;
                document.getElementById('editPlayerPrice').value = data.player_price || 0;
                
                // Load teams for this league
                loadTeamsForPlayer(leagueId, data.team_id);
                
                // Show modal
                document.getElementById('playerModal').classList.add('active');
            })
            .catch(error => {
                console.error(error);
                alert('Error loading player data');
            });
    }
    
    function loadTeamsForPlayer(leagueId, selectedTeamId) {
        const teamSelect = document.getElementById('editPlayerTeam');
        teamSelect.innerHTML = '<option value="">Loading...</option>';
        
        fetch('?ajax=get_league_teams&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    teamSelect.innerHTML = '<option value="">Error loading teams</option>';
                    return;
                }
                
                let html = '<option value="">No Team</option>';
                data.forEach(team => {
                    const selected = team.id == selectedTeamId ? 'selected' : '';
                    html += `<option value="${team.id}" ${selected}>${team.team_name}</option>`;
                });
                teamSelect.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                teamSelect.innerHTML = '<option value="">Error loading teams</option>';
            });
    }
    
    function closePlayerModal() {
        document.getElementById('playerModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const playerModal = document.getElementById('playerModal');
        
        if (event.target === playerModal) {
            closePlayerModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>