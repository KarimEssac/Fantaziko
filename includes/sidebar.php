<style>
    @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
    
    .sidebar {
        position: fixed;
        top: 70px;
        left: 0;
        width: 280px;
        height: calc(100vh - 70px);
        background: #FFFFFF;
        box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        overflow-y: auto;
        z-index: 1002;
        transition: transform 0.3s ease;
    }
    
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        border-radius: 3px;
    }
    
    .sidebar-menu {
        padding: 20px 0;
    }
    
    .menu-section {
        margin-bottom: 25px;
    }
    
    .menu-section-title {
        padding: 0 25px 10px;
        font-size: 11px;
        font-weight: 700;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .menu-item {
        display: flex;
        align-items: center;
        padding: 12px 25px;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
        font-size: 14px;
        font-weight: 500;
    }
    
    .menu-item:hover {
        background: linear-gradient(90deg, rgba(29,96,172,0.1), transparent);
        color: #1D60AC;
    }
    
    .menu-item.active {
        background: linear-gradient(90deg, rgba(29,96,172,0.15), transparent);
        color: #1D60AC;
        border-left: 4px solid #1D60AC;
        font-weight: 600;
    }
    
    .menu-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
    }
    
    .menu-icon {
        min-width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-right: 12px;
        border-radius: 8px;
        background: rgba(29,96,172,0.1);
        transition: all 0.3s ease;
    }
    
    .menu-item:hover .menu-icon {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
        transform: scale(1.1);
    }
    
    .menu-item.active .menu-icon {
        background: linear-gradient(135deg, #1D60AC, #0A92D7);
        color: #FFFFFF;
    }
    
    .menu-text {
        flex: 1;
    }
    
    .menu-badge {
        background: #F1A155;
        color: #FFFFFF;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .menu-arrow {
        font-size: 12px;
        transition: transform 0.3s ease;
    }
    
    .menu-item.has-submenu.open .menu-arrow {
        transform: rotate(180deg);
    }
    
    .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: rgba(29,96,172,0.03);
    }
    
    .submenu.open {
        max-height: 500px;
    }
    
    .submenu-item {
        display: flex;
        align-items: center;
        padding: 10px 25px 10px 72px;
        color: #666;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.3s ease;
    }
    
    .submenu-item:hover {
        color: #1D60AC;
        background: rgba(29,96,172,0.05);
    }
    
    .submenu-item::before {
        content: '•';
        margin-right: 10px;
        color: #1D60AC;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
    }
</style>

<aside class="sidebar">
    <nav class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="index.php" class="menu-item active">
                <span class="menu-icon">📊</span>
                <span class="menu-text">Dashboard</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">User Management</div>
            <a href="accounts.php" class="menu-item">
                <span class="menu-icon">👥</span>
                <span class="menu-text">Accounts</span>
            </a>
            <a href="admins.php" class="menu-item">
                <span class="menu-icon">🔐</span>
                <span class="menu-text">Administrators</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">League Management</div>
            <a href="leagues.php" class="menu-item">
                <span class="menu-icon">🏆</span>
                <span class="menu-text">Leagues</span>
            </a>
            <a href="league-contributors.php" class="menu-item">
                <span class="menu-icon">👤</span>
                <span class="menu-text">Contributors</span>
            </a>
            <a href="league-roles.php" class="menu-item">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">League Roles & Points</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Game Management</div>
            <a href="teams.php" class="menu-item">
                <span class="menu-icon">🎯</span>
                <span class="menu-text">Teams</span>
            </a>
            <a href="players.php" class="menu-item">
                <span class="menu-icon">⚽</span>
                <span class="menu-text">Players</span>
            </a>
            <a href="matches.php" class="menu-item">
                <span class="menu-icon">📅</span>
                <span class="menu-text">Matches</span>
            </a>
            <a href="match-points.php" class="menu-item">
                <span class="menu-icon">📈</span>
                <span class="menu-text">Match Points</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Reports & Analytics</div>
            <a href="reports.php" class="menu-item">
                <span class="menu-icon">📊</span>
                <span class="menu-text">Reports</span>
            </a>
            <a href="analytics.php" class="menu-item">
                <span class="menu-icon">📉</span>
                <span class="menu-text">Analytics</span>
            </a>
            <a href="leaderboards.php" class="menu-item">
                <span class="menu-icon">🥇</span>
                <span class="menu-text">Leaderboards</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">Financial</div>
            <a href="payments.php" class="menu-item">
                <span class="menu-icon">💰</span>
                <span class="menu-text">Payments</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-section-title">System</div>
            <a href="logout.php" class="menu-item">
                <span class="menu-icon">🚪</span>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<script>
    // Handle active menu item
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname.split('/').pop() || 'index.php';
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPath) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    });
    
    // Handle submenu toggle
    document.querySelectorAll('.menu-item.has-submenu').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            this.classList.toggle('open');
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('submenu')) {
                submenu.classList.toggle('open');
            }
        });
    });
</script>