<?php
session_start();
require_once 'config/db.php';

// Handle AJAX requests for getting admin data
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_admin' && isset($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                echo json_encode($admin);
            } else {
                echo json_encode(['error' => 'Admin not found']);
            }
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
                    $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $_POST['email'],
                        $hashedPassword
                    ]);
                    $success_message = "Administrator created successfully!";
                    break;
                    
                case 'update':
                    if (!empty($_POST['password'])) {
                        $stmt = $pdo->prepare("UPDATE admins SET email = ?, password = ? WHERE id = ?");
                        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt->execute([
                            $_POST['email'],
                            $hashedPassword,
                            $_POST['id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE admins SET email = ? WHERE id = ?");
                        $stmt->execute([
                            $_POST['email'],
                            $_POST['id']
                        ]);
                    }
                    $success_message = "Administrator updated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $success_message = "Administrator deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch admins with search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE id = ? OR email LIKE ?";
    $params = [$search, "%$search%"];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM admins $where_clause ORDER BY id DESC");
    $stmt->execute($params);
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $admins = [];
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
        max-width: 500px;
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
        <h1 class="page-title">Administrators Management</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">
            ➕ Create New Administrator
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search by ID or Email..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <br>
    
    <div class="data-card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($admins)): ?>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['id']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td>
                                    <span class="badge badge-info">Administrator</span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-secondary btn-sm" onclick="editAdmin(<?php echo $admin['id']; ?>)">✏️ Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['email']); ?>')">🗑️ Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999; padding: 30px;">No administrators found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="adminModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Create New Administrator</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="adminForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="adminId">
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password <span id="passwordHint" style="display:none;">(Leave empty to keep current)</span></label>
                    <input type="password" name="password" id="password" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Administrator</button>
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
                <input type="hidden" name="id" id="deleteAdminId">
                <p style="font-size: 14px; color: #333; margin: 0;">Are you sure you want to delete administrator <strong id="deleteAdminEmail"></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Search functionality with real-time filtering
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        const searchValue = this.value;
        const url = new URL(window.location.href);
        
        if (searchValue) {
            url.searchParams.set('search', searchValue);
        } else {
            url.searchParams.delete('search');
        }
        
        window.history.replaceState({}, '', url);
        
        // Reload page with new search parameter
        if (e.key === 'Enter' || searchValue === '' || /^\d+$/.test(searchValue)) {
            window.location.href = url;
        }
    });
    
    // Auto-submit on Enter key
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            window.location.href = window.location.href.split('?')[0] + (this.value ? '?search=' + encodeURIComponent(this.value) : '');
        }
    });
    
    function openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Create New Administrator';
        document.getElementById('formAction').value = 'create';
        document.getElementById('adminForm').reset();
        document.getElementById('adminId').value = '';
        document.getElementById('passwordHint').style.display = 'none';
        document.getElementById('password').required = true;
        document.getElementById('adminModal').classList.add('active');
    }
    
    function editAdmin(id) {
        fetch('?ajax=get_admin&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                document.getElementById('modalTitle').textContent = 'Edit Administrator';
                document.getElementById('formAction').value = 'update';
                document.getElementById('adminId').value = data.id;
                document.getElementById('email').value = data.email;
                document.getElementById('password').value = '';
                document.getElementById('password').required = false;
                document.getElementById('passwordHint').style.display = 'inline';
                document.getElementById('adminModal').classList.add('active');
            })
            .catch(error => {
                alert('Error loading administrator data');
                console.error(error);
            });
    }
    
    function deleteAdmin(id, email) {
        document.getElementById('deleteAdminId').value = id;
        document.getElementById('deleteAdminEmail').textContent = email;
        document.getElementById('deleteModal').classList.add('active');
    }
    
    function closeModal() {
        document.getElementById('adminModal').classList.remove('active');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const adminModal = document.getElementById('adminModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target === adminModal) {
            closeModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>