<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_league_matches' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    l.name as league_name,
                    l.round as current_round,
                    t1.team_name as team1_name,
                    t1.team_score as team1_league_score,
                    t2.team_name as team2_name,
                    t2.team_score as team2_league_score
                FROM matches m
                LEFT JOIN leagues l ON m.league_id = l.id
                LEFT JOIN league_teams t1 ON m.team1_id = t1.id
                LEFT JOIN league_teams t2 ON m.team2_id = t2.id
                WHERE m.league_id = ?
                ORDER BY m.round DESC, m.created_at DESC
            ");
            $stmt->execute([$_GET['league_id']]);
            $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($matches);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_match' && isset($_GET['match_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    l.name as league_name,
                    l.activated as league_activated,
                    t1.team_name as team1_name,
                    t2.team_name as team2_name
                FROM matches m
                LEFT JOIN leagues l ON m.league_id = l.id
                LEFT JOIN league_teams t1 ON m.team1_id = t1.id
                LEFT JOIN league_teams t2 ON m.team2_id = t2.id
                WHERE m.match_id = ?
            ");
            $stmt->execute([$_GET['match_id']]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($match) {
                echo json_encode($match);
            } else {
                echo json_encode(['error' => 'Match not found']);
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
    
    if ($_GET['ajax'] === 'get_league_teams' && isset($_GET['league_id'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, team_name, team_score
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
}

// Function to update team scores based on match result
function updateTeamScores($pdo, $match_id, $team1_id, $team2_id, $team1_score, $team2_score, $old_team1_id = null, $old_team2_id = null, $old_team1_score = null, $old_team2_score = null) {
    try {
        // If editing, first revert the old scores
        if ($old_team1_id && $old_team2_id && $old_team1_score !== null && $old_team2_score !== null) {
            if ($old_team1_score > $old_team2_score) {
                // Team 1 won - revert +3 from team1
                $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$old_team1_id]);
            } elseif ($old_team2_score > $old_team1_score) {
                // Team 2 won - revert +3 from team2
                $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$old_team2_id]);
            } else {
                // Draw - revert +1 from both
                $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$old_team1_id]);
                $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$old_team2_id]);
            }
        }
        
        // Apply new scores
        if ($team1_score > $team2_score) {
            // Team 1 wins
            $pdo->prepare("UPDATE league_teams SET team_score = team_score + 3 WHERE id = ?")->execute([$team1_id]);
        } elseif ($team2_score > $team1_score) {
            // Team 2 wins
            $pdo->prepare("UPDATE league_teams SET team_score = team_score + 3 WHERE id = ?")->execute([$team2_id]);
        } else {
            // Draw
            $pdo->prepare("UPDATE league_teams SET team_score = team_score + 1 WHERE id = ?")->execute([$team1_id]);
            $pdo->prepare("UPDATE league_teams SET team_score = team_score + 1 WHERE id = ?")->execute([$team2_id]);
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_match':
                    // Validate teams are different
                    if ($_POST['team1_id'] == $_POST['team2_id']) {
                        $error_message = "Team 1 and Team 2 cannot be the same!";
                        break;
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Insert match
                    $stmt = $pdo->prepare("
                        INSERT INTO matches (league_id, round, team1_id, team2_id, team1_score, team2_score)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['league_id'],
                        $_POST['round'],
                        $_POST['team1_id'],
                        $_POST['team2_id'],
                        $_POST['team1_score'] ?? 0,
                        $_POST['team2_score'] ?? 0
                    ]);
                    
                    $match_id = $pdo->lastInsertId();
                    
                    // Update team scores
                    updateTeamScores($pdo, $match_id, $_POST['team1_id'], $_POST['team2_id'], 
                                   $_POST['team1_score'], $_POST['team2_score']);
                    
                    $pdo->commit();
                    $success_message = "Match added successfully! Team scores updated.";
                    break;
                    
                case 'update_match':
                    // Validate teams are different
                    if ($_POST['team1_id'] == $_POST['team2_id']) {
                        $error_message = "Team 1 and Team 2 cannot be the same!";
                        break;
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Get old match data
                    $stmt = $pdo->prepare("SELECT team1_id, team2_id, team1_score, team2_score FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    $old_match = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update match
                    $stmt = $pdo->prepare("
                        UPDATE matches 
                        SET round = ?, team1_id = ?, team2_id = ?, team1_score = ?, team2_score = ?
                        WHERE match_id = ?
                    ");
                    $stmt->execute([
                        $_POST['round'],
                        $_POST['team1_id'],
                        $_POST['team2_id'],
                        $_POST['team1_score'],
                        $_POST['team2_score'],
                        $_POST['match_id']
                    ]);
                    
                    // Update team scores (revert old and apply new)
                    updateTeamScores($pdo, $_POST['match_id'], 
                                   $_POST['team1_id'], $_POST['team2_id'], 
                                   $_POST['team1_score'], $_POST['team2_score'],
                                   $old_match['team1_id'], $old_match['team2_id'],
                                   $old_match['team1_score'], $old_match['team2_score']);
                    
                    $pdo->commit();
                    $success_message = "Match updated successfully! Team scores updated.";
                    break;
                    
                case 'delete_match':
                    $pdo->beginTransaction();
                    
                    // Get match data to revert scores
                    $stmt = $pdo->prepare("SELECT team1_id, team2_id, team1_score, team2_score FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    $match = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($match) {
                        // Revert team scores
                        if ($match['team1_score'] > $match['team2_score']) {
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$match['team1_id']]);
                        } elseif ($match['team2_score'] > $match['team1_score']) {
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 3 WHERE id = ?")->execute([$match['team2_id']]);
                        } else {
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$match['team1_id']]);
                            $pdo->prepare("UPDATE league_teams SET team_score = team_score - 1 WHERE id = ?")->execute([$match['team2_id']]);
                        }
                    }
                    
                    // Delete match points
                    $stmt = $pdo->prepare("DELETE FROM matches_points WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    
                    // Delete match
                    $stmt = $pdo->prepare("DELETE FROM matches WHERE match_id = ?");
                    $stmt->execute([$_POST['match_id']]);
                    
                    $pdo->commit();
                    $success_message = "Match deleted successfully! Team scores reverted.";
                    break;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
            l.round as current_round,
            l.num_of_players,
            l.num_of_teams,
            l.system,
            a.username as owner_name,
            a2.username as other_owner_name,
            (SELECT COUNT(*) FROM matches WHERE league_id = l.id) as total_matches
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
    
    .badge-primary {
        background: rgba(29, 96, 172, 0.15);
        color: #1D60AC;
    }
    
    .badge-danger {
        background: #f8d7da;
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
        background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .match-score {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        font-size: 18px;
        font-weight: 700;
        color: #1D60AC;
    }
    
    .match-vs {
        font-size: 14px;
        color: #999;
        font-weight: 600;
    }
    
    .team-name {
        font-weight: 600;
        color: #1D60AC;
    }
    
    .result-badge {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 12px;
        font-weight: 700;
        margin-left: 5px;
    }
    
    .result-win {
        background: #d4edda;
        color: #155724;
    }
    
    .result-draw {
        background: #fff3cd;
        color: #856404;
    }
    
    .result-loss {
        background: #f8d7da;
        color: #721c24;
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
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">📅 Matches Management</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('league-selection')">🏆 Select League</button>
        <button class="tab tab-disabled" id="matchesTab" disabled>📅 Matches Management</button>
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
                            <th>Current Round</th>
                            <th>Teams</th>
                            <th>Total Matches</th>
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
                                    <td><span class="badge badge-primary">Round <?php echo $league['current_round']; ?></span></td>
                                    <td><?php echo $league['num_of_teams']; ?></td>
                                    <td><strong style="color: #1D60AC;"><?php echo $league['total_matches']; ?></strong></td>
                                    <td>
                                        <?php if ($league['league_activated']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="selectLeague(<?php echo $league['league_id']; ?>)">
                                            📅 Select
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
    
    <!-- Matches Management Tab -->
    <div id="matches-management" class="tab-content">
        <div class="data-card">
            <div class="data-card-header">
                <div class="header-info">
                    <div class="header-title" id="matchesLeagueName">Select a league to manage matches</div>
                    <div class="header-meta" id="matchesLeagueMeta"></div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="openAddMatchModal()" id="addMatchBtn" style="display: none;">
                        ➕ Add Match
                    </button>
                    <button class="back-btn" onclick="backToSelection()">
                        ← Back to Selection
                    </button>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-card-title">📅 About Matches Management</div>
                <div class="info-card-text">
                    Manage matches within the selected league. Select two teams and enter their match scores. 
                    <strong>Scoring System:</strong> Winner gets +3 points, Draw gives +1 to each team, Loser gets 0 points. 
                    Team scores are automatically updated in the league standings based on match results.
                </div>
            </div>
            
            <div class="stats-row" id="matchesStats" style="display: none;">
                <div class="stat-box">
                    <div class="stat-label">Total Matches</div>
                    <div class="stat-value" id="statTotalMatches">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Current Round</div>
                    <div class="stat-value" id="statCurrentRound">1</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Goals</div>
                    <div class="stat-value" id="statTotalGoals">0</div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Match ID</th>
                            <th>Round</th>
                            <th>Teams</th>
                            <th>Score</th>
                            <th>Result</th>
                            <th>Total Goals</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="matchesTableBody">
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 30px;">Select a league to view matches</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Match Modal -->
<div id="addMatchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Add New Match</span>
            <button class="modal-close" onclick="closeAddMatchModal()">&times;</button>
        </div>
        <form id="addMatchForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_match">
                <input type="hidden" name="league_id" id="addMatchLeagueId">
                
                <div class="form-group">
                    <label class="form-label">Round *</label>
                    <input type="number" name="round" id="addMatchRound" class="form-control" required min="1" placeholder="Enter round number">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 1 *</label>
                    <select name="team1_id" id="addMatchTeam1" class="form-control" required>
                        <option value="">Select Team 1</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 1 Score *</label>
                    <input type="number" name="team1_score" id="addMatchTeam1Score" class="form-control" value="0" min="0" required placeholder="Team 1 score">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 2 *</label>
                    <select name="team2_id" id="addMatchTeam2" class="form-control" required>
                        <option value="">Select Team 2</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 2 Score *</label>
                    <input type="number" name="team2_score" id="addMatchTeam2Score" class="form-control" value="0" min="0" required placeholder="Team 2 score">
                </div>
                
                <div class="info-card">
                    <div class="info-card-text">
                        💡 <strong>Scoring Rules:</strong><br>
                        • Winner: +3 points to league standings<br>
                        • Draw: +1 point to each team<br>
                        • Loser: 0 points<br>
                        Team standings will be automatically updated based on the match result.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeAddMatchModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Add Match</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Match Modal -->
<div id="editMatchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Edit Match</span>
            <button class="modal-close" onclick="closeEditMatchModal()">&times;</button>
        </div>
        <form id="editMatchForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_match">
                <input type="hidden" name="match_id" id="editMatchId">
                
                <div class="form-group">
                    <label class="form-label">Round *</label>
                    <input type="number" name="round" id="editMatchRound" class="form-control" required min="1">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 1 *</label>
                    <select name="team1_id" id="editMatchTeam1" class="form-control" required>
                        <option value="">Select Team 1</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 1 Score *</label>
                    <input type="number" name="team1_score" id="editMatchTeam1Score" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 2 *</label>
                    <select name="team2_id" id="editMatchTeam2" class="form-control" required>
                        <option value="">Select Team 2</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Team 2 Score *</label>
                    <input type="number" name="team2_score" id="editMatchTeam2Score" class="form-control" min="0" required>
                </div>
                
                <div class="info-card">
                    <div class="info-card-text">
                        ⚠️ <strong>Note:</strong> Changing the match result will automatically update team standings. The old points will be reverted and new points will be applied based on the new result.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeEditMatchModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Match Modal -->
<div id="deleteMatchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Delete Match</span>
            <button class="modal-close" onclick="closeDeleteMatchModal()">&times;</button>
        </div>
        <form id="deleteMatchForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete_match">
                <input type="hidden" name="match_id" id="deleteMatchId">
                
                <div class="info-card" style="border-left-color: #dc3545; background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                    <div class="info-card-title" style="color: #dc3545;">⚠️ Warning</div>
                    <div class="info-card-text">
                        Are you sure you want to delete this match?
                        <br><br>
                        <strong>Match Details:</strong>
                        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Match ID: <strong id="deleteMatchIdDisplay"></strong></li>
                            <li>Round: <strong id="deleteMatchRound"></strong></li>
                            <li>Teams: <strong id="deleteMatchTeams"></strong></li>
                            <li>Score: <strong id="deleteMatchScore"></strong></li>
                        </ul>
                        <br>
                        <strong>This action will:</strong>
                        <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Revert team standings points automatically</li>
                            <li>Delete all associated match points (scorers, assists, cards, etc.)</li>
                            <li>Remove the match permanently</li>
                            <li>This action cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteMatchModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">🗑️ Delete Match</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentLeagueId = null;
    let currentTab = 'league-selection';
    let currentLeagueData = null;
    let leagueTeams = [];
    
    function switchTab(tabName) {
        const tabs = document.querySelectorAll('.tab');
        const contents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        contents.forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
        
        currentTab = tabName;
        
        // Load data if league is selected and switching to matches tab
        if (currentLeagueId && tabName === 'matches-management') {
            loadLeagueMatches(currentLeagueId);
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
        
        // Enable the matches tab
        const matchesTab = document.getElementById('matchesTab');
        matchesTab.disabled = false;
        matchesTab.classList.remove('tab-disabled');
        matchesTab.onclick = function() { switchTab('matches-management'); };
        
        // Load league info
        fetch('?ajax=get_league_info&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                currentLeagueData = data;
                
                // Update league header
                updateLeagueHeader(data);
                
                // Load league teams
                loadLeagueTeams(leagueId);
                
                // Switch to matches tab and load data
                document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                matchesTab.classList.add('active');
                document.getElementById('matches-management').classList.add('active');
                currentTab = 'matches-management';
                
                // Show add match button and stats
                document.getElementById('addMatchBtn').style.display = 'inline-flex';
                document.getElementById('matchesStats').style.display = 'grid';
                
                // Set default round for add match form
                document.getElementById('addMatchRound').value = data.round;
                
                loadLeagueMatches(leagueId);
            })
            .catch(error => {
                console.error(error);
                alert('Error loading league information');
            });
    }
    
    function loadLeagueTeams(leagueId) {
        fetch('?ajax=get_league_teams&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading teams:', data.error);
                    leagueTeams = [];
                    return;
                }
                
                leagueTeams = data;
                populateTeamSelects();
            })
            .catch(error => {
                console.error('Error loading teams:', error);
                leagueTeams = [];
            });
    }
    
    function populateTeamSelects() {
        const selects = [
            document.getElementById('addMatchTeam1'),
            document.getElementById('addMatchTeam2'),
            document.getElementById('editMatchTeam1'),
            document.getElementById('editMatchTeam2')
        ];
        
        selects.forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select Team</option>';
            
            leagueTeams.forEach(team => {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.team_name + ' (Score: ' + team.team_score + ')';
                select.appendChild(option);
            });
            
            if (currentValue) {
                select.value = currentValue;
            }
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
            <span>Current Round: ${league.round}</span>
            <span>Teams: ${league.num_of_teams}</span>
            <span>System: ${league.system}</span>
            <span>Status: ${statusBadge}</span>
        `;
        
        document.getElementById('matchesLeagueName').textContent = league.name;
        document.getElementById('matchesLeagueMeta').innerHTML = metaHtml;
        document.getElementById('statCurrentRound').textContent = league.round;
    }
    
    function backToSelection() {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelectorAll('.tab')[0].classList.add('active');
        document.getElementById('league-selection').classList.add('active');
        
        // Disable the matches tab
        const matchesTab = document.getElementById('matchesTab');
        matchesTab.disabled = true;
        matchesTab.classList.add('tab-disabled');
        matchesTab.onclick = null;
        
        // Hide add match button and stats
        document.getElementById('addMatchBtn').style.display = 'none';
        document.getElementById('matchesStats').style.display = 'none';
        
        currentTab = 'league-selection';
        currentLeagueId = null;
        currentLeagueData = null;
        leagueTeams = [];
    }
    
    function loadLeagueMatches(leagueId) {
        const tbody = document.getElementById('matchesTableBody');
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #999;">Loading...</td></tr>';
        
        fetch('?ajax=get_league_matches&league_id=' + leagueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #dc3545;">Error: ' + data.error + '</td></tr>';
                    return;
                }
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #999;">No matches found in this league. Click "Add Match" to create one.</td></tr>';
                    updateStats(0, 0);
                    return;
                }
                
                // Calculate statistics
                let totalGoals = 0;
                data.forEach(match => {
                    totalGoals += parseInt(match.team1_score || 0) + parseInt(match.team2_score || 0);
                });
                
                updateStats(data.length, totalGoals);
                
                let html = '';
                data.forEach(match => {
                    const team1Score = parseInt(match.team1_score || 0);
                    const team2Score = parseInt(match.team2_score || 0);
                    const totalMatchGoals = team1Score + team2Score;
                    const matchDate = new Date(match.created_at);
                    const formattedDate = matchDate.toLocaleDateString() + ' ' + matchDate.toLocaleTimeString();
                    
                    let team1Result = '';
                    let team2Result = '';
                    
                    if (team1Score > team2Score) {
                        team1Result = '<span class="result-badge result-win">WIN +3</span>';
                        team2Result = '<span class="result-badge result-loss">LOSS</span>';
                    } else if (team2Score > team1Score) {
                        team1Result = '<span class="result-badge result-loss">LOSS</span>';
                        team2Result = '<span class="result-badge result-win">WIN +3</span>';
                    } else {
                        team1Result = '<span class="result-badge result-draw">DRAW +1</span>';
                        team2Result = '<span class="result-badge result-draw">DRAW +1</span>';
                    }
                    
                    html += `
                        <tr>
                            <td><strong>#${match.match_id}</strong></td>
                            <td><span class="badge badge-primary">Round ${match.round}</span></td>
                            <td>
                                <div style="font-size: 13px;">
                                    <div class="team-name">${match.team1_name || 'Unknown'}</div>
                                    <div style="color: #999; margin: 5px 0;">vs</div>
                                    <div class="team-name">${match.team2_name || 'Unknown'}</div>
                                </div>
                            </td>
                            <td>
                                <div class="match-score">
                                    <span>${team1Score}</span>
                                    <span class="match-vs">-</span>
                                    <span>${team2Score}</span>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 11px; line-height: 1.8;">
                                    ${team1Result}
                                    <br>
                                    ${team2Result}
                                </div>
                            </td>
                            <td><strong style="color: #1D60AC;">${totalMatchGoals}</strong></td>
                            <td style="font-size: 12px; color: #999;">${formattedDate}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-secondary btn-sm" onclick="editMatch(${match.match_id})">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteMatch(${match.match_id}, ${match.round}, '${match.team1_name || 'Unknown'}', '${match.team2_name || 'Unknown'}', ${team1Score}, ${team2Score})">
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
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px; color: #dc3545;">Error loading matches</td></tr>';
            });
    }
    
    function updateStats(totalMatches, totalGoals) {
        document.getElementById('statTotalMatches').textContent = totalMatches;
        document.getElementById('statTotalGoals').textContent = totalGoals;
    }
    
    function openAddMatchModal() {
        document.getElementById('addMatchLeagueId').value = currentLeagueId;
        
        // Set default round to current league round
        if (currentLeagueData) {
            document.getElementById('addMatchRound').value = currentLeagueData.round;
        }
        
        document.getElementById('addMatchTeam1Score').value = 0;
        document.getElementById('addMatchTeam2Score').value = 0;
        
        // Reset team selections
        document.getElementById('addMatchTeam1').value = '';
        document.getElementById('addMatchTeam2').value = '';
        
        populateTeamSelects();
        document.getElementById('addMatchModal').classList.add('active');
    }
    
    function closeAddMatchModal() {
        document.getElementById('addMatchModal').classList.remove('active');
    }
    
    function editMatch(matchId) {
        fetch('?ajax=get_match&match_id=' + matchId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('editMatchId').value = data.match_id;
                document.getElementById('editMatchRound').value = data.round;
                document.getElementById('editMatchTeam1Score').value = data.team1_score || 0;
                document.getElementById('editMatchTeam2Score').value = data.team2_score || 0;
                
                populateTeamSelects();
                
                document.getElementById('editMatchTeam1').value = data.team1_id || '';
                document.getElementById('editMatchTeam2').value = data.team2_id || '';
                
                document.getElementById('editMatchModal').classList.add('active');
            })
            .catch(error => {
                console.error(error);
                alert('Error loading match data');
            });
    }
    
    function closeEditMatchModal() {
        document.getElementById('editMatchModal').classList.remove('active');
    }
    
    function deleteMatch(matchId, round, team1Name, team2Name, team1Score, team2Score) {
        document.getElementById('deleteMatchId').value = matchId;
        document.getElementById('deleteMatchIdDisplay').textContent = matchId;
        document.getElementById('deleteMatchRound').textContent = 'Round ' + round;
        document.getElementById('deleteMatchTeams').textContent = team1Name + ' vs ' + team2Name;
        document.getElementById('deleteMatchScore').textContent = team1Score + ' - ' + team2Score;
        document.getElementById('deleteMatchModal').classList.add('active');
    }
    
    function closeDeleteMatchModal() {
        document.getElementById('deleteMatchModal').classList.remove('active');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addMatchModal');
        const editModal = document.getElementById('editMatchModal');
        const deleteModal = document.getElementById('deleteMatchModal');
        
        if (event.target === addModal) {
            closeAddMatchModal();
        }
        if (event.target === editModal) {
            closeEditMatchModal();
        }
        if (event.target === deleteModal) {
            closeDeleteMatchModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>