<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: #f5f7fa;
            color: #000000;
            padding-top: 70px;
        }
        
        .header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 70px;
    background: linear-gradient(135deg, #1D60AC, #0A92D7);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .header-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        
        .header-logo img {
            height: 45px;
            width: auto;
            object-fit: contain;
        }
        
        .header-logo-text {
            font-size: 24px;
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: -0.5px;
        }
        
        .menu-toggle {
    display: none;
    background: linear-gradient(135deg, #1D60AC, #0A92D7);
    border: 1px solid #FFFFFF;
    color: #FFFFFF;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 20px;
    transition: transform 0.3s ease;
}
        
        .menu-toggle:hover {
            transform: scale(1.05);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .header-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #1D60AC, #0A92D7);
            border-radius: 25px;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .header-profile:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(10, 146, 215, 0.3);
        }
        
        .header-profile-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #1D60AC;
            font-size: 14px;
        }
        
        .header-profile-info {
            display: flex;
            flex-direction: column;
        }
        
        .header-profile-name {
            font-size: 14px;
            font-weight: 600;
            color: #FFFFFF;
        }
        
        .header-profile-role {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.85);
        }
        
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(29, 96, 172, 0.1), rgba(10, 146, 215, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1D60AC;
            font-size: 20px;
            transition: all 0.3s ease;
        }
        
        .notification-icon:hover {
            background: linear-gradient(135deg, #1D60AC, #0A92D7);
            color: #FFFFFF;
            transform: scale(1.1);
        }
        
        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #F1A155;
            color: #FFFFFF;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            border: 2px solid #FFFFFF;
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            
            .header-logo-text {
                display: none;
            }
            
            .header-profile-info {
                display: none;
            }
        }
        .sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(128, 128, 128, 0.5);
    z-index: 1001; 
    display: none;
}

    </style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
        <a href="index.php" class="header-logo">
            <img src="assets/images/logo white outline.png" alt="Fantaziko Logo">
            <span class="header-logo-text">Fantaziko Admin Board</span>
        </a>
    </div>
    
    <div class="header-right">
        
        <div class="header-profile">
            <div class="header-profile-avatar">AD</div>
            <div class="header-profile-info">
                <span class="header-profile-name">Admin User</span>
                <span class="header-profile-role">Administrator</span>
            </div>
        </div>
    </div>
    
</header>
<div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>
<script>

    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) {
            sidebar.classList.toggle('active');
            if (overlay) {
                overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
            }
        }
    }

</script>