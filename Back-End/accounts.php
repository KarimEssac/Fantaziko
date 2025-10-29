<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_account' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, phone_number, activated, created_at FROM accounts WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                echo json_encode($account);
            } else {
                echo json_encode(['error' => 'Account not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['ajax'] === 'get_account_details' && isset($_GET['id'])) {
        try {
            $id = $_GET['id'];
            
            $stmt = $pdo->prepare("SELECT id, username, email, phone_number, activated, created_at FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$account) {
                echo json_encode(['error' => 'Account not found']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    l.id,
                    l.name,
                    l.num_of_players,
                    l.num_of_teams,
                    l.system,
                    l.activated,
                    CASE 
                        WHEN l.owner = ? OR l.other_owner = ? THEN 'Owner'
                        ELSE lc.role
                    END as role,
                    lc.total_score
                FROM leagues l
                LEFT JOIN league_contributors lc ON l.id = lc.league_id AND lc.user_id = ?
                WHERE l.owner = ? OR l.other_owner = ? OR lc.user_id = ?
            ");
            $stmt->execute([$id, $id, $id, $id, $id, $id]);
            $leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $account['leagues'] = $leagues;
            
            echo json_encode($account);
            
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create':
                    $username     = trim($_POST['username']);
                    $email        = trim($_POST['email']);
                    $password     = $_POST['password'];
                    $phone_number = !empty($_POST['phone_number']) ? trim($_POST['phone_number']) : null;
                    $activated    = isset($_POST['activated']) ? 1 : 0;
                    $hashed       = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("INSERT INTO accounts (username, email, password, phone_number, activated) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed, $phone_number, $activated]);
                    $success_message = "Account created successfully!";
                    break;
                    
                case 'update':
                    $id           = $_POST['id'];
                    $username     = trim($_POST['username']);
                    $email        = trim($_POST['email']);
                    $phone_number = !empty($_POST['phone_number']) ? trim($_POST['phone_number']) : null;
                    $activated    = isset($_POST['activated']) ? 1 : 0;

                    if (!empty($_POST['password'])) {
                        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE accounts SET username = ?, email = ?, password = ?, phone_number = ?, activated = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $hashed, $phone_number, $activated, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE accounts SET username = ?, email = ?, phone_number = ?, activated = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $phone_number, $activated, $id]);
                    }
                    $success_message = "Account updated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = "Account deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE id = ? OR username LIKE ?";
    $params = [$search, "%$search%"];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM accounts $where_clause ORDER BY created_at DESC");
    $stmt->execute($params);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // FIXED: Removed &$account reference to prevent duplication bug
    foreach ($accounts as $key => $account) {
        $id = $account['id'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT l.id)
            FROM leagues l
            LEFT JOIN league_contributors lc ON l.id = lc.league_id AND lc.user_id = ?
            WHERE l.owner = ? OR l.other_owner = ? OR (lc.user_id = ? AND lc.role = 'Admin')
        ");
        $stmt->execute([$id, $id, $id, $id]);
        $accounts[$key]['admin_leagues'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM league_contributors
            WHERE user_id = ? AND role = 'Contributor'
        ");
        $stmt->execute([$id]);
        $accounts[$key]['contrib_leagues'] = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $accounts = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- [REST OF HTML/CSS/JS UNCHANGED - ONLY PHP LOOP FIXED ABOVE] -->

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
    
    .search-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #FFFFFF;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        flex: 1;
        max-width: 500px;
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
    
    .action-buttons {
        display: flex;
        gap: 8px;
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
    
    .leagues-section {
        margin-top: 25px;
    }
    
    .leagues-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .league-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 4px solid #F1A155;
    }
    
    .league-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .league-info {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        font-size: 13px;
        color: #666;
    }
    
    .league-info-item {
        display: flex;
        align-items: center;
        gap: 5px;
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
        
        .search-bar {
            max-width: 100%;
        }
        
        .table-container {
            overflow-x: scroll;
        }
        
        .modal-content {
            width: 95%;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">Accounts Management</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">
            Create New Account
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="search-bar">
        <span class="search-icon">Search</span>
        <input type="text" id="searchInput" placeholder="Search by ID or Username..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <br>
    
    <div class="data-card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Stats</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($accounts)): ?>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['id']); ?></td>
                                <td><?php echo htmlspecialchars($account['username']); ?></td>
                                <td><?php echo htmlspecialchars($account['email']); ?></td>
                                <td><?php echo htmlspecialchars($account['phone_number'] ?: 'N/A'); ?></td>
                                <td>
                                    <?php if ($account['activated']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($account['created_at'])); ?></td>
                                <td>Admin: <?php echo $account['admin_leagues']; ?> | Contributor: <?php echo $account['contrib_leagues']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info btn-sm" onclick="viewAccount(<?php echo $account['id']; ?>)">View</button>
                                        <button class="btn btn-secondary btn-sm" onclick="editAccount(<?php echo $account['id']; ?>)">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteAccount(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['username'], ENT_QUOTES); ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 30px;">No accounts found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals and JS unchanged -->
<div id="accountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Create New Account</span>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form id="accountForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="accountId">
                
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password <span id="passwordHint" style="display:none;">(Leave empty to keep current)</span></label>
                    <input type="password" name="password" id="password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" id="phone_number" class="form-control">
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
                <button type="submit" class="btn btn-primary">Save Account</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Account Details</span>
            <button class="modal-close" onclick="closeViewModal()">×</button>
        </div>
        <div class="modal-body" id="viewModalBody">
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span>Confirm Delete</span>
            <button class="modal-close" onclick="closeDeleteModal()">×</button>
        </div>
        <form id="deleteForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteAccountId">
                <p style="font-size: 14px; color: #333; margin: 0;">Are you sure you want to delete account <strong id="deleteAccountName"></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        const searchValue = this.value;
        const url = new URL(window.location.href);
        
        if (searchValue) {
            url.searchParams.set('search', searchValue);
        } else {
            url.searchParams.delete('search');
        }
        
        if (e.key === 'Enter') {
            window.location.href = url;
        }
    });
    
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Create New Account';
        document.getElementById('formAction').value = 'create';
        document.getElementById('accountForm').reset();
        document.getElementById('accountId').value = '';
        document.getElementById('passwordHint').style.display = 'none';
        document.getElementById('password').required = true;
        document.getElementById('accountModal').classList.add('active');
    }
    
    function editAccount(id) {
        fetch('?ajax=get_account&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                document.getElementById('modalTitle').textContent = 'Edit Account';
                document.getElementById('formAction').value = 'update';
                document.getElementById('accountId').value = data.id;
                document.getElementById('username').value = data.username;
                document.getElementById('email').value = data.email;
                document.getElementById('phone_number').value = data.phone_number || '';
                document.getElementById('activated').checked = data.activated == 1;
                document.getElementById('password').value = '';
                document.getElementById('password').required = false;
                document.getElementById('passwordHint').style.display = 'inline';
                document.getElementById('accountModal').classList.add('active');
            })
            .catch(error => {
                alert('Error loading account data');
                console.error(error);
            });
    }
    
    function viewAccount(id) {
        fetch('?ajax=get_account_details&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                let html = '<div class="details-grid">';
                html += `<div class="detail-item">
                    <div class="detail-label">ID</div>
                    <div class="detail-value">${data.id}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Username</div>
                    <div class="detail-value">${data.username}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">${data.email}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value">${data.phone_number || 'N/A'}</div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="badge ${data.activated == 1 ? 'badge-success' : 'badge-warning'}">
                            ${data.activated == 1 ? 'Active' : 'Pending'}
                        </span>
                    </div>
                </div>`;
                html += `<div class="detail-item">
                    <div class="detail-label">Created At</div>
                    <div class="detail-value">${new Date(data.created_at).toLocaleDateString()}</div>
                </div>`;
                html += '</div>';
                
                if (data.leagues && data.leagues.length > 0) {
                    html += '<div class="leagues-section">';
                    html += '<div class="leagues-title">Associated Leagues</div>';
                    data.leagues.forEach(league => {
                        html += `<div class="league-card">
                            <div class="league-name">${league.name}</div>
                            <div class="league-info">
                                <div class="league-info-item">
                                    <strong>Role:</strong> <span class="badge badge-info">${league.role}</span>
                                </div>
                                <div class="league-info-item">
                                    <strong>Players:</strong> ${league.num_of_players}
                                </div>
                                <div class="league-info-item">
                                    <strong>Teams:</strong> ${league.num_of_teams}
                                </div>
                                <div class="league-info-item">
                                    <strong>System:</strong> ${league.system}
                                </div>
                                <div class="league-info-item">
                                    <strong>Status:</strong>
                                    <span class="badge ${league.activated == 1 ? 'badge-success' : 'badge-warning'}">
                                        ${league.activated == 1 ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                                <div class="league-info-item">
                                    <strong>Total Score:</strong> ${league.total_score ?? 'N/A'}
                                </div>
                            </div>
                        </div>`;
                    });
                    html += '</div>';
                }
                
                document.getElementById('viewModalBody').innerHTML = html;
                document.getElementById('viewModal').classList.add('active');
            })
            .catch(error => {
                alert('Error loading account details');
                console.error(error);
            });
    }
    
    function deleteAccount(id, username) {
        document.getElementById('deleteAccountId').value = id;
        document.getElementById('deleteAccountName').textContent = username;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('accountModal').classList.remove('active');
    }
    
    function closeViewModal() {
        document.getElementById('viewModal').classList.remove('active');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    
    window.onclick = function(event) {
        const accountModal = document.getElementById('accountModal');
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target === accountModal) {
            closeModal();
        }
        if (event.target === viewModal) {
            closeViewModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>