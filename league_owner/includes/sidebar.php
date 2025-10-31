<?php
// league_owner/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$league_id = $_GET['id'] ?? '';
?>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: var(--nav-bg);
        backdrop-filter: blur(10px);
        box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        overflow-y: auto;
        z-index: 1003;
        transition: transform 0.3s ease;
        border-right: 1px solid var(--border-color);
    }
    
    body.dark-mode .sidebar {
        background: rgba(10, 10, 10, 0.95);
        box-shadow: 2px 0 20px rgba(0,0,0,0.5);
    }
    
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        border-radius: 3px;
    }
    
    .sidebar-logo {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }
    
    .sidebar-logo img {
        height: 45px;
        width: auto;
    }
    
    body.dark-mode .sidebar-logo img {
        content: url('../assets/images/logo white outline.png');
    }
    
    body:not(.dark-mode) .sidebar-logo img {
        content: url('../assets/images/logo.png');
    }
    
    .sidebar-logo-text {
        font-size: 1.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    body.dark-mode .sidebar-logo-text {
        background: none;
        -webkit-text-fill-color: white;
        color: white;
    }
    
    .sidebar-menu {
        padding: 1.5rem 0;
    }
    
    .menu-section {
        margin-bottom: 2rem;
    }
    
    .menu-section-title {
        padding: 0 1.5rem 0.8rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .menu-item {
        display: flex;
        align-items: center;
        padding: 0.9rem 1.5rem;
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        font-size: 0.95rem;
        font-weight: 500;
        border-left: 3px solid transparent;
    }
    
    .menu-item:hover {
        background: linear-gradient(90deg, rgba(29,96,172,0.1), transparent);
        color: var(--gradient-end);
        border-left-color: var(--gradient-end);
    }
    
    body.dark-mode .menu-item:hover {
        background: linear-gradient(90deg, rgba(10,146,215,0.2), transparent);
    }
    
    .menu-item.active {
        background: linear-gradient(90deg, rgba(29,96,172,0.15), transparent);
        color: var(--gradient-end);
        border-left-color: var(--gradient-end);
        font-weight: 600;
    }
    
    body.dark-mode .menu-item.active {
        background: linear-gradient(90deg, rgba(10,146,215,0.25), transparent);
    }
    
    .menu-icon {
        min-width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-right: 1rem;
        border-radius: 10px;
        background: rgba(29,96,172,0.1);
        transition: all 0.3s ease;
        color: var(--gradient-end);
    }
    
    body.dark-mode .menu-icon {
        background: rgba(10,146,215,0.15);
    }
    
    .menu-item:hover .menu-icon {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: white;
        transform: scale(1.1);
    }
    
    .menu-item.active .menu-icon {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: white;
    }
    
    .menu-text {
        flex: 1;
    }
    
    .menu-badge {
        background: linear-gradient(135deg, #F1A155, #e89944);
        color: white;
        padding: 0.25rem 0.6rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
    }
    
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
    }
</style>

<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/images/logo white outline.png" alt="Fantazina">
        <span class="sidebar-logo-text">FANTAZINA</span>
    </div>
    
    <nav class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-section-title">Overview</div>
            <a href="league_settings.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'league_settings.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-home"></i></span>
                <span class="menu-text">Dashboard</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Management</div>
            <a href="manage_teams.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'manage_teams.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-shield-alt"></i></span>
                <span class="menu-text">Teams</span>
            </a>
            <a href="manage_players.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'manage_players.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-users"></i></span>
                <span class="menu-text">Players</span>
            </a>
            <a href="manage_contributors.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'manage_contributors.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-user-friends"></i></span>
                <span class="menu-text">Contributors</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Matches & Points</div>
            <a href="manage_matches.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'manage_matches.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-calendar-alt"></i></span>
                <span class="menu-text">Matches</span>
            </a>
            <a href="match_points.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'match_points.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-star"></i></span>
                <span class="menu-text">Match Points</span>
            </a>
            <a href="points_rules.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'points_rules.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-cog"></i></span>
                <span class="menu-text">Points Rules</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Reports</div>
            <a href="leaderboard.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'leaderboard.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-trophy"></i></span>
                <span class="menu-text">Leaderboard</span>
            </a>
            <a href="statistics.php?id=<?php echo $league_id; ?>" 
               class="menu-item <?php echo $current_page === 'statistics.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-chart-bar"></i></span>
                <span class="menu-text">Statistics</span>
            </a>
        </div>
        

    </nav>
</aside>

<script>
    // Handle active menu item based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname.split('/').pop();
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && href.includes(currentPath)) {
                item.classList.add('active');
            }
        });
    });
</script>