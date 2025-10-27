<?php

ini_set('session.gc_maxlifetime', 2592000); 
session_set_cookie_params(2592000); 
session_start();

require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone_number = trim($_POST['phone_number'] ?? '');

    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    } elseif (strlen($username) > 50) {
        $errors[] = "Username must not exceed 50 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username already exists";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already registered";
        }
    }

    if (!empty($phone_number) && empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        if ($stmt->fetch()) {
            $errors[] = "Phone number already registered";
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO accounts (username, email, password, phone_number, activated) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$username, $email, $hashed_password, $phone_number]);
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            header("Location: main.php");
            exit();
            
        } catch (PDOException $e) {
            $errors[] = "An error occurred while creating your account. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Sign Up - Fantazina</title>
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
            --nav-bg: rgba(255, 255, 255, 0.95);
            --input-bg: #ffffff;
            --input-border: rgba(0, 0, 0, 0.15);
            --input-focus: rgba(10, 146, 215, 0.15);
            --gradient-start: #1D60AC;
            --gradient-end: #0A92D7;
            --error-bg: rgba(220, 53, 69, 0.1);
            --error-border: rgba(220, 53, 69, 0.3);
            --error-text: #dc3545;
            --success-bg: rgba(40, 167, 69, 0.1);
            --success-border: rgba(40, 167, 69, 0.3);
            --success-text: #28a745;
        }
        
        body.dark-mode {
            --bg-primary: #000000;
            --bg-secondary: #0a0a0a;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
            --border-color: rgba(255, 255, 255, 0.15);
            --card-bg: rgba(20, 20, 20, 0.95);
            --nav-bg: rgba(0, 0, 0, 0.95);
            --input-bg: rgba(30, 30, 30, 0.8);
            --input-border: rgba(255, 255, 255, 0.2);
            --input-focus: rgba(10, 146, 215, 0.3);
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
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
            display: inline-block;
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
        
        .signup-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 5% 60px;
            position: relative;
            min-height: 100vh;
        }
        
        body.dark-mode .signup-container {
            background: radial-gradient(ellipse at center, rgba(29, 96, 172, 0.15), transparent);
        }
        
        .signup-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                repeating-linear-gradient(0deg, var(--border-color) 0px, transparent 1px, transparent 40px, var(--border-color) 41px),
                repeating-linear-gradient(90deg, var(--border-color) 0px, transparent 1px, transparent 40px, var(--border-color) 41px);
            opacity: 0.3;
            pointer-events: none;
        }
        
        .signup-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 3rem;
            max-width: 550px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease;
        }
        
        body.dark-mode .signup-card {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .signup-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .signup-header h1 {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }
        
        .signup-header .gradient-text {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .signup-header p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-top: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group label .required {
            color: var(--error-text);
            margin-left: 3px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
            transition: all 0.3s ease;
            z-index: 2;
        }
        
        body.dark-mode .input-wrapper i {
            color: rgba(10, 146, 215, 0.7);
            text-shadow: 0 0 8px rgba(10, 146, 215, 0.3);
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem 1.2rem 1rem 3rem;
            border: 2px solid var(--input-border);
            border-radius: 12px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        body.dark-mode .form-group input {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 2px solid rgba(10, 146, 215, 0.3);
            color: #ffffff;
            box-shadow: 
                inset 0 1px 3px rgba(0, 0, 0, 0.4),
                0 0 20px rgba(10, 146, 215, 0.05);
            backdrop-filter: blur(10px);
        }
        
        body.dark-mode .form-group input:hover {
            border-color: rgba(10, 146, 215, 0.5);
            box-shadow: 
                inset 0 1px 3px rgba(0, 0, 0, 0.3),
                0 0 25px rgba(10, 146, 215, 0.1);
        }
        
        body.dark-mode .form-group input:focus {
            background: linear-gradient(135deg, rgba(25, 35, 55, 0.8), rgba(20, 30, 48, 0.9));
            border-color: var(--gradient-end);
            box-shadow: 
                0 0 0 4px rgba(10, 146, 215, 0.2),
                0 0 30px rgba(10, 146, 215, 0.15),
                inset 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        
        body.dark-mode .input-wrapper:focus-within i {
            color: var(--gradient-end);
            text-shadow: 0 0 12px rgba(10, 146, 215, 0.6);
            transform: translateY(-50%) scale(1.1);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--gradient-end);
            box-shadow: 0 0 0 3px var(--input-focus);
        }
        
        .input-wrapper:focus-within i {
            color: var(--gradient-end);
        }
        
        .form-group input::placeholder {
            color: var(--text-secondary);
            opacity: 0.6;
        }
        
        body.dark-mode .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-weight: 300;
        }
        
        .error-messages {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
            padding: 1rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .error-messages ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .error-messages li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .error-messages li:last-child {
            margin-bottom: 0;
        }
        
        .error-messages i {
            font-size: 1rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            display: none;
        }
        
        .password-strength.active {
            display: block;
        }
        
        .strength-bar {
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.3rem;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-fill.weak {
            width: 33%;
            background: var(--error-text);
        }
        
        .strength-fill.medium {
            width: 66%;
            background: #ffa500;
        }
        
        .strength-fill.strong {
            width: 100%;
            background: var(--success-text);
        }
        
        .strength-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .submit-btn {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .signup-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .signup-footer p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .signup-footer a {
            color: var(--gradient-end);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .signup-footer a:hover {
            color: var(--gradient-start);
            text-decoration: underline;
        }

        .football-icon {
            position: fixed;
            font-size: 2.5rem;
            opacity: 0.08;
            pointer-events: none;
            z-index: 0;
            color: var(--text-primary);
            animation: float 20s infinite ease-in-out;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(100px, -100px) rotate(90deg); }
            50% { transform: translate(200px, 0) rotate(180deg); }
            75% { transform: translate(100px, 100px) rotate(270deg); }
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
        
        @media (max-width: 768px) {
            nav {
                padding: 0.8rem 4%;
            }
            
            .logo-container {
                gap: 0.5rem;
            }
            
            .logo-container img {
                height: 35px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .theme-toggle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .signup-container {
                padding: 90px 4% 40px;
                min-height: auto;
            }
            
            .signup-card {
                padding: 2rem 1.5rem;
                border-radius: 20px;
                max-width: 100%;
            }
            
            .signup-header h1 {
                font-size: 2rem;
            }
            
            .signup-header p {
                font-size: 0.9rem;
            }
            
            .form-group {
                margin-bottom: 1.2rem;
            }
            
            .form-group input {
                padding: 0.9rem 1rem 0.9rem 2.8rem;
                font-size: 16px; 
            }
            
            .input-wrapper i {
                left: 1rem;
                font-size: 1rem;
            }
            
            .submit-btn {
                padding: 1rem;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            nav {
                padding: 0.7rem 3%;
            }
            
            .logo-container img {
                height: 30px;
            }
            
            .logo-text {
                font-size: 1.1rem;
            }
            
            .theme-toggle {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .signup-container {
                padding: 80px 3% 30px;
            }
            
            .signup-card {
                padding: 1.5rem 1.2rem;
                border-radius: 15px;
            }
            
            .signup-header {
                margin-bottom: 1.5rem;
            }
            
            .signup-header h1 {
                font-size: 1.7rem;
            }
            
            .signup-header p {
                font-size: 0.85rem;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-group label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }
            
            .form-group input {
                padding: 0.85rem 0.9rem 0.85rem 2.6rem;
                font-size: 16px;
                border-radius: 10px;
            }
            
            .input-wrapper i {
                left: 0.9rem;
                font-size: 0.95rem;
            }
            
            .submit-btn {
                padding: 0.9rem;
                font-size: 0.95rem;
                border-radius: 10px;
            }
            
            .signup-footer {
                margin-top: 1.5rem;
                padding-top: 1rem;
            }
            
            .signup-footer p {
                font-size: 0.85rem;
            }
            
            .error-messages {
                padding: 0.8rem 1rem;
                font-size: 0.85rem;
            }
        }

        @media (max-height: 600px) and (orientation: landscape) {
            .signup-container {
                padding: 80px 3% 30px;
                min-height: auto;
            }
            
            .signup-card {
                margin: 20px auto;
            }
            
            .signup-header {
                margin-bottom: 1rem;
            }
            
            .form-group {
                margin-bottom: 0.8rem;
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
        <div class="loading-text">Loading Fantazina...</div>
    </div>
    
    <!-- Floating Football Icons -->
    <i class="fas fa-futbol football-icon" style="top: 15%; left: 10%;"></i>
    <i class="fas fa-futbol football-icon" style="top: 70%; right: 12%; animation-delay: -5s;"></i>
    <i class="fas fa-futbol football-icon" style="top: 40%; left: 85%; animation-delay: -10s;"></i>

    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo-container">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
            <span class="logo-text">FANTAZINA</span>
        </a>
        <div class="nav-right">
            <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <a href="index.php" class="btn btn-outline">Back to Home</a>
        </div>
    </nav>

    <!-- Signup Container -->
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <h1>Join <span class="gradient-text">Fantazina</span></h1>
                <p>Create your account and start building your fantasy league</p>
            </div>
            
            <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li>
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="signupForm">
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Choose a unique username" 
                            required
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            minlength="3"
                            maxlength="50"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email address" 
                            required
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone_number">Phone Number <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input 
                            type="tel" 
                            id="phone_number" 
                            name="phone_number" 
                            placeholder="Enter your phone number" 
                            required
                            value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                        >
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Create a strong password" 
                            required
                            minlength="6"
                        >
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Re-enter your password" 
                            required
                            minlength="6"
                        >
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="signup-footer">
                <p>Already have an account? <a href="index.php" id="loginLink">Login here</a></p>
            </div>
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

        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length === 0) {
                passwordStrength.classList.remove('active');
                return;
            }
            
            passwordStrength.classList.add('active');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;

            if (/\d/.test(password)) strength++;

            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;

            if (/[^A-Za-z0-9]/.test(password)) strength++;

            strengthFill.className = 'strength-fill';
            
            if (strength <= 2) {
                strengthFill.classList.add('weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = 'var(--error-text)';
            } else if (strength <= 3) {
                strengthFill.classList.add('medium');
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#ffa500';
            } else {
                strengthFill.classList.add('strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = 'var(--success-text)';
            }
        });

        const signupForm = document.getElementById('signupForm');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        signupForm.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                confirmPasswordInput.focus();
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                passwordInput.focus();
                return false;
            }
        });

        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = 'var(--success-text)';
                } else {
                    this.style.borderColor = 'var(--error-text)';
                }
            } else {
                this.style.borderColor = 'var(--input-border)';
            }
        });
        const usernameInput = document.getElementById('username');
        usernameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
        const phoneInput = document.getElementById('phone_number');
        phoneInput.addEventListener('input', function() {
            let value = this.value;
            if (value.startsWith('+')) {
                this.value = '+' + value.slice(1).replace(/[^0-9]/g, '');
            } else {
                this.value = value.replace(/[^0-9]/g, '');
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            usernameInput.focus();
        });
        document.getElementById('loginLink').addEventListener('click', function(e) {
            e.preventDefault();
            sessionStorage.setItem('openLoginModal', 'true');
            window.location.href = 'index.php';
        });
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
    </script>
</body>
</html>