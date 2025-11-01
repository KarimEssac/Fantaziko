<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$league_id = $_GET['id'] ?? '';

if (empty($league_id)) {
    header("Location: ../main.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT l.*, lt.token 
    FROM leagues l
    LEFT JOIN league_tokens lt ON l.id = lt.league_id
    WHERE l.id = ?
");
$stmt->execute([$league_id]);
$league = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$league) {
    $league_not_found = true;
    $not_owner = false;
    $not_activated = false;
} else {
    $league_not_found = false;
    $league_token = $league['token'] ?? '';
    if ($league['owner'] != $user_id && $league['other_owner'] != $user_id) {
        $not_owner = true;
        $not_activated = false;
    } else {
        $not_owner = false;
        if (!$league['activated']) {
            $not_activated = true;
        } else {
            $not_activated = false;
        }
    }
}
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$league_not_found && !$not_owner && !$not_activated) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_ticket') {
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            if (empty($subject) || empty($message)) {
                $error_message = 'Please provide both subject and message.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("
                        INSERT INTO communication_tickets (user_id, subject, status, created_at)
                        VALUES (?, ?, 'open', NOW())
                    ");
                    $stmt->execute([$user_id, $subject]);
                    $ticket_id = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("
                        INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at)
                        VALUES (?, 'user', ?, ?, NOW())
                    ");
                    $stmt->execute([$ticket_id, $user_id, $message]);
                    
                    $pdo->commit();
                    $success_message = 'Ticket created successfully! Our team will respond soon.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = 'Failed to create ticket. Please try again.';
                }
            }
        } elseif ($_POST['action'] === 'send_message') {
            $ticket_id = $_POST['ticket_id'] ?? 0;
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                $error_message = 'Please enter a message.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, status 
                    FROM communication_tickets 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$ticket_id, $user_id]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$ticket) {
                    $error_message = 'Invalid ticket.';
                } elseif ($ticket['status'] !== 'open') {
                    $error_message = 'This ticket is closed and cannot receive new messages.';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, created_at)
                            VALUES (?, 'user', ?, ?, NOW())
                        ");
                        $stmt->execute([$ticket_id, $user_id, $message]);
                        $success_message = 'Message sent successfully!';
                    } catch (Exception $e) {
                        $error_message = 'Failed to send message. Please try again.';
                    }
                }
            }
        }
    }
}

