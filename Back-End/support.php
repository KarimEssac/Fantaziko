<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth_check.php';
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'get_ticket_messages' && isset($_GET['id'])) {
        try {
            $ticket_id = $_GET['id'];
            $stmt = $pdo->prepare("
                SELECT ct.*, a.username, a.email
                FROM communication_tickets ct
                JOIN accounts a ON ct.user_id = a.id
                WHERE ct.id = ?
            ");
            $stmt->execute([$ticket_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                echo json_encode(['error' => 'Ticket not found']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                SELECT tm.*, 
                       CASE 
                           WHEN tm.sender_type = 'user' THEN a.username
                           WHEN tm.sender_type = 'admin' THEN ad.username
                       END as sender_name
                FROM ticket_messages tm
                LEFT JOIN accounts a ON tm.sender_type = 'user' AND tm.sender_id = a.id
                LEFT JOIN admins ad ON tm.sender_type = 'admin' AND tm.sender_id = ad.id
                WHERE tm.ticket_id = ?
                ORDER BY tm.created_at ASC
            ");
            $stmt->execute([$ticket_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $ticket['messages'] = $messages;
            
            echo json_encode($ticket);
            
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
                case 'send_message':
                    $ticket_id = $_POST['ticket_id'];
                    $message = trim($_POST['message']);
                    $admin_id = $_SESSION['admin_id']; 
                    
                    if (empty($message)) {
                        $error_message = "Message cannot be empty";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message)
                        VALUES (?, 'admin', ?, ?)
                    ");
                    $stmt->execute([$ticket_id, $admin_id, $message]);
                    
                    $success_message = "Message sent successfully!";
                    break;
                    
                case 'update_status':
                    $ticket_id = $_POST['ticket_id'];
                    $status = $_POST['status'];
                    $closed_at = ($status === 'closed' || $status === 'solved') ? date('Y-m-d H:i:s') : null;
                    $stmt = $pdo->prepare("
                        UPDATE communication_tickets 
                        SET status = ?, closed_at = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, $closed_at, $ticket_id]);
                    
                    $success_message = "Ticket status updated to " . ucfirst($status) . "!";
                    break;
                    
                case 'delete_ticket':
                    $ticket_id = $_POST['ticket_id'];
                    $stmt = $pdo->prepare("DELETE FROM communication_tickets WHERE id = ?");
                    $stmt->execute([$ticket_id]);
                    $success_message = "Ticket deleted successfully!";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(ct.id = ? OR ct.subject LIKE ? OR a.username LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "ct.status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT ct.*, 
               a.username, 
               a.email,
               (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = ct.id) as message_count,
               (SELECT created_at FROM ticket_messages WHERE ticket_id = ct.id ORDER BY created_at DESC LIMIT 1) as last_message_at
        FROM communication_tickets ct
        JOIN accounts a ON ct.user_id = a.id
        $where_clause
        ORDER BY 
            CASE 
                WHEN ct.status = 'open' THEN 1
                WHEN ct.status = 'solved' THEN 2
                WHEN ct.status = 'closed' THEN 3
            END,
            ct.created_at DESC
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM communication_tickets
        GROUP BY status
    ");
    $status_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_counts[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $tickets = [];
    $status_counts = [];
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
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-icon.open {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: #FFFFFF;
    }
    
    .stat-icon.solved {
        background: linear-gradient(135deg, #28a745, #218838);
        color: #FFFFFF;
    }
    
    .stat-icon.closed {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: #FFFFFF;
    }
    
    .stat-icon.total {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #333;
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
    
    .badge-open {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .badge-solved {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-closed {
        background: #e2e3e5;
        color: #383d41;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
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
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(29, 96, 172, 0.3);
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
    
    .btn-danger {
        background: #dc3545;
        color: #FFFFFF;
    }
    
    .btn-danger:hover {
        background: #c82333;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: #FFFFFF;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
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
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
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
        flex-shrink: 0;
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
        overflow-y: auto;
        flex: 1;
    }
    
    .ticket-header {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #1D60AC;
    }
    
    .ticket-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .info-label {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }
    
    .messages-container {
        max-height: 400px;
        overflow-y: auto;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .message {
        margin-bottom: 15px;
        padding: 15px;
        border-radius: 8px;
        position: relative;
    }
    
    .message.user {
        background: #e3f2fd;
        border-left: 4px solid #2196F3;
        margin-right: 20%;
    }
    
    .message.admin {
        background: #f3e5f5;
        border-left: 4px solid #9C27B0;
        margin-left: 20%;
    }
    
    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .message-sender {
        font-weight: 600;
        font-size: 13px;
        color: #333;
    }
    
    .message-time {
        font-size: 11px;
        color: #666;
    }
    
    .message-text {
        font-size: 14px;
        color: #333;
        line-height: 1.6;
        word-wrap: break-word;
    }
    
    .reply-form {
        background: #FFFFFF;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
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
        font-family: inherit;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #1D60AC;
        box-shadow: 0 0 0 3px rgba(29, 96, 172, 0.1);
    }
    
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .status-actions {
        display: flex;
        gap: 10px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
        font-size: 14px;
    }
    
    .ticket-subject {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    
    .ticket-meta {
        font-size: 12px;
        color: #999;
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
        
        .stats-row {
            grid-template-columns: 1fr;
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
        
        .message.user,
        .message.admin {
            margin-left: 0;
            margin-right: 0;
        }
        
        .modal-footer {
            flex-direction: column;
        }
        
        .status-actions {
            width: 100%;
            flex-direction: column;
        }
    }
</style>

<div class="main-content">
    <div class="page-header">
        <h1 class="page-title">Support Tickets</h1>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon open">📭</div>
            <div class="stat-content">
                <div class="stat-label">Open Tickets</div>
                <div class="stat-value"><?php echo $status_counts['open'] ?? 0; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon solved">✅</div>
            <div class="stat-content">
                <div class="stat-label">Solved</div>
                <div class="stat-value"><?php echo $status_counts['solved'] ?? 0; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon closed">🔒</div>
            <div class="stat-content">
                <div class="stat-label">Closed</div>
                <div class="stat-value"><?php echo $status_counts['closed'] ?? 0; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon total">📊</div>
            <div class="stat-content">
                <div class="stat-label">Total Tickets</div>
                <div class="stat-value"><?php echo array_sum($status_counts); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-bar">
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Search by ID, Subject, or Username..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <select id="statusFilter" class="filter-select">
            <option value="">All Status</option>
            <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
            <option value="solved" <?php echo $status_filter === 'solved' ? 'selected' : ''; ?>>Solved</option>
            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
        </select>
    </div>
    
    <!-- Tickets Table -->
    <div class="data-card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Messages</th>
                        <th>Created</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($ticket['id']); ?></strong></td>
                                <td>
                                    <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                    <div class="ticket-meta">by <?php echo htmlspecialchars($ticket['username']); ?></div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($ticket['username']); ?><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($ticket['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $ticket['status']; ?>">
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $ticket['message_count']; ?></strong> messages
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></td>
                                <td>
                                    <?php if ($ticket['last_message_at']): ?>
                                        <?php echo date('M d, Y H:i', strtotime($ticket['last_message_at'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No messages</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-primary btn-sm" onclick="viewTicket(<?php echo $ticket['id']; ?>)">
                                            💬 View
                                        </button>
                                        <?php if ($ticket['status'] === 'open'): ?>
                                            <button class="btn btn-success btn-sm" onclick="updateStatus(<?php echo $ticket['id']; ?>, 'solved')">
                                                ✅ Solve
                                            </button>
                                            <button class="btn btn-secondary btn-sm" onclick="updateStatus(<?php echo $ticket['id']; ?>, 'closed')">
                                                🔒 Close
                                            </button>
                                        <?php elseif ($ticket['status'] === 'solved'): ?>
                                            <button class="btn btn-info btn-sm" onclick="updateStatus(<?php echo $ticket['id']; ?>, 'open')">
                                                📭 Reopen
                                            </button>
                                            <button class="btn btn-secondary btn-sm" onclick="updateStatus(<?php echo $ticket['id']; ?>, 'closed')">
                                                🔒 Close
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-info btn-sm" onclick="updateStatus(<?php echo $ticket['id']; ?>, 'open')">
                                                📭 Reopen
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteTicket(<?php echo $ticket['id']; ?>, '<?php echo htmlspecialchars(addslashes($ticket['subject'])); ?>')">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">No tickets found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View/Reply Ticket Modal -->
<div id="ticketModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Ticket Details</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="ticketModalBody">
            <!-- Content will be loaded dynamically -->
        </div>
        <div class="modal-footer">
            <div class="status-actions" id="statusActions">
                <!-- Status buttons will be added dynamically -->
            </div>
            <button class="btn btn-danger" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<!-- Update Status Confirmation Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span>Update Ticket Status</span>
            <button class="modal-close" onclick="closeStatusModal()">&times;</button>
        </div>
        <form id="statusForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" id="statusTicketId">
                <input type="hidden" name="status" id="statusValue">
                <p style="font-size: 14px; color: #333; margin: 0;" id="statusMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Ticket Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span>Confirm Delete</span>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form id="deleteForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="delete_ticket">
                <input type="hidden" name="ticket_id" id="deleteTicketId">
                <p style="font-size: 14px; color: #333; margin: 0;">
                    Are you sure you want to delete ticket <strong id="deleteTicketSubject"></strong>? 
                    This action cannot be undone and will delete all messages associated with this ticket.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentTicketId = null;
    let currentTicketStatus = null;
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
    
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    
    function applyFilters() {
        const search = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilter').value;
        
        const url = new URL(window.location.href);
        url.search = '';
        
        if (search) url.searchParams.set('search', search);
        if (status) url.searchParams.set('status', status);
        
        window.location.href = url;
    }

    function viewTicket(id) {
        currentTicketId = id;
        
        fetch('?ajax=get_ticket_messages&id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                currentTicketStatus = data.status;
                
                let html = '';
                html += '<div class="ticket-header">';
                html += '<h3 style="margin: 0 0 15px 0; color: #1D60AC;">' + escapeHtml(data.subject) + '</h3>';
                html += '<div class="ticket-info">';
                html += '<div class="info-item">';
                html += '<div class="info-label">Ticket ID</div>';
                html += '<div class="info-value">#' + data.id + '</div>';
                html += '</div>';
                html += '<div class="info-item">';
                html += '<div class="info-label">User</div>';
                html += '<div class="info-value">' + escapeHtml(data.username) + '</div>';
                html += '</div>';
                html += '<div class="info-item">';
                html += '<div class="info-label">Email</div>';
                html += '<div class="info-value">' + escapeHtml(data.email) + '</div>';
                html += '</div>';
                html += '<div class="info-item">';
                html += '<div class="info-label">Status</div>';
                html += '<div class="info-value"><span class="badge badge-' + data.status + '">' + capitalize(data.status) + '</span></div>';
                html += '</div>';
                html += '<div class="info-item">';
                html += '<div class="info-label">Created</div>';
                html += '<div class="info-value">' + formatDate(data.created_at) + '</div>';
                html += '</div>';
                if (data.closed_at) {
                    html += '<div class="info-item">';
                    html += '<div class="info-label">Closed</div>';
                    html += '<div class="info-value">' + formatDate(data.closed_at) + '</div>';
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';
                html += '<div class="messages-container">';
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        html += '<div class="message ' + msg.sender_type + '">';
                        html += '<div class="message-header">';
                        html += '<span class="message-sender">';
                        html += msg.sender_type === 'admin' ? '👔 ' : '👤 ';
                        html += escapeHtml(msg.sender_name || 'Unknown');
                        html += '</span>';
                        html += '<span class="message-time">' + formatDate(msg.created_at) + '</span>';
                        html += '</div>';
                        html += '<div class="message-text">' + escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>';
                        html += '</div>';
                    });
                } else {
                    html += '<div class="empty-state">No messages yet</div>';
                }
                html += '</div>';
                if (data.status === 'open' || data.status === 'solved') {
                    html += '<div class="reply-form">';
                    html += '<form id="replyForm" method="POST">';
                    html += '<input type="hidden" name="action" value="send_message">';
                    html += '<input type="hidden" name="ticket_id" value="' + data.id + '">';
                    html += '<div class="form-group">';
                    html += '<label class="form-label">Reply to Ticket</label>';
                    html += '<textarea name="message" class="form-control" placeholder="Type your message here..." required></textarea>';
                    html += '</div>';
                    html += '<button type="submit" class="btn btn-primary">📤 Send Reply</button>';
                    html += '</form>';
                    html += '</div>';
                } else {
                    html += '<div class="alert alert-error">This ticket is closed. Reopen it to send messages.</div>';
                }
                
                document.getElementById('ticketModalBody').innerHTML = html;
                let statusActionsHtml = '';
                if (data.status === 'open') {
                    statusActionsHtml += '<button class="btn btn-success btn-sm" onclick="updateStatusFromModal(\'solved\')">✅ Mark as Solved</button>';
                    statusActionsHtml += '<button class="btn btn-secondary btn-sm" onclick="updateStatusFromModal(\'closed\')">🔒 Close Ticket</button>';
                } else if (data.status === 'solved') {
                    statusActionsHtml += '<button class="btn btn-info btn-sm" onclick="updateStatusFromModal(\'open\')">📭 Reopen</button>';
                    statusActionsHtml += '<button class="btn btn-secondary btn-sm" onclick="updateStatusFromModal(\'closed\')">🔒 Close Ticket</button>';
                } else {
                    statusActionsHtml += '<button class="btn btn-info btn-sm" onclick="updateStatusFromModal(\'open\')">📭 Reopen Ticket</button>';
                }
                document.getElementById('statusActions').innerHTML = statusActionsHtml;
                document.getElementById('ticketModal').classList.add('active');
                const messagesContainer = document.querySelector('.messages-container');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            })
            .catch(error => {
                alert('Error loading ticket details');
                console.error(error);
            });
    }
    
    function updateStatus(ticketId, status) {
        document.getElementById('statusTicketId').value = ticketId;
        document.getElementById('statusValue').value = status;
        
        let message = '';
        switch(status) {
            case 'open':
                message = 'Are you sure you want to reopen this ticket?';
                break;
            case 'solved':
                message = 'Are you sure you want to mark this ticket as solved?';
                break;
            case 'closed':
                message = 'Are you sure you want to close this ticket?';
                break;
        }
        
        document.getElementById('statusMessage').textContent = message;
        document.getElementById('statusModal').classList.add('active');
    }
    
    function updateStatusFromModal(status) {
        if (!currentTicketId) return;
        
        document.getElementById('statusTicketId').value = currentTicketId;
        document.getElementById('statusValue').value = status;
        
        let message = '';
        switch(status) {
            case 'open':
                message = 'Are you sure you want to reopen this ticket?';
                break;
            case 'solved':
                message = 'Are you sure you want to mark this ticket as solved?';
                break;
            case 'closed':
                message = 'Are you sure you want to close this ticket?';
                break;
        }
        
        document.getElementById('statusMessage').textContent = message;
        closeModal();
        document.getElementById('statusModal').classList.add('active');
    }
    function deleteTicket(id, subject) {
        document.getElementById('deleteTicketId').value = id;
        document.getElementById('deleteTicketSubject').textContent = subject;
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('ticketModal').classList.remove('active');
        currentTicketId = null;
        currentTicketStatus = null;
    }
    
    function closeStatusModal() {
        document.getElementById('statusModal').classList.remove('active');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }

    window.onclick = function(event) {
        const ticketModal = document.getElementById('ticketModal');
        const statusModal = document.getElementById('statusModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (event.target === ticketModal) {
            closeModal();
        }
        if (event.target === statusModal) {
            closeStatusModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        return date.toLocaleDateString('en-US', options);
    }
</script>

<?php include 'includes/footer.php'; ?>