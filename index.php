<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username_or_email = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, username, email, password, activated FROM accounts WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$username_or_email, $username_or_email]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) === 1) {
        $user = $result[0];
        
        if (password_verify($password, $user['password'])) {
            if ($user['activated'] == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                header("Location: main.php");
                exit();
            } else {
                $login_error = "Your account is not activated. Please check your email to activate your account.";
            }
        } else {
            $login_error = "Invalid username/email or password.";
        }
    } else {
        $login_error = "Invalid username/email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fantazina - Create Your Own Fantasy Football League</title>
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
            --card-bg: rgba(255, 255, 255, 0.8);
            --card-hover: rgba(255, 255, 255, 0.95);
            --nav-bg: rgba(255, 255, 255, 0.95);
            --gradient-start: #1D60AC;
            --gradient-end: #0A92D7;
        }
        
        body.dark-mode {
            --bg-primary: #000000;
            --bg-secondary: #0a0a0a;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --border-color: rgba(255, 255, 255, 0.1);
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-hover: rgba(255, 255, 255, 0.08);
            --nav-bg: rgba(0, 0, 0, 0.95);
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
            transition: background 0.3s ease, color 0.3s ease;
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
        
        .footer-logo img {
            height: 50px;
            width: auto;
        }
        
        body.dark-mode .footer-logo img {
            content: url('assets/images/logo white outline.png');
        }
        
        body:not(.dark-mode) .footer-logo img {
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
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
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
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            border: none;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
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
        
        .modal-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .modal-header h2 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        
        body.dark-mode .modal-header h2 {
            background: none;
            -webkit-text-fill-color: white;
            color: white;
        }
        
        .modal-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
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
        
        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        
        .forgot-password a {
            color: var(--gradient-end);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .forgot-password a:hover {
            color: var(--gradient-start);
            text-decoration: underline;
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
        
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 140px 5% 80px;
            background: var(--bg-primary);
        }
        
        body.dark-mode .hero {
            background: radial-gradient(ellipse at center, rgba(29, 96, 172, 0.15), transparent);
        }
        
        .hero::before {
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
        
        .hero-content {
            text-align: center;
            z-index: 1;
            max-width: 1400px;
            width: 100%;
        }
        
        .hero h1 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero h1 .gradient-text {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2.5rem;
            color: var(--text-secondary);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 5rem;
        }
        
        .feature-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            margin-top: 0;
        }
        
        .feature-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        body.dark-mode .feature-card {
            box-shadow: none;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            background: var(--card-hover);
            border-color: rgba(10, 146, 215, 0.5);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.2);
        }
        
        .feature-card i {
            font-size: 3rem;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .feature-card p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .stats-section {
            padding: 100px 5%;
            background: var(--bg-secondary);
            position: relative;
            margin-top: 30px;
        }
        
        body.dark-mode .stats-section {
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
        }
        
        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gradient-end), transparent);
        }
        
        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 3rem;
            color: var(--text-primary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 3rem;
            margin-top: 3rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
            background: var(--bg-primary);
            border-radius: 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--gradient-end);
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.2);
        }
        
        .stat-number {
            font-size: 3.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1.2rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .pros-section {
            padding: 100px 5%;
            background: var(--bg-primary);
            position: relative;
        }
        
        .pros-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .section-subtitle {
            text-align: center;
            font-size: 1.3rem;
            color: var(--text-secondary);
            margin-bottom: 4rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .pros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
            margin-top: 3rem;
        }
        
        .pro-card {
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        body.dark-mode .pro-card {
            box-shadow: none;
        }
        
        .pro-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .pro-card:hover::before {
            transform: scaleX(1);
        }
        
        .pro-card:hover {
            transform: translateY(-10px);
            background: var(--card-hover);
            border-color: rgba(10, 146, 215, 0.5);
            box-shadow: 0 15px 40px rgba(10, 146, 215, 0.2);
        }
        
        .pro-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .pro-card:hover .pro-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .pro-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .pro-card h3 {
            font-size: 1.6rem;
            margin-bottom: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .pro-card p {
            color: var(--text-secondary);
            line-height: 1.7;
            font-size: 1.05rem;
        }
        
        .how-it-works {
            padding: 100px 5%;
            max-width: 1600px;
            margin: 0 auto;
            background: transparent;
        }
        
        body.dark-mode .how-it-works {
            background: var(--bg-primary);
        }
        
        .video-container {
            text-align: center;
            margin-top: 4rem;
        }
        
        .video-link {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            padding: 1.2rem 3rem;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(10, 146, 215, 0.3);
        }
        
        .video-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.5);
        }
        
        .video-link i {
            font-size: 1.5rem;
        }
        
        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 3rem;
            margin-top: 3rem;
            max-width: 1600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        @media (min-width: 1600px) {
            .steps-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .step-card {
            position: relative;
            padding: 2.5rem;
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        body.dark-mode .step-card {
            box-shadow: none;
        }
        
        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.15);
        }
        
        .step-number {
            position: absolute;
            top: -20px;
            left: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 900;
            color: white;
        }
        
        .step-card h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            margin-top: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .step-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 1.1rem;
        }
        
        .cta-section {
            padding: 100px 5%;
            text-align: center;
            background: var(--bg-secondary);
            margin-top: 50px;
        }
        
        body.dark-mode .cta-section {
            background: radial-gradient(ellipse at center, rgba(10, 146, 215, 0.2), transparent);
        }
        
        .cta-section h2 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .cta-section p {
            font-size: 1.3rem;
            color: var(--text-secondary);
            margin-bottom: 2.5rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        footer {
            padding: 3rem 5%;
            background: var(--bg-primary);
            border-top: 1px solid var(--border-color);
            text-align: center;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .footer-logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        body.dark-mode .footer-logo-text {
            background: none;
            -webkit-text-fill-color: white;
            color: white;
        }
        
        footer p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .footer-links a:hover {
            color: var(--gradient-end);
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .social-links a {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            font-size: 1.2rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .social-links a:hover {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-color: transparent;
            transform: translateY(-5px);
        }
        
        .football-icon {
            position: fixed;
            font-size: 3rem;
            opacity: 0.1;
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
        
        @media (max-width: 1200px) {
            .feature-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            nav {
                padding: 1rem 3%;
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .logo-container {
                flex: 1;
                min-width: 200px;
            }
            
            .nav-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .nav-buttons {
                flex: 1;
                justify-content: flex-end;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .logo-container img {
                height: 35px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .hero-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .feature-cards {
                grid-template-columns: 1fr;
            }
            
            .theme-toggle {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .pros-grid {
                grid-template-columns: 1fr;
            }
            
            .section-subtitle {
                font-size: 1.1rem;
            }
            
            .modal {
                padding: 2rem;
            }
            
            .modal-header h2 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .logo-text {
                font-size: 1.1rem;
            }
            
            .logo-container img {
                height: 30px;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .theme-toggle {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
        }
        
        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.6s ease;
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
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
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        body.dark-mode .loading-text {
            background: none;
            -webkit-text-fill-color: var(--text-primary);
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
    <i class="fas fa-futbol football-icon" style="top: 10%; left: 10%;"></i>
    <i class="fas fa-futbol football-icon" style="top: 60%; right: 15%; animation-delay: -5s;"></i>
    <i class="fas fa-futbol football-icon" style="top: 80%; left: 20%; animation-delay: -10s;"></i>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Welcome Back!</h2>
                <p>Sign in to access your fantasy leagues</p>
            </div>
            <?php if (isset($login_error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username or email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="forgot-password">
                    <a href="password_retrieve.php">Forgot Your Password?</a>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" id="cancelLogin">Cancel</button>
                    <button type="submit" class="btn btn-gradient">Login</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Navigation -->
    <nav>
        <div class="logo-container">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
            <span class="logo-text">FANTAZINA</span>
        </div>
        <div class="nav-right">
            <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                <i class="fas fa-moon"></i>
            </button>
            <div class="nav-buttons">
                <button class="btn btn-outline" id="loginBtn">Login</button>
                <a href="signup.php" class="btn btn-gradient">Sign Up</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>
                Create Your Own <br>
                <span class="gradient-text">Fantasy Football League</span>
            </h1>
            <p>
                Build custom leagues, invite friends, and compete with your own rules. 
                Fantazina gives you complete control to create the ultimate fantasy football experience.
            </p>
            <div class="hero-buttons">
                <a href="signup.php" class="btn btn-gradient" style="padding: 1rem 3rem; font-size: 1.1rem;">
                    <i class="fas fa-rocket"></i> Get Started
                </a>
                <a href="#how-it-works" class="btn btn-outline" style="padding: 1rem 3rem; font-size: 1.1rem;">
                    <i class="fas fa-play-circle"></i> How It Works
                </a>
            </div>
            
            <div class="feature-cards reveal">
                <div class="feature-card">
                    <i class="fas fa-trophy"></i>
                    <h3>Custom Leagues</h3>
                    <p>Create leagues with your own scoring rules, player prices, and game settings</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-users"></i>
                    <h3>Invite Friends</h3>
                    <p>Generate unique league tokens and invite unlimited players to join your competition</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Live Scoring</h3>
                    <p>Track real-time points with custom scoring systems including assists, clean sheets, and more</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-cogs"></i>
                    <h3>Full Control</h3>
                    <p>Manage teams, players, matches, and rules - you're the commissioner of your league</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section" id="stats">
        <div class="stats-container">
            <h2 class="section-title reveal">Fantazina By The Numbers</h2>
            <div class="stats-grid">
                <div class="stat-card reveal">
                    <span class="stat-number" data-count="0">0</span>
                    <p class="stat-label">Active Leagues</p>
                </div>
                <div class="stat-card reveal">
                    <span class="stat-number" data-count="0">0</span>
                    <p class="stat-label">Registered Users</p>
                </div>
                <div class="stat-card reveal">
                    <span class="stat-number" data-count="0">0</span>
                    <p class="stat-label">Players Created</p>
                </div>
                <div class="stat-card reveal">
                    <span class="stat-number" data-count="0">0</span>
                    <p class="stat-label">Matches Played</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Fantazina Pros Section -->
    <section class="pros-section" id="features">
        <div class="pros-container">
            <h2 class="section-title reveal">Why Choose Fantazina?</h2>
            <p class="section-subtitle reveal">Powerful features that give you complete control over your fantasy league</p>
            
            <div class="pros-grid">
                <div class="pro-card reveal">
                    <div class="pro-icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    <h3>Complete Role Management</h3>
                    <p>Take full control of your league's scoring system. Customize points for goals, assists, clean sheets, saves, penalties, and cards. Set different point values for goalkeepers, defenders, midfielders, and forwards to create a balanced and exciting competition.</p>
                </div>
                
                <div class="pro-card reveal">
                    <div class="pro-icon">
                        <i class="fas fa-table"></i>
                    </div>
                    <h3>Automatic League Table</h3>
                    <p>No more manual calculations! Fantazina automatically generates and updates your league standings table in real-time. Every match result instantly reflects on the table, making it effortless for everyone to track their team's position and points.</p>
                </div>
                
                <div class="pro-card reveal">
                    <div class="pro-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Instant Analytics & Stats</h3>
                    <p>Get comprehensive analytics at your fingertips. View top scorers, top assisters, most clean sheets, disciplinary records, and detailed performance metrics. All statistics update automatically after each match, giving you insights that matter.</p>
                </div>
                
                <div class="pro-card reveal">
                    <div class="pro-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <h3>Contributors Leaderboard</h3>
                    <p>Track the real champions! An automatically updated leaderboard shows the top-performing contributors based on their fantasy teams' total points. See who's leading the competition and fuel the competitive spirit in your league.</p>
                </div>
                
                <div class="pro-card reveal">
                    <div class="pro-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3>Personal Player Profiles</h3>
                    <p>Every player gets their own detailed profile page with complete statistics. Track goals, assists, appearances, points earned, and performance trends. Players can easily monitor their fantasy team's progress and make informed decisions.</p>
                </div>
                
                <div class="pro-card reveal">
                    <div class="pro-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Real-Time Updates</h3>
                    <p>Experience live fantasy football like never before. All data, scores, and statistics update instantly as you add match results. No delays, no refresh needed - just seamless, real-time fantasy management.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <h2 class="section-title reveal">How It Works</h2>
        <div class="steps-container">
            <div class="step-card reveal">
                <div class="step-number">1</div>
                <h3>Create Your League</h3>
                <p>Set up your fantasy league with custom rules, scoring systems, and budget settings. Choose between Budget mode or No Limits gameplay.</p>
            </div>
            <div class="step-card reveal">
                <div class="step-number">2</div>
                <h3>Add Teams & Players</h3>
                <p>Build your league by adding real teams and players. Set player prices, positions, and stats to match your league's style.</p>
            </div>
            <div class="step-card reveal">
                <div class="step-number">3</div>
                <h3>Invite Participants</h3>
                <p>Generate a unique league token and share it with friends. They can join instantly and start building their dream teams.</p>
            </div>
            <div class="step-card reveal">
                <div class="step-number">4</div>
                <h3>Compete & Track</h3>
                <p>Record match results, update player points, and watch the leaderboard come alive. Use power-ups like Triple Captain and Bench Boost!</p>
            </div>
        </div>
        
        <div class="video-container reveal">
            <a href="#" class="video-link" id="videoLink">
                <i class="fas fa-play-circle"></i>
                Watch How It Works
            </a>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <h2 class="reveal">Ready to Create Your League?</h2>
        <p class="reveal">Join thousands of fantasy football enthusiasts and start your custom league today.</p>
        <a href="signup.php" class="btn btn-gradient reveal" style="padding: 1.2rem 3.5rem; font-size: 1.2rem;">
            <i class="fas fa-user-plus"></i> Sign Up Now
        </a>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-logo">
            <img src="assets/images/logo white outline.png" alt="Fantazina Logo">
            <span class="footer-logo-text">FANTAZINA</span>
        </div>
        <div class="social-links">
            <a href="#" title="Facebook" target="_blank">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" title="Instagram" target="_blank">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="#" title="WhatsApp" target="_blank">
                <i class="fab fa-whatsapp"></i>
            </a>
            <a href="#" title="TikTok" target="_blank">
                <i class="fab fa-tiktok"></i>
            </a>
            <a href="#" title="Twitter" target="_blank">
                <i class="fab fa-twitter"></i>
            </a>
        </div>
    </footer>

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
        <?php if (isset($login_error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('loginModal').classList.add('active');
        });
        <?php endif; ?>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light') {
            body.classList.remove('dark-mode');
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        } else {
            body.classList.add('dark-mode');
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
            if (!savedTheme) {
                localStorage.setItem('theme', 'dark');
            }
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

        const loginBtn = document.getElementById('loginBtn');
        const loginModal = document.getElementById('loginModal');
        const cancelLogin = document.getElementById('cancelLogin');
        
        loginBtn.addEventListener('click', () => {
            loginModal.classList.add('active');
        });
        
        cancelLogin.addEventListener('click', () => {
            loginModal.classList.remove('active');
        });

        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                loginModal.classList.remove('active');
            }
        });
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && loginModal.classList.contains('active')) {
                loginModal.classList.remove('active');
            }
        });

        function reveal() {
            const reveals = document.querySelectorAll('.reveal');
            
            reveals.forEach(element => {
                const windowHeight = window.innerHeight;
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < windowHeight - elementVisible) {
                    element.classList.add('active');
                }
            });
        }
        
        window.addEventListener('scroll', reveal);
        reveal(); 

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString() + '+';
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 20);
        }
        
        function loadStats() {
            fetch('api/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    const statNumbers = document.querySelectorAll('.stat-number');
                    statNumbers[0].setAttribute('data-count', data.leagues || 0);
                    statNumbers[1].setAttribute('data-count', data.users || 0);
                    statNumbers[2].setAttribute('data-count', data.players || 0);
                    statNumbers[3].setAttribute('data-count', data.matches || 0);
                    
                    const statsObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                statNumbers.forEach(stat => {
                                    const target = parseInt(stat.getAttribute('data-count'));
                                    animateCounter(stat, target);
                                });
                                statsObserver.unobserve(entry.target);
                            }
                        });
                    });
                    
                    statsObserver.observe(document.querySelector('.stats-section'));
                })
                .catch(error => {
                    console.log('Stats will be loaded from database');
                    const statNumbers = document.querySelectorAll('.stat-number');
                    const demoStats = [150, 1200, 5400, 890];
                    
                    const statsObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                statNumbers.forEach((stat, index) => {
                                    animateCounter(stat, demoStats[index]);
                                });
                                statsObserver.unobserve(entry.target);
                            }
                        });
                    });
                    
                    statsObserver.observe(document.querySelector('.stats-section'));
                });
        }

        loadStats();
    </script>
</body>
</html>