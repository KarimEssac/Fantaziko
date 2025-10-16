<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
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

// Add this new endpoint RIGHT AFTER get_league_info
if ($_GET['ajax'] === 'get_league_teams' && isset($_GET['league_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                team_name,
                team_score
            FROM league_teams
            WHERE league_id = ?
            ORDER BY team_name
        ");
        $stmt->execute([$_GET['league_id']]);
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($teams);
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
                    l.name as league_name,
                    l.activated as league_activated,
                    l.system as league_system
                FROM league_players lp
                LEFT JOIN leagues l ON lp.league_id = l.id
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
    
    if ($_GET['ajax'] === 'get_league_players' && isset($_GET['league_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                lp.*,
                l.name as league_name,
                lt.team_name
            FROM league_players lp
            LEFT JOIN leagues l ON lp.league_id = l.id
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
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_player':
    $stmt = $pdo->prepare("
        INSERT INTO league_players (league_id, team_id, player_name, player_role, player_price)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_POST['league_id'],
        $_POST['team_id'],
        $_POST['player_name'],
        $_POST['player_role'],
        $_POST['player_price']
    ]);
    
    // Update num_of_players in leagues table
    $stmt = $pdo->prepare("
        UPDATE leagues 
        SET num_of_players = (SELECT COUNT(*) FROM league_players WHERE league_id = ?)
        WHERE id = ?
    ");
    $stmt->execute([$_POST['league_id'], $_POST['league_id']]);
    
    $success_message = "Player added successfully!";
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
        $_POST['team_id'],
        $_POST['player_id']
    ]);
    $success_message = "Player updated successfully!";
    break;
                    
                case 'delete_player':
                    // Get league_id before deleting
                    $stmt = $pdo->prepare("SELECT league_id FROM league_players WHERE player_id = ?");
                    $stmt->execute([$_POST['player_id']]);
                    $league_id = $stmt->fetch(PDO::FETCH_ASSOC)['league_id'];
                    
                    // Delete player
                    $stmt = $pdo->prepare("DELETE FROM league_players WHERE player_id = ?");
                    $stmt->execute([$_POST['player_id']]);
                    
                    // Update num_of_players in leagues table
                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET num_of_players = (SELECT COUNT(*) FROM league_players WHERE league_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$league_id, $league_id]);
                    
                    $success_message = "Player deleted successfully!";
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
        background: #ffc107;
        color: #000;
    }
    
    .badge-def {
        background: #28a745;
        color: #fff;
    }
    
    .badge-mid {
        background: #007bff;
        color: #fff;
    }
    
    .badge-att {
        background: #dc3545;
        color: #fff;
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
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: linear-gradient(135deg, rgba(29, 96, 172, 0.05), rgba(10, 146, 215, 0.05));
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        border: 1px solid rgba(29, 96, 172, 0.2);
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .price-display {
        font-size: 18px;
        font-weight: 700;
        color: #F1A155;
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
        
        .header-meta {
            flex-direction: column;
            gap: 5px;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">⚽ Players Management</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('league-selection')">🏆 Select League</button>
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
                                            ⚽ Select
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
    
    <!-- Players Management Tab -->
    <div id="players-management" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="playersLeagueName">Select a league to manage players</div>
                    <div class="header-meta" id="playersLeagueMeta"></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="openAddPlayerModal()" id="addPlayerBtn" style="display: none;">
                        ➕ Add Player
                    </button>
                    <button class="back-btn" onclick="backToSelection()">
                        ← Back to Selection
                    </button>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">⚽ About Players Management</div>
                <div class="info-card-text">
                    Manage players within the selected league. You can add new players, edit player information (name, position, price), and delete players. Players are categorized by their roles: Goalkeeper (GK), Defender (DEF), Midfielder (MID), and Attacker (ATT). Note: Player prices are only relevant for leagues with "Budget" system enabled.
                </div>
            </div>
            
            <div id="playersStatsRow" class="stats-row" style="display: none;">
                <div class="stat-box">
                    <div class="stat-label">Goalkeepers</div>
                    <div class="stat-value" id="statGK">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Defenders</div>
                    <div class="stat-value" id="statDEF">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Midfielders</div>
                    <div class="stat-value" id="statMID">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Attackers</div>
                    <div class="stat-value" id="statATT">0</div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
    <tr>
        <th>Player ID</th>
        <th>Player Name</th>
        <th>Team</th>
        <th>Position</th>
        <th>Price</th>
        <th>Actions</th>
    </tr>
</thead>
                    <tbody id="playersTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999; padding: 30px;">Select a league to view players</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Player Modal -->
<div id="addPlayerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Add New Player</span>
            <button class="modal-close" onclick="closeAddPlayerModal()">&times;</button>
        </div>
        <form id="addPlayerForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_player">
                <input type="hidden" name="league_id" id="addPlayerLeagueId">
                <div class="form-group">
    <label class="form-label">Team *</label>
    <select name="team_id" id="addPlayerTeam" class="form-control" required>
        <option value="">Select Team</option>
    </select>
</div>
                <div class="form-group">
                    <label class="form-label">Player Name *</label>
                    <input type="text" name="player_name" id="addPlayerName" class="form-control" required placeholder="Enter player name">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Position *</label>
                    <select name="player_role" id="addPlayerRole" class="form-control" required>
                        <option value="">Select Position</option>
                        <option value="GK">🟡 Goalkeeper (GK)</option>
                        <option value="DEF">🟢 Defender (DEF)</option>
                        <option value="MID">🔵 Midfielder (MID)</option>
                        <option value="ATT">🔴 Attacker (ATT)</option>
                    </select>
                </div>
                
                <div class="form-group" id="addPlayerPriceGroup" style="display: none;">
    <label class="form-label">Price <span id="addPriceOptional" style="color: #999; font-weight: normal;">(Optional)</span></label>
    <input type="number" name="player_price" id="addPlayerPrice" class="form-control" step="0.01" min="0" placeholder="Enter player price">
</div>

<div class="info-card" id="addPlayerPriceInfo" style="display: none;">
    <div class="info-card-text">
        💡 This league uses a "Budget" system. Player prices are important for team building.
    </div>
</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeAddPlayerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Add Player</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Player Modal -->
<div id="editPlayerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit Player</span>
            <button class="modal-close" onclick="closeEditPlayerModal()">&times;</button>
        </div>
        <form id="editPlayerForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_player">
                <input type="hidden" name="player_id" id="editPlayerId">
                <div class="form-group">
    <label class="form-label">Team *</label>
    <select name="team_id" id="editPlayerTeam" class="form-control" required>
        <option value="">Select Team</option>
    </select>
</div>
                <div class="form-group">
                    <label class="form-label">Player Name *</label>
                    <input type="text" name="player_name" id="editPlayerName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Position *</label>
                    <select name="player_role" id="editPlayerRole" class="form-control" required>
                        <option value="">Select Position</option>
                        <option value="GK">🟡 Goalkeeper (GK)</option>
                        <option value="DEF">🟢 Defender (DEF)</option>
                        <option value="MID">🔵 Midfielder (MID)</option>
                        <option value="ATT">🔴 Attacker (ATT)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price <span id="editPriceOptional" style="color: #999; font-weight: normal;">(Optional)</span></label>
                    <input type="number" name="player_price" id="editPlayerPrice" class="form-control" step="0.01" min="0">
                </div>
                
                <div class="info-card" id="editPlayerPriceInfo" style="display: none;">
                    <div class="info-card-text">
                        💡 This league uses a "Budget" system. Player prices affect team building strategies.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeEditPlayerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Player Modal -->
<div id="deletePlayerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Delete Player</span>
            <button class="modal-close" onclick="closeDeletePlayerModal()">&times;</button>
        </div>
        <form id="deletePlayerForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete_player">
                <input type="hidden" name="player_id" id="deletePlayerId">
                
                <div class="info-card" style="border-left-color: #dc3545; background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                    <div class="info-card-title" style="color: #dc3545;">⚠️ Warning</div>
                    <div class="info-card-text">
                        Are you sure you want to delete the player "<strong id="deletePlayerName"></strong>" (<span id="deletePlayerPosition"></span>)?
                        <br><br>
                        <strong>This action will:</strong>
                        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Remove the player permanently from the league</li>
                            <li>Remove all associated match points records</li>
                            <li>This action cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeletePlayerModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Delete Player</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentLeagueSystem = null;
    let currentTab = 'league-selection';
let currentLeagueTeams = [];
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
        // Load data if league is selected and switching to players tab
        if (currentLeagueId && tabName === 'players-management') {
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
    
    // Enable the players tab
    const playersTab = document.getElementById('playersTab');
    playersTab.disabled = false;
    playersTab.classList.remove('tab-disabled');
    playersTab.onclick = function() { switchTab('players-management'); };
    
    // Load league info and teams
    Promise.all([
        fetch('?ajax=get_league_info&league_id=' + leagueId).then(r => r.json()),
        fetch('?ajax=get_league_teams&league_id=' + leagueId).then(r => r.json())
    ])
    .then(([leagueData, teamsData]) => {
        if (leagueData.error) {
            alert('Error: ' + leagueData.error);
            return;
        }
        
        if (teamsData.error) {
            alert('Error loading teams: ' + teamsData.error);
            return;
        }
        
        currentLeagueSystem = leagueData.system;
        currentLeagueTeams = teamsData;
        
        // Update league header
        updateLeagueHeader(leagueData);
        
        // Switch to players tab and load data
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        playersTab.classList.add('active');
        document.getElementById('players-management').classList.add('active');
        currentTab = 'players-management';
        
        // Show add player button
        document.getElementById('addPlayerBtn').style.display = 'inline-flex';
        
        loadLeaguePlayers(leagueId);
    })
    .catch(error => {
        console.error(error);
        alert('Error loading league information');
    });
}
    
    function updateLeagueHeader(league) {
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
        
        document.getElementById('playersLeagueName').textContent = league.name;
        document.getElementById('playersLeagueMeta').innerHTML = metaHtml;
    }
    
    function backToSelection() {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelectorAll('.tab')[0].classList.add('active');
        document.getElementById('league-selection').classList.add('active');
        
        // Disable the players tab
        const playersTab = document.getElementById('playersTab');
        playersTab.disabled = true;
        playersTab.classList.add('tab-disabled');
        playersTab.onclick = null;
        
        // Hide add player button and stats
        document.getElementById('addPlayerBtn').style.display = 'none';
        document.getElementById('playersStatsRow').style.display = 'none';
        
        currentTab = 'league-selection';
        currentLeagueId = null;
        currentLeagueSystem = null;
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
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No players found in this league. Click "Add Player" to create one.</td></tr>';
                document.getElementById('playersStatsRow').style.display = 'none';
                return;
            }
            
            // Calculate statistics
            const stats = { GK: 0, DEF: 0, MID: 0, ATT: 0 };
            data.forEach(player => {
                if (stats.hasOwnProperty(player.player_role)) {
                    stats[player.player_role]++;
                }
            });
            
            // Update stats display
            document.getElementById('statGK').textContent = stats.GK;
            document.getElementById('statDEF').textContent = stats.DEF;
            document.getElementById('statMID').textContent = stats.MID;
            document.getElementById('statATT').textContent = stats.ATT;
            document.getElementById('playersStatsRow').style.display = 'grid';
            
            let html = '';
            data.forEach(player => {
                const roleBadge = getRoleBadge(player.player_role);
                const priceDisplay = player.player_price ? 
                    '<span class="price-display">$' + parseFloat(player.player_price).toFixed(2) + '</span>' : 
                    '<span style="color: #999;">N/A</span>';
                const teamDisplay = player.team_name ? 
                    '<strong>' + player.team_name + '</strong>' : 
                    '<span style="color: #999;">No Team</span>';
                
                html += `
                    <tr>
                        <td>${player.player_id}</td>
                        <td><strong>${player.player_name}</strong></td>
                        <td>${teamDisplay}</td>
                        <td>${roleBadge}</td>
                        <td>${priceDisplay}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-secondary btn-sm" onclick="editPlayer(${player.player_id})">
                                    ✏️ Edit
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deletePlayer(${player.player_id}, '${player.player_name.replace(/'/g, "\\'")}', '${player.player_role}')">
                                    🗑️ Delete
                                </button>
                            </div>
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
    
    function getRoleBadge(role) {
        const badges = {
            'GK': '<span class="badge badge-gk">🟡 Goalkeeper</span>',
            'DEF': '<span class="badge badge-def">🟢 Defender</span>',
            'MID': '<span class="badge badge-mid">🔵 Midfielder</span>',
            'ATT': '<span class="badge badge-att">🔴 Attacker</span>'
        };
        return badges[role] || '<span class="badge badge-info">' + role + '</span>';
    }
    
    function openAddPlayerModal() {
    document.getElementById('addPlayerLeagueId').value = currentLeagueId;
    document.getElementById('addPlayerName').value = '';
    document.getElementById('addPlayerRole').value = '';
    document.getElementById('addPlayerPrice').value = '';
    
    // Populate team dropdown
    populateTeamDropdown('addPlayerTeam');
    
    // Show/hide price field based on league system
    if (currentLeagueSystem === 'Budget') {
        document.getElementById('addPlayerPriceGroup').style.display = 'block';
        document.getElementById('addPlayerPriceInfo').style.display = 'block';
        document.getElementById('addPriceOptional').style.display = 'none';
        document.getElementById('addPlayerPrice').required = true;
    } else {
        document.getElementById('addPlayerPriceGroup').style.display = 'none';
        document.getElementById('addPlayerPriceInfo').style.display = 'none';
        document.getElementById('addPlayerPrice').required = false;
    }
    
    document.getElementById('addPlayerModal').classList.add('active');
}
    
    function closeAddPlayerModal() {
        document.getElementById('addPlayerModal').classList.remove('active');
    }
    function populateTeamDropdown(selectId) {
    const select = document.getElementById(selectId);
    select.innerHTML = '<option value="">Select Team</option>';
    
    if (!currentLeagueTeams || currentLeagueTeams.length === 0) {
        select.innerHTML = '<option value="">No teams available</option>';
        select.disabled = true;
        return;
    }
    
    select.disabled = false;
    currentLeagueTeams.forEach(team => {
        const option = document.createElement('option');
        option.value = team.id;
        option.textContent = team.team_name;
        select.appendChild(option);
    });
}
    function editPlayer(playerId) {
    fetch('?ajax=get_player&player_id=' + playerId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            document.getElementById('editPlayerId').value = data.player_id;
            document.getElementById('editPlayerName').value = data.player_name;
            document.getElementById('editPlayerRole').value = data.player_role;
            document.getElementById('editPlayerPrice').value = data.player_price || '';
            
            // Populate and set team dropdown
            populateTeamDropdown('editPlayerTeam');
            document.getElementById('editPlayerTeam').value = data.team_id || '';
            
            // Show/hide price info based on league system
            if (data.league_system === 'Budget') {
                document.getElementById('editPlayerPriceInfo').style.display = 'block';
                document.getElementById('editPriceOptional').style.display = 'none';
                document.getElementById('editPlayerPrice').required = true;
            } else {
                document.getElementById('editPlayerPriceInfo').style.display = 'none';
                document.getElementById('editPriceOptional').style.display = 'inline';
                document.getElementById('editPlayerPrice').required = false;
            }
            
            document.getElementById('editPlayerModal').classList.add('active');
        })
        .catch(error => {
            console.error(error);
            alert('Error loading player data');
        });
}
    
    function closeEditPlayerModal() {
        document.getElementById('editPlayerModal').classList.remove('active');
    }
    
    function deletePlayer(playerId, playerName, playerRole) {
        document.getElementById('deletePlayerId').value = playerId;
        document.getElementById('deletePlayerName').textContent = playerName;
        document.getElementById('deletePlayerPosition').textContent = getRoleText(playerRole);
        document.getElementById('deletePlayerModal').classList.add('active');
    }
    
    function getRoleText(role) {
        const roles = {
            'GK': '🟡 Goalkeeper',
            'DEF': '🟢 Defender',
            'MID': '🔵 Midfielder',
            'ATT': '🔴 Attacker'
        };
        return roles[role] || role;
    }
    
    function closeDeletePlayerModal() {
        document.getElementById('deletePlayerModal').classList.remove('active');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addPlayerModal');
        const editModal = document.getElementById('editPlayerModal');
        const deleteModal = document.getElementById('deletePlayerModal');
        
        if (event.target === addModal) {
            closeAddPlayerModal();
        }
        if (event.target === editModal) {
            closeEditPlayerModal();
        }
        if (event.target === deleteModal) {
            closeDeletePlayerModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>