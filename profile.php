<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_profile') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone_number = trim($_POST['phone_number']);
        if (empty($username) || empty($email)) {
            $error_message = "Username and email are required.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $error_message = "Username is already taken.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM accounts WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error_message = "Email is already in use.";
                } else {
                    if (!empty($phone_number)) {
                        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE phone_number = ? AND id != ?");
                        $stmt->execute([$phone_number, $user_id]);
                        if ($stmt->fetch()) {
                            $error_message = "Phone number is already in use.";
                        }
                    }
                    
                    if (empty($error_message)) {
                        $stmt = $pdo->prepare("UPDATE accounts SET username = ?, email = ?, phone_number = ? WHERE id = ?");
                        if ($stmt->execute([$username, $email, $phone_number ?: null, $user_id])) {
                            $_SESSION['username'] = $username;
                            $_SESSION['email'] = $email;
                            $success_message = "Profile updated successfully!";
                            $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $error_message = "Failed to update profile.";
                        }
                    }
                }
            }
        }
    }
    
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Failed to change password.";
            }
        }
    }
    
    if ($_POST['action'] === 'delete_account') {
        $password = $_POST['delete_password'];
        
        if (empty($password)) {
            $error_message = "Password is required to delete account.";
        } elseif (!password_verify($password, $user['password'])) {
            $error_message = "Incorrect password.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                session_destroy();
                header("Location: index.php?deleted=1");
                exit();
            } else {
                $error_message = "Failed to delete account.";
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT l.id) as leagues_owned
    FROM leagues l
    WHERE l.owner = ? OR l.other_owner = ?
");
$stmt->execute([$user_id, $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT lc.league_id) as leagues_joined
    FROM league_contributors lc
    WHERE lc.user_id = ?
");
$stmt->execute([$user_id]);
$joined_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(lc.total_score), 0) as total_points
    FROM league_contributors lc
    WHERE lc.user_id = ?
");
$stmt->execute([$user_id]);
$points_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Fantazina</title>
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
            --success-bg: rgba(40, 167, 69, 0.1);
            --success-border: rgba(40, 167, 69, 0.3);
            --success-color: #28a745;
            --error-bg: rgba(220, 53, 69, 0.1);
            --error-border: rgba(220, 53, 69, 0.3);
            --error-color: #dc3545;
            --danger-bg: rgba(220, 53, 69, 0.05);
            --danger-border: rgba(220, 53, 69, 0.2);
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
            --success-bg: rgba(40, 167, 69, 0.2);
            --success-border: rgba(40, 167, 69, 0.4);
            --error-bg: rgba(220, 53, 69, 0.2);
            --error-border: rgba(220, 53, 69, 0.4);
            --danger-bg: rgba(220, 53, 69, 0.1);
            --danger-border: rgba(220, 53, 69, 0.3);
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
        
        body.dark-mode::before {
            background: radial-gradient(ellipse at center, rgba(29, 96, 172, 0.15), transparent);
        }
        
        body.dark-mode::after {
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
        
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: var(--nav-bg);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        
        .logo-container img {
            height: 50px;
            width: auto;
        }
        
        body.dark-mode .logo-container img {
            content: url('assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .logo-container img {
            content: url('assets/images/logo.png');
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        body.dark-mode .logo-text {
            background: none;
            -webkit-text-fill-color: white;
            color: white;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .theme-toggle {
            background: transparent;
            border: 2px solid var(--text-primary);
            color: var(--text-primary);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .theme-toggle:hover {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-color: transparent;
            transform: rotate(180deg);
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
        
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--text-primary);
        }
        
        .btn-outline:hover {
            background: var(--text-primary);
            color: var(--bg-primary);
            transform: translateY(-2px);
        }
        
        .main-container {
            padding: 140px 5% 60px;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .page-header {
            margin-bottom: 3rem;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .page-header h1 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }
        
        .page-header .gradient-text {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-header p {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            animation: fadeInUp 0.6s ease 0.2s;
            animation-fill-mode: both;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .profile-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        body.dark-mode .profile-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        

        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .profile-email {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gradient-end);
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.3rem;
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .content-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        body.dark-mode .content-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
        }
        
        .content-card:hover {
            box-shadow: var(--shadow-hover);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .card-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
        }
        
        .form-group input:disabled {
            background: var(--bg-secondary);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            border: none;
            flex: 1;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }
        
        .btn-danger {
            background: transparent;
            color: var(--error-color);
            border: 2px solid var(--error-color);
        }
        
        .btn-danger:hover {
            background: var(--error-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.4);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.95rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-color);
        }
        
        .alert-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-color);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .danger-zone {
            background: var(--danger-bg);
            border: 2px solid var(--danger-border);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .danger-zone-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .danger-zone-header i {
            font-size: 1.5rem;
            color: var(--error-color);
        }
        
        .danger-zone-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--error-color);
        }
        
        .danger-zone p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        .modal {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 3rem;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }
        
        body.dark-mode .modal {
            background: rgba(20, 20, 20, 0.95);
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .modal-header i {
            font-size: 3rem;
            color: var(--error-color);
            margin-bottom: 1rem;
        }
        
        .modal-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .modal-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .modal-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .modal-buttons .btn {
            flex: 1;
            padding: 1rem;
            font-size: 1rem;
            justify-content: center;
        }
        
        .btn-cancel {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-cancel:hover {
            background: var(--card-hover);
            border-color: var(--text-secondary);
            color: var(--text-primary);
        }
        
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: rgba(10, 146, 215, 0.1);
            border: 1px solid rgba(10, 146, 215, 0.3);
            border-radius: 50px;
            color: var(--gradient-end);
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
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
            content: url('assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .loading-logo img {
            content: url('assets/images/logo.png');
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: grid;
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            nav {
                padding: 1rem 3%;
                flex-wrap: wrap;
            }
            
            .logo-container img {
                height: 35px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .nav-right {
                gap: 0.5rem;
            }
            
            .theme-toggle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .btn-outline {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .main-container {
                padding: 120px 3% 40px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }
            
            .content-card {
                padding: 1.5rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.8rem;
            }
            
            .card-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }
            
            .card-title {
                font-size: 1.4rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
            
            .modal {
                padding: 2rem;
            }
            
            .modal-header h2 {
                font-size: 1.5rem;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="loading-logo">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
        </div>
        <div class="spinner"></div>
        <div class="loading-text">Loading Profile...</div>
    </div>

    <!-- Navigation -->
    <nav>
        <a href="main.php" class="logo-container">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
            <span class="logo-text">FANTAZINA</span>
        </a>
        <div class="nav-right">
            <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <a href="main.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><span class="gradient-text">My Profile</span></h1>
            <p>Manage your account information and settings</p>
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

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="profile-card">

                    <div class="profile-name"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    
                    <?php if ($user['activated']): ?>
                    <div class="info-badge">
                        <i class="fas fa-check-circle"></i>
                        <span>Verified Account</span>
                    </div>
                    <?php else: ?>
                    <div class="info-badge" style="background: var(--error-bg); border-color: var(--error-border); color: var(--error-color);">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Not Verified</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $stats['leagues_owned'] ?? 0; ?></span>
                            <span class="stat-label">Leagues Owned</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $joined_stats['leagues_joined'] ?? 0; ?></span>
                            <span class="stat-label">Joined</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo number_format($points_stats['total_points'] ?? 0); ?></span>
                            <span class="stat-label">Points</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Profile Information -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="card-title">Profile Information</h2>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number">Phone Number (Optional)</label>
                            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="+1234567890">
                        </div>
                        
                        <div class="form-group">
                            <label>Account Created</label>
                            <input type="text" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <h2 class="card-title">Change Password</h2>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password (min. 6 characters)" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="card-icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <h2 class="card-title">Danger Zone</h2>
                        </div>
                    </div>
                    
                    <div class="danger-zone">
                        <div class="danger-zone-header">
                            <i class="fas fa-trash-alt"></i>
                            <h3>Delete Account</h3>
                        </div>
                        <p>
                            Once you delete your account, there is no going back. This will permanently delete your account, 
                            all your leagues, and remove you from all contributed leagues. All your data will be lost forever.
                        </p>
                        <button type="button" class="btn btn-danger" id="deleteAccountBtn">
                            <i class="fas fa-trash-alt"></i> Delete My Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Delete Account?</h2>
                <p>This action cannot be undone. Please confirm your password to proceed.</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_account">
                
                <div class="form-group">
                    <label for="delete_password">Enter Your Password</label>
                    <input type="password" id="delete_password" name="delete_password" placeholder="Confirm your password" required>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" id="cancelDelete">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete Forever
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            
            if (savedTheme === 'light') {
                body.classList.remove('dark-mode');
            } else {
                body.classList.add('dark-mode');
            }
        })();
        window.addEventListener('load', function() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            setTimeout(() => {
                loadingSpinner.classList.add('hidden');
            }, 500);
        });

        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');

        if (body.classList.contains('dark-mode')) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
        
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        const deleteAccountBtn = document.getElementById('deleteAccountBtn');
        const deleteModal = document.getElementById('deleteModal');
        const cancelDelete = document.getElementById('cancelDelete');
        
        deleteAccountBtn.addEventListener('click', () => {
            deleteModal.classList.add('active');
        });
        
        cancelDelete.addEventListener('click', () => {
            deleteModal.classList.remove('active');
            document.getElementById('delete_password').value = '';
        });

        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.classList.remove('active');
                document.getElementById('delete_password').value = '';
            }
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && deleteModal.classList.contains('active')) {
                deleteModal.classList.remove('active');
                document.getElementById('delete_password').value = '';
            }
        });
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.animation = 'slideUp 0.3s ease forwards';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        confirmPasswordInput.addEventListener('input', () => {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        });
        
        newPasswordInput.addEventListener('input', () => {
            if (newPasswordInput.value.length > 0 && newPasswordInput.value.length < 6) {
                newPasswordInput.setCustomValidity('Password must be at least 6 characters');
            } else {
                newPasswordInput.setCustomValidity('');
            }
            
            if (confirmPasswordInput.value) {
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            }
        });
    </script>
</body>
</html>