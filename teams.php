<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_teams' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lt.*,
                    l.name as league_name
                FROM league_teams lt
                LEFT JOIN leagues l ON lt.league_id = l.id
                WHERE lt.league_id = ?
                ORDER BY lt.team_name
            ");
            $stmt->execute([$_GET['league_id']]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($teams);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_team' && isset($_GET['team_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lt.*,
                    l.name as league_name,
                    l.activated as league_activated
                FROM league_teams lt
                LEFT JOIN leagues l ON lt.league_id = l.id
                WHERE lt.id = ?
            ");
            $stmt->execute([$_GET['team_id']]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($team) {
                echo json_encode($team);
            } else {
                echo json_encode(['error' => 'Team not found']);
            }
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
    
    if ($_GET['ajax'] === 'get_team_players' && isset($_GET['team_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lp.*,
                    lt.team_name,
                    lt.league_id,
                    l.system as league_system
                FROM league_players lp
                LEFT JOIN league_teams lt ON lp.team_id = lt.id
                LEFT JOIN leagues l ON lt.league_id = l.id
                WHERE lp.team_id = ?
                ORDER BY 
                    FIELD(lp.player_role, 'GK', 'DEF', 'MID', 'ATT'),
                    lp.player_name
            ");
            $stmt->execute([$_GET['team_id']]);
            $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($players);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_team_info' && isset($_GET['team_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lt.*,
                    l.name as league_name,
                    l.system as league_system,
                    l.activated as league_activated
                FROM league_teams lt
                LEFT JOIN leagues l ON lt.league_id = l.id
                WHERE lt.id = ?
            ");
            $stmt->execute([$_GET['team_id']]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode($team);
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
                case 'add_team':
                    $stmt = $pdo->prepare("
                        INSERT INTO league_teams (league_id, team_name, team_score)
                        VALUES (?, ?, 0)
                    ");
                    $stmt->execute([
                        $_POST['league_id'],
                        $_POST['team_name']
                    ]);
                    
                    // Update num_of_teams in leagues table
                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET num_of_teams = (SELECT COUNT(*) FROM league_teams WHERE league_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$_POST['league_id'], $_POST['league_id']]);
                    
                    $success_message = "Team added successfully!";
                    break;
                    
                case 'update_team':
                    $stmt = $pdo->prepare("
                        UPDATE league_teams 
                        SET team_name = ?, team_score = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['team_name'],
                        $_POST['team_score'],
                        $_POST['team_id']
                    ]);
                    $success_message = "Team updated successfully!";
                    break;
                    
                case 'delete_team':
                    // Get league_id before deleting
                    $stmt = $pdo->prepare("SELECT league_id FROM league_teams WHERE id = ?");
                    $stmt->execute([$_POST['team_id']]);
                    $league_id = $stmt->fetch(PDO::FETCH_ASSOC)['league_id'];
                    
                    // Delete team
                    $stmt = $pdo->prepare("DELETE FROM league_teams WHERE id = ?");
                    $stmt->execute([$_POST['team_id']]);
                    
                    // Update num_of_teams in leagues table
                    $stmt = $pdo->prepare("
                        UPDATE leagues 
                        SET num_of_teams = (SELECT COUNT(*) FROM league_teams WHERE league_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$league_id, $league_id]);
                    
                    $success_message = "Team deleted successfully!";
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
    
    .btn-success {
        background: #28a745;
        color: #FFFFFF;
    }
    
    .btn-success:hover {
        background: #218838;
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
    
    .badge-role-gk {
        background: #ffc107;
        color: #856404;
    }
    
    .badge-role-def {
        background: #28a745;
        color: #FFFFFF;
    }
    
    .badge-role-mid {
        background: #17a2b8;
        color: #FFFFFF;
    }
    
    .badge-role-att {
        background: #dc3545;
        color: #FFFFFF;
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
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">🎯 Teams Management</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
    <button class="tab active" onclick="switchTab('league-selection')">🏆 Select League</button>
    <button class="tab tab-disabled" id="teamsTab" disabled>🎯 Teams Management</button>
    <button class="tab tab-disabled" id="playersTab" disabled>👥 Team Players</button>
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
    
    <!-- Teams Management Tab -->
    <div id="teams-management" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="teamsLeagueName">Select a league to manage teams</div>
                    <div class="header-meta" id="teamsLeagueMeta"></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="openAddTeamModal()" id="addTeamBtn" style="display: none;">
                        ➕ Add Team
                    </button>
                    <button class="back-btn" onclick="backToSelection()">
                        ← Back to Selection
                    </button>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">🎯 About Teams Management</div>
                <div class="info-card-text">
                    Manage teams within the selected league. You can add new teams, edit team information, view team players, and delete teams. Team scores are automatically calculated based on player performance in matches.
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Team ID</th>
                            <th>Team Name</th>
                            <th>Team Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="teamsTableBody">
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999; padding: 30px;">Select a league to view teams</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Team Players Tab -->
    <div id="team-players" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="playersTeamName">Team Players</div>
                    <div class="header-meta" id="playersTeamMeta"></div>
                </div>
                <button class="back-btn" onclick="backToTeamsManagement()">
                    ← Back to Teams
                </button>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">👥 Team Players</div>
                <div class="info-card-text">
                    View all players assigned to this team. Player scores are calculated based on their performance in matches throughout the league.
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Player ID</th>
                            <th>Player Name</th>
                            <th>Role</th>
                            <th id="priceHeader" style="display: none;">Price</th>
                            <th>Total Points</th>
                        </tr>
                    </thead>
                    <tbody id="playersTableBody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999; padding: 30px;">Select a team to view players</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Team Modal -->
<div id="addTeamModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Add New Team</span>
            <button class="modal-close" onclick="closeAddTeamModal()">&times;</button>
        </div>
        <form id="addTeamForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_team">
                <input type="hidden" name="league_id" id="addTeamLeagueId">
                
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="team_name" id="addTeamName" class="form-control" required placeholder="Enter team name">
                </div>
                
                <div class="info-card">
                    <div class="info-card-text">
                        The team score will be automatically set to 0 and updated based on match results.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeAddTeamModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Add Team</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Modal -->
<div id="editTeamModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit Team</span>
            <button class="modal-close" onclick="closeEditTeamModal()">&times;</button>
        </div>
        <form id="editTeamForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_team">
                <input type="hidden" name="team_id" id="editTeamId">
                
                <div class="form-group">
                    <label class="form-label">Team Name *</label>
                    <input type="text" name="team_name" id="editTeamName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team Score</label>
                    <input type="number" name="team_score" id="editTeamScore" class="form-control" step="1" value="0">
                </div>
                
                <div class="info-card">
                    <div class="info-card-text">
                        ⚠️ Note: Team scores are typically calculated automatically based on match results. Manual changes should be made carefully.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeEditTeamModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Team Modal -->
<div id="deleteTeamModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Delete Team</span>
            <button class="modal-close" onclick="closeDeleteTeamModal()">&times;</button>
        </div>
        <form id="deleteTeamForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete_team">
                <input type="hidden" name="team_id" id="deleteTeamId">
                
                <div class="info-card" style="border-left-color: #dc3545; background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                    <div class="info-card-title" style="color: #dc3545;">⚠️ Warning</div>
                    <div class="info-card-text">
                        Are you sure you want to delete the team "<strong id="deleteTeamName"></strong>"?
                        <br><br>
                        <strong>This action will:</strong>
                        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Remove the team permanently</li>
                            <li>This action cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteTeamModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Delete Team</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentTeamId = null;
    let currentTab = 'league-selection';
    
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
        // Load data if league is selected and switching to teams tab
        if (currentLeagueId && tabName === 'teams-management') {
            loadLeagueTeams(currentLeagueId);
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
        
        // Enable the teams tab
        const teamsTab = document.getElementById('teamsTab');
        teamsTab.disabled = false;
        teamsTab.classList.remove('tab-disabled');
        teamsTab.onclick = function() { switchTab('teams-management'); };
        
        // Load league info
        fetch('?ajax=get_league_info&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Update league header
                updateLeagueHeader(data);
                
                // Switch to teams tab and load data
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                teamsTab.classList.add('active');
                document.getElementById('teams-management').classList.add('active');
                currentTab = 'teams-management';
                
                // Show add team button
                document.getElementById('addTeamBtn').style.display = 'inline-flex';
                
                loadLeagueTeams(leagueId);
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
        
        document.getElementById('teamsLeagueName').textContent = league.name;
        document.getElementById('teamsLeagueMeta').innerHTML = metaHtml;
    }
    
    function backToSelection() {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelectorAll('.tab')[0].classList.add('active');
    document.getElementById('league-selection').classList.add('active');
    
    // Disable the teams tab
    const teamsTab = document.getElementById('teamsTab');
    teamsTab.disabled = true;
    teamsTab.classList.add('tab-disabled');
    teamsTab.onclick = null;
    
    // Disable the players tab
    const playersTab = document.getElementById('playersTab');
    playersTab.disabled = true;
    playersTab.classList.add('tab-disabled');
    playersTab.onclick = null;
    
    // Hide add team button
    document.getElementById('addTeamBtn').style.display = 'none';
    
    currentTab = 'league-selection';
    currentLeagueId = null;
    currentTeamId = null;
}
    
    function backToTeamsManagement() {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    const teamsTab = document.getElementById('teamsTab');
    teamsTab.classList.add('active');
    document.getElementById('teams-management').classList.add('active');
    
    // Keep the players tab visible but disabled
    const playersTab = document.getElementById('playersTab');
    playersTab.disabled = true;
    playersTab.classList.add('tab-disabled');
    playersTab.onclick = null;
    
    currentTab = 'teams-management';
    currentTeamId = null;
    
    // Reload teams
    if (currentLeagueId) {
        loadLeagueTeams(currentLeagueId);
    }
}
    
    function loadLeagueTeams(leagueId) {
        const tbody = document.getElementById('teamsTableBody');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_teams&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #999;">No teams found in this league. Click "Add Team" to create one.</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(team => {
                    html += `
                        <tr>
                            <td>${team.id}</td>
                            <td><strong>${team.team_name}</strong></td>
                            <td><strong style="color: #1D60AC; font-size: 18px;">${team.team_score}</strong></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-success btn-sm" onclick="viewTeamPlayers(${team.id})">
                                        👥 Players
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="editTeam(${team.id})">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteTeam(${team.id}, '${team.team_name.replace(/'/g, "\\'")}')">
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
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #dc3545;">Error loading teams</td></tr>';
            });
    }
    
    function viewTeamPlayers(teamId) {
        currentTeamId = teamId;
        
        const playersTab = document.getElementById('playersTab');
        playersTab.disabled = true; 
        playersTab.classList.add('tab-disabled');
        fetch('?ajax=get_team_info&team_id=' + teamId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                // Update team header
                updateTeamHeader(data);
                
                // Switch to players tab
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                playersTab.classList.add('active');
                document.getElementById('team-players').classList.add('active');
                currentTab = 'team-players';
                
                // Show/hide price column based on league system
                const priceHeader = document.getElementById('priceHeader');
                if (data.league_system === 'Budget') {
                    priceHeader.style.display = 'table-cell';
                } else {
                    priceHeader.style.display = 'none';
                }
                
                loadTeamPlayers(teamId, data.league_system);
            })
            .catch(error => {
                console.error(error);
                alert('Error loading team information');
            });
    }
    
    function updateTeamHeader(team) {
        const statusBadge = team.league_activated == 1 ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-warning">Inactive</span>';
        
        const metaHtml = `
            <span>League: ${team.league_name}</span>
            <span>Team Score: ${team.team_score}</span>
            <span>System: ${team.league_system}</span>
            <span>Status: ${statusBadge}</span>
        `;
        
        document.getElementById('playersTeamName').textContent = team.team_name;
        document.getElementById('playersTeamMeta').innerHTML = metaHtml;
    }
    
    function loadTeamPlayers(teamId, leagueSystem) {
        const tbody = document.getElementById('playersTableBody');
        const showPrice = leagueSystem === 'Budget';
        
        tbody.innerHTML = '<tr><td colspan="' + (showPrice ? '5' : '4') + '" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_team_players&team_id=' + teamId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="' + (showPrice ? '5' : '4') + '" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="' + (showPrice ? '5' : '4') + '" style="text-align: center; padding: 20px; color: #999;">No players found in this team.</td></tr>';
                    return;
                }
                
                let html = '';
                data.forEach(player => {
                    const roleBadgeClass = 
                        player.player_role === 'GK' ? 'badge-role-gk' :
                        player.player_role === 'DEF' ? 'badge-role-def' :
                        player.player_role === 'MID' ? 'badge-role-mid' :
                        'badge-role-att';
                    
                    html += `
                        <tr>
                            <td>${player.player_id}</td>
                            <td><strong>${player.player_name}</strong></td>
                            <td><span class="badge ${roleBadgeClass}">${player.player_role}</span></td>
                            ${showPrice ? `<td><strong style="color: #F1A155;">$${parseFloat(player.player_price).toFixed(2)}</strong></td>` : ''}
                            <td><strong style="color: #1D60AC; font-size: 16px;">${player.total_points}</strong></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            })
            .catch(error => {
                console.error(error);
                tbody.innerHTML = '<tr><td colspan="' + (showPrice ? '5' : '4') + '" style="text-align: center; padding: 20px; color: #dc3545;">Error loading players</td></tr>';
            });
    }
    
    function openAddTeamModal() {
        document.getElementById('addTeamLeagueId').value = currentLeagueId;
        document.getElementById('addTeamName').value = '';
        document.getElementById('addTeamModal').classList.add('active');
    }
    
    function closeAddTeamModal() {
        document.getElementById('addTeamModal').classList.remove('active');
    }
    
    function editTeam(teamId) {
        fetch('?ajax=get_team&team_id=' + teamId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('editTeamId').value = data.id;
                document.getElementById('editTeamName').value = data.team_name;
                document.getElementById('editTeamScore').value = data.team_score || 0;
                
                document.getElementById('editTeamModal').classList.add('active');
            })
            .catch(error => {
                console.error(error);
                alert('Error loading team data');
            });
    }
    
    function closeEditTeamModal() {
        document.getElementById('editTeamModal').classList.remove('active');
    }
    
    function deleteTeam(teamId, teamName) {
        document.getElementById('deleteTeamId').value = teamId;
        document.getElementById('deleteTeamName').textContent = teamName;
        document.getElementById('deleteTeamModal').classList.add('active');
    }
    
    function closeDeleteTeamModal() {
        document.getElementById('deleteTeamModal').classList.remove('active');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addTeamModal');
        const editModal = document.getElementById('editTeamModal');
        const deleteModal = document.getElementById('deleteTeamModal');
        
        if (event.target === addModal) {
            closeAddTeamModal();
        }
        if (event.target === editModal) {
            closeEditTeamModal();
        }
        if (event.target === deleteModal) {
            closeDeleteTeamModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>