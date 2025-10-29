<?php
session_start();
require_once 'config/db.php';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle AJAX requests for token validation and joining
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'validate_token') {
        $token = trim($_POST['token'] ?? '');
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a token']);
            exit();
        }
        
        // Find league by token
        $stmt = $pdo->prepare("
            SELECT l.*, lt.token, a.username as owner_name 
            FROM league_tokens lt
            INNER JOIN leagues l ON lt.league_id = l.id
            INNER JOIN accounts a ON l.owner = a.id
            WHERE lt.token = ?
        ");
        $stmt->execute([$token]);
        $league = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$league) {
            echo json_encode(['success' => false, 'message' => 'Invalid token. League not found.']);
            exit();
        }
        
        // Check if user is already in the league
        $stmt = $pdo->prepare("SELECT * FROM league_contributors WHERE user_id = ? AND league_id = ?");
        $stmt->execute([$user_id, $league['id']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You are already a member of this league']);
            exit();
        }
        
        // Check if user is the owner
        if ($league['owner'] == $user_id || $league['other_owner'] == $user_id) {
            echo json_encode(['success' => false, 'message' => 'You are already the owner of this league']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'league' => [
                'id' => $league['id'],
                'name' => $league['name'],
                'owner_name' => $league['owner_name'],
                'system' => $league['system'],
                'num_of_teams' => $league['num_of_teams'],
                'num_of_players' => $league['num_of_players']
            ]
        ]);
        exit();
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'join_league') {
        $league_id = intval($_POST['league_id'] ?? 0);
        
        if ($league_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid league ID']);
            exit();
        }
        
        // Verify league exists
        $stmt = $pdo->prepare("SELECT * FROM leagues WHERE id = ?");
        $stmt->execute([$league_id]);
        $league = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$league) {
            echo json_encode(['success' => false, 'message' => 'League not found']);
            exit();
        }
        
        // Check if user is already in the league
        $stmt = $pdo->prepare("SELECT * FROM league_contributors WHERE user_id = ? AND league_id = ?");
        $stmt->execute([$user_id, $league_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You are already a member of this league']);
            exit();
        }
        
        // Add user as contributor
        try {
            $stmt = $pdo->prepare("INSERT INTO league_contributors (user_id, league_id, role, total_score) VALUES (?, ?, 'Contributor', 0)");
            $stmt->execute([$user_id, $league_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Successfully joined the league!',
                'redirect' => 'main.php'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to join league. Please try again.']);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Get user's owned leagues
$stmt = $pdo->prepare("SELECT * FROM leagues WHERE owner = ? OR other_owner = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $user_id]);
$owned_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's contributed leagues
$stmt = $pdo->prepare("
    SELECT l.*, lc.role, lc.total_score 
    FROM leagues l
    INNER JOIN league_contributors lc ON l.id = lc.league_id
    WHERE lc.user_id = ? AND l.owner != ? AND (l.other_owner != ? OR l.other_owner IS NULL)
    ORDER BY l.created_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$contributed_leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total stats across all leagues
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT lc.league_id) as total_leagues,
        COALESCE(SUM(lc.total_score), 0) as total_points
    FROM league_contributors lc
    WHERE lc.user_id = ?
");
$stmt->execute([$user_id]);
$total_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's best performing league
$stmt = $pdo->prepare("
    SELECT l.name, lc.total_score, l.id
    FROM league_contributors lc
    INNER JOIN leagues l ON lc.league_id = l.id
    WHERE lc.user_id = ?
    ORDER BY lc.total_score DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$best_league = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's rank in each league
function getUserRankInLeague($pdo, $user_id, $league_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as rank
        FROM league_contributors
        WHERE league_id = ? AND total_score > (
            SELECT total_score FROM league_contributors 
            WHERE user_id = ? AND league_id = ?
        )
    ");
    $stmt->execute([$league_id, $user_id, $league_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['rank'] ?? 0;
}

// Get total contributors in league
function getTotalContributors($pdo, $league_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM league_contributors WHERE league_id = ?");
    $stmt->execute([$league_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Get league token
function getLeagueToken($pdo, $league_id) {
    $stmt = $pdo->prepare("SELECT token FROM league_tokens WHERE league_id = ?");
    $stmt->execute([$league_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['token'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fantazina</title>
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
        
        body.dark-mode::before {
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.6rem 1.2rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-info:hover {
            background: var(--card-hover);
            border-color: var(--gradient-end);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
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
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            border: none;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(10, 146, 215, 0.4);
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
        
        .btn-secondary {
            background: transparent;
            color: var(--gradient-end);
            border: 2px solid var(--gradient-end);
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
        }
        
        .btn-secondary:hover {
            background: var(--gradient-end);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(10, 146, 215, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .main-container {
            padding: 140px 5% 60px;
            max-width: 1600px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .welcome-section {
            margin-bottom: 4rem;
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
        
        .welcome-section h1 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }
        
        .welcome-section .gradient-text {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .welcome-section p {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease;
            animation-fill-mode: both;
            position: relative;
            overflow: hidden;
        }
        
        body.dark-mode .stat-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(10, 146, 215, 0.25);
            border-color: rgba(10, 146, 215, 0.6);
        }
        
        body.dark-mode .stat-card:hover {
            box-shadow: 0 15px 50px rgba(10, 146, 215, 0.4);
            background: linear-gradient(135deg, rgba(25, 40, 60, 0.8), rgba(20, 35, 55, 0.9));
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: white;
            font-size: 2rem;
            box-shadow: 0 8px 20px rgba(10, 146, 215, 0.3);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 12px 30px rgba(10, 146, 215, 0.5);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-primary);
        }
        
        .leagues-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .league-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
            animation-fill-mode: both;
        }
        
        body.dark-mode .league-card {
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.6), rgba(15, 25, 40, 0.8));
            border: 1px solid rgba(10, 146, 215, 0.3);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .league-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .league-card:hover::before {
            transform: scaleX(1);
        }
        
        .league-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 50px rgba(10, 146, 215, 0.3);
            border-color: rgba(10, 146, 215, 0.6);
        }
        
        body.dark-mode .league-card:hover {
            box-shadow: 0 15px 50px rgba(10, 146, 215, 0.4);
            background: linear-gradient(135deg, rgba(25, 40, 60, 0.8), rgba(20, 35, 55, 0.9));
        }
        
        .league-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .league-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .league-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-owner {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }
        
        .badge-contributor {
            background: rgba(10, 146, 215, 0.1);
            color: var(--gradient-end);
            border: 1px solid rgba(10, 146, 215, 0.3);
        }
        
        .league-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .info-item i {
            color: var(--gradient-end);
            width: 20px;
        }
        
        .league-stats {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gradient-end);
        }
        
        .stat-item-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }
        
        .league-actions {
            display: flex;
            gap: 0.8rem;
        }
        
        .league-actions .btn {
            flex: 1;
            justify-content: center;
            padding: 0.8rem 1rem;
            font-size: 0.9rem;
        }
        
        .empty-state {
            background: var(--card-bg);
            border: 2px dashed var(--border-color);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            animation: fadeInUp 0.6s ease;
        }
        
        body.dark-mode .empty-state {
            background: rgba(20, 30, 48, 0.4);
            border-color: rgba(10, 146, 215, 0.3);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }
        
        .user-dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1001;
        }
        
        body.dark-mode .dropdown-menu {
            background: rgba(20, 30, 48, 0.95);
            backdrop-filter: blur(10px);
        }
        
        .dropdown-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            padding: 0.8rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background: var(--bg-secondary);
            color: var(--gradient-end);
        }
        
        .dropdown-item i {
            font-size: 1rem;
            width: 20px;
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

    /* Invitation Link Button */
    .invitation-link-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: white;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        z-index: 10;
    }

    .invitation-link-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(10, 146, 215, 0.5);
    }

    /* Modal Styles */
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
        padding: 3rem;
        max-width: 550px;
        width: 90%;
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
        text-align: center;
        margin-bottom: 2rem;
    }

    .modal-header h2 {
        font-size: 2rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
    }

    .modal-header p {
        color: var(--text-secondary);
        font-size: 1rem;
    }

    .modal-body {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .copy-section {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    body.dark-mode .copy-section {
        background: rgba(10, 20, 35, 0.5);
    }

    .copy-section:hover {
        border-color: var(--gradient-end);
    }

    .copy-section-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.8rem;
    }

    .copy-input-group {
        display: flex;
        gap: 0.8rem;
        align-items: center;
    }

    .copy-input {
        flex: 1;
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 0.8rem 1rem;
        font-family: 'Roboto', monospace;
        font-size: 1rem;
        color: var(--text-primary);
        font-weight: 600;
        transition: all 0.3s ease;
    }

    body.dark-mode .copy-input {
        background: rgba(0, 0, 0, 0.3);
    }

    .copy-input:focus {
        outline: none;
        border-color: var(--gradient-end);
        box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
    }

    .copy-btn {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: white;
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    .copy-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(10, 146, 215, 0.4);
    }

    .copy-btn.copied {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .modal-footer {
        text-align: center;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
        margin-top: 1rem;
    }

    .modal-footer p {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    /* Join League Modal Styles */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 0.9rem 1rem;
        font-family: 'Roboto', sans-serif;
        font-size: 1rem;
        color: var(--text-primary);
        transition: all 0.3s ease;
    }

    body.dark-mode .form-input {
        background: rgba(0, 0, 0, 0.3);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--gradient-end);
        box-shadow: 0 0 0 3px rgba(10, 146, 215, 0.1);
    }

    .league-preview {
        background: var(--bg-secondary);
        border: 2px solid var(--gradient-end);
        border-radius: 15px;
        padding: 1.5rem;
        margin: 1.5rem 0;
        display: none;
        animation: fadeInUp 0.4s ease;
    }

    body.dark-mode .league-preview {
        background: rgba(10, 20, 35, 0.5);
    }

    .league-preview.active {
        display: block;
    }

    .league-preview-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .league-preview-icon {
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

    .league-preview-title {
        flex: 1;
    }

    .league-preview-name {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.3rem;
    }

    .league-preview-owner {
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .league-preview-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }

    .league-preview-detail {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .league-preview-detail i {
        color: var(--gradient-end);
        width: 20px;
    }

    .alert {
        padding: 1rem 1.2rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        font-size: 0.9rem;
        animation: fadeInUp 0.4s ease;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: var(--error);
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: var(--success);
    }

    .alert i {
        font-size: 1.2rem;
    }

    .modal-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .modal-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    @media (max-width: 1200px) {
        .leagues-grid {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }
    }
    
    @media (max-width: 768px) {
        nav {
            padding: 1rem 3%;
            flex-wrap: wrap;
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
        
        .nav-right {
            width: 100%;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        
        .user-info {
            padding: 0.5rem 1rem;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        
        .user-name {
            font-size: 0.85rem;
        }
        
        .theme-toggle {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        
        .main-container {
            padding: 120px 3% 40px;
        }
        
        .welcome-section h1 {
            font-size: 2rem;
        }
        
        .welcome-section p {
            font-size: 1rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .stat-card {
            padding: 1.5rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
        }
        
        .leagues-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .league-card {
            padding: 1.5rem;
            padding-top: 3.5rem;
        }
        
        .league-name {
            font-size: 1.3rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            font-size: 0.85rem;
        }
        
        .btn-secondary {
            padding: 0.6rem 1.2rem;
            font-size: 0.85rem;
        }

        .invitation-link-btn {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
        }

        .modal-content {
            padding: 2rem 1.5rem;
        }

        .modal-header h2 {
            font-size: 1.5rem;
        }

        .copy-input-group {
            flex-direction: column;
        }

        .copy-btn {
            width: 100%;
            justify-content: center;
        }

        .league-preview-details {
            grid-template-columns: 1fr;
        }

        .modal-actions {
            flex-direction: column;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .league-info {
            grid-template-columns: 1fr;
            gap: 0.8rem;
        }
        
        .league-actions {
            flex-direction: column;
        }
        
        .league-actions .btn {
            width: 100%;
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
        <div class="loading-text">Loading Dashboard...</div>
    </div>
<!-- Invitation Modal -->
<div class="modal-overlay" id="invitationModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeInvitationModal()">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-header">
            <h2>Invitation Link</h2>
            <p>Share this link or token with others to join your league</p>
        </div>
        <div class="modal-body">
            <div class="copy-section">
                <div class="copy-section-label">League Token</div>
                <div class="copy-input-group">
                    <input type="text" class="copy-input" id="tokenInput" readonly>
                    <button class="copy-btn" onclick="copyToken()">
                        <i class="fas fa-copy"></i>
                        <span id="tokenBtnText">Copy</span>
                    </button>
                </div>
            </div>
            <div class="copy-section">
                <div class="copy-section-label">Invitation Link</div>
                <div class="copy-input-group">
                    <input type="text" class="copy-input" id="linkInput" readonly>
                    <button class="copy-btn" onclick="copyLink()">
                        <i class="fas fa-link"></i>
                        <span id="linkBtnText">Copy</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <p><i class="fas fa-info-circle"></i> Anyone with this token or link can join your league</p>
        </div>
    </div>
</div>

<!-- Join League Modal -->
<div class="modal-overlay" id="joinLeagueModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeJoinLeagueModal()">
            <i class="fas fa-times"></i>
        </button>
        <div class="modal-header">
            <h2>Join a League</h2>
            <p>Enter the league token to join</p>
        </div>
        <div class="modal-body">
            <div id="alertContainer"></div>
            
            <div class="form-group">
                <label class="form-label" for="leagueTokenInput">
                    <i class="fas fa-key"></i> League Token
                </label>
                <input 
                    type="text" 
                    class="form-input" 
                    id="leagueTokenInput" 
                    placeholder="Enter 8-character token"
                    maxlength="8"
                >
            </div>

            <div class="league-preview" id="leaguePreview">
                <div class="league-preview-header">
                    <div class="league-preview-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="league-preview-title">
                        <div class="league-preview-name" id="previewLeagueName"></div>
                        <div class="league-preview-owner">
                            <i class="fas fa-crown"></i> Owner: <span id="previewOwnerName"></span>
                        </div>
                    </div>
                </div>
                <div class="league-preview-details">
                    <div class="league-preview-detail">
                        <i class="fas fa-gamepad"></i>
                        <span id="previewSystem"></span>
                    </div>
                    <div class="league-preview-detail">
                        <i class="fas fa-shield-alt"></i>
                        <span id="previewTeams"></span>
                    </div>
                    <div class="league-preview-detail">
                        <i class="fas fa-users"></i>
                        <span id="previewPlayers"></span>
                    </div>
                    <div class="league-preview-detail">
                        <i class="fas fa-check-circle"></i>
                        <span style="color: var(--success); font-weight: 600;">Ready to Join!</span>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeJoinLeagueModal()">
                    Cancel
                </button>
                <button class="btn btn-gradient" id="validateTokenBtn" onclick="validateToken()">
                    <i class="fas fa-search"></i> Validate Token
                </button>
                <button class="btn btn-gradient" id="joinLeagueBtn" onclick="joinLeague()" style="display: none;">
                    <i class="fas fa-sign-in-alt"></i> Join League
                </button>
            </div>
        </div>
    </div>
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
        <div class="user-dropdown">
            <div class="user-info" id="userInfo">
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="?action=logout" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Main Container -->
<div class="main-container">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1>Welcome back, <span class="gradient-text"><?php echo htmlspecialchars($username); ?>!</span></h1>
        <p>Here's your fantasy football overview</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-value"><?php echo count($owned_leagues); ?></div>
            <div class="stat-label">Leagues Owned</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo $total_stats['total_leagues'] ?? 0; ?></div>
            <div class="stat-label">Total Leagues</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_stats['total_points'] ?? 0); ?></div>
            <div class="stat-label">Total Points</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-medal"></i>
            </div>
            <div class="stat-value">
                <?php 
                if ($best_league) {
                    echo number_format($best_league['total_score']);
                } else {
                    echo '0';
                }
                ?>
            </div>
            <div class="stat-label">Best Score<?php if ($best_league) echo ' in ' . htmlspecialchars($best_league['name']); ?></div>
        </div>
    </div>

    <!-- Owned Leagues Section -->
    <?php if (!empty($owned_leagues)): ?>
    <div class="section-header">
        <h2 class="section-title">My Leagues</h2>
        <div style="display: flex; gap: 1rem;">
            <a href="create_league.php" class="btn btn-gradient">
                <i class="fas fa-plus"></i> Create New League
            </a>
        </div>
    </div>
    
    <div class="leagues-grid">
        <?php foreach ($owned_leagues as $index => $league): ?>
        <div class="league-card" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
            <?php if ($league['activated']): 
                $token = getLeagueToken($pdo, $league['id']);
                if ($token):
            ?>
            <button class="invitation-link-btn" onclick="openInvitationModal('<?php echo htmlspecialchars($token); ?>')">
                <i class="fas fa-share-alt"></i> Invitation Link
            </button>
            <?php endif; endif; ?>
            
            <div class="league-header">
                <div>
                    <h3 class="league-name"><?php echo htmlspecialchars($league['name']); ?></h3>
                    <span class="league-badge badge-owner">Owner</span>
                </div>
            </div>
            
            <div class="league-info">
                <div class="info-item">
                    <i class="fas fa-gamepad"></i>
                    <span><?php echo htmlspecialchars($league['system']); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-user-friends"></i>
                    <span><?php echo getTotalContributors($pdo, $league['id']); ?> In this league</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo $league['num_of_teams']; ?> Teams</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-circle-notch"></i>
                    <span>Round <?php echo $league['round']; ?></span>
                </div>
            </div>
            
            <div class="league-stats">
                <div class="stat-item">
                    <div class="stat-item-value"><?php echo $league['num_of_players']; ?></div>
                    <div class="stat-item-label">Players</div>
                </div>
                <div class="stat-item">
                    <div class="stat-item-value"><?php echo $league['activated'] ? 'Active' : 'Setup'; ?></div>
                    <div class="stat-item-label">Status</div>
                </div>
                <div class="stat-item">
                    <?php
                    $stmt = $pdo->prepare("SELECT total_score FROM league_contributors WHERE user_id = ? AND league_id = ?");
                    $stmt->execute([$user_id, $league['id']]);
                    $my_score = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="stat-item-value"><?php echo $my_score ? number_format($my_score['total_score']) : '0'; ?></div>
                    <div class="stat-item-label">Your Score</div>
                </div>
            </div>
            
            <div class="league-actions">
                <a href="league_dashboard.php?id=<?php echo $league['id']; ?>" class="btn btn-gradient">
                    <i class="fas fa-sign-in-alt"></i> Enter
                </a>
                <a href="league_settings.php?id=<?php echo $league['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-cog"></i> Manage
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="section-header">
        <h2 class="section-title">My Leagues</h2>
        <div style="display: flex; gap: 1rem;">

            <a href="create_league.php" class="btn btn-gradient">
                <i class="fas fa-plus"></i> Create New League
            </a>
        </div>
    </div>
    <div class="empty-state">
        <i class="fas fa-trophy"></i>
        <h3>No Leagues Yet</h3>
        <p>Create your first fantasy football league and invite your friends!</p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="create_league.php" class="btn btn-gradient">
                Create Your First League
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contributed Leagues Section -->
    <div class="section-header" style="margin-top: 3rem;">
        <h2 class="section-title">Leagues I'm In</h2>
        <?php if (empty($contributed_leagues)): ?>
        <button class="btn btn-secondary" onclick="openJoinLeagueModal()">
            <i class="fas fa-sign-in-alt"></i> Join League
        </button>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($contributed_leagues)): ?>
    <div class="leagues-grid">
        <?php foreach ($contributed_leagues as $index => $league): ?>
        <div class="league-card" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
<div class="league-header">
<div>
<h3 class="league-name"><?php echo htmlspecialchars($league['name']); ?></h3>
<span class="league-badge badge-contributor">
<?php echo htmlspecialchars($league['role']); ?>
</span>
</div>
</div>
            <div class="league-info">
                <div class="info-item">
                    <i class="fas fa-gamepad"></i>
                    <span><?php echo htmlspecialchars($league['system']); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-user-friends"></i>
                    <span><?php echo getTotalContributors($pdo, $league['id']); ?> In this league</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo $league['num_of_teams']; ?> Teams</span>
                </div>
                <div class="info-item">
                    <i class="fas fa-circle-notch"></i>
                    <span>Round <?php echo $league['round']; ?></span>
                </div>
            </div>
            
            <div class="league-stats">
                <div class="stat-item">
                    <div class="stat-item-value"><?php echo number_format($league['total_score']); ?></div>
                    <div class="stat-item-label">Your Points</div>
                </div>
                <div class="stat-item">
                    <?php 
                    $rank = getUserRankInLeague($pdo, $user_id, $league['id']);
                    $total = getTotalContributors($pdo, $league['id']);
                    ?>
                    <div class="stat-item-value"><?php echo $rank; ?>/<?php echo $total; ?></div>
                    <div class="stat-item-label">Rank</div>
                </div>
                <div class="stat-item">
                    <div class="stat-item-value"><?php echo $league['activated'] ? 'Active' : 'Setup'; ?></div>
                    <div class="stat-item-label">Status</div>
                </div>
            </div>
            
            <div class="league-actions">
                <a href="league_dashboard?id=<?php echo $league['id']; ?>" class="btn btn-gradient">
                    <i class="fas fa-sign-in-alt"></i> Enter
                </a>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-user-friends"></i>
        <h3>Not in Any Leagues</h3>
        <p>Join an existing league using a league token!</p>
        <button class="btn btn-gradient" onclick="openJoinLeagueModal()">
            Join a League
        </button>
    </div>
    <?php endif; ?>
</div>

<script>
    // Theme handling
    (function() {
        const savedTheme = localStorage.getItem('theme');
        const body = document.body;
        
        if (savedTheme === 'light') {
            body.classList.remove('dark-mode');
        } else {
            body.classList.add('dark-mode');
        }
    })();

    // Loading spinner
    window.addEventListener('load', function() {
        const loadingSpinner = document.getElementById('loadingSpinner');
        setTimeout(() => {
            loadingSpinner.classList.add('hidden');
        }, 500);
    });

    // Theme toggle
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

    // User dropdown
    const userInfo = document.getElementById('userInfo');
    const dropdownMenu = document.getElementById('dropdownMenu');

    userInfo.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!userInfo.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('active');
        }
    });

    // Close dropdown when clicking a link
    dropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
            dropdownMenu.classList.remove('active');
        });
    });

    // Escape key to close dropdown
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && dropdownMenu.classList.contains('active')) {
            dropdownMenu.classList.remove('active');
        }
    });

    // Animation on scroll
    function reveal() {
        const cards = document.querySelectorAll('.league-card, .stat-card');
        
        cards.forEach(card => {
            const windowHeight = window.innerHeight;
            const cardTop = card.getBoundingClientRect().top;
            const cardVisible = 150;
            
            if (cardTop < windowHeight - cardVisible) {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }
        });
    }
    
    window.addEventListener('scroll', reveal);
    reveal();

    // Invitation Modal Functions
    function openInvitationModal(token) {
        const modal = document.getElementById('invitationModal');
        const tokenInput = document.getElementById('tokenInput');
        const linkInput = document.getElementById('linkInput');
        
        tokenInput.value = token;
        linkInput.value = `${window.location.origin}/join_league.php?token=${token}`;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeInvitationModal() {
        const modal = document.getElementById('invitationModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset button texts
        document.getElementById('tokenBtnText').textContent = 'Copy';
        document.getElementById('linkBtnText').textContent = 'Copy';
        
        // Reset button classes
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.classList.remove('copied');
        });
    }

    function copyToken() {
        const tokenInput = document.getElementById('tokenInput');
        const btn = event.currentTarget;
        const btnText = document.getElementById('tokenBtnText');
        
        tokenInput.select();
        document.execCommand('copy');
        
        btn.classList.add('copied');
        btnText.textContent = 'Copied!';
        
        setTimeout(() => {
            btn.classList.remove('copied');
            btnText.textContent = 'Copy';
        }, 2000);
    }

    function copyLink() {
        const linkInput = document.getElementById('linkInput');
        const btn = event.currentTarget;
        const btnText = document.getElementById('linkBtnText');
        
        linkInput.select();
        document.execCommand('copy');
        
        btn.classList.add('copied');
        btnText.textContent = 'Copied!';
        
        setTimeout(() => {
            btn.classList.remove('copied');
            btnText.textContent = 'Copy';
        }, 2000);
    }

    // Close modal when clicking overlay
    document.getElementById('invitationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeInvitationModal();
        }
    });

    // Join League Modal Functions
    let validatedLeagueId = null;

    function openJoinLeagueModal() {
        const modal = document.getElementById('joinLeagueModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reset form
        document.getElementById('leagueTokenInput').value = '';
        document.getElementById('leaguePreview').classList.remove('active');
        document.getElementById('alertContainer').innerHTML = '';
        document.getElementById('validateTokenBtn').style.display = 'inline-flex';
        document.getElementById('joinLeagueBtn').style.display = 'none';
        validatedLeagueId = null;
    }

    function closeJoinLeagueModal() {
        const modal = document.getElementById('joinLeagueModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Reset form
        document.getElementById('leagueTokenInput').value = '';
        document.getElementById('leaguePreview').classList.remove('active');
        document.getElementById('alertContainer').innerHTML = '';
        document.getElementById('validateTokenBtn').style.display = 'inline-flex';
        document.getElementById('joinLeagueBtn').style.display = 'none';
        validatedLeagueId = null;
    }

    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
        
        alertContainer.innerHTML = `
            <div class="alert alert-${type}">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
        `;
    }

    async function validateToken() {
        const token = document.getElementById('leagueTokenInput').value.trim();
        const validateBtn = document.getElementById('validateTokenBtn');
        const alertContainer = document.getElementById('alertContainer');
        
        if (!token) {
            showAlert('Please enter a league token', 'error');
            return;
        }

        if (token.length !== 8) {
            showAlert('Token must be exactly 8 characters', 'error');
            return;
        }

        // Disable button and show loading
        validateBtn.disabled = true;
        validateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
        alertContainer.innerHTML = '';

        try {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'validate_token');
            formData.append('token', token);

            const response = await fetch('main.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Show league preview
                validatedLeagueId = data.league.id;
                document.getElementById('previewLeagueName').textContent = data.league.name;
                document.getElementById('previewOwnerName').textContent = data.league.owner_name;
                document.getElementById('previewSystem').textContent = data.league.system;
                document.getElementById('previewTeams').textContent = data.league.num_of_teams + ' Teams';
                document.getElementById('previewPlayers').textContent = data.league.num_of_players + ' Players';
                
                document.getElementById('leaguePreview').classList.add('active');
                document.getElementById('validateTokenBtn').style.display = 'none';
                document.getElementById('joinLeagueBtn').style.display = 'inline-flex';
                
                showAlert('League found! Click "Join League" to continue.', 'success');
            } else {
                showAlert(data.message, 'error');
                document.getElementById('leaguePreview').classList.remove('active');
            }
        } catch (error) {
            showAlert('An error occurred. Please try again.', 'error');
            console.error('Error:', error);
        } finally {
            validateBtn.disabled = false;
            validateBtn.innerHTML = '<i class="fas fa-search"></i> Validate Token';
        }
    }

    async function joinLeague() {
        if (!validatedLeagueId) {
            showAlert('Please validate the token first', 'error');
            return;
        }

        const joinBtn = document.getElementById('joinLeagueBtn');
        
        // Disable button and show loading
        joinBtn.disabled = true;
        joinBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Joining...';

        try {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'join_league');
            formData.append('league_id', validatedLeagueId);

            const response = await fetch('main.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showAlert(data.message, 'success');
                
                // Redirect after a short delay
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                showAlert(data.message, 'error');
                joinBtn.disabled = false;
                joinBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Join League';
            }
        } catch (error) {
            showAlert('An error occurred. Please try again.', 'error');
            console.error('Error:', error);
            joinBtn.disabled = false;
            joinBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Join League';
        }
    }

    // Close join league modal when clicking overlay
    document.getElementById('joinLeagueModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeJoinLeagueModal();
        }
    });

    // Allow Enter key to validate/join
    document.getElementById('leagueTokenInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            if (document.getElementById('validateTokenBtn').style.display !== 'none') {
                validateToken();
            } else {
                joinLeague();
            }
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const invitationModal = document.getElementById('invitationModal');
            const joinModal = document.getElementById('joinLeagueModal');
            
            if (invitationModal.classList.contains('active')) {
                closeInvitationModal();
            }
            if (joinModal.classList.contains('active')) {
                closeJoinLeagueModal();
            }
        }
    });
</script>
</body>
</html>