$tickets = [];
if (!$league_not_found && !$not_owner && !$not_activated) {
    $stmt = $pdo->prepare("
        SELECT 
            ct.id,
            ct.subject,
            ct.status,
            ct.created_at,
            ct.closed_at,
            (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = ct.id) as message_count,
            (SELECT message FROM ticket_messages WHERE ticket_id = ct.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM ticket_messages WHERE ticket_id = ct.id ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM communication_tickets ct
        WHERE ct.user_id = ?
        ORDER BY 
            CASE ct.status 
                WHEN 'open' THEN 1 
                WHEN 'solved' THEN 2 
                WHEN 'closed' THEN 3 
            END,
            ct.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTicketMessages($pdo, $ticket_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            tm.*,
            a.username as sender_name
        FROM ticket_messages tm
        LEFT JOIN accounts a ON tm.sender_id = a.id AND tm.sender_type = 'user'
        LEFT JOIN admins ad ON tm.sender_id = ad.id AND tm.sender_type = 'admin'
        WHERE tm.ticket_id = ?
        ORDER BY tm.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$selected_ticket = null;
$ticket_messages = [];
if (isset($_GET['ticket_id']) && !$league_not_found && !$not_owner && !$not_activated) {
    $ticket_id = $_GET['ticket_id'];
    $stmt = $pdo->prepare("
        SELECT * FROM communication_tickets 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $selected_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_ticket) {
        $ticket_messages = getTicketMessages($pdo, $ticket_id, $user_id);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - <?php echo htmlspecialchars($league['name'] ?? 'Fantazina'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fc;
            --text-primary: #000000;
            --text-secondary: #666666;
            --border-color: rgba(0, 0, 0, 0.1);
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-hover: rgba(255, 255, 255, 1);
            --nav-bg: rgba(255, 255, 255, 0.95);
            --gradient-start: #1D60AC;
            --gradient-end: #0A92D7;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            --shadow-hover: 0 10px 30px rgba(10, 146, 215, 0.2);
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }
        
        body.dark-mode {
            --bg-primary: #000000;
            --bg-secondary: #0a0a0a;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
            --border-color: rgba(255, 255, 255, 0.1);
            --card-bg: rgba(20, 20, 20, 0.95);
            --card-hover: rgba(30, 30, 30, 0.95);
            --nav-bg: rgba(0, 0, 0, 0.95);
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 10px 30px rgba(10, 146, 215, 0.3);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                repeating-linear-gradient(0deg, var(--border-color) 0px, transparent 1px, transparent 40px, var(--border-color) 41px),
                repeating-linear-gradient(90deg, var(--border-color) 0px, transparent 1px, transparent 40px, var(--border-color) 41px);
            opacity: 0.3;
            pointer-events: none;
            z-index: 0;
        }
        
        body.dark-mode::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at center, rgba(29, 96, 172, 0.15), transparent);
            pointer-events: none;
            z-index: 0;
        }

        .loading-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .loading-spinner.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .spinner {
            width: 80px;
            height: 80px;
            border: 6px solid var(--border-color);
            border-top: 6px solid var(--gradient-end);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            margin-top: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .loading-logo {
            margin-bottom: 2rem;
        }
        
        .loading-logo img {
            height: 80px;
            width: auto;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
        }
        
        body.dark-mode .loading-logo img {
            content: url('../assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .loading-logo img {
            content: url('../assets/images/logo.png');
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            position: relative;
            z-index: 1;
        }

        .not-owner-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 80vh;
        }

        .not-owner-card, .not-activated-card, .not-found-card {
            background: var(--card-bg);
            border: 2px solid var(--error);
            border-radius: 25px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(239, 68, 68, 0.2);
        }

        .not-activated-card {
            border-color: var(--warning);
            box-shadow: 0 20px 60px rgba(245, 158, 11, 0.2);
        }

        .not-found-card {
            border-color: var(--text-secondary);
            box-shadow: 0 20px 60px rgba(102, 102, 102, 0.2);
        }

        body.dark-mode .not-owner-card,
        body.dark-mode .not-activated-card,
        body.dark-mode .not-found-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
        }

        .not-owner-icon, .not-activated-icon, .not-found-icon {
            font-size: 5rem;
            color: var(--error);
            margin-bottom: 1.5rem;
        }

        .not-activated-icon {
            color: var(--warning);
        }

        .not-found-icon {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .not-owner-title, .not-activated-title, .not-found-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--error);
            margin-bottom: 1rem;
        }

        .not-activated-title {
            color: var(--warning);
        }

        .not-found-title {
            color: var(--text-primary);
        }

        .not-owner-text {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            color: var(--gradient-end);
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }

        .alert {
            padding: 1.2rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
        }

        .alert i {
            font-size: 1.5rem;
        }

        .support-container {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            height: calc(100vh - 180px);
        }

        .tickets-sidebar {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        body.dark-mode .tickets-sidebar {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .tickets-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .tickets-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .new-ticket-btn {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .new-ticket-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.4);
        }

        .tickets-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .tickets-list::-webkit-scrollbar {
            width: 6px;
        }

        .tickets-list::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 10px;
        }

        .tickets-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
        }

        .ticket-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }

        body.dark-mode .ticket-item {
            background: rgba(10, 20, 35, 0.5);
        }

        .ticket-item:hover {
            background: rgba(10, 146, 215, 0.1);
            border-color: var(--gradient-end);
            transform: translateX(5px);
        }

        .ticket-item.active {
            background: rgba(10, 146, 215, 0.15);
            border-color: var(--gradient-end);
        }

        .ticket-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .ticket-subject {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .ticket-status {
            padding: 0.25rem 0.6rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .ticket-status.open {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .ticket-status.solved {
            background: rgba(59, 130, 246, 0.2);
            color: var(--info);
        }

        .ticket-status.closed {
            background: rgba(102, 102, 102, 0.2);
            color: var(--text-secondary);
        }

        .ticket-preview {
            font-size: 0.85rem;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 0.5rem;
        }

        .ticket-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .ticket-time {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .ticket-messages-count {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .empty-tickets {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-tickets i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .chat-area {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        body.dark-mode .chat-area {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-header-left {
            display: flex;
            flex-direction: column;
        }

        .chat-ticket-subject {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.3rem;
        }

        .chat-ticket-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .chat-messages {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
        }

        .message {
            display: flex;
            gap: 1rem;
            animation: messageSlideIn 0.3s ease;
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .message.admin .message-avatar {
            background: linear-gradient(135deg, #F1A155, #e89944);
        }

        .message-content {
            flex: 1;
            max-width: 70%;
        }

        .message-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .message.user .message-header {
            flex-direction: row-reverse;
        }

        .message-sender {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .message-bubble {
            background: var(--bg-secondary);
            padding: 1rem 1.2rem;
            border-radius: 18px;
            color: var(--text-primary);
            line-height: 1.6;
            word-wrap: break-word;
        }

        body.dark-mode .message-bubble {
            background: rgba(10, 20, 35, 0.5);
        }

        .message.user .message-bubble {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }

        .message.admin .message-bubble {
            background: linear-gradient(135deg, rgba(241, 161, 85, 0.2), rgba(232, 153, 68, 0.2));
            border: 1px solid rgba(241, 161, 85, 0.3);
        }

        .chat-input-area {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .chat-input-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }

        .chat-input-wrapper {
            flex: 1;
            position: relative;
        }

        .chat-input {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            font-size: 0.95rem;
            resize: none;
            transition: all 0.3s ease;
            min-height: 50px;
            max-height: 150px;
        }

        body.dark-mode .chat-input {
            background: rgba(10, 20, 35, 0.5);
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }

        .chat-send-btn {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.4);
        }

        .chat-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .chat-closed-notice {
            padding: 1rem;
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            border-top: 1px solid var(--border-color);
        }

        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            padding: 2rem;
        }

        .empty-chat i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.3;
        }

        .empty-chat h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-chat p {
            font-size: 1rem;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 25px;
            padding: 2.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
            position: relative;
        }

        body.dark-mode .modal-content {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.95), rgba(15, 25, 40, 0.95));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }

        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            transform: rotate(90deg);
        }

        .modal-header {
            margin-bottom: 2rem;
            padding-right: 2rem;
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .modal-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        body.dark-mode .form-input,
        body.dark-mode .form-textarea {
            background: rgba(10, 20, 35, 0.5);
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 150px;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 50px;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-gradient {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--gradient-end);
            border: 2px solid var(--gradient-end);
        }

        .btn-secondary:hover {
            background: var(--gradient-end);
            color: white;
        }

        @media (max-width: 1200px) {
            .support-container {
                grid-template-columns: 350px 1fr;
            }
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .support-container {
                grid-template-columns: 1fr;
                height: auto;
            }

            .tickets-sidebar {
                height: 400px;
            }

            .chat-area {
                height: 600px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .support-container {
                gap: 1rem;
            }

            .message-content {
                max-width: 85%;
            }

            .chat-input-form {
                flex-direction: column;
            }

            .chat-send-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="loading-spinner" id="loadingSpinner">
        <div class="loading-logo">
            <img src="../assets/images/logo white outline.png" alt="Fantazina Logo">
        </div>
        <div class="spinner"></div>
        <div class="loading-text">Loading Support...</div>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/sidebar.php'; ?>
    <?php endif; ?>

    <?php include 'includes/header.php'; ?>

    <!-- New Ticket Modal -->
    <div class="modal-overlay" id="newTicketModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeNewTicketModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">Create New Ticket</h2>
                <p class="modal-subtitle">Describe your issue and our team will help you</p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_ticket">
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-input" placeholder="Brief description of your issue" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-textarea" placeholder="Provide details about your issue..." required></textarea>
                </div>
                <button type="submit" class="btn btn-gradient" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i>
                    Submit Ticket
                </button>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($league_not_found): ?>
            <div class="not-owner-container">
                <div class="not-found-card">
                    <div class="not-found-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h1 class="not-found-title">Oops, No Such League Exists</h1>
                    <p class="not-owner-text">The league you're looking for could not be found. It may have been deleted or the link is incorrect.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($not_owner): ?>
            <div class="not-owner-container">
                <div class="not-owner-card">
                    <div class="not-owner-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h1 class="not-owner-title">Access Denied</h1>
                    <p class="not-owner-text">You don't have permission to access this league's support. Only the league owner can create support tickets.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php elseif ($not_activated): ?>
            <div class="not-owner-container">
                <div class="not-activated-card">
                    <div class="not-activated-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h1 class="not-activated-title">League Not Activated Yet</h1>
                    <p class="not-owner-text">This league is currently under review. Please wait until we review your application. You will be notified once your league is activated.</p>
                    <a href="../main.php" class="btn btn-gradient">
                        <i class="fas fa-home"></i>
                        Return to Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="page-header">
                <h2 class="page-title">
                    <i class="fas fa-life-ring"></i>
                    Support Center
                </h2>
                <p class="page-subtitle">Get help from our team</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="support-container">
                <!-- Tickets Sidebar -->
                <div class="tickets-sidebar">
                    <div class="tickets-header">
                        <h3>Your Tickets</h3>
                        <button class="new-ticket-btn" onclick="openNewTicketModal()">
                            <i class="fas fa-plus"></i>
                            New
                        </button>
                    </div>
                    <div class="tickets-list">
                        <?php if (empty($tickets)): ?>
                            <div class="empty-tickets">
                                <i class="fas fa-inbox"></i>
                                <p>No tickets yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <a href="support.php?id=<?php echo $league_id; ?>&ticket_id=<?php echo $ticket['id']; ?>" 
                                   class="ticket-item <?php echo ($selected_ticket && $selected_ticket['id'] == $ticket['id']) ? 'active' : ''; ?>">
                                    <div class="ticket-item-header">
                                        <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                        <span class="ticket-status <?php echo $ticket['status']; ?>">
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </div>
                                    <div class="ticket-preview">
                                        <?php echo htmlspecialchars(substr($ticket['last_message'] ?? 'No messages', 0, 60)) . (strlen($ticket['last_message'] ?? '') > 60 ? '...' : ''); ?>
                                    </div>
                                    <div class="ticket-meta">
                                        <div class="ticket-time">
                                            <i class="fas fa-clock"></i>
                                            <?php 
                                            $time = strtotime($ticket['last_message_time'] ?? $ticket['created_at']);
                                            $diff = time() - $time;
                                            if ($diff < 60) echo 'Just now';
                                            elseif ($diff < 3600) echo floor($diff / 60) . 'm ago';
                                            elseif ($diff < 86400) echo floor($diff / 3600) . 'h ago';
                                            else echo floor($diff / 86400) . 'd ago';
                                            ?>
                                        </div>
                                        <div class="ticket-messages-count">
                                            <i class="fas fa-comments"></i>
                                            <?php echo $ticket['message_count']; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if ($selected_ticket): ?>
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <div class="chat-ticket-subject"><?php echo htmlspecialchars($selected_ticket['subject']); ?></div>
                                <div class="chat-ticket-meta">
                                    Ticket #<?php echo $selected_ticket['id']; ?> • 
                                    Created <?php echo date('M j, Y', strtotime($selected_ticket['created_at'])); ?>
                                </div>
                            </div>
                            <span class="ticket-status <?php echo $selected_ticket['status']; ?>">
                                <?php echo ucfirst($selected_ticket['status']); ?>
                            </span>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($ticket_messages as $message): ?>
                                <div class="message <?php echo $message['sender_type']; ?>">
                                    <div class="message-avatar">
                                        <?php 
                                        if ($message['sender_type'] === 'user') {
                                            echo strtoupper(substr($username, 0, 1));
                                        } else {
                                            echo 'A';
                                        }
                                        ?>
                                    </div>
                                    <div class="message-content">
                                        <div class="message-header">
                                            <div class="message-sender">
                                                <?php echo $message['sender_type'] === 'user' ? htmlspecialchars($username) : 'Admin Support'; ?>
                                            </div>
                                            <div class="message-time">
                                                <?php echo date('M j, Y • g:i A', strtotime($message['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="message-bubble">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($selected_ticket['status'] === 'open'): ?>
                            <div class="chat-input-area">
                                <form method="POST" action="" class="chat-input-form" onsubmit="return validateMessage()">
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="ticket_id" value="<?php echo $selected_ticket['id']; ?>">
                                    <div class="chat-input-wrapper">
                                        <textarea 
                                            name="message" 
                                            class="chat-input" 
                                            placeholder="Type your message..."
                                            id="messageInput"
                                            rows="1"
                                            required
                                        ></textarea>
                                    </div>
                                    <button type="submit" class="chat-send-btn">
                                        <i class="fas fa-paper-plane"></i>
                                        Send
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="chat-closed-notice">
                                This ticket is <?php echo $selected_ticket['status']; ?> and cannot receive new messages.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-chat">
                            <i class="fas fa-comments"></i>
                            <h3>Select a Ticket</h3>
                            <p>Choose a ticket from the sidebar or create a new one</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$league_not_found && !$not_owner && !$not_activated): ?>
    <?php include 'includes/footer.php'; ?>
    <?php endif; ?>

    <script>
        window.addEventListener('load', function() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            setTimeout(() => {
                loadingSpinner.classList.add('hidden');
            }, 500);
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });

        function openNewTicketModal() {
            const modal = document.getElementById('newTicketModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeNewTicketModal() {
            const modal = document.getElementById('newTicketModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('newTicketModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewTicketModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('newTicketModal');
                if (modal.classList.contains('active')) {
                    closeNewTicketModal();
                }
            }
        });
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 150) + 'px';
            });
        }

        function validateMessage() {
            const messageInput = document.getElementById('messageInput');
            if (messageInput && messageInput.value.trim() === '') {
                alert('Please enter a message');
                return false;
            }
            return true;
        }
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
    </script>
</body>
</html>